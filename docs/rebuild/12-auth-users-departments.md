# 12 — Auth, users, and departments

**Purpose:** Session auth, registration with CEO approval, departments, ID proof.

**Source:**

- `routes/auth.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/DepartmentRequestController.php`
- `app/Http/Controllers/CeoUserManagementController.php`
- `app/Http/Controllers/CeoDepartmentController.php`
- `app/Http/Controllers/CeoDepartmentRequestController.php`
- `app/Models/User.php` (`getPrimarySVPRole`)
- `app/Policies/PurchaseRequestPolicy.php`
- `app/Providers/AppServiceProvider.php`

---

## Auth stack

- Laravel Breeze **session** auth (not Sanctum SPA). Rebuild: Breeze React / Inertia equivalent.
- Dashboard: middleware `auth` + **`verified`** (`/dashboard`).
- Email verification routes exist (`verification.notice`, signed verify, resend throttled 6/min).
- Password reset + confirm password + profile update/destroy (`ProfileController`).
- Logout: `POST logout`.

`Gate::before`: **System Admin** and **Executive Officer** pass all abilities.

---

## Registration

Guest: `GET/POST register`.

Required: name, unique email, confirmed password, `department_id`, `position_id`, **at least one ID proof** (jpeg/png/webp/pdf, max 10 MB each).

Created user:

- `is_active = false`
- `approval_status = pending`
- ID files → `documents` morph User, type `other`, path `user-id-proofs/{Y}/{m}/`

Default position in UI: **Employee**.

Users **cannot** use the app until CEO approves (active + approved). Rebuild must keep this gate on login/middleware.

**System Admin cannot create PRs** (`StorePurchaseRequestRequest::authorize`). Policy `create()` still lists System Admin — **form request wins**. Replacement PR also blocks System Admin.

---

## CEO user queue

Routes `ceo.users.*` (`role:Executive Officer`):

- Approve: `approval_status = approved`, `is_active = true`, `approved_by/at`
- Reject: `rejected`, timestamps, reason

After approve, role is assigned via position map (`users:assign-roles`) or CEO UI if present — preserve: approved users need a Spatie role before they can hit role middleware.

Livewire today: `Ceo/UsersTable`. Rebuild: Inertia table.

---

## Departments

CEO CRUD: `ceo.departments.*` — name, code, contacts, `is_active`, `is_archived`.

Guest **request new department** if missing from register dropdown:

- `register.request-department` GET/POST
- Table `department_requests` status `pending` → CEO approve creates `departments` row or reject with reason
- CEO routes `ceo.department-requests.*`

Livewire today: `Ceo/DepartmentManagement`.

---

## Internal suppliers (not guest)

`can:manage-suppliers`: CRUD `/supply/suppliers`. Status often `pending_verification` until CEO `POST supply.suppliers.approve` → `active`. Portal `password` stays null.

---

## PR visibility policy

`PurchaseRequestPolicy::view`:

- Roles that see **all** PRs: System Admin, Executive Officer, Supply Officer, Budget Office, BAC Chair, BAC Members, BAC Secretariat
- Dean / End User: **same `department_id`** (college-scoped, not only `requester_id`)

`viewAny` is true for all authenticated; controllers still filter lists (own PRs vs office queues).

Requester export: own PR only (`purchase-requests.export`).

---

## Dashboard primary role

`User::getPrimarySVPRole()` first match in order:

System Admin → Executive Officer → Supply Officer → BAC Chair → Budget Office → BAC Members → BAC Secretariat → Canvassing Unit → Accounting Office → Dean → End User → Supplier → default End User

Use this for nav/dashboard widgets, not for authorization (Spatie + gates do that).

---

## Acceptance criteria

- [ ] Register requires ID proof and lands in pending/inactive.
- [ ] Unapproved users cannot reach `verified` dashboard.
- [ ] CEO approve activates the account; reject stores reason.
- [ ] Guest can request a department; CEO approve/reject works.
- [ ] System Admin cannot submit a PR.
- [ ] Deans see college PRs; End Users are department-scoped the same way in policy.
- [ ] Super-admin gate still bypasses permission middleware for CEO/Admin.
