# 16 — Rebuild implementation order

**Purpose:** Ordered slices so an AI does not start at BAC quotations. Each slice has **done when** checks. Read [`00-README.md`](00-README.md) for stack.

**Preserve:** models/behavior in files 01–14. **Do not** greenfield a second workflow.

Suggested stack for this rebuild: Laravel 13 + Inertia React + TypeScript + PostgreSQL + Spatie Permission. GSAP only on landing. Impeccable Operate for app chrome. Package-level choices: [`17-toolchain.md`](17-toolchain.md).

---

## Slice 0 — Foundation

The target app is a **bare** Inertia React starter (no shadcn). See [`17-toolchain.md`](17-toolchain.md) “Bare starter”.

- PostgreSQL in `.env`; tests SQLite in-memory unless a query is Postgres-specific
- `GET /health` JSON `{ status: ok, time }`, named route `health`
- Install: TypeScript (if missing), **shadcn/ui**, lucide-react, Wayfinder, Spatie Permission, Pint, Larastan
- CagSU CSS tokens (maroon `#800000`, gold `#FFD700`, orange `#FF8C00`); App + Guest layouts
- Do **not** install Pulse, Backup, GSAP, Playwright, Recharts, Horizon, Zod, TanStack Table, Precognition yet
- Do **not** create the full SVP schema yet (numbering/status strings still come from file 02 when Slice 2–3 start)

**Done when:** `migrate` works on Postgres; `/health` JSON; a sample shadcn page renders in the Inertia shell; tests bootstrap on SQLite or Postgres consistently.

---

## Slice 1 — Auth and org

Specs: 01, 11, 12.

- Roles/permissions seed (exact names)
- Positions, departments, users, CEO approval, department requests, ID proof
- Super-admin gate
- Register → pending → CEO approve → login
- Reuse Slice 0 App/Guest layouts and shadcn. Do not add another UI kit.

**Done when:** demo users from file 11 can log in; unapproved users cannot reach dashboard.

---

## Slice 2 — Catalog and PPMP

Specs: 05, 09.

- `app_items` import
- PPMP CRUD + validate + CSV import
- Remaining-qty math
- Consolidated APP read-only

**Done when:** import catalog then PPMP; validate PPMP; remaining qty tests pass.

---

## Slice 3 — PR create + observer budget

Specs: 03 (create only), 06 (reserve), 02.

- PR + items + optional lots at create
- Status `supply_office_review`
- `pr_quarter`, PPMP validation
- Observer reserve
- Activity `created`/`submitted`
- Mail `PurchaseRequestSubmitted`
- Budget check JSON API

**Done when:** Dean with validated PPMP submits a PR; Supply Officer is emailed; budget reserved.

---

## Slice 4 — Supply review and lots

Specs: 03, 04.

- Supply queue, activate/return/reject/cancel
- Lot CRUD (min 2 standalones)
- PR Excel export if templates exist
- Replacement PR after return (archive original)

**Done when:** activate moves to `budget_office_review` and creates `budget_office_earmarking` pending + mail.

---

## Slice 5 — Budget and CEO

Specs: 06, 03, 10 (earmark xlsx).

- Department budgets UI
- Earmark approve/reject/amend/export
- CEO approve/reject
- Auto `small_value_procurement` + resolution attempt
- Requester status mail

**Done when:** PR reaches `bac_evaluation` with `resolution_number`; reject is Deferred.

---

## Slice 6 — BAC core (ungrouped)

Specs: 07, 10.

- Signatory roster
- RFQ generate/download
- Quotation store/evaluate, ABC checks
- AOQ generate, ties, override
- Finalize **or** item-level winners sufficient for PO
- Meetings (can be thin)

**Done when:** three quotes → AOQ with auto winner; over-ABC disqualified; unresolved tie blocks AOQ.

---

## Slice 7 — Item groups, withdrawal, failed PR

Specs: 04, 07.

- Group CRUD G1…
- Per-group RFQ/AOQ/quotes unique per supplier+group
- `syncStatusFromGroups` / `partial_po_generation`
- Withdrawal succession
- Mark failed + replacement PR (original not archived)

**Done when:** two groups can be at different stages; min-group status is stored on the PR.

---

## Slice 8 — PO, receipts, accounting

Specs: 08, 10.

- Winner grouping; single vs batch PO
- Send / ack / delivered / complete + PR sync
- Inventory receipts
- Disbursement vouchers
- PO Excel

**Done when:** batch PO for two suppliers; complete utilizes budget.

---

## Slice 9 — Reports, documents, polish

Specs: 10, 13, 14.

- Report CSVs + analytics
- `files.show` + policies
- Activity timeline on PR show
- Internal supplier CRUD + CEO approve
- Landing (optional GSAP); Impeccable Operate on app shell
- Port incumbent feature tests to Inertia assertions

**Done when:** named routes in file 14 exist; document policy tests pass.

---

## Slice 10 — Gaps (optional)

Only if chartered: file 15 recommended fixes (supplier portal, method picker, CEO PO approval, template git, college codes).

---

## What not to do

- Do not rewrite `AoqService` rules “to be cleaner” without tests.
- Do not put bidding logic in React; props + form posts / Inertia visits only.
- Do not apply GSAP to tables or approval queues.
- Do not skip PPMP remaining-qty or lot quotable scope.

---

## Acceptance criteria for the whole rebuild

- [ ] Slices 1–9 match happy path in file 03.
- [ ] Lots ≠ item groups (file 04).
- [ ] Imports/exports match files 09–10 (templates present).
- [ ] Seed matches file 11 (or a documented single college-code set).
- [ ] Gaps in file 15 are either still absent or implemented as listed new scope.
