# 02 — Database schema

**Purpose:** Recreate the domain schema. Column lists below are the **logical contract**. Re-read `database/migrations/` and `app/Models/` when implementing types, indexes, and later ALTER columns.

**Source:** `app/Models/*`, `database/migrations/`

**Rebuild note:** Use PostgreSQL. Current MySQL `ENUM` columns should become PostgreSQL enums or `varchar` + check constraints. **Values must match the strings below.** PHP has **no** domain enums today; do not invent PHP enum names that diverge from these strings.

Tests in the incumbent app use SQLite in-memory; SQLite skips some MySQL ENUM `ALTER` migrations. Postgres rebuild should apply the full status set in one migration.

---

## Domain chain

```
app_items (PS-DBMS / APP catalog, by fiscal year)
    → ppmps + ppmp_items (department PPMP, quarterly qty)
    → purchase_requests + purchase_request_items (lines link ppmp_item_id)
    → optional: lots on items (is_lot / parent_lot_id)
    → optional: pr_item_groups (G1, G2…) + items.pr_item_group_id
    → rfq_generations → quotations → quotation_items
    → aoq_generations + aoq_item_decisions
    → purchase_orders + purchase_order_items
    → inventory_receipts → disbursement_vouchers
```

Budget: `department_budgets` reserved on PR create (non-draft), utilized on `completed`, released on `cancelled` / `rejected` / `returned_by_supply`. See [`06-budget-and-earmark.md`](06-budget-and-earmark.md).

There is **no** separate `apps` or `earmarks` table. APP catalog = `app_items`. Earmark data lives on `purchase_requests`.

---

## Numbering formats

Sequence is per prefix + `MMYY` (or year for suppliers). Pad sequence to 4 digits.

| Entity | Format | Example | Generator |
|--------|--------|---------|-----------|
| PR | `PR-MMYY-####` | `PR-0126-0003` | `PurchaseRequest::generateNextPrNumber()` |
| Earmark | `EM-MMYY-####` | `EM-0126-0042` | `generateNextEarmarkId()` |
| Resolution | `RES-MMYY-####` | `RES-0126-0001` | `generateNextResolutionNumber()` |
| RFQ (PR-level) | `RFQ-MMYY-####` | | `PurchaseRequest::generateNextRfqNumber()` |
| RFQ (group) | same pattern on `rfq_generations` | | `RfqGeneration::generateNextRfqNumber()` |
| AOQ | `AOQ-MMYY-####` | | `AoqGeneration::generateNextReferenceNumber()` |
| PO | `PO-MMYY-####` | | `PurchaseOrder::generateNextPoNumber()` |
| DV | `DV-MMYY-####` | | `DisbursementVoucher::generateNextVoucherNumber()` |
| Document | `DOC-MMYY-####` | | `Document::generateNextDocumentNumber()` |
| Supplier | `SUP-{YEAR}-####` | `SUP-2026-0001` | `Supplier::generateSupplierCode()` |
| Item group | `G1`, `G2`, … | | `PrItemGroup::generateNextGroupCode()` |

`MMYY` = month then two-digit year (`n->format('my')`).

---

## Status / type strings

### `purchase_requests.status`

`draft`, `submitted`, `supply_office_review`, `budget_office_review`, `ceo_approval`, `bac_evaluation`, `bac_approved`, `partial_po_generation`, `po_generation`, `po_approved`, `supplier_processing`, `delivered`, `completed`, `cancelled`, `rejected`, `returned_by_supply`

Default in original migration: `draft`. **Runtime:** new PRs are created as `supply_office_review`. UI label for `rejected`: **Deferred**.

### Other machines

