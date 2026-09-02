# CagSU SVP — Rebuild specification set

**Audience:** an AI (or developer) implementing a **from-scratch rebuild**. These files are behavioral contracts extracted from the current Laravel app. They are not tutorials and not a copy of Blade.

**Product:** Small Value Procurement (SVP) web application for **Cagayan State University — Sanchez Mira Campus**, aligned with Philippine procurement practice under **RA 9184**. It is **not** a student volunteer program.

**Source of truth for this set:** the codebase at the time these files were written. If a spec is thin, re-read the cited class.

Related existing doc (do not treat as the rebuild contract): [`docs/purchase-request-process-swimlane.md`](../purchase-request-process-swimlane.md).

---

## How to use this set

1. Read this file, then [`16-rebuild-implementation-order.md`](16-rebuild-implementation-order.md).
2. Implement in the order given in file 16. Do **not** start at BAC quotations.
3. Treat **status strings, role names, permission names, numbering formats, and table names** as frozen unless [`15-known-gaps.md`](15-known-gaps.md) marks them as a recommended fix.
4. Default: **preserve current behavior**. Only change behavior when file 15 says “Rebuild note: recommended fix”.
5. Do not invent RA 9184 rules that are not encoded here (for example, do not auto-route to public bidding by amount unless you implement a documented gap fix).

Each spec file contains:

- **Purpose** — what the rebuild must implement
- **Source** — current classes/paths to re-read
- **Contract** — exact identifiers and rules
- **Acceptance criteria** — done-when checks

---

## Recommended rebuild stack

Domain files (01–15) are **stack-agnostic**. Stack lives here, in file 16 (slice order), and in [`17-toolchain.md`](17-toolchain.md).

| Layer | Choice |
|--------|--------|
| Runtime | PHP 8.4+ / Laravel 13 |
| UI | Inertia.js + React 19 + TypeScript. **Bare starter:** install shadcn in Slice 0 ([`17-toolchain.md`](17-toolchain.md)) |
| CSS | Tailwind CSS 3 (or 4 if you accept a one-time migration) |
| Auth | Session (Breeze React or equivalent) + Spatie Permission |
| Database | **PostgreSQL** (tests may stay SQLite in-memory unless a query is Postgres-specific) |
| Documents | PHPWord (DOCX), PhpSpreadsheet (XLSX) — keep as **server** jobs |
| Design process | Impeccable **Operate** mode for app UI; **Persuade** only for the public landing |
| Motion | GSAP **only** on landing (optional). Not on PR/BAC/budget/PO screens |

Keep: Eloquent domain, form requests, policies, queued mail, Excel/Word generation. Replace: Blade, Alpine register wizard, four Livewire tables.

Libraries on top of this stack (shadcn, TanStack Table, Zod, Recharts, Playwright, Pulse, Spatie Backup, Impeccable, GSAP limits, plus Wayfinder / Precognition / Larastan): [`17-toolchain.md`](17-toolchain.md).

---

## Reading order

| # | File | When |
|---|------|------|
| 01 | [product-and-roles](01-product-and-roles.md) | Before any auth or nav work |
| 02 | [database-schema](02-database-schema.md) | Before migrations |
| 03 | [workflow](03-workflow.md) | Before PR status UI |
| 04 | [lots-and-item-groups](04-lots-and-item-groups.md) | Before Supply review or BAC grouping |
| 05 | [ppmp-ps-dbms-app](05-ppmp-ps-dbms-app.md) | Before PR create |
| 06 | [budget-and-earmark](06-budget-and-earmark.md) | Before Budget Office |
| 07 | [bac-rfq-aoq-quotations](07-bac-rfq-aoq-quotations.md) | After CEO approval works |
| 08 | [purchase-orders-fulfillment](08-purchase-orders-fulfillment.md) | After AOQ winners exist |
| 09 | [imports](09-imports.md) | With PS-DBMS / PPMP |
| 10 | [exports-and-documents](10-exports-and-documents.md) | With document generation |
| 11 | [seeders-and-demo-data](11-seeders-and-demo-data.md) | After schema |
| 12 | [auth-users-departments](12-auth-users-departments.md) | With Breeze |
| 13 | [notifications-and-audit](13-notifications-and-audit.md) | With workflow transitions |
| 14 | [routes-and-permissions](14-routes-and-permissions.md) | When wiring HTTP |
| 15 | [known-gaps](15-known-gaps.md) | Before claiming feature-parity |
| 16 | [rebuild-implementation-order](16-rebuild-implementation-order.md) | Always first after this README |
| 17 | [toolchain](17-toolchain.md) | With slice 0 (before adding UI kits) |

---

## What to preserve vs optional

**Preserve (do not redesign the domain):**

- PR status machine as coded (new PRs land on `supply_office_review`)
- Supply lots vs BAC item groups (two different concepts)
- PPMP remaining-qty math
- Budget reserve / utilize / release via observer-equivalent
- ABC check: quotation unit price ≤ item estimated unit cost
- Numbering formats (`PR-MMYY-####`, etc.)
- Spatie role and permission **names**

**Optional / recommended fixes:** listed in [`15-known-gaps.md`](15-known-gaps.md) (unwired supplier portal, unused workflow steps, SVP threshold inconsistency, Excel templates not in git).

---

## Current vs rebuild (orientation)

The incumbent app is Laravel 13 + Blade + Livewire 4 + Alpine + Tailwind 3 + MySQL (SQLite in tests). ~33 models, ~40 controllers, ~88 migrations, 124 Blade views, 46 feature tests.

A rebuild should re-implement **behavior**, not copy view files. Prefer the existing feature tests as a contract; adapt `assertSee` to Inertia assertions as screens move.
