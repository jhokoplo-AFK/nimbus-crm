# Nimbus CRM

An enterprise B2B CRM modelled on Salesforce Sales Cloud: lead conversion, pricebook-backed
quotations with manager approval, contract and sales-order generation, and a drag-and-drop
opportunity pipeline.

Built with **Laravel 12**, **Inertia.js v2**, **React 18**, **Tailwind CSS 4**, and **Spatie Laravel-Permission**.

---

## 1. Architecture

```
app/
├── Actions/            # Single-purpose domain operations
│   ├── ApproveQuotationAction.php
│   ├── CalculateQuoteAction.php
│   ├── CloseWonOpportunityAction.php
│   ├── ConvertLeadAction.php
│   └── RejectQuotationAction.php
├── Data/               # Immutable value objects (QuoteTotals, LeadConversionResult)
├── Enums/              # Backed enums with UI metadata (label/tone/probability)
├── Exceptions/         # Domain exceptions -> 422 validation payloads
├── Http/
│   ├── Controllers/    # Inertia page controllers
│   │   └── Api/        # Token-authenticated REST resources
│   ├── Middleware/     # HandleInertiaRequests (shared props)
│   └── Requests/       # Strict Form Request validation
├── Models/             # Eloquent models, relationships, scopes
├── Observers/          # OpportunityObserver -> Closed-Won automation
├── Policies/           # Lead, Opportunity, Quotation authorization
├── Services/           # QuotationService, TargetAchievementService
└── Support/            # DocumentNumberGenerator (locked sequential numbers)
```

Routing, middleware and exception handling live in `bootstrap/app.php` (Laravel 12 streamlined
structure). Laravel merges framework configuration defaults with anything published in `config/`,
so only `config/crm.php` (sales policy) is committed.

### Domain rules

| Rule | Implementation |
| --- | --- |
| Lead conversion is atomic | `ConvertLeadAction` wraps account + contact + opportunity + lead lock in `DB::transaction`, with row locking and an audit trail (`converted_at`, `converted_*_id`). |
| Converted leads are read-only | `LeadPolicy::update/delete` and `UpdateLeadRequest::authorize` reject edits. |
| Prices come from the pricebook | `QuotationService::resolveLines` looks up `pricebook_entries` and ignores client-supplied `unit_price` entirely. Inactive pricebooks and missing entries throw domain exceptions. |
| Quote maths | `CalculateQuoteAction`: line gross → line discount → line total → subtotal → quote discount (flat/percent) → 11% PPN → grand total. Every step rounded to 2 decimals. |
| Approval routing | `PriceApprovalService` reads the `price_approval_layers` matrix: the quote is routed to the lowest layer (Sales → Manager → Regional Manager → General Manager → Director) that covers both its effective discount and grand total. Unseeded installs fall back to the `config/crm.php` thresholds (15% / $50,000). |
| Revisioning | `QuotationService::revise` clones line items, increments `revision_number`, appends `-R{n}` to the quote number, resets approvals and demotes the previous quote from primary. |
| Separation of duties | `quotations.approve` is only granted to Sales Manager and Admin; `QuotationPolicy::approve` requires `In Review`. |
| Closed-Won automation | `OpportunityObserver` funnels every stage transition into `CloseWonOpportunityAction`, locking probability at 100% and creating an idempotent Draft `SalesOrder` (priced from the latest approved/sent quotation). |
| Document numbers | `DocumentNumberGenerator` uses `SELECT … FOR UPDATE` inside the caller's transaction, so `Q-2026-00042` cannot be issued twice. |

### Roles and permissions

| Permission group | Admin | Director | General Manager | Regional Manager | Manager | Sales |
| --- | --- | --- | --- | --- | --- | --- |
| Leads (view/create/update/convert) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Leads delete | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| Accounts / Contacts view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Accounts / Contacts manage | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| Opportunities manage | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Quotations create/update | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Quotations approve** | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| Contracts / Orders / Products view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Products manage | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| **Approval matrix manage** | ✅ | ✅ | ✅ | — | — | — |
| Users manage | ✅ | — | — | — | — | — |