| Column | Values |
|--------|--------|
| `purchase_request_items.item_status` | `pending`, `approved`, `rejected`, `modified`, `cancelled` |
| `purchase_request_items.procurement_status` | `pending`, `awarded`, `failed`, `re_pr_created` |
| `purchase_orders.status` | `draft`, `pending_approval`, `approved`, `sent_to_supplier`, `acknowledged_by_supplier`, `in_progress`, `delivered`, `completed`, `cancelled` |
| `quotations.bac_status` | `pending_evaluation`, `compliant`, `non_compliant`, `lowest_bidder`, `awarded`, `not_awarded` |
| `ppmps.status` | `draft`, `validated` |
| `workflow_approvals.status` | `pending`, `approved`, `rejected`, `returned_for_revision`, `skipped` |
| `workflow_approvals.step_name` | `supply_office_review`, `budget_office_earmarking`, `ceo_initial_approval`, `bac_evaluation`, `bac_award_recommendation`, `ceo_final_approval`, `po_generation`, `po_approval` |
| `documents.status` | `draft`, `pending_review`, `approved`, `rejected`, `archived` |
| `documents.document_type` | `purchase_request`, `ppmp`, `earmark_document`, `bac_resolution`, `bac_rfq`, `abstract_of_quotation`, `purchase_order`, `quotation_file`, `delivery_receipt`, `inspection_report`, `ris`, `ics`, `par`, `other` |
| `suppliers.status` | `active`, `inactive`, `blacklisted`, `pending_verification` |
| `suppliers.business_type` | `sole_proprietorship`, `partnership`, `corporation`, `cooperative` |
| `bac_meetings.status` | `scheduled`, `completed`, `cancelled` |
| `inventory_receipts.status` | `draft`, `posted` |
| `disbursement_vouchers.status` | `draft`, `submitted`, `approved`, `released`, `paid`, `cancelled` |
| `supplier_messages.status` | `new`, `read`, `archived` |
| `department_requests.status` | `pending`, `approved`, `rejected` |
| `users.approval_status` | `pending`, `approved`, `rejected` |
| `purchase_requests.procurement_type` | `supplies_materials`, `equipment`, `infrastructure`, `services`, `consulting_services` (nullable) |
| `purchase_requests.procurement_method` | `small_value_procurement`, `public_bidding`, `direct_contracting`, `negotiated_procurement` (nullable) |
| `aoq_item_decisions.decision_type` | `auto`, `tie_resolution`, `bac_override`, `withdrawal_succession` |
| `purchase_requests.fund_cluster_code` | `01`, `05`, `06`, `07` |

Fund cluster labels: `01` Regular Agency Fund, `05` Off-Budgetary Fund, `06` Income Generating Enterprise, `07` Trust Receipts.

Resolution/AOQ signatory `position` values: `bac_chairman`, `bac_vice_chairman`, `bac_member_1`, `bac_member_2`, `bac_member_3`, `head_bac_secretariat`, `ceo`

RFQ signatory positions: `bac_chairperson`, `canvassing_officer`

PO signatory positions: `ceo`, `chief_accountant`

---

## Tables

### Organization and users

**`departments`:** `name`, `code`, `description`, `head_name`, contact fields, `is_active`, `is_archived`

**`department_requests`:** requested dept fields, `requester_email`, `status`, `reviewed_by`, `reviewed_at`, `rejection_reason`

**`department_budgets`:** unique `(department_id, fiscal_year)`; `allocated_budget`, `utilized_budget`, `reserved_budget`, `notes`, `set_by`

**`positions`:** `name`

**`users`:** `name`, `email`, `password`, `department_id`, `employee_id`, `position_id`, `phone`, `is_active`, `is_archived`, `approval_status`, `approved_at`, `rejected_at`, `approved_by`, `rejected_by`, email verification, remember token. Spatie `HasRoles`.

Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

