# 03 — Purchase request workflow

**Purpose:** Implement the PR status machine **as coded**, not the older swimlane that says “create as draft”.

**Source:**

- `app/Models/PurchaseRequest.php`
- `app/Http/Controllers/PurchaseRequestController.php` (`store` sets `supply_office_review`)
- `app/Http/Controllers/SupplyPurchaseRequestController.php`
- `app/Http/Controllers/BudgetEarmarkController.php`
- `app/Http/Controllers/CeoApprovalController.php`
- `app/Services/WorkflowRouter.php`
- `app/Observers/PurchaseRequestObserver.php`
- [`docs/purchase-request-process-swimlane.md`](../purchase-request-process-swimlane.md) (process picture; **status strings in this file win**)

---

## Happy path (actual)

```
Requester creates PR
  → supply_office_review
  → [Supply activate] budget_office_review
  → [Budget earmark] ceo_approval
  → [CEO approve] bac_evaluation  (+ procurement_method = small_value_procurement, resolution generated)
  → [BAC RFQ / quotes / AOQ] bac_evaluation or bac_approved
  → [Supply PO] po_generation | partial_po_generation
  → [PO send/ack] supplier_processing
  → [PO delivered] delivered
  → [PO complete] completed
```

Grouped PRs: stored `status` is synced from the **earliest** group status (`PurchaseRequest::syncStatusFromGroups()`). See [`04-lots-and-item-groups.md`](04-lots-and-item-groups.md).

---

## Status meanings and who moves them

| Status | Set by | Typical next |
|--------|--------|----------------|
| `draft` | Legacy / unused on create | `submitted` (observer reserves if leaving draft) |
| `submitted` | Legacy | Supply `start_review` → `supply_office_review`. Lots still allowed in this status |
| **`supply_office_review`** | **`PurchaseRequestController::store()`** (new PRs) | `budget_office_review` (activate), `returned_by_supply`, `rejected`, `cancelled` |
| `budget_office_review` | Supply `updateStatus(action: activate)` | `ceo_approval` or `rejected` |
| `ceo_approval` | Budget earmark approve | `bac_evaluation` or `rejected` |
| `bac_evaluation` | CEO `decision: approve` | stays during canvassing; grouped min status can keep PR here |
| `bac_approved` | `BacQuotationController::finalize()` **or** all groups `aoq_generated` | PO creation |
| `partial_po_generation` | `syncStatusFromGroups()` when some groups have POs | continues per group |
| `po_generation` | PO store / batch for **non-grouped** PRs | `supplier_processing` |
| `po_approved` | **Computed only** from group `all_po_approved` | no CEO PO-approve controller today |
| `supplier_processing` | PO `send_to_supplier` / `acknowledge` | `delivered` |
| `delivered` | PO `mark_delivered` | `completed` |
| `completed` | PO `complete` or group sync | terminal; budget utilized |
| `returned_by_supply` | Supply `return` | requester replacement PR → new PR at `supply_office_review`; original archived |
| `rejected` | Supply / Budget / CEO reject | terminal; UI **Deferred**; budget released |
| `cancelled` | Supply `cancel` | terminal; budget released |

---

## Supply status actions

`SupplyPurchaseRequestController::updateStatus()` actions (preserve names):

| Action | Result status |
|--------|----------------|
| `start_review` | `supply_office_review` (from `submitted`) |
| `activate` | `budget_office_review` + `WorkflowRouter::createPendingForRole(..., 'budget_office_earmarking', 'Budget Office')` |
| `return` | `returned_by_supply` (requires remarks) |
| `reject` | `rejected` |
| `cancel` | `cancelled` |

Lots: only while status is `submitted` or `supply_office_review`.

---

## Budget and CEO

- Budget **approve** (`BudgetEarmarkController::update`): fills earmark fields, status `ceo_approval`, `createPendingForRole(..., 'ceo_initial_approval', 'Executive Officer')`.
- Budget **reject**: status `rejected`.
- Budget **amend**: changes earmark fields **without** changing workflow status (`earmark_amended` activity).
- CEO **approve**: status `bac_evaluation`; `procurement_method = 'small_value_procurement'`; `procurement_method_set_at/by`; generate `resolution_number`; `BacResolutionService::generateResolution()` (best-effort); notify BAC Secretariat via `createPendingForRole(..., 'bac_evaluation', 'BAC Secretariat')`.
- CEO **reject**: status `rejected`.

---

## WorkflowRouter

`createPendingForRole($pr, $stepName, $roleName)`:

1. First user with that Spatie role (`orderBy('id')`). If none, return null (no notification).
2. `WorkflowApproval::firstOrCreate` on `(purchase_request_id, step_name)` with `status = pending`.
3. Mail `PurchaseRequestActionRequired`.

**Wired steps:** `budget_office_earmarking`, `ceo_initial_approval`, `bac_evaluation`.

**Declared in `getStepOrder()` but not invoked by controllers:** `bac_award_recommendation`, `ceo_final_approval`, `po_generation`, `po_approval`, `supply_office_review` (legacy).

Preserve the map; do not invent new step names. Wiring unused steps is a gap fix (file 15).

---

## Replacement PRs (two paths)

### A. Supply return → requester

- Original: `returned_by_supply`, own requester.
- Routes: `purchase-requests/{originalPr}/replacement/create|store`.
- New PR: `supply_office_review`, `replaces_pr_id` = original.
- Original: `replaced_by_pr_id` set, `is_archived = true`.
- PPMP qty: archived original no longer counts (see file 05). Replacement restore/grace logic lives in `PurchaseRequestController::preparePrCreationDataForReplacement()`.

### B. BAC failed items → auto replacement

- `BacQuotationController::createReplacementPr()` → `AoqService::handleFailedProcurement()`.
- New PR: `supply_office_review`, copies failed items; original items `procurement_status = re_pr_created`.
- Original PR is **not** archived.

---

## Budget side effects (must keep)

See observer table in [`02-database-schema.md`](02-database-schema.md). Amount = PR `calculateTotalCost()` (sum of item qty × unit cost). Fiscal year = `created_at` year.

---

## Notifications on transitions

| Event | Notification | Recipient |
|-------|--------------|-----------|
| PR created | `PurchaseRequestSubmitted` | All `Supply Officer` users |
| Supply activate / budget / CEO pending | `PurchaseRequestActionRequired` | First user of target role |
| Supply/Budget/CEO status change | `PurchaseRequestStatusUpdated` | PR requester |

Mail only, queued (`ShouldQueue`). Details: [`13-notifications-and-audit.md`](13-notifications-and-audit.md).

---

## Acceptance criteria

- [ ] Creating a PR as a department user yields `status = supply_office_review`, a `PR-MMYY-####` number, reserved budget (if department budget can cover — current code logs errors rather than blocking if reserve fails).
- [ ] Supply activate moves to `budget_office_review` and creates a pending `budget_office_earmarking` approval.
- [ ] Budget approve → `ceo_approval`; CEO approve → `bac_evaluation` + `procurement_method = small_value_procurement`.
- [ ] `rejected` is shown as Deferred in UI copy.
- [ ] Returned PR can spawn a replacement; original is archived.
- [ ] Grouped PRs use min-group status sync after PO/AOQ changes.
- [ ] Unused WorkflowRouter steps exist in the order map but are not required to fire.
