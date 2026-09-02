# 09 — File imports and uploads

**Purpose:** CSV catalog/PPMP imports and other user file uploads.

**Source:**

- `app/Console/Commands/ImportAppCsv.php` (`app:import {file?} {--year=}`)
- `app/Console/Commands/ImportPpmpFromCsv.php` (`ppmp:import-csv {file} {--year=} {--department=}`)
- `app/Console/Commands/ImportPpmpCsv.php` (`ppmp:import {file?}` — **legacy, do not rebuild as primary**)
- `app/Http/Controllers/AppItemController.php`, `PpmpController.php`
- `PurchaseRequestController::handleAttachments`
- `BacQuotationController::store` (quotation scan)
- `RegisteredUserController::store` (ID proof)
- `PurchaseOrderController::update` (inspection report)

---

## APP-CSE / PS-DBMS CSV (shared format)

Source files look like `APP-CSE 2025 Form CICS.csv` (PHP `fgetcsv`).

**Category rows:**

- First cell ALL CAPS, not numeric, length > 10, not containing `PART I.`, `APP-CSE`, `ANNUAL`
- Special: first cell `SOFTWARE` (case-insensitive) → category `SOFTWARE`
- Any row whose joined text contains `PART II` and `OTHER ITEMS` → category `PART II - OTHER ITEMS NOT AVAILABLE AT PS-DBM`

**Item rows:** first cell is a **numeric sequence**.

| Index | Field |
|-------|--------|
| 0 | Sequence (numeric → item row) |
| 1 | Item code |
| 2 | Item name |
| 3 | Unit of measure |
| 7 | Q1 quantity (PPMP import only) |
| 12 | Q2 quantity |
| 17 | Q3 quantity |
| 22 | Q4 quantity |
| 25 | Unit price (strip `₱`, commas, spaces) |

SOFTWARE / PART II: zero/null price allowed. Other items: price parsed from col 25; `> 0` or null.

Empty rows skipped.

---

## PS-DBMS catalog import → `app_items`

| | |
|--|--|
| Artisan | `php artisan app:import {file} --year={year}` (default file `APP-CSE 2025 Form CICS.csv`, year = current) |
| UI | `GET/POST /reference/ps-dbms/import` — `ps-dbms.import`, `ps-dbms.process` |
| Permission | `manage-ps-dbms` |
| Validation | `csv_file` required, `mimes:csv,txt`, max 10 MB; `fiscal_year` 2020–2100 |
| Temp | `imports/app_import_{timestamp}.csv` on default disk; delete after |
| Upsert | `(item_code, fiscal_year)` |

---

## PPMP import → `ppmps` + `ppmp_items`

| | |
|--|--|
| Artisan | `php artisan ppmp:import-csv {file} --year= --department=` |
| UI | `GET/POST /ppmp/import` — uses **logged-in user’s** `department_id` |
| Validation | Same CSV rules |
| Flow | `Ppmp::getOrCreateForDepartment()`; match `AppItem` by `item_code` + year; **skip** missing catalog codes or total qty 0; recalc `total_estimated_cost` |

**Prerequisite:** catalog imported for that fiscal year first.

**Do not rebuild** `ppmp:import` (`ImportPpmpCsv`) as the main path — it predates `ppmp_id` / `app_item_id`.

---

## Other uploads

| Upload | Validation | Disk / path | Document |
|--------|------------|-------------|----------|
| PR attachments | `attachments.*` file, max 10 MB | `public` `documents/` | type `purchase_request` morph PR |
| Quotation scan | `pdf,jpg,jpeg,png` max 5 MB | `public` `quotations/quotation_{time}_{supplier_id}.{ext}` | usually path on `quotations`, not always `documents` |
| User ID proof | jpeg/png/webp/pdf max 10 MB | `public` `user-id-proofs/{Y}/{m}/` | type `other` morph User |
| Inspection report | optional on PO complete | `public` `documents/` | type `inspection_report` |

Default `FILESYSTEM_DISK=local` → `storage/app/private`. User-facing uploads use disk **`public`** (`storage/app/public`). `php artisan storage:link` required for public URLs.

---

## Other Artisan (not CSV catalog)

| Signature | Purpose |
|-----------|---------|
| `data:archive-existing {--force}` | Soft-archive departments, users, PRs |
| `users:assign-roles` | Position → role (file 01) |
| `seed:purchase-requests {--fresh}` | Sample PRs |
| `seed:ceo-approval-prs` | PRs in `ceo_approval` |
| `seed:office-prs` | Office-specific sample PRs |

---

## Acceptance criteria

- [ ] Catalog and PPMP parsers share the column map above (including SOFTWARE / PART II).
- [ ] PPMP import skips unknown item codes and zero-qty rows.
- [ ] UI CSV max 10 MB; quotation scans max 5 MB.
- [ ] ID proofs attach to the User via `documents`.
- [ ] Legacy `ppmp:import` is not the documented happy path.