Laravel infra: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`

### Planning

**`app_items`:** `fiscal_year`, `category`, `item_code`, `item_name`, `unit_of_measure`, `unit_price`, `specifications`, `is_active`. Lookup key: `(item_code, fiscal_year)`.

**`ppmps`:** `department_id`, `fiscal_year`, `status`, `total_estimated_cost`, `validated_at`, `validated_by`. One PPMP per department per year (get-or-create).

**`ppmp_items`:** `ppmp_id`, `app_item_id`, `q1_quantity`–`q4_quantity`, `total_quantity`, `estimated_unit_cost`, `estimated_total_cost`. Unique `(ppmp_id, app_item_id)`.

### Purchase requests

**`purchase_requests`** — hub. Key columns:

- Identity: `pr_number`, `pr_title`
- Actors: `requester_id`, `department_id`, `current_handler_id`, `returned_by`, `rejected_by`
- Narrative: `purpose`, `justification`, `date_needed`, `estimated_total`
- Funding: `funding_source`, `fund_cluster_code`, `fund_details`, `budget_code`
- Procurement: `procurement_type`, `procurement_method`, `procurement_method_set_at`, `procurement_method_set_by`
- Workflow: `status`, `pr_quarter`, `is_archived`, `current_step_notes`, timestamps (`submitted_at`, `approved_at`, `completed_at`, `returned_at`, `rejected_at`, `status_updated_at`)
- PPMP: `has_ppmp`, `ppmp_reference`
- Earmark (on PR, not a table): `earmark_id`, `legal_basis`, `earmark_programs_activities`, `earmark_responsibility_center`, `earmark_date_to`, `earmark_object_expenditures` (JSON array)
- BAC numbers: `resolution_number`, `rfq_number` (legacy PR-level; groups use `rfq_generations`)
- Replacement: `replaces_pr_id`, `replaced_by_pr_id`
- Return/reject text: `return_remarks`, `rejection_reason`

**`purchase_request_items`:**

- FKs: `purchase_request_id`, `ppmp_item_id`, `pr_item_group_id`
- Lots: `is_lot`, `lot_name`, `parent_lot_id` (self-FK)
- Catalog snapshot: `item_code`, `item_name`, `detailed_specifications`, `unit_of_measure`, `quantity_requested`, `estimated_unit_cost`, `estimated_total_cost`, `item_category`
- PPMP snapshot: `ppmp_quarter`, `ppmp_planned_qty_for_quarter`, `ppmp_remaining_qty_at_creation`
- Status: `item_status`, `procurement_status`, awarded fields, `replacement_pr_id`, failure timestamps/reason

**`pr_item_groups`:** `purchase_request_id`, `group_name`, `group_code`, `display_order`, `status` (stored default `pending`; **effective** status is computed). Unique `(purchase_request_id, group_code)`.

### BAC / quotations

**`quotations`:** `quotation_number`, `purchase_request_id`, `pr_item_group_id`, `supplier_id`, `supplier_location`, dates, `total_amount`, `exceeds_abc`, `bac_status`, `technical_score`, `financial_score`, `total_score`, `is_winning_bid`, `quotation_file_path`, `supporting_documents` (JSON). Unique `(purchase_request_id, supplier_id, pr_item_group_id)`.

**`quotation_items`:** `quotation_id`, `purchase_request_item_id`, `unit_price`, `total_price`, `is_within_abc`, `rank`, `is_lowest`, `is_tied`, `is_winner`, `disqualification_reason`, `is_withdrawn`, `withdrawn_at`, `withdrawal_reason`. Unique `(quotation_id, purchase_request_item_id)`.

**`rfq_generations`:** `pr_item_group_id`, `rfq_number`, `generated_by`, `generated_at`, `file_path`

**`rfq_signatories`:** `rfq_generation_id` nullable, `purchase_request_id` nullable, `position`, `user_id`, `name`, `prefix`, `suffix`

**`aoq_generations`:** `aoq_reference_number`, `purchase_request_id`, `pr_item_group_id`, `generated_by`, `document_hash`, `exported_data_snapshot`, `file_path`, `file_format`, counts

**`aoq_item_decisions`:** `purchase_request_id`, `purchase_request_item_id`, `winning_quotation_item_id`, `decision_type`, `justification`, `decided_by`, `decided_at`, `is_active`

**`aoq_signatories`:** `aoq_generation_id`, `position`, `user_id`, `name`, `prefix`, `suffix`

**`bac_meetings`:** `purchase_request_id` nullable, `meeting_datetime`, `location`, `status`, `title`, `agenda`, `minutes`, `created_by`

**`bac_meeting_attendees`:** pivot `bac_meeting_id`, `user_id`, `role_at_meeting`, `attended`, `remarks`

**`bac_signatories`:** standing roster `user_id`, `position`, `prefix`, `suffix`, `is_active`

**`resolution_signatories`:** per-PR, same position pattern

**`po_signatories`:** `user_id` nullable, `manual_name`, `position`, `prefix`, `suffix`, `is_active`

**`supplier_withdrawals`:** `quotation_item_id`, `supplier_id`, `purchase_request_item_id`, `pr_item_group_id`, `withdrawal_reason`, `withdrawn_at`, `withdrawn_by`, `successor_quotation_item_id`, `resulted_in_failure`

### Fulfillment

**`purchase_orders`:** `po_number`, `purchase_request_id`, `pr_item_group_id`, `supplier_id`, `quotation_id`, `po_date`, `total_amount`, delivery/financial (`tin`, `funds_cluster`, `ors_burs_no`, etc.), `status`, approval/delivery timestamps

**`purchase_order_items`:** `purchase_order_id`, `purchase_request_item_id`, `quotation_item_id`, `quantity`, `unit_price`, `total_price`

**`inventory_receipts`:** `purchase_order_id`, `received_date`, `reference_no`, `status`, `notes`, `received_by`

**`inventory_receipt_items`:** `inventory_receipt_id`, `description`, `unit_of_measure`, `quantity`, `unit_price`, `total_price` (free-text lines; **not** FK to PO items)

**`disbursement_vouchers`:** `voucher_number`, `purchase_order_id`, `supplier_id`, `amount`, `voucher_date`, `status`, `prepared_by`, `approved_by`, payment timestamps

### Documents, workflow, audit, suppliers

**`documents`:** polymorphic `documentable_type` / `documentable_id` (used: `PurchaseRequest`, `User`). `document_number`, `document_type`, `title`, file meta, `version`, `previous_version_id`, `is_current_version`, `uploaded_by`, `is_public`, `visible_to_roles` (JSON role names), `status`

**`workflow_approvals`:** `purchase_request_id`, `step_name`, `step_order`, `approver_id`, `approved_by`, `status`, `comments`, `remarks`, `assigned_at`, decision timestamps. Unique-ish: firstOrCreate on `(purchase_request_id, step_name)`

**`purchase_request_activities`:** `purchase_request_id`, `user_id`, `pr_item_group_id`, `action`, `old_value`/`new_value` (JSON), `description`, `ip_address`, `user_agent`, `created_at` (no `updated_at`)

**`suppliers`:** `supplier_code`, `business_name`, `business_type`, contact/address, `tin`, `status`, `performance_rating`, optional portal `password` / `remember_token` (password is null on internal registration)

**`supplier_messages`:** `purchase_request_id`, `supplier_id`, `supplier_name`, `supplier_email`, `subject`, `message_body`, `status`

---

## Uniques / constraints to preserve

- `quotations (purchase_request_id, supplier_id, pr_item_group_id)` — one quote per supplier per PR (or per group)
- `quotation_items (quotation_id, purchase_request_item_id)`
- `ppmp_items (ppmp_id, app_item_id)`
- `pr_item_groups (purchase_request_id, group_code)`
- `department_budgets (department_id, fiscal_year)`
- Signatory uniqueness per parent + `position`

---

## Observer

`PurchaseRequestObserver` (register equivalent):

| Event | Behavior |
|-------|----------|
| creating | Set `pr_quarter` via quarterly tracker if null |
| created | Log creation; if status ≠ `draft`, reserve department budget; log submission if `supply_office_review` or `submitted` |
| updated status | Log; `draft`→`submitted` reserve; `completed` utilize; `cancelled`/`rejected`/`returned_by_supply` release if old status ≠ `draft` |
| updated handler/notes | Log assignment / notes |
| deleted | Release reserved budget unless status is `draft` / `completed` / `cancelled` / `rejected` |

---

## Acceptance criteria

- [ ] All tables above exist with FKs and uniques listed.
- [ ] Status strings match exactly (including `returned_by_supply`, `partial_po_generation`).
- [ ] Number generators produce the documented prefixes.
- [ ] `earmark_object_expenditures` is JSON; `visible_to_roles` is JSON array of role names.
- [ ] Polymorphic `documents` attach to PRs and users (ID proof).
- [ ] No separate APP or earmark tables unless you explicitly migrate data into them **and** update all specs — default is keep columns on PR / `app_items`.
