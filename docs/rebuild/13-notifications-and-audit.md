# 13 — Notifications and audit log

**Purpose:** Mail notifications and PR activity timeline.

**Source:**

- `app/Notifications/*.php`
- `app/Services/WorkflowRouter.php`
- `app/Services/PurchaseRequestActivityLogger.php`
- `app/Models/PurchaseRequestActivity.php`
- `app/Observers/PurchaseRequestObserver.php`

---

## Notifications (mail only, queued)

All four implement `ShouldQueue`. `via()` = `['mail']`. **No database notification channel.**

| Class | When | Who | Subject / gist |
|-------|------|-----|----------------|
| `PurchaseRequestSubmitted` | PR create (`notifySupplyOffice`) | **All** users with role `Supply Officer` | `New Purchase Request Submitted: {pr_number}` → `supply.purchase-requests.index` |
| `PurchaseRequestActionRequired` | `WorkflowRouter::createPendingForRole` | **First** user of target role (`orderBy id`) | Action needed for Budget / CEO / BAC steps |
| `PurchaseRequestStatusUpdated` | Supply status, Budget earmark/reject, CEO decision | PR **requester** | Status changed |
| `SupplierMessageReceived` | `SupplierCommunicationController::store` | Supply Officers | **Controller not routed** — preserve class; wire only if building supplier contact (file 15) |

If no Supply Officer exists, submit notification is a no-op / skipped. If `createPendingForRole` finds no user for the role, it returns null and **does not mail**.

**Not mailed (activity log only):** AOQ generate, quotation entry, PO create, withdrawal, failed procurement, earmark amend.

Queue: incumbent uses `QUEUE_CONNECTION=database`. Rebuild: database or Redis; still queue mail.

---

## Activity log

Table `purchase_request_activities` (no `updated_at`). Fields: `purchase_request_id`, `user_id`, `pr_item_group_id`, `action`, `old_value`/`new_value` (JSON), `description`, `ip_address`, `user_agent`, `created_at`.

`action` strings used in logger/UI:

`created`, `submitted`, `status_changed`, `returned`, `rejected`, `approved`, `replacement_created`, `notes_added`, `assigned`, `updated`, `resolution_generated`, `resolution_regenerated`, `rfq_generated`, `quotation_submitted`, `quotation_evaluated`, `aoq_generated`, `tie_resolved`, `bac_override`, `supplier_withdrawal`, `item_groups_created`, `item_groups_updated`, `earmark_amended`

Observer also logs creation, submission (`supply_office_review`/`submitted`), status change, return, rejection, assignment, notes.

PR show page renders a timeline from this table (`pr-timeline` component today). Rebuild must keep an equivalent audit trail; do not replace it with only Laravel Pulse.

---

## Workflow approval rows

`workflow_approvals` are **pending task records**, not the full audit. Created by `WorkflowRouter` for wired steps only. Status values: `pending`, `approved`, `rejected`, `returned_for_revision`, `skipped`. Controllers do not always close these rows when the PR moves — preserve or fix as a gap (file 15).

---

## Acceptance criteria

- [ ] New PR emails every Supply Officer (mail, queued).
- [ ] Activate/earmark/CEO-approve each create a pending approval and email the first matching role user if one exists.
- [ ] Requester is emailed on supply/budget/CEO status changes.
- [ ] Activity actions listed above are written with old/new JSON where relevant.
- [ ] No in-app notification inbox is required for parity (mail only).
