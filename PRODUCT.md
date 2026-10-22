# CagSU SVP Portal

Small Value Procurement (SVP) for **Cagayan State University — Sanchez Mira Campus**. End-to-end staff procurement under RA 9184: PPMP → purchase request → Supply lots → budget earmark → CEO approval → BAC (RFQ / quotations / AOQ) → purchase order → inventory receipt → disbursement voucher.

This is not a student portal. There is one authenticated app. Navigation is role-gated.

## Design modes

- **Operate** — authenticated UI. Dense forms, queues, tables, and RA 9184 paperwork. App chrome (sidebar, dashboard, tokens) is Operate. Do not restyle BAC or budget tables with marketing motion.
- **Persuade** — public `/` landing only. Copy must include **“CagSU SVP Portal”**. CTAs: Log in / Register. No supplier-portal links.

GSAP is optional and imported only from the landing page. Honor `prefers-reduced-motion`. Do not use GSAP on authenticated workflow screens.

## Actors

Spatie roles (do not rename): System Admin, Supply Officer, End User, Budget Office, Executive Officer, BAC Chair, BAC Members, BAC Secretariat, Accounting Office, Canvassing Unit, Dean.

`Gate::before`: System Admin and Executive Officer pass every ability check.

Positions (`positions` table) are job titles, not RBAC.

## Tooling that must stay in its lane

| Tool | Job |
|------|-----|
| Inertia React + shadcn | App UI |
| TanStack Table | Lists |
| Recharts | Reports analytics only (not Chart.js) |
| Zod / Precognition | Mirror server Form Requests, never replace them |
| Laravel Pulse | Ops: queue wait, slow requests, exceptions. Not the PR activity timeline. |
| Spatie Backup | Nightly Postgres dump + `storage/` |
| Playwright | Five smoke journeys |
| Pest | Status strings, qty math, policies |

## Hard stops (file 15 / Slice 10)

No supplier portal, no `BacProcurementMethodController`, no CEO PO approval, no Pulse-as-timeline, no Horizon (keep `QUEUE_CONNECTION=database`).
