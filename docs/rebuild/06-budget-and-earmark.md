# 06 — Budget and earmark

**Purpose:** Department fiscal budgets and PR earmark data (no earmark table).

**Source:**

- `app/Models/DepartmentBudget.php`
- `app/Observers/PurchaseRequestObserver.php`
- `app/Http/Controllers/BudgetEarmarkController.php`
- `app/Http/Controllers/BudgetManagementController.php`
- `app/Http/Controllers/Api/BudgetCheckController.php`
- `app/Services/EarmarkExportService.php` (Excel: file 10)

---

## Department budgets

Table `department_budgets`, unique `(department_id, fiscal_year)`.

```
available = allocated_budget − utilized_budget − reserved_budget
committed = utilized_budget + reserved_budget
```

`getOrCreateForDepartment($departmentId, $fiscalYear)` creates zeros if missing.

| Method | Effect |
|--------|--------|
| `reserveBudget($amount)` | Fail (return false) if `available < amount`; else add to `reserved_budget` |
| `utilizeBudget($amount)` | Move amount from reserved → utilized; reserved floored at 0 |
| `releaseReservedBudget($amount)` | Subtract from reserved; floored at 0 |

Fiscal year for a PR = **`created_at` year**. Amount = sum of items `quantity_requested * estimated_unit_cost`.

Observer rules: [`02-database-schema.md`](02-database-schema.md) / [`03-workflow.md`](03-workflow.md).

Budget Office CRUD: `/budget/departments` (`BudgetManagementController`) — set `allocated_budget`, notes, `set_by`.

---

## Earmark is columns on `purchase_requests`

| Column | Role |
|--------|------|
| `earmark_id` | `EM-MMYY-####`; assigned on Budget approve or on earmark Excel export if missing |
| `legal_basis` | Free text (placeholder in UI e.g. “Section 86 of RA 9184”) |
| `earmark_programs_activities` | Text |
| `earmark_responsibility_center` | Text |
| `earmark_date_to` | Date |
| `earmark_object_expenditures` | JSON array of expenditure objects (code/description + amount) |
| `funding_source` / `fund_cluster_code` / `fund_details` / `budget_code` | Funding display (`PurchaseRequest::formatFundingSourceFromFundCluster`) |

Document type `earmark_document` may be stored in `documents`; Excel export is the operational artifact.

---

## Budget Office actions

| Action | HTTP | Status change |
|--------|------|----------------|
| List PRs in budget queue | `budget.purchase-requests.index` | — |
| Approve earmark | `PUT budget.purchase-requests.update` | → `ceo_approval`; pending `ceo_initial_approval` |
| Reject (Deferred) | `POST .../reject` | → `rejected` |
| Export earmark Excel | `GET .../export-earmark` | Allowed in `budget_office_review` (preview) **or** if `earmark_id` exists; may mint `earmark_id` |
| Amend | `GET amend` / `PATCH amend-earmark` | **No workflow status change**; activity `earmark_amended` |

Roles: middleware `role:Budget Office` (plus super-admin gate).

---

## Budget check API (authenticated)

| Route | Name | Use |
|-------|------|-----|
| `GET /api/budget/check` | `api.budget.check` | Remaining budget for a department/year (PR create UI) |
| `POST /api/budget/validate` | `api.budget.validate` | Validate a requested amount can be reserved |

Session auth (not a public REST API). Rebuild as Inertia-friendly JSON endpoints with the same names if possible.

---

## Acceptance criteria

- [ ] Available budget formula matches above.
- [ ] Non-draft PR create reserves; `completed` utilizes; cancel/reject/return releases.
- [ ] Earmark fields persist on the PR; IDs use `EM-MMYY-####`.
- [ ] Amend does not move the PR backward or forward in workflow.
- [ ] Reject uses status `rejected` (Deferred in copy).
- [ ] Fund cluster codes `01/05/06/07` format the funding source string as in `formatFundingSourceFromFundCluster`.
