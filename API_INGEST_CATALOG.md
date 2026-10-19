# API Ingest Catalog

This dashboard currently discovers the public root but the ingest service does not expose OpenAPI, Swagger, or route-list metadata. The catalog below documents every ingest/store endpoint the dashboard can currently find or call from configuration and code.

## API Ingest

Base URL: `JG_API_INGEST_BASE_URL` / `api_ingest_base_url`, currently expected as `https://api.jenanggemi.com`.

| Endpoint | Auth | Meaning | Chart-ready fields |
| --- | --- | --- | --- |
| `GET /` | none | Root service identity. Confirms the API Ingest service is reachable. | `ok`, `service` |
| `GET /health` | none | Health probe for uptime checks. | `ok`, `service` |
| `GET /sales/summary?year=YYYY&setup_token=...` | setup token | Yearly marketplace sales rollup. This is the main source for executive sales charts. Add `audit=1` only for Back Dash provenance. | `months`, `months[].accounts`, `months[].platforms`, `accounts`, `platforms`, `products.by_product`, `products.by_platform`, `products.by_month`, `totals`, `sync_status`; with `audit=1`: `financial_sources` |
| `GET /shopee/auth/status?account=...&setup_token=...` | setup token | Shopee authorization status for an account. Use it to detect expired/missing access and refresh tokens. | `status.account_key`, `status.shop_id`, `status.has_access_token`, `status.has_refresh_token`, token expiry fields |
| `GET /shopee/orders/listed?account=...&setup_token=...` | setup token | Current Shopee listed orders, currently checked as READY_TO_SHIP order feed health. | `orders[]` |

## Store Ops

Base URL: `JG_STORE_OPS_BASE_URL` / `store_ops_base_url`, currently expected as `https://store.jenanggemi.com`.

| Endpoint | Auth | Meaning | Chart-ready fields |
| --- | --- | --- | --- |
| `GET /api/orders/` | Store Ops session | Store Ops order proxy route. Health checks treat `401` as proof the protected route exists. | Depends on authenticated Store Ops response |
| `GET /store-home.js` | none | Store Ops frontend asset. Checked for the `jg-store-live-orders` marker so deployment drift is visible. | Not a data source |

## Dashboard Proxy APIs

These endpoints live inside this executive dashboard and are already normalized for charting.

| Endpoint | Meaning |
| --- | --- |
| `GET /api/sales/?year=YYYY` | Authenticated proxy around `/sales/summary`, with local SKU DB enrichment for seller-received revenue, COGS, monthly per-item packing, gross profit, and syrup flavor charts. |
| `GET /api/orders/?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` | Authenticated read from the dashboard-local `dashboard_order_mirror` table. The table is the server-side order mirror used by the Orders, Daily, and hourly Executive views. |
| `POST /api/orders/?action=webhook` | Token-authenticated order mirror upsert endpoint for API Ingest webhooks. It stores extracted order fields plus the raw order row JSON and bumps dashboard live-state so clients refresh themselves. |
| `GET /api/analytics/?dataset=landing|website|affiliate` | Campaign, website, and affiliate analytics rollups from the dashboard analytics database. |
| `GET /api/api-health/?run=1` | Runs every configured API/database probe and logs failures. |
| `GET /api/sku-db/` | SKU master data and SKU approval workflow API. |
| `GET/POST /api/product-costs/` | Admin-only grouped Product Costs API for quarter/period COGS and monthly, month-range, or retroactive per-item packing. |
| `GET /api/partner-db-status/` | Partner profile database status. |
| `GET /api/partners/` | Partner profile management API. |
| `GET /api/profit-share/?recipient=NAME[&year=YYYY]` | Bearer-token (`JG_ARUS_PROFIT_SHARE_TOKEN`, or HMAC-derived from the setup token) read-only monthly net profit and one allocation recipient's share, path, payout date and completeness. Same net-profit formula as `/profit-and-loss/`; sales from the last complete `/api/sales/` snapshot. Used by Arus. |

## Chart Notes

- Monthly unit sales by account should use `sales summary -> months[].accounts[*].item_count`.
- Monthly revenue by account should use `months[].accounts[*].net_revenue` or `sales`; this is seller-received money after marketplace deductions.
- Monthly platform totals should use `months[].platforms[*].item_count` or `net_revenue`.
- Executive revenue trend should use `/api/sales/ -> months[].revenue`, falling back to `net_revenue` or `sales`.
- Executive gross profit trend should use `/api/sales/ -> months[].gross_profit`, calculated as seller-received `revenue - cogs - packing_cost`.
- COGS comes from the local SKU DB `sku_skus.cogs`, matched to marketplace product SKU/tag during `/api/sales/` enrichment.
- Packing comes from local `sku_packing_costs.packing_per_item` for the sale month and is multiplied by physical item quantity, including free gifts.
- Exact monthly COGS comes from API Ingest `products.by_month` when available; `/api/sales/` falls back to product-total allocation only for older ingest payloads that do not expose monthly SKU rollups.
- The executive dashboard proxy strips customer-paid gross fields from `/api/sales/` output so revenue charts cannot accidentally use them.
- Dashboard UI refresh actions only reload cached/client views. They do not call
  `/sales/sync`; order detail freshness comes from API Ingest webhooks writing to
  `/api/orders/?action=webhook`.
- `sync_status` proves whether unattended marketplace sync is fresh. API Health should fail if the latest sync is stale or missing.
- `financial_sources` lists the raw marketplace JSON paths used for net revenue, gross revenue, and marketplace-fee calculations by account. It is audit-only so the homepage sales endpoint stays fast.
- Product charts should use `products.by_product`.
- Product-by-platform charts should use `products.by_product[*].platforms`.
- Syrup flavor charts use `/api/sales/` because the dashboard enriches `products.syrup_flavors` from the local SKU DB.
