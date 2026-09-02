# 11 — Seeders and demo data

**Purpose:** Reproduce a usable demo campus after migrate.

**Source:** `database/seeders/*`, `app/Console/Commands/Seed*.php`

---

## `DatabaseSeeder` order (required)

```
PositionSeeder
RolePermissionSeeder
CollegeSeeder
ComprehensiveUserSeeder
SupplierSeeder
AppItemSeeder
```

`PurchaseRequestSeeder` is **commented out** — not part of default `db:seed`.

---

## 1. `PositionSeeder`

Creates `positions.name`:

System Administrator, Supply Officer, Employee, Budget Officer, Executive Officer, BAC Chairman, BAC Member, BAC Secretary, Accounting Officer, Canvassing Officer

---

## 2. `RolePermissionSeeder`

- 48 permissions + 12 roles (file 01)
- Department: **Administrative Office**, code **`ADMIN`**
- Sample admin/supply user creates are **commented out** (users come from ComprehensiveUserSeeder)

---

## 3. `CollegeSeeder` (10 departments)

| Code | Name |
|------|------|
| CALEXT | Calayan Extension |
| COA | College of Agriculture |
| CBEA | College of Business Entrepreneurship and Accountancy |
| CCJE | College of Criminal Justice Education |
| COE | College of Engineering |
| CHM | College of Hospitality Management |
| CIT | College of Industrial Technology |
| CICS | College of Information and Computing Sciences |
| CTE | College of Teacher Education |
| GRADSCH | Graduate School |

Upserts by name **or** code.

---

## 4. `ComprehensiveUserSeeder`

**Password for all demo users: `password123`.** All `approval_status = approved`, `email_verified_at` set, `is_active = true`.

Office users (department `ADMIN` unless noted):

| Email | Role | Name (as seeded) |
|-------|------|------------------|
| `sysadmin@cagsu.edu.ph` | System Admin | System Administrator |
| `supply@cagsu.edu.ph` | Supply Officer | Ronnie S. Agcaoili |
| `budget@cagsu.edu.ph` | Budget Office | Catalina B. Talosig |
| `executive@cagsu.edu.ph` | Executive Officer | Rodel Francisco T. Alegado |
| `bac.chairman@cagsu.edu.ph` | BAC Chair | Christopher R. Garingan |
| `bac.vicechairman@cagsu.edu.ph` | BAC Chair | Allan O. De La Cruz |
| `bac.member1@cagsu.edu.ph` | BAC Members | Valentin M. Apostol |
| `bac.member2@cagsu.edu.ph` | BAC Members | Chris Ian T. Rodriguez |
| `bac.member3@cagsu.edu.ph` | BAC Members | Melvin S. Atayan |
| `bac.secretary@cagsu.edu.ph` | BAC Secretariat | Chanda T. Aquino |
| `accounting@cagsu.edu.ph` | Accounting Office | Fely Jane R. Reyes |
| `canvassing@cagsu.edu.ph` | Canvassing Unit | Chito D. Temporal |

**10 Deans** (role `Dean`, position Employee). This seeder **may change department codes** to match its list:

| Code in this seeder | Email |
|---------------------|-------|
| CALEXT | `calayanextension.sanchezmira@csu.edu.ph` |
| CA | `maycmartinez03@csu.edu.ph` |
| CBEA | `cbea.sanchezmira@csu.edu.ph` |
| CCJE | `ccje.csusm@csu.edu.ph` |
| COE | `coe.sanchezmira@csu.edu.ph` |
| CHM | `angelabtuliao@csu.edu.ph` |
| CIT | `cit.sanchezmira@csu.edu.ph` |
| CICS | `cics_csusm@csu.edu.ph` |
| CTED | `ctedcsusm@csu.edu.ph` |
| GS | `graduateschool.sanchezmira@csu.edu.ph` |

**Rebuild note:** `CollegeSeeder` uses `COA`/`CTE`/`GRADSCH`; this seeder prefers `CA`/`CTED`/`GS` and updates codes if the name matches. Preserve the update-or-create behavior or pick one code set and document it. Tests and fixtures should not assume both codes exist.

---

## 5. `SupplierSeeder`

Seven **active** local suppliers: Lienmavel, ME, Mr. DIY, AW Commercial, Pandayan, Derima, Migrants. Codes `SUP-{YEAR}-####`. Emails `*@supplier.local`. No portal passwords.

---

## 6. `AppItemSeeder`

Sample `app_items` for **current calendar year**: OFFICE SUPPLIES (ballpens), ICT EQUIPMENT (desktop/laptop/printer), SOFTWARE (zero price licenses). Codes as in `AppItemSeeder.php`.

---

## Optional (Artisan only)

| Command | Seeder | Content |
|---------|--------|---------|
| `seed:purchase-requests {--fresh}` | `PurchaseRequestSeeder` | ~9 factory PRs (draft/submitted/completed/equipment). `--fresh` deletes all PRs+items |
| `seed:ceo-approval-prs` | `CEOApprovalPurchaseRequestSeeder` | ~6 PRs in `ceo_approval` |
| `seed:office-prs` | `OfficeSpecificPurchaseRequestSeeder` | ~9 PRs for Budget/Executive/Accounting offices |

Rebuild these as optional demo commands, not default seed.

---

## Acceptance criteria

- [ ] `php artisan migrate --seed` yields ADMIN + colleges, 22 users, 7 suppliers, sample catalog, all roles/permissions.
- [ ] Login `supply@cagsu.edu.ph` / `password123` reaches Supply queues.
- [ ] Dean emails can create PRs for their department after a validated PPMP exists (PPMP is not default-seeded beyond empty get-or-create on use).
- [ ] Document the CollegeSeeder vs ComprehensiveUserSeeder **code drift** (`COA` vs `CA`, etc.).
