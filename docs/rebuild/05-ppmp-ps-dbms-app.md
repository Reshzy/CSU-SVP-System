# 05 — PPMP, PS-DBMS, and consolidated APP

**Purpose:** Item catalog and department procurement plans that **gate PR line items**.

**Source:**

- `app/Models/AppItem.php`, `Ppmp.php`, `PpmpItem.php`
- `app/Http/Controllers/AppItemController.php`, `PpmpController.php`, `AppConsolidationController.php`
- `app/Services/AppConsolidationService.php`, `PpmpQuarterlyTracker.php`
- `app/Console/Commands/ImportAppCsv.php`, `ImportPpmpFromCsv.php`
- Import column map: [`09-imports.md`](09-imports.md)

---

## Naming (do not invent extra tables)

| UI name | Table | Notes |
|---------|-------|--------|
| **PS-DBMS** | `app_items` | Procurement Service / DBM reference catalog by `fiscal_year` |
| **APP** (Annual Procurement Plan reference) | **same** `app_items` | No `apps` table |
| **Consolidated APP** | computed view | Aggregates **validated** PPMPs; read-only; **no file export** |
| **PPMP** | `ppmps` + `ppmp_items` | One per `(department_id, fiscal_year)` |

Permission `manage-ps-dbms` (BAC Secretariat): `/reference/ps-dbms`.  
Permission `view-consolidated-app`: `GET /bac/app`.

---

## `app_items`

Columns: `fiscal_year`, `category`, `item_code`, `item_name`, `unit_of_measure`, `unit_price`, `specifications`, `is_active`.

Natural key: `(item_code, fiscal_year)`. Import upserts on that key (`app:import`).

Categories include all-caps CSV headers plus specials: `SOFTWARE`, `PART II - OTHER ITEMS NOT AVAILABLE AT PS-DBM`. SOFTWARE / PART II may have null/zero price.

---

## PPMP header (`ppmps`)

- `status`: `draft` | `validated`
- `Ppmp::getOrCreateForDepartment($departmentId, $year)`
- `validate` action: status `validated`, `validated_at`, `validated_by`
- `total_estimated_cost` recalculated from items after import/edit

PR create **requires** a **validated** PPMP for the requester’s department (`StorePurchaseRequestRequest`). Sets `purchase_requests.has_ppmp = true`.

---

## PPMP lines (`ppmp_items`)

- FK `app_item_id` + `ppmp_id`; unique pair
- Quarterly planned qty: `q1_quantity` … `q4_quantity`, `total_quantity`
- Costs: `estimated_unit_cost`, `estimated_total_cost`

PR items store `ppmp_item_id`, `ppmp_quarter`, and snapshots `ppmp_planned_qty_for_quarter`, `ppmp_remaining_qty_at_creation`.

---

## Remaining quantity (must preserve)

`PpmpItem::getRemainingQuantity(?$quarter, ?$excludePurchaseRequestId)`:

```
planned − SUM(quantity_requested on linked PR items
  whose PR is_archived = false
  AND status NOT IN (rejected, cancelled))
```

Optional filter: `ppmp_quarter = $quarter`. Optional exclude one PR id (edit/replacement).

**Returned PRs still consume qty unless archived.** Replacement path archives the original so qty is freed. Failed-item `re_pr_created` originals stay in the sum until those PRs are rejected/cancelled/archived — failed-item re-PR relies on the new PR using remaining math after failed items are excluded from “active” consumption where `getRemainingQuantity` already excludes rejected/cancelled only. Rebuild: re-read `PpmpItem` and replacement controllers if tests fail.

Items with **zero remaining** or **no allocation for current quarter** cannot be requested. Current quarter from `PpmpQuarterlyTracker` (also sets `pr_quarter` on create).

**Validation on PR store:**

- PPMP `validated`
- Item has qty for **current quarter**
- Requested qty ≤ remaining for that quarter

---

## Import order

1. PS-DBMS CSV → `app_items` for fiscal year (`php artisan app:import` or UI `ps-dbms.process`)
2. Same CSV shape → PPMP (`ppmp:import-csv` or UI `ppmp.import.process`) linking by `item_code` + year; skip items not in catalog or with all-quarter qty 0

Legacy `ppmp:import` writes a pre-`ppmp_id` shape — **do not use** for rebuild. See file 09.

---

## Consolidated APP

`AppConsolidationService` aggregates validated PPMP items (SQL). BAC Secretariat / anyone with `view-consolidated-app`. No download.

---

## Acceptance criteria

- [ ] Catalog import is per fiscal year and keyed by item code.
- [ ] PPMP import cannot create lines for codes missing from `app_items` that year.
- [ ] Unvalidated PPMP: PR create fails validation.
- [ ] Remaining qty excludes archived, rejected, and cancelled PRs only (returned still counts until archived).
- [ ] Current-quarter allocation is required for each requested PPMP item.
- [ ] Consolidated APP is read-only and permission-gated.
- [ ] One PPMP row per department per fiscal year.