`Sales Manager` and `Sales Rep` remain as legacy aliases and are mapped to the Manager and Sales
approval layers. Admin bypasses policies through `Gate::before`; every other role is checked
against granular Spatie permissions in `app/Policies`.

---

## 2. Database

25 migrations create the schema: users/sessions/cache/jobs, Spatie permission tables, Sanctum
personal access tokens, and the CRM entities (`leads`, `accounts`, `contacts`, `opportunities`,
`brands`, `products`, `pricebooks`, `pricebook_entries`, `quotations`, `quote_line_items`,
`contracts`, `sales_orders`, `sales_order_items`, `sales_targets`, `activities`,
`price_approval_layers`).

Schema notes beyond the brief:

- `leads.converted_at` + `converted_account_id` / `converted_contact_id` / `converted_opportunity_id`
  provide a two-way audit trail and drive the edit lock.
- `quotations` also stores `discount_type`, `discount_value`, `tax_rate`, `approved_by_id`,
  `approved_at`, `rejection_reason` — required to recompute a quote and audit its approval.
- Status columns are portable strings cast to PHP backed enums (`LeadStatus`, `OpportunityStage`,
  `QuotationStatus`, `ContractStatus`, `SalesOrderStatus`, `ActivityType`, …). This avoids
  database-specific `ENUM` migrations and keeps MySQL/PostgreSQL/SQLite identical.
- `contacts.is_primary` is enforced by `Contact::makePrimary()` (demotes siblings).
- `activities` uses a morph map (`lead`, `account`, `contact`, `opportunity`) instead of class names.
- `products.brand_id` and `sales_order_items` exist so revenue can be reported by brand and product.
- `sales_orders.user_id` / `subtotal` attribute booked revenue to a rep and hold the net (pre-tax)
  figure used for target attainment; `total_amount` remains the tax-inclusive ERP amount.
- `price_approval_layers` parameterises the commercial ladder: one row per role level with
  `max_discount_pct`, `max_quote_amount` (null = unlimited), `is_active` and the roles mapped to
  that layer. `quotations.required_approval_level` stores the minimum authority that may approve
  the quote, so a later matrix change cannot silently widen an in-flight approval.
- `sales_targets` stores one row per scope: company-wide (`user_id`, `brand_id`, `account_id`,
  `product_id` all null), per rep, or per brand/customer/product. `year` is required;
  `quarter` set means quarterly, `month` set means monthly, both null means annual.

---

## 3. Frontend

Inertia pages live in `resources/js/Pages`, split by entity. Shared UI primitives are in
`resources/js/Components/UI`.

Highlights:

- **`OpportunityKanban.jsx`** — native HTML5 drag and drop plus a per-card stage selector.
  Column totals and weighted values recalculate locally on drop and reconcile with the server
  after the `PATCH /opportunities/{id}/stage` partial reload (optimistic with rollback on error).
  Closing as lost opens a reason dialog because loss reasons are mandatory.
- **`QuoteForm.jsx`** — `useForm` + reducer-style updates for dynamic line items, products filtered
  by the selected active pricebook, read-only pricebook prices, live row/quote/tax totals, and an
  live routing preview that names the layer (Sales, Manager, Regional Manager, General Manager,
  Director) that must approve the quote.
- **`RecordDetailLayout.jsx`** — Salesforce-style header with badges, highlights, stage tracker,
  tabbed primary column and a right-hand activity panel.
- **`ConvertLeadModal.jsx`** — previews the Account / Contact / Opportunity to be created and
  allows overrides before the atomic conversion.
- **`TargetVsAchievement.jsx`** — year switcher plus annual/quarterly/monthly attainment bars and
  dimension tabs (by sales rep, brand, customer, product). Targets are summed from `sales_targets`;
  achievement is net booked order subtotal, with missing period targets falling back to an even
  split of the annual plan.
