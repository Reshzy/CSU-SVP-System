# 15 — Known gaps and inconsistencies

**Purpose:** Features that exist in code, schema, or UI copy but are **not** complete. Default rebuild: **preserve absence** unless a row says **recommended fix**.

Do not invent extra RA 9184 automation.

---

## Unwired HTTP (code + views, no routes)

| Item | Evidence | Rebuild default |
|------|----------|-----------------|
| Public supplier registration | Views/`SupplierRegistrationController` public methods; `route('suppliers.register')` referenced; `LandingPageTest` expects **404** | Keep 404 unless product wants a portal |
| Supplier contact form | `SupplierCommunicationController`, `SupplierMessageReceived` | Leave unused |
| Supplier quotation/PO public portal | `SupplierQuotationPublicController`, `SupplierPOStatusController`, views under `suppliers/` | Role `Supplier` is seeded; portal not required for parity |
| `BacProcurementMethodController` | Full controller + views choosing method | CEO already hard-sets `small_value_procurement`. Wiring this **changes** behavior |
| `QuotationController` PhpWord AOQ prototype | Not in `routes/web.php` | Ignore; real path is `AoqService` |

---

## Workflow declared but not connected

| Gap | Detail |
|-----|--------|
| `draft` / `submitted` first step | New PRs skip to `supply_office_review`. Lots still allow `submitted`. Seeders/factory PRs may still use draft/submitted |
| `WorkflowRouter` steps `bac_award_recommendation`, `ceo_final_approval`, `po_generation`, `po_approval` | In `getStepOrder()` and approval `step_name` enum; controllers never `createPendingForRole` for them |
| CEO PO approval | Swimlane says CEO approves PO. No controller sets PO `approved` or PR via CEO. POs are created `pending_approval` then Supply send/ack |
| `workflow_approvals` closure | Pending rows may never flip to `approved` when the office acts |
| `po_approved` PR status | Only from grouped `all_po_approved` if POs are `approved` — rare with current PO actions |

**Rebuild note (optional):** either implement CEO PO approval to match the swimlane, **or** drop unused statuses/steps from the new schema. Do not leave both “documented in UI” and unused without a comment.

---

## Business-rule inconsistencies

| Gap | Detail |
|-----|--------|
| SVP threshold | Unwired BAC method UI: SVP below **₱1,000,000**. Old migration comment: “Under 50k”. **No amount-based routing in code** |
| `PurchaseRequestPolicy::create` vs `StorePurchaseRequestRequest` | Policy allows System Admin; form request **forbids** System Admin creating PRs. **Request wins** |
| College codes | `CollegeSeeder` `COA`/`CTE`/`GRADSCH` vs `ComprehensiveUserSeeder` `CA`/`CTED`/`GS` — seeder updates codes by name |
| Observer reserve failure | Reserve errors are **logged**, PR still created |
| `calculateTotalCost` vs lots | Sum of qty × unit cost on **all** items may double-count if both headers and children are included — re-read `calculateTotalCost` when porting tests |

---

## Documents / templates

| Gap | Detail |
|-----|--------|
| Excel templates | `EarmarkTemplate.xlsx`, `PurchaseRequestTemplate.xlsx`, `PurchaseOrderTemplate.xlsx` live in `storage/app/templates/` and are **not in git**. Rebuild must obtain campus templates |
| Inspection report validation | PO complete upload has weak/no explicit mime rules in controller |
| Quotation files vs `documents` | Scans often only on `quotations.quotation_file_path` |

---

## UI / product incomplete (incumbent)

| Gap | Detail |
|-----|--------|
| Mobile nav | Responsive menu does not expose full role menus |
| Settings / landing metrics | Placeholder links (`#`, “Placeholder”) |
| In-app notifications | Mail only |
| Supplier `password` | Always null on internal registration |
| `PurchaseRequestSubmitted` | Silent if no Supply Officer user |

---

## Recommended fixes (only if the rebuild charter includes them)

1. **Commit Excel templates** (or generate DOCX/XLSX without campus binaries).
2. **Unify college codes** in seeders.
3. **Align policy + form request** for System Admin PR create (keep forbid).
4. **Either wire or delete** supplier portal artifacts.
5. **Document SVP threshold** as a single number if method selection is ever UI-driven.
6. **Close `workflow_approvals`** when offices act.

None of these are required for “behavior parity with production happy path.”

---

## Acceptance criteria for a parity rebuild

- [ ] Gaps in “unwired HTTP” remain unwired **or** are explicitly listed as new scope.
- [ ] CEO still auto-sets `small_value_procurement` unless BacProcurementMethod is deliberately revived.
- [ ] Tests do not assume `/suppliers/register` exists unless the portal is in scope.
- [ ] Template files are obtained before claiming Excel export done.
