# 08 — Purchase orders, receipts, and disbursement

**Purpose:** Turn AOQ winners into POs, track delivery, pay.

**Source:**

- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Services/PurchaseOrderService.php`
- `app/Http/Controllers/InventoryReceiptController.php`
- `app/Http/Controllers/AccountingDisbursementController.php`
- `app/Http/Controllers/PoSignatoryController.php`
- `app/Models/PurchaseOrder.php`, `PurchaseOrderItem.php`

---

## When PO creation is allowed

`PurchaseRequest::canCreatePo()`:

- **Grouped:** any group `computeStatus() === aoq_generated` (AOQ exists, no PO yet)
- **Non-grouped:** PR status `bac_approved` **or** `bac_evaluation`

Winners: `PurchaseOrderService::getWinningItemsGroupedBySupplier()` — `QuotationItem` where `is_winner = true`, `is_withdrawn = false`, excluding lot children.

---

## Single vs batch

| Path | When | Routes |
|------|------|--------|
| Single | One winning supplier | `supply.purchase-orders.create` / `store` |
| Batch | Multiple winning suppliers | `preview` redirects to single if count = 1; else `batch-create` / `batch-store` → `createBatchPurchaseOrders()` |

Created status: **`pending_approval`**. Number: `PO-MMYY-####`.

- Header FKs: PR, optional `pr_item_group_id`, `supplier_id`, `quotation_id`
- Lines: `purchase_order_items` link PR item + quotation item + qty/price
- Financial: TIN, funds cluster, ORS/BURS fields, etc.
- Default terms copy in UI: government / RA 9184 wording

After store: grouped PR `syncStatusFromGroups()`; non-grouped may set `po_generation`.

**Gap:** no controller sets PO to `approved` or runs CEO `ceo_final_approval`. Group status `all_po_approved` / PR `po_approved` is **computed** if POs somehow reach `approved`. Supply lifecycle below jumps from `pending_approval` into send/ack. Preserve this unless implementing the unused CEO PO-approval step (file 15).

---

## PO lifecycle (`PurchaseOrderController::update` actions)

| Action | PO status | PR effect (typical) |
|--------|-----------|---------------------|
| `send_to_supplier` | `sent_to_supplier` | `supplier_processing` |
| `acknowledge` | `acknowledged_by_supplier` | `supplier_processing` |
| `mark_delivered` | `delivered` | `delivered` |
| `complete` | `completed` | `completed` (+ optional inspection `Document` type `inspection_report`) |

Other PO statuses in schema: `draft`, `approved`, `in_progress`, `cancelled` — keep in enum even if unused.

Excel export: `PurchaseOrderExportService` + `PurchaseOrderTemplate.xlsx` (file 10).

---

## PO signatories

Table `po_signatories` (global roster). Positions: `ceo`, `chief_accountant`. Used when filling Excel (and related docs). CRUD: `supply.po-signatories.*` (Supply `edit-purchase-request`).

---

## Inventory receipts

Supply, after delivery:

- Header: `purchase_order_id`, `received_date`, `reference_no`, `status` `draft`|`posted`, `notes`, `received_by`
- Lines: **free-text** (`description`, UOM, qty, prices) — **not** FK to `purchase_order_items`

Routes: `supply.inventory-receipts.*`

---

## Disbursement vouchers

Accounting Office, typically on completed POs:

- `voucher_number` `DV-MMYY-####`
- FKs: `purchase_order_id`, `supplier_id`
- `status`: `draft` → `submitted` → `approved` → `released` → `paid` (or `cancelled`)
- `prepared_by`, `approved_by`, payment timestamps

Routes: `accounting.vouchers.*` (`role:Accounting Office`).

---

## Acceptance criteria

- [ ] Preview sends a single-winner PR to the single PO form; multi-winner to batch.
- [ ] PO lines come only from winning, non-withdrawn, non-child items.
- [ ] Grouped PRs: one group can receive a PO while others stay in BAC (`partial_po_generation`).
- [ ] Send → ack → delivered → complete updates PO and syncs PR (and groups).
- [ ] Receipt lines are independent descriptions, not required to match PO item IDs.
- [ ] DVs use `DV-MMYY-####` and the accounting status chain.
- [ ] Inspection report upload on complete is optional and stored as `inspection_report`.