- **Inertia v2** — `Inertia::defer()` for dashboard panels and related lists, `Link prefetch` in the
  sidebar, and 12-second polling on an `In Review` quotation so another manager's approval appears
  without a manual refresh.

---

## 4. Local setup

Requirements: PHP 8.3+, Composer, Node 20+, and SQLite (default) or PostgreSQL/MySQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev          # Vite
php artisan serve    # http://localhost:8000
```

Or rebuild the demo dataset at any time:

```bash
php artisan crm:demo
```

### Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@nimbus.test | password |
| Sales Manager | manager@nimbus.test | password |
| Sales Rep | rep@nimbus.test | password |

Seed data includes 6 accounts with primary contacts, 10 leads, 8 opportunities across every stage,
3 pricebooks (one inactive), 3 brands, 8 branded products, quotations in
Draft/In Review/Approved/Sent with a revision chain, an active contract, won deals that generated
sales orders, and month-by-month confirmed revenue for the current year. Company, per-rep, brand,
customer and product targets are seeded for the same year, so the dashboard has something to report
immediately.

### PostgreSQL for production

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nimbus_crm
DB_USERNAME=nimbus
DB_PASSWORD=secret
```

Then `php artisan migrate --seed`. Sales policy is configurable without code changes:

```dotenv
CRM_TAX_RATE=11
CRM_DISCOUNT_APPROVAL_THRESHOLD_PCT=15
CRM_QUOTE_APPROVAL_THRESHOLD_AMOUNT=50000
```

---

## 5. Tests

```bash
php artisan test
```

Coverage:

- `tests/Unit/CalculateQuoteActionTest` — line maths, flat discount cap, 11% tax, both approval thresholds.
- `tests/Feature/ConvertLeadActionTest` — atomic conversion, audit links, lock, double-conversion guard, HTTP 403 on edit.
- `tests/Feature/QuotationServiceTest` — pricebook-authoritative pricing, missing price rejection, discount/amount approval triggers, revision cloning and demotion.
- `tests/Feature/ClosedWonAutomationTest` — probability lock, draft order creation, idempotency, approved-quote totals.
- `tests/Feature/QuotationApprovalTest` — reps forbidden from approving, managers can approve, drafts cannot be approved.
- `tests/Feature/PriceApprovalLayerTest` — discount and value escalation, manager cannot approve a General Manager-layer quote, Director unbounded, runtime re-parameterisation.
- `tests/Feature/OpportunityStageTest` — Kanban stage moves, loss-reason validation, probability resets.
- `tests/Feature/TargetAchievementTest` — annual/quarterly/monthly attainment, rep and dimension breakdowns, annual-target fallback.
- `tests/Feature/ApiTest` — token issuance/revocation, 401s, lead conversion, Closed-Won automation, every paginated resource, catalog/target/order/activity/quotation-approval writes.

Tests run against SQLite in memory (`phpunit.xml`) with `RefreshDatabase`.

---

## 6. REST API

Every table has a Sanctum-authenticated JSON resource under `/api`. Issue a token with the same
credentials as the web login:

```bash
curl -X POST http://localhost:8000/api/auth/token \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@nimbus.test","password":"password","device_name":"cli"}'
```

The response contains `token`; send it as `Authorization: Bearer <token>` on every other call.
Tokens are rate limited to 6 login attempts per minute and API traffic to 120 requests per minute
per user/IP.

