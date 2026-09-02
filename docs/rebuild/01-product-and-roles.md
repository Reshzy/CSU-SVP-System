# 01 — Product and roles

**Purpose:** Define the product, actors, Spatie roles, and permissions the rebuild must seed and enforce.

**Source:**

- `database/seeders/RolePermissionSeeder.php`
- `app/Providers/AppServiceProvider.php` (`Gate::before`)
- `app/Models/User.php`
- `[docs/purchase-request-process-swimlane.md](../purchase-request-process-swimlane.md)`

---

## Product

Cagayan State University — Sanchez Mira Campus **Small Value Procurement (SVP)** system. End-to-end staff procurement:

PPMP (department plan) → Purchase Request → Supply review (lots) → Budget earmark → CEO approval → BAC (RFQ / quotations / AOQ, optional item groups) → Purchase Order(s) → inventory receipt → disbursement voucher.

Legal framing: RA 9184 (Government Procurement Reform Act). Only rules **encoded in the app** are mandatory; see `[07-bac-rfq-aoq-quotations.md](07-bac-rfq-aoq-quotations.md)` and `[15-known-gaps.md](15-known-gaps.md)`.

There is **no student portal**. One authenticated app shell; navigation and dashboards are role-gated.

---



## Super-admin gate

`Gate::before`: users with role **System Admin** or **Executive Officer** pass **all** ability checks.

Rebuild must keep this (or an equivalent “bypass all policies”) so CEO/admin screens do not fail on missing individual permissions.

---



## Positions vs roles

`positions` is a **job-title lookup**, not RBAC. Spatie `roles` are RBAC.

Position → role map used by `php artisan users:assign-roles` (`AssignRolesToApprovedUsers`):


| Position name        | Spatie role       |
| -------------------- | ----------------- |
| System Administrator | System Admin      |
| Supply Officer       | Supply Officer    |
| Budget Officer       | Budget Office     |
| Executive Officer    | Executive Officer |
| BAC Chairman         | BAC Chair         |
| BAC Member           | BAC Members       |
| BAC Secretary        | BAC Secretariat   |
| Accounting Officer   | Accounting Office |
| Canvassing Officer   | Canvassing Unit   |
| Dean                 | Dean              |
| Employee             | End User          |
| *(unknown / null)*   | End User          |


Command skips users who already have any role.

---



## Permissions (48)

Exact names from `RolePermissionSeeder`. Do not rename.

**Purchase request:** `create-purchase-request`, `view-purchase-request`, `edit-purchase-request`, `approve-purchase-request`, `reject-purchase-request`, `assign-pr-control-number`

**Budget:** `view-budget-info`, `create-earmark`, `approve-earmark`, `validate-budget`

**BAC:** `view-bac-documents`, `create-bac-resolution`, `evaluate-quotations`, `approve-abstract-quotation`, `conduct-bac-meeting`, `award-contract`

**Suppliers:** `manage-suppliers`, `view-supplier-info`, `request-quotations`, `evaluate-supplier-performance`

**Purchase orders:** `create-purchase-order`, `approve-purchase-order`, `send-po-to-supplier`, `track-delivery`, `accept-delivery`

**Documents:** `upload-documents`, `view-documents`, `approve-documents`, `archive-documents`

**Workflow:** `view-workflow-status`, `manage-approvals`, `assign-tasks`, `escalate-issues`

**Reports:** `view-reports`, `create-reports`, `view-analytics`, `export-data`

**System:** `manage-users`, `manage-roles`, `manage-permissions`, `manage-ps-dbms`, `view-consolidated-app`, `system-configuration`, `view-audit-logs`

**Accounting:** `process-payments`, `view-financial-data`, `create-disbursement-voucher`, `validate-costs`

Route middleware often uses **role names** (`role:Budget Office`) or a **single permission** (`can:edit-purchase-request`) rather than every permission above. See `[14-routes-and-permissions.md](14-routes-and-permissions.md)`. Permissions still must exist for Spatie and future gates.

---



## Roles (12)

Exact names (spaces included):

`System Admin`, `End User`, `Dean`, `Supply Officer`, `Budget Office`, `BAC Chair`, `BAC Members`, `BAC Secretariat`, `Canvassing Unit`, `Executive Officer`, `Accounting Office`, `Supplier`

### Permission assignment


