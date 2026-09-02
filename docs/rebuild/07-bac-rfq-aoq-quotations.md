# 07 — BAC: RFQ, quotations, AOQ

**Purpose:** Canvassing after CEO approval: resolution, RFQ, supplier quotes, Abstract of Quotations, ties, override, withdrawal, failed procurement.

**Source:**

- `app/Http/Controllers/CeoApprovalController.php`
- `app/Http/Controllers/BacQuotationController.php`
- `app/Http/Controllers/BacItemGroupController.php`
- `app/Http/Controllers/BacProcurementMethodController.php` (**not routed** — file 15)
- `app/Services/BacResolutionService.php`, `BacRfqService.php`, `AoqService.php`
- `app/Services/SupplierWithdrawalService.php`, `SignatoryLoaderService.php`
- `app/Http/Controllers/BacSignatoryController.php`, `BacMeetingController.php`

---

## Entry to BAC

On CEO approve (`CeoApprovalController::update`):

1. PR `status = bac_evaluation`
2. `procurement_method = 'small_value_procurement'` (always; not chosen in UI)
3. `procurement_method_set_at`, `procurement_method_set_by`
4. Mint `resolution_number` (`RES-MMYY-####`)
5. `BacResolutionService::generateResolution()` (best-effort; failure must not block approval)
6. `WorkflowRouter::createPendingForRole(..., 'bac_evaluation', 'BAC Secretariat')`

Quotation store **aborts** if `procurement_method` is empty.

**Encoded RA 9184:** method enum includes `public_bidding`, `direct_contracting`, `negotiated_procurement`, but CEO path never sets them. UI text on the **unwired** procurement-method screen says SVP below **₱1,000,000**. A migration comment historically said “Under 50k”. **Do not auto-switch method by amount** unless implementing a documented gap fix.

ABC (Approved Budget for the Contract) per line = `purchase_request_items.estimated_unit_cost`. Quote `unit_price` must be ≤ ABC to be eligible (`QuotationItem::isWithinAbc()`). Exceeding ABC: `quotations.exceeds_abc = true`, `bac_status = non_compliant`; AOQ auto-disqualifies.

---

## BAC resolution (DOCX)

- PHPWord, portrait, Century Gothic 10pt; WHEREAS/RESOLVED; BAC + CEO sign-off
- Path: local disk `resolutions/{resolution_number}.docx`
- `documents.document_type = bac_resolution`
- Download / regenerate: `bac.quotations.resolution.download|regenerate`
- Signatories from `bac_signatories` via `SignatoryLoaderService`, stored on `resolution_signatories`

---

## Item groups (optional)

If BAC splits the PR: [`04-lots-and-item-groups.md`](04-lots-and-item-groups.md). RFQ/AOQ/quotations then run **per group**. Unique quote: one supplier per `(purchase_request_id, supplier_id, pr_item_group_id)`.

---

## RFQ (Request for Quotation)

| Mode | Service | Number | Storage |
|------|---------|--------|---------|
| Whole PR | `BacRfqService::generateRfq` | `purchase_requests.rfq_number` | `Document` type `bac_rfq`, path `rfq/{rfq_number}.docx` |
| Item group | `generateRfqForGroup` | `rfq_generations.rfq_number` | `RfqGeneration.file_path` `rfq/RFQ_{no}_{group_code}_{timestamp}.docx` |

Requires `hasBeenThroughBac()`. Items table includes lot headers with indented children. Signatories: `bac_chairperson`, `canvassing_officer`.

Routes: `bac.quotations.rfq.*`, `bac.item-groups.rfq.*`.

---

## Quotation entry (`BacQuotationController::store`)

- Requires `procurement_method`
- Optional scan: `quotation_file` pdf/jpg/png, max 5 MB → `public` `quotations/`
- Per-item `unit_price` optional; **at least one** quotable item priced
- Lot children skipped
- Auto lowest-bidder: `identifyLowestBidder()` may set `bac_status = lowest_bidder`

`quotations.bac_status`: `pending_evaluation`, `compliant`, `non_compliant`, `lowest_bidder`, `awarded`, `not_awarded`

**Evaluate:** `technical_score` (60%) + `financial_score` (40%) → `total_score`. Status: `compliant` | `non_compliant` | `lowest_bidder`.

Activity: `quotation_submitted`, `quotation_evaluated`.

---

## AOQ (Abstract of Quotations)

`AoqService::calculateWinnersAndTies`:

1. Per quotable item, rank by `total_price` ascending
2. Auto-disqualify over ABC
3. Mark `is_lowest`, `is_tied`
4. No tie → auto winner + `AoqItemDecision` `decision_type = auto`
5. Unresolved tie → **block** AOQ generation until `resolveTie`

**Tie:** `resolveTie` — `decision_type = tie_resolution`, justification **min 10 characters**.

**BAC override:** `applyBacOverride` — `decision_type = bac_override`, justification **min 20 characters**.

Generate:

- PR-level: `generateAoqDocument`
- Group: `generateAoqDocumentForGroup` (group `canCreateAoq` / regenerate `canRegenerateAoq` — regenerate deletes prior AOQ if no PO)
- Consolidated grouped PR: `generateConsolidatedAoq`

`AoqGeneration`: `AOQ-MMYY-####`, snapshot + hash, landscape DOCX 13×8.5 in, Century Gothic 7pt, path `aoq_documents/AOQ_{ref}_{timestamp}.docx`. Signatories include BAC Head + CEO.

Activity: `aoq_generated`, `tie_resolved`, `bac_override`.

---

## Finalize (legacy PR-level)

`BacQuotationController::finalize()` sets one quotation `is_winning_bid`, PR → `bac_approved`. Modern item-level AOQ + `canCreatePo()` may allow PO at `bac_evaluation` **or** `bac_approved` without this. Preserve both paths.

---

## Supplier withdrawal

`SupplierWithdrawalService::withdraw` / `AoqService::processSupplierWithdrawal`:

- Only winning, quoted, non-withdrawn, non-disqualified items
- PR status `bac_evaluation` or `bac_approved`
- May auto-promote next eligible bidder (`decision_type = withdrawal_succession`)
- Audit row in `supplier_withdrawals`

Activity: `supplier_withdrawal`.

---

## Failed procurement

- `markItemFailed`: only if **no eligible bidders** remain; `procurement_status = failed`; cascade lot children
- `createReplacementPr`: `AoqService::handleFailedProcurement` for failed items without `replacement_pr_id`; new PR at `supply_office_review`; items `re_pr_created`

---

## Meetings and signatory roster

- `bac_meetings` + attendees pivot; statuses `scheduled`, `completed`, `cancelled`
- Global `bac_signatories`: System Admin or BAC Chair CRUD (`/bac/signatories`)

---

## Acceptance criteria

- [ ] CEO approve always sets `small_value_procurement` and attempts resolution DOCX.
- [ ] Quotes cannot be stored without `procurement_method`.
- [ ] Unit price > estimated unit cost → non-compliant / ineligible for AOQ.
- [ ] AOQ generation blocked on unresolved ties.
- [ ] Tie justification ≥ 10 chars; override ≥ 20 chars.
- [ ] Group RFQ/AOQ/PO are independent tracks.
- [ ] Withdrawal can succession the next eligible bid and writes `supplier_withdrawals`.
- [ ] Failed items can spawn a replacement PR without archiving the original.
