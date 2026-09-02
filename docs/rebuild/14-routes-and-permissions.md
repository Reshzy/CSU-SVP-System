# 14 — Routes and permissions

**Purpose:** HTTP surface area. Preserve **named routes** where possible so mail links and tests port.

**Source:** `routes/web.php`, `routes/auth.php`

Middleware aliases: Spatie `role:` / `permission:` / `can:` plus Laravel `auth`, `verified`, `guest`. Super-admin `Gate::before` still applies to `can:`.

There is **no** `routes/api.php`. JSON budget endpoints live under the **web** `auth` group.

---

## Public

| Method | Path | Name |
|--------|------|------|
| GET | `/` | (landing, unnamed in code) |
| GET | `/health` | `health` → JSON `{ status: ok, time }` |

---

## Guest (`routes/auth.php`)

`register`, `login`, `password.request`, `password.email`, `password.reset`, `password.store`, `register.request-department`, `register.request-department.store`

## Authenticated auth extras

`verification.notice`, `verification.verify` (signed, throttle 6,1), `verification.send`, `password.confirm`, `password.update`, `logout`

---

## All authenticated

| Name | Path | Notes |
|------|------|--------|
| `dashboard` | GET `/dashboard` | also `verified` |
| `profile.edit/update/destroy` | `/profile` | |
| `files.show` | GET `/files/{document}` | |
| `purchase-requests.index/create/store/show` | resource, only those four | no edit/update/destroy |
| `purchase-requests.export` | GET `.../export` | own PR |
| `purchase-requests.replacement.create/store` | `.../{originalPr}/replacement` | |
| `ppmp.*` | `/ppmp` index, import, create, store, edit, update, validate, summary | |
| `api.budget.check` | GET `/api/budget/check` | |
| `api.budget.validate` | POST `/api/budget/validate` | |

---

## `can:edit-purchase-request` (Supply Officer)

Prefix `/supply/`:

- PRs: `supply.purchase-requests.index/show/status/export`
- Lots: `supply.purchase-requests.lots.store/update/destroy`
- POs: `supply.purchase-orders.index`, `preview`, `batch-create`, `batch-store`, `create`, `store`, `show`, `edit`, `update`, `export`
- Signatories: `supply.po-signatories` resource except show
- Receipts: `supply.inventory-receipts.index/create/store/show`

---

## `can:manage-ps-dbms`

`ps-dbms.index/import/process` → `/reference/ps-dbms`

## `can:view-consolidated-app`

`bac.app.index` → GET `/bac/app`

## `can:manage-suppliers`

`supply.suppliers.index/create/store/edit/update`

## `role:Executive Officer`

- `supply.suppliers.approve`
- `ceo.purchase-requests.index/show/update`
- `ceo.users.index/show/approve/reject`
- `ceo.departments` CRUD (no destroy in routes)
- `ceo.department-requests.index/show/approve/reject`

## `role:Budget Office`

- `budget.purchase-requests.index/edit/update/reject/export-earmark/amend/amend-earmark`
- `budget.index/edit/update/show` (department budgets)

## `role:Accounting Office`

`accounting.vouchers.index/create/store/show/update`

## `can:view-reports`

`reports.pr`, `reports.pr.export`, `reports.analytics`, `reports.suppliers`, `reports.suppliers.export`, `reports.budget`, `reports.budget.export`, `reports.custom`, `reports.custom.export`

## `role:System Admin|BAC Chair`

`bac.signatories.*` CRUD except show

## `role:BAC Chair|BAC Members|BAC Secretariat`

Item groups: `bac.item-groups.create/store/edit/update/destroy`

Quotations: `bac.quotations.index/manage/group-quotations/store/evaluate/finalize`

Resolution: `bac.quotations.resolution.download/regenerate`

RFQ: `bac.quotations.rfq.generate/download/regenerate` + `bac.item-groups.rfq.*`

AOQ: `bac.quotations.aoq`, `aoq.generate/download/resolve-tie/bac-override`, `bac.item-groups.aoq.generate/download`, `bac.quotations.aoq.consolidated`

Withdrawal: `bac.quotation-items.withdraw`, `withdrawal-preview`, `bac.quotations.withdrawal-history`

Failed: `bac.pr-items.mark-failed`, `bac.quotations.create-replacement-pr`

Meetings: `bac.meetings.index/create/store/show`

---

## Controllers without routes (do not expose unless implementing gaps)

- `BacProcurementMethodController`
- `SupplierQuotationPublicController`
- `SupplierCommunicationController`
- Public `suppliers.register` (views exist; tests may expect 404)
- `QuotationController` (AOQ prototype)

---

## Acceptance criteria

- [ ] Named routes above exist (or a documented mapping table if Inertia paths differ).
- [ ] Supply group is permission `edit-purchase-request`, not only role name.
- [ ] BAC routes require any of the three BAC roles.
- [ ] Health endpoint remains unauthenticated JSON.
- [ ] Unwired controllers stay unwired unless file 15 fix is in scope.