| Resource | Endpoints |
| --- | --- |
| Auth | `POST /api/auth/token`, `GET /api/auth/user`, `POST /api/auth/logout` |
| Leads | `GET/POST /api/leads`, `GET/PATCH/DELETE /api/leads/{lead}`, `GET /api/leads/statuses`, `POST /api/leads/{lead}/convert` |
| Accounts | `apiResource /api/accounts` |
| Contacts | `apiResource /api/contacts` |
| Opportunities | `apiResource /api/opportunities`, `PATCH /api/opportunities/{opportunity}/stage` |
| Brands | `apiResource /api/brands` |
| Products | `apiResource /api/products` |
| Pricebooks | `apiResource /api/pricebooks` |
| Pricebook entries | `apiResource /api/pricebook-entries` |
| Price approval layers | `GET /api/price-approval-layers`, `GET/PATCH /api/price-approval-layers/{layer}` (requires `approvals.manage`) |
| Quotations | `apiResource /api/quotations`, `POST /api/quotations/{quotation}/approve\|reject\|revise\|send` |
| Quote line items | `apiResource /api/quote-line-items` |
| Contracts | `apiResource /api/contracts` |
| Sales orders | `apiResource /api/sales-orders`, `PATCH /api/sales-orders/{salesOrder}/status` |
| Sales order items | `apiResource /api/sales-order-items` |
| Activities | `apiResource /api/activities`, `PATCH /api/activities/{activity}/complete` |
| Sales targets | `apiResource /api/sales-targets` |
| Users | `GET /api/users`, `GET /api/users/{user}` (directory, admin only) |

List endpoints are paginated (`?page=`, `?per_page=` up to 100) and most accept filters such as
`search`, `status`, `stage`, `account_id`, `owner_id`, `year` and `brand_id`. Writes reuse the same
Form Requests, policies and actions as the web UI, so pricebook pricing, approval thresholds and
the Closed-Won automation behave identically over HTTP.

### Price approval matrix

Who may sign a quote off is data, not code. `price_approval_layers` holds one row per authority
level; a quote is routed to the lowest layer whose **discount** and **value** ceilings both cover
it, and the approver must sit at or above that layer.

| Level | Layer | Max discount | Max quote value |
| --- | --- | --- | --- |
| 1 | Sales (Sales Rep) | 5% | $25,000 |
| 2 | Manager (Sales Manager) | 10% | $100,000 |
| 3 | Regional Manager | 15% | $300,000 |
| 4 | General Manager | 20% | $1,000,000 |
| 5 | Director | 100% | unlimited |

A rep quoting 8% off a $2,400 deal lands at layer 2; 18% escalates to layer 4 because the Manager
and Regional ceilings are 10% and 15%. Editing a quote re-evaluates routing: a cheaper revision
returns to Draft, a harsher one escalates. Limits are retuned over the API without a deployment:

```bash
curl -X PATCH http://localhost:8000/api/price-approval-layers/2 \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"max_discount_pct":12,"max_quote_amount":125000}'
```

### Target vs achievement

`TargetAchievementService` builds the dashboard model for a given `?year=`:

- `summary` / `annual` — company target, booked revenue, gap and attainment percentage.
- `monthly` and `quarterly` — explicit period targets, falling back to `annual / 12` or `annual / 4`.
- `by_sales`, `by_brand`, `by_customer`, `by_product` — one row per rep/brand/account/product with
  target, achieved, gap and attainment.

Achievement is `SUM(sales_orders.subtotal)` recognised on `order_date`; brand/product rows use
`sales_order_items.total_price`, which sums to the same net figure. Missing prices or unassigned
products surface as an explicit `Unbranded` row rather than disappearing.

---

## 7. Operational notes

- `OpportunityObserver` runs in the request transaction; the ERP hand-off is deliberately modelled as
  a Draft `SalesOrder` row rather than a synchronous external call. A queued `SyncSalesOrderToErp`
  job is the natural next extension point.
- Approval events are stored on the quotation (`approved_by_id`, `approved_at`,
  `rejection_reason`). Wire them to notifications/listeners for a full audit log.
- Quotation numbers, order numbers and contract numbers share `DocumentNumberGenerator`; add a
  per-entity sequence table if you need numbers scoped by business unit.
- `public/build` is produced by `npm run build`; commit the built assets only if your deployment
  target cannot run Vite.
