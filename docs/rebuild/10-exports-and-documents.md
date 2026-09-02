# 10 — Exports and documents

**Purpose:** Generated XLSX/DOCX/CSV and the `documents` file store.

**Source:**

- `app/Services/EarmarkExportService.php`
- `app/Services/PurchaseRequestExportService.php`
- `app/Services/PurchaseOrderExportService.php`
- `app/Services/BacResolutionService.php`, `BacRfqService.php`, `AoqService.php`
- `app/Http/Controllers/ReportsController.php`
- `app/Http/Controllers/DocumentController.php`, `app/Policies/DocumentPolicy.php`
- `config/filesystems.php`

---

## Excel templates (not in git)

Load from `storage/app/templates/`. **Missing templates throw at runtime.** Rebuild must vendor these files or regenerate equivalent layouts.

| File | Service | Download name |
|------|---------|-----------------|
| `EarmarkTemplate.xlsx` | `EarmarkExportService` | `Earmark-{earmark_id}.xlsx` |
| `PurchaseRequestTemplate.xlsx` | `PurchaseRequestExportService` | `PR-{pr_number}.xlsx` |
| `PurchaseOrderTemplate.xlsx` | `PurchaseOrderExportService` | `PO-{po_number}.xlsx` |

Temp files: `storage/app/temp/` — delete after send (`deleteFileAfterSend(true)`). Temp names include timestamps (`EARMARK-{id}-{time}.xlsx`, etc.).

### Earmark cells (`fillEarmarkData`)

| Cell | Content |
|------|---------|
| A6 | `EARMARK NO. {earmark_id}` |
| A7 | `Dated: {printed} to {earmark_date_to}` |
| B10 | `funding_source` |
| B11 | `legal_basis` |
| B12 | requester name |
| B13 | `current_step_notes` |
| B14 | PR title / number / created date (PR number may be reformatted from `PR-MMYY-####`) |
| A16 | `earmark_programs_activities` |
| A17 | `earmark_responsibility_center` |
| A18+ | object of expenditures: description in A, amount in C |
| B27+ | date printed |

### PR cells

- D7 PR number, F7 date, B58 purpose
- B66–B67 requester name / position
- E66 CEO from `PoSignatory`
- Item rows **11–55** per sheet; extra pages clone template; C56 page numbers
- Lots: header row UOM `lot`, children indented in description column C

### PO cells

- C4–C6 supplier / address / TIN (overrides if set)
- F4–F5 PO number / date
- D57 / G57 funds cluster / ORS no; D58 / G58 funds available / ORS date; G59 total
- Item rows from **14**: C unit, D description, E qty, F unit cost, G amount
- D44 amount in words (`kwn/number-to-words`), G44 total
- E51 CEO, C61 chief accountant (`PoSignatory`)

Routes: `purchase-requests.export` (own PR), `supply.purchase-requests.export`, `supply.purchase-orders.export`, `budget.purchase-requests.export-earmark`.

---

## PHPWord BAC documents (no DOCX template)

Stored on **`local`** disk (`storage/app/private/`).

| Doc | Path | Type / record |
|-----|------|----------------|
| Resolution | `resolutions/{resolution_number}.docx` | `documents` `bac_resolution` |
| RFQ PR-level | `rfq/{rfq_number}.docx` | `documents` `bac_rfq` |
| RFQ group | `rfq/RFQ_{no}_{group_code}_{Ymd_His}.docx` | `rfq_generations.file_path` |
| AOQ | `aoq_documents/AOQ_{ref}_{Ymd_His}.docx` | `aoq_generations`; download `AOQ_{ref}.docx` |

Resolution: portrait, Century Gothic 10pt. AOQ: landscape 13×8.5 in, Century Gothic 7pt.

`QuotationController` contains an unused PhpWord AOQ prototype — **not routed**. Do not treat as the real AOQ.

---

## Reports CSV (no PhpSpreadsheet)

`ReportsController`, permission `view-reports`. Streaming CSV.

| Method | Filename | Columns |
|--------|----------|---------|
| `prExport` | `purchase_requests_report_{Ymd_His}.csv` | PR Number, Created At, Requester, Department, Purpose, Date Needed, Priority, Estimated Total, Status |
| `suppliersExport` | `supplier_performance_{Ymd_His}.csv` | Supplier, Quotes, Awards, Win Rate %, Awarded Value, POs, Completed POs, PO Value |
| `budgetExport` | `budget_utilization_{Ymd_His}.csv` | Department, PR Count, PR Total, PO Count, PO Total, Utilization % (+ totals row) |
| `customExport` | `custom_report_{Ymd_His}.csv` | User-selected PR field subset |

Analytics page is Chart.js view-only (no export). Consolidated APP: no export (file 05).

---

## `documents` model

- Morph: used on **PurchaseRequest** and **User**
- Number `DOC-MMYY-####`; version chain `version`, `previous_version_id`, `is_current_version`
- Access: `is_public`, `visible_to_roles` (JSON array of **role names**), `status`
- Download: `GET /files/{document}` `files.show` — `DocumentPolicy@view` (public, PR policy, role visibility, uploader, System Admin / Executive Officer)
- Tries **public** then **local**; force attachment for `.docx` / `.xlsx` / `.pptx`

---

## Disks

| Disk | Root | Use |
|------|------|-----|
| `local` | `storage/app/private` | Generated BAC docs, import temps |
| `public` | `storage/app/public` | User uploads |

---

## Acceptance criteria

- [ ] Excel exporters fail clearly if templates are missing; rebuild ships templates or equivalent.
- [ ] PR export paginates items and renders lots as header + children.
- [ ] PO total in words uses a number-to-words library.
- [ ] BAC DOCX land on the private disk; downloads go through authorization.
- [ ] Report CSVs match column headers above.
- [ ] `files.show` never streams another user’s private file without policy.