| Role                  | Permissions                                                                                                                                                                                                                                                                                                                         |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **System Admin**      | All 48                                                                                                                                                                                                                                                                                                                              |
| **Executive Officer** | All 48                                                                                                                                                                                                                                                                                                                              |
| **End User**          | `create-purchase-request`, `view-purchase-request`, `view-workflow-status`, `upload-documents`, `view-documents`                                                                                                                                                                                                                    |
| **Dean**              | End User set + `view-budget-info`, `view-reports`                                                                                                                                                                                                                                                                                   |
| **Supply Officer**    | `view-purchase-request`, `edit-purchase-request`, `assign-pr-control-number`, `create-purchase-order`, `send-po-to-supplier`, `track-delivery`, `accept-delivery`, `manage-suppliers`, `view-supplier-info`, `request-quotations`, `upload-documents`, `view-documents`, `view-workflow-status`, `manage-approvals`, `view-reports` |
| **Budget Office**     | `view-purchase-request`, `view-budget-info`, `create-earmark`, `approve-earmark`, `validate-budget`, `validate-costs`, `view-workflow-status`, `view-documents`, `view-reports`                                                                                                                                                     |
| **BAC Chair**         | Dean set + `view-bac-documents`, `create-bac-resolution`, `evaluate-quotations`, `approve-abstract-quotation`, `conduct-bac-meeting`, `award-contract`, `view-supplier-info`, `evaluate-supplier-performance`, `manage-approvals`, `approve-documents`                                                                              |
| **BAC Members**       | Dean set + `view-bac-documents`, `evaluate-quotations`, `conduct-bac-meeting`, `view-supplier-info`                                                                                                                                                                                                                                 |
| **BAC Secretariat**   | Dean set + `view-bac-documents`, `create-bac-resolution`, `evaluate-quotations`, `conduct-bac-meeting`, `view-supplier-info`, `request-quotations`, `create-reports`, `manage-ps-dbms`, `view-consolidated-app`                                                                                                                     |
| **Canvassing Unit**   | `view-purchase-request`, `manage-suppliers`, `view-supplier-info`, `request-quotations`, `evaluate-supplier-performance`, `view-workflow-status`, `upload-documents`, `view-documents`, `view-reports`                                                                                                                              |
| **Accounting Office** | `view-purchase-request`, `process-payments`, `view-financial-data`, `create-disbursement-voucher`, `validate-costs`, `view-workflow-status`, `view-documents`, `view-reports`                                                                                                                                                       |
| **Supplier**          | `view-purchase-request`, `view-supplier-info`, `upload-documents`, `view-documents`, `track-delivery`                                                                                                                                                                                                                               |


**Supplier role is seeded but the public supplier portal is not routed.** Preserve the role. Wiring a portal is a gap fix (file 15).

---



## Capability matrix (what each actor does)


| Actor                                     | Primary work                                                                                                     |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **End User / Dean**                       | Create PR from validated PPMP; view own PRs; replacement PR after supply return; manage department PPMP          |
| **Supply Officer**                        | Review PR, lots, activate/return/reject/cancel; create PO (single/batch); inventory receipts; internal suppliers |
| **Budget Office**                         | Earmark, reject (Deferred), amend earmark after the fact; department budget allocations                          |
| **Executive Officer**                     | Approve/reject PR into BAC; approve users, departments, department requests; approve suppliers; super-admin gate |
| **BAC Chair / Members / Secretariat**     | Item groups, RFQ, quotations, AOQ, ties, override, withdrawal, failed procurement, meetings                      |
| **BAC Secretariat only (via permission)** | PS-DBMS catalog import; consolidated APP view                                                                    |
| **Canvassing Unit**                       | Internal supplier CRUD (no CEO approve)                                                                          |
| **Accounting Office**                     | Disbursement vouchers on POs                                                                                     |
| **System Admin**                          | BAC signatories (with BAC Chair); full access via gate                                                           |
| **Supplier (intended)**                   | Portal for quotes/delivery — **not wired**                                                                       |


---



## Acceptance criteria

- [ ] Seed creates all 12 roles and 48 permissions with **exact names**.
- [ ] System Admin and Executive Officer receive all permissions **and** bypass gates.
- [ ] Dean can create PRs and view reports; End User cannot `view-reports` unless granted later.
- [ ] Supply Officer is the role that receives `edit-purchase-request` (used as route middleware).
- [ ] Supplier role exists even if portal routes are deferred.
- [ ] Position lookup is separate from roles; `users:assign-roles` mapping is preserved.