# 18 — Cursor prompts (one slice per chat)

**Purpose:** Copy-paste prompts for rebuilding CagSU SVP in a **new** Laravel + Inertia React repo. Specs stay in files 00–17; this file is only how to ask Cursor.

**How to use**

1. Copy this whole `docs/rebuild/` folder into the new repo (required). Optionally keep the incumbent app at `D:\SystemFiles\Herd\cagsu-svp-system` so Cursor can re-read cited classes.
2. New **Agent** chat per slice. Do not rebuild the whole product in one prompt.
3. Optional: **Plan mode** first with `Plan Slice N from docs/rebuild/16-rebuild-implementation-order.md. Do not implement yet.` Then paste the slice prompt in Agent after you accept.
4. After a slice is done, start a fresh chat: `Slice N-1 is done. Continue Slice N. Same standing rules.`
5. `@` the spec files listed in each prompt every time.

Incumbent path (if the spec is thin): `D:\SystemFiles\Herd\cagsu-svp-system`

---

## Standing rules (paste once, or save as `.cursor/rules/svp-rebuild.mdc`)

```
You are rebuilding CagSU Small Value Procurement (SVP) in this Laravel + Inertia React starter kit.

Behavior contract: docs/rebuild/ (read 00-README.md, 16-rebuild-implementation-order.md, 17-toolchain.md, 18-cursor-prompts.md first).
If a spec is thin, re-read the cited class in the incumbent app at:
D:\SystemFiles\Herd\cagsu-svp-system

Hard rules:
- Preserve status strings, Spatie role/permission names, numbering formats, table names.
- New PRs land on supply_office_review (not draft).
- Supply lots (is_lot / parent_lot_id) are NOT BAC item groups (pr_item_groups G1/G2).
- Form Requests are the validation source of truth. Zod only mirrors. No bidding/ABC/PPMP remaining-qty logic in React.
- Do not invent RA 9184 rules that are not in the specs.
- Do not start at BAC quotations. Follow file 16 slices.
- This starter is bare (no shadcn). Slice 0 installs shadcn. Later slices reuse that kit. Do not add a second UI library.
- GSAP only on the public landing. Impeccable Operate for authenticated UI.
- Keep PHPWord/PhpSpreadsheet on the server. Do not generate BAC resolutions in React.
- Skip gaps in 15-known-gaps.md unless I explicitly ask.
- Every slice: PHPUnit tests for the new behavior, then vendor/bin/pint --dirty.
- Do not add Livewire, Filament, or TanStack Query as the data layer. Inertia Form / useForm only.
```

---

## Slice 0 — Foundation (bare starter)

**Specs:** `00-README.md`, `16` (Slice 0 only), `17-toolchain.md` (Bare starter)

```
This is a barebones Laravel + Inertia React starter kit. I am rebuilding CagSU SVP here.

Read docs/rebuild/00-README.md, 16-rebuild-implementation-order.md (Slice 0 only), and 17-toolchain.md (Bare starter section).

Slice 0 only — foundation + UI kit:

1. PostgreSQL in .env. Tests stay SQLite in-memory unless a query is Postgres-specific.
2. GET /health JSON { status: ok, time }, named route health.
3. Install and wire (do not skip):
   - TypeScript for Inertia pages if not already
   - shadcn/ui + Tailwind (keep starter Tailwind major version)
   - lucide-react
   - Wayfinder (prefer over Ziggy)
   - Spatie Permission
   - Pint + Larastan if straightforward
4. Set shadcn CSS variables to CagSU colors: maroon #800000, gold #FFD700, orange #FF8C00, blue #1D4ED8. Not default zinc. Dark mode only if the starter already has class dark mode.
5. One App layout + one Guest layout using shadcn primitives so later slices have a shell.
6. Do NOT install yet: Pulse, Backup, GSAP, Playwright, Recharts, Horizon, Precognition, TanStack Table, Zod.
7. Do NOT create the full SVP schema or auth workflow yet.

Stop when: migrate works on Postgres, /health works, a sample shadcn page renders in the Inertia shell, vendor/bin/pint --dirty is clean.

Do not start Slice 1.
```

---

## Slice 1 — Auth and org

**Specs:** `01-product-and-roles.md`, `11-seeders-and-demo-data.md`, `12-auth-users-departments.md`

```
Implement rebuild Slice 1 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 1), 01-product-and-roles.md, 11-seeders-and-demo-data.md, 12-auth-users-departments.md.

shadcn and the App/Guest layouts already exist from Slice 0. Reuse them. Do not add another UI kit.

Adapt the starter-kit auth (do not rip it out). Add:
- Spatie roles/permissions with EXACT names from file 01 (12 roles, 48 permissions)
- Gate::before: System Admin and Executive Officer pass all abilities
- Positions, departments, CEO user approval, department requests, ID proof upload (jpeg/png/webp/pdf, max 10 MB)
- Register → pending / is_active false → CEO approve → can reach verified dashboard
- Unapproved users cannot reach dashboard
- Seeders from file 11 (demo password password123). Document CollegeSeeder vs ComprehensiveUserSeeder code drift (COA vs CA, etc.)
- System Admin cannot create PRs (StorePurchaseRequestRequest wins over policy)
- User::getPrimarySVPRole() priority order from file 12
- Install TanStack Table only if you need it for CEO users/departments tables this slice; otherwise wait until a queue needs it

PHPUnit: register, pending login block, CEO approve/reject, department request approve. Then vendor/bin/pint --dirty.

Done when: demo users from file 11 can log in; unapproved users cannot reach dashboard.

Do not start PPMP or purchase requests.
```

---

## Slice 2 — Catalog and PPMP

**Specs:** `05-ppmp-ps-dbms-app.md`, `09-imports.md`, `02-database-schema.md` (planning tables)

```
Implement rebuild Slice 2 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 2), 05-ppmp-ps-dbms-app.md, 09-imports.md, and the planning tables in 02-database-schema.md.

Implement:
- app_items PS-DBMS catalog import (Artisan app:import + UI ps-dbms.*). Permission manage-ps-dbms.
- PPMP CRUD, validate, CSV import (ppmp:import-csv + UI). Logged-in user’s department_id.
- APP-CSE column map from file 09 (SOFTWARE / PART II specials). Prerequisite: catalog for that fiscal year first.
- Remaining-qty math from file 05 (archived/rejected/cancelled excluded; returned still counts until archived).
- Consolidated APP read-only (view-consolidated-app). No file export.
- One PPMP per (department_id, fiscal_year). Do not use legacy ppmp:import as the happy path.

PHPUnit for remaining qty and import skip rules. Pint. No Purchase Requests yet.

Done when: import catalog then PPMP; validate PPMP; remaining qty tests pass.
```

---

## Slice 3 — PR create + observer budget

**Specs:** `03-workflow.md` (create only), `06-budget-and-earmark.md` (reserve), `02-database-schema.md`, `13-notifications-and-audit.md`

```
Implement rebuild Slice 3 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 3), 03-workflow.md (create/store only), 06-budget-and-earmark.md (reserve + budget check API), 02-database-schema.md (purchase_requests / items), 13-notifications-and-audit.md.

Add now:
- Zod (mirror only) + Laravel Precognition on PR create
- PR + items + optional lots at create (file 04 lot shape, but no Supply lot CRUD yet)
- Status MUST be supply_office_review (not draft)
- pr_number PR-MMYY-####, pr_quarter via quarterly tracker
- Validated PPMP required; remaining qty for current quarter
- Observer-equivalent: reserve department budget on create (non-draft); log created/submitted
- Mail PurchaseRequestSubmitted to ALL Supply Officer users (queued)
- GET /api/budget/check and POST /api/budget/validate (session auth)

PHPUnit for store status, PPMP validation, budget reserve. Pint.

Done when: Dean with validated PPMP submits a PR; Supply Officer is emailed; budget reserved.

Do not build Supply review, Budget Office, or BAC.
```

---

## Slice 4 — Supply review and lots

**Specs:** `03-workflow.md`, `04-lots-and-item-groups.md`

```
Implement rebuild Slice 4 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 4), 03-workflow.md, 04-lots-and-item-groups.md.

Install TanStack Table for the Supply PR queue if not already present.

Supply Officer (can:edit-purchase-request):
- Queue, show, updateStatus: start_review, activate, return, reject, cancel
- activate → budget_office_review + WorkflowRouter createPendingForRole(..., budget_office_earmarking, Budget Office) + mail
- Lot CRUD only in submitted or supply_office_review; min 2 standalones; destroy detaches children
- Quotable scope: parent_lot_id IS NULL (do not price lot children)
- Replacement PR after return: new PR supply_office_review, original archived (replaces_pr_id / replaced_by_pr_id)
- PR Excel export only if storage/app/templates/PurchaseRequestTemplate.xlsx exists; otherwise skip export and note it (file 15)

rejected UI label: Deferred. Mail PurchaseRequestStatusUpdated to requester on supply status changes.

PHPUnit for activate, lots min-2, replacement archive. Pint.

Done when: activate moves to budget_office_review and creates budget_office_earmarking pending + mail.

Do not build Budget earmark UI or BAC.
```

---

## Slice 5 — Budget and CEO

**Specs:** `06-budget-and-earmark.md`, `03-workflow.md`, `10-exports-and-documents.md` (earmark xlsx)

```
Implement rebuild Slice 5 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 5), 06-budget-and-earmark.md, 03-workflow.md, 10-exports-and-documents.md (earmark Excel cells).

Budget Office:
- Department budgets UI (TanStack Table)
- Earmark approve → ceo_approval + pending ceo_initial_approval for Executive Officer
- Reject → rejected (Deferred)
- Amend earmark: NO workflow status change; activity earmark_amended
- Earmark Excel if EarmarkTemplate.xlsx exists; mint EM-MMYY-#### if missing

CEO:
- Approve → bac_evaluation, procurement_method = small_value_procurement (always), resolution_number RES-MMYY-####, best-effort BacResolutionService DOCX
- Reject → rejected
- Mail requester on budget/CEO decisions

Install PHPWord + kwn/number-to-words if needed for resolution. Precognition/Zod on earmark form (mirror Form Request).

PHPUnit for approve/reject/amend and CEO auto method. Pint.

Done when: PR reaches bac_evaluation with resolution_number; reject is Deferred.

Do not build RFQ/quotations/AOQ yet (resolution generate on CEO approve is enough).
```

---

## Slice 6 — BAC core (ungrouped)

**Specs:** `07-bac-rfq-aoq-quotations.md`, `10-exports-and-documents.md`

```
Implement rebuild Slice 6 only. Ungrouped PRs only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 6), 07-bac-rfq-aoq-quotations.md, 10-exports-and-documents.md.

BAC Chair | Members | Secretariat:
- bac_signatories roster (System Admin | BAC Chair CRUD)
- PR-level RFQ generate/download/regenerate (DOCX on local disk)
- Quotation store/evaluate; ABC: unit_price > estimated_unit_cost → non_compliant / ineligible
- AOQ generate; ties block generation until resolveTie (justification min 10 chars); bac_override min 20 chars
- Finalize OR item-level winners sufficient for later PO (preserve both paths)
- Meetings can be thin (scheduled/completed/cancelled)
- Quotes abort if procurement_method empty

Bidding logic in PHP (AoqService-equivalent). React is forms + tables only. TanStack Table for quotation grids.

PHPUnit: three quotes → auto winner; over-ABC disqualified; unresolved tie blocks AOQ. Pint.

Done when those tests pass.

Do not implement item groups, withdrawal, or failed-procurement replacement yet.
```

---

## Slice 7 — Item groups, withdrawal, failed PR

**Specs:** `04-lots-and-item-groups.md`, `07-bac-rfq-aoq-quotations.md`

```
Implement rebuild Slice 7 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 7), 04-lots-and-item-groups.md, 07-bac-rfq-aoq-quotations.md.

- BAC item groups G1, G2…; unique quotes per (purchase_request_id, supplier_id, pr_item_group_id)
- Per-group RFQ/AOQ; canManageGroups only bac_evaluation or partial_po_generation
- syncStatusFromGroups: PR status = MINIMUM group status (mapping in file 04)
- Supplier withdrawal: succession next eligible bid; supplier_withdrawals audit
- markItemFailed only if no eligible bidders; lot children cascade
- BAC replacement PR: new PR supply_office_review; original NOT archived; items re_pr_created

PHPUnit: two groups at different stages; min-group status stored on PR; withdrawal succession. Pint.

Done when: two groups can be at different stages; min-group status is stored on the PR.

Do not build purchase orders yet.
```

---

## Slice 8 — PO, receipts, accounting

**Specs:** `08-purchase-orders-fulfillment.md`, `10-exports-and-documents.md`

```
Implement rebuild Slice 8 only. Do not work ahead.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 8), 08-purchase-orders-fulfillment.md, 10-exports-and-documents.md.

- Winners: is_winner, not withdrawn, not lot children; group by supplier
- Preview: one winner → single PO form; multiple → batch
- PO created pending_approval; PO-MMYY-####
- Grouped: canCreatePo when group aoq_generated; syncStatusFromGroups after PO
- Actions: send_to_supplier, acknowledge, mark_delivered, complete (optional inspection_report)
- Do not invent CEO PO approval unless I ask (file 15 gap)
- Inventory receipts: free-text lines, not FK to PO items
- Disbursement vouchers DV-MMYY-####; Accounting Office status chain
- PO Excel if PurchaseOrderTemplate.xlsx exists
- complete utilizes department budget

PHPUnit: batch PO for two suppliers; complete utilizes budget. Pint.

Done when those pass.

Do not build reports, landing GSAP, or Playwright yet.
```

---

## Slice 9 — Reports, documents, polish

**Specs:** `10-exports-and-documents.md`, `13-notifications-and-audit.md`, `14-routes-and-permissions.md`, `17-toolchain.md`

```
Implement rebuild Slice 9 only.

Read docs/rebuild/16-rebuild-implementation-order.md (Slice 9), 10-exports-and-documents.md, 13-notifications-and-audit.md, 14-routes-and-permissions.md, 17-toolchain.md.

- Report CSVs (columns from file 10) + analytics with Recharts (not Chart.js)
- GET /files/{document} + DocumentPolicy
- PR activity timeline on show
- Internal supplier CRUD + CEO approve (portal still unwired unless I say Slice 10)
- Named routes from file 14 (or a documented mapping)
- Landing: optional GSAP + prefers-reduced-motion; Impeccable Persuade on landing only; Operate on app shell
- Playwright: 4–6 journeys (login → create PR → supply activate → budget earmark → CEO approve; plus one grouped AOQ/PO)
- Laravel Pulse + Spatie Backup (Postgres + storage/). Backup is not the PR timeline.
- Port incumbent feature tests to Inertia assertions where screens moved

PHPUnit for document policy; Playwright smoke. Pint.

Done when: named routes in file 14 exist; document policy tests pass.

Do not implement file 15 gaps unless I explicitly start Slice 10.
```

---

## Slice 10 — Gaps (optional)

**Specs:** `15-known-gaps.md`

```
Implement rebuild Slice 10 only if I listed which gaps. Default: preserve absence.

Read docs/rebuild/15-known-gaps.md.

Only implement the items I name from: supplier portal, BacProcurementMethodController, CEO PO approval, commit Excel templates, unify college codes, close workflow_approvals, align System Admin PR policy.

Do not invent extra RA 9184 automation (no amount-based public bidding unless I ask).
```

---

## Do not send Cursor

- “Rebuild the whole SVP system from the old Blade app.”
- “Make the UI beautiful with GSAP” on workflow screens.
- “Copy every Blade view into React.”
- Mixing Slice 6 (BAC) into Slice 3 (PR create).
- Installing Filament, Livewire, or TanStack Query as the default data layer.
