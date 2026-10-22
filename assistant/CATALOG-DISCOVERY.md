# MCP catalog discovery investigation

Verified 2026-10-22 at 07:30 UTC (14:30 Asia/Jakarta). This records a metadata-discovery problem separately from data access and owner consent. No credentials, grants, permissions, installations or publication settings were changed.

## Observed mismatch

The existing Primary connection successfully called hosted `jg_get_context`. It reported MCP 0.3.2, managed owner connection, `jg:sales:read` and `jg:dashboard:read`, and these ten server capabilities:

`jg_get_context`, `jg_resolve_product`, `jg_product_report`, `jg_list_tables`, `jg_describe_table`, `jg_query_table`, `jg_annualize_sales`, `jg_monthly_profit`, `jg_get_report_note`, `jg_save_report_note`.

The tool catalog loaded into this task exposes only the first seven. Its query and annualization schemas also omit `partner_date_basis`, while the live server descriptions include it. This establishes that loaded definitions differ from current server metadata. It does not establish the internal reason the platform retained the older definitions.

The locally installed, generated remote plugin manifest is version 1.0.0, with the original description naming three ZERO product/sales tools. Its `.app.json` points at the existing registered application. This confirms stale package presentation, but does not itself explain tool discovery: seven tools are callable despite that three-tool description. Editing this cache would neither update the registered server metadata nor prove publication.

## Server checks

- `assistant/lib/rpc.php`: `tools/list` returns `tools($c)` and `jg_get_context.capabilities` uses the same function. There is no separate hardcoded discovery list.
- `assistant/lib/connection-tools.php`: enhanced tools are included when the managed owner's approved scopes permit them. Runtime token scope is checked separately before tool execution. Describing a finance tool does not grant finance access.
- `assistant/lib/config.php`: JSON responses emit `Cache-Control: no-store`. No server-side HTTP cache defect was identified.
- Initialization advertises `tools.listChanged: false`. This PHP transport does not implement server notifications. Changing the flag without implementing notifications would not force a client refresh.
- A local synthetic configuration with dashboard token scopes and owner-approved finance/notes metadata returned identical `tools/list` and context capability sets, and identical initialization/context instructions. The 0.4.0 release adds `jg_ads_history` and `jg_business_report`, yielding twelve tools for that managed fixture. A finance call with that dashboard-only token was denied before any source read. No real token or source database was used.

These checks support a discovery/publication-layer discrepancy. They are not a direct hosted raw `tools/list` capture; the existing connector provides `jg_get_context`, not a raw MCP transport. No authenticated raw transport was constructed or credential copied.

## Verified supported refresh paths

Official OpenAI documentation distinguishes two paths:

1. **Custom MCP connection:** after deploying metadata changes, open the existing connection in [ChatGPT Plugins](https://chatgpt.com/plugins), select **Refresh**, confirm updated metadata, then test in a new conversation. The [quickstart](https://developers.openai.com/plugins/build/app-quickstart#next-steps) places refresh on the plugin's detail page. The [connection guide](https://developers.openai.com/plugins/deploy/connect-chatgpt#refresh-metadata) documents the sequence.
2. **Publicly published plugin:** tool definitions undergo continuous review. New tools become available after checks pass; changed tools retain their old definitions until accepted. Server-only changes to compatible results take effect immediately. Directory information and imported skills require a new version. See [published metadata maintenance](https://developers.openai.com/plugins/deploy/app-review#ongoing-maintenance).

The generated `created-by-me-remote`/development manifest and the owner's developer-mode screenshots are consistent with the first path. Publication/review status was not inspected, so the distinction should be confirmed on the actual connection detail page rather than assumed from the plugin's displayed version.

## Exact remaining UI blocker

This investigation has no supported browser-control tool or catalog-refresh action available. The supplied screenshots show Manage and ellipsis controls but no visible Refresh. Neither the screenshots nor the official documentation establish which of those menus contains the action in this account's current UI.

**Pending action:** inspect the existing Jenang Gemi Reporting detail page at ChatGPT Plugins using the owner's already-authenticated supported browser, and locate its documented Refresh control. Do not tell the owner to disconnect/reconnect, reinstall, change permission mode, or create another grant as a substitute. If Refresh is absent, capture that account-specific discrepancy and the metadata comparison for platform support, following the [official troubleshooting guidance](https://developers.openai.com/plugins/deploy/troubleshooting#when-to-escalate). No external support message has been sent.

No operation was denied. Refresh is pending because the supported control is unavailable to this investigation, not because owner approval or a new OAuth grant was refused.

## Release acceptance

After the server release and an actual metadata refresh/review:

- Compare the client's callable tool names and input schemas with the hosted registry. Context's capability strings alone are insufficient.
- Confirm the client includes `jg_monthly_profit`, `jg_ads_history`, `jg_business_report`, and `partner_date_basis` on the relevant existing sales tools. Confirm table arguments use the new bounded string schema rather than the old frozen enum. The server still resolves names against its reviewed allowlist.
- Confirm the client receives the current reporting instructions and descriptions, including ads source, accounting-category and bill-payment guidance.
- Run a read-only call using already granted scopes. Any finance call still needs an actual token carrying `jg:finance:read`; the observed Primary token does not carry it. Do not change scopes merely to test discovery.
- Preserve backward compatibility with the seven existing published schemas until the refreshed definitions are demonstrably callable.

No claim of repaired discovery or full usable feature coverage is justified until these checks pass.
