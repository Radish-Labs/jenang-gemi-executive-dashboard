# Per-account ad monitoring status — exec3.98.50

The homepage now names the account and saved condition behind a monitoring warning. It distinguishes no complete baseline, failed or incomplete collection, an attempt without recorded completion, stale settings, and missing/unknown monitored campaign status. Each account's last verified settings and latest attempt remain visible without opening the campaign drilldown. Authorization is not inferred from a failed sync. The ingestion server's freshness result is authoritative; the browser clock cannot downgrade it.

The old generic warning included missing/unknown campaigns even when intentionally paused, ignored, unselected or already ended. Those campaigns remain in the drilldown but no longer create an expected-running warning. Existing incidents are always preserved, including when their current observation is unknown. Expiration is evaluated against the last verified snapshot time, not an unverified device clock. No monitoring state, ad state, account selection, Growth filter or resume capability is changed by this presentation fix.

Reload saved status reads stored observations. It does not trigger collection. Closing the banner remains a local browser preference; a changed account condition or new incident reopens it. Repeated timestamps and acknowledgement changes alone do not reopen it.

## Production diagnosis and release handoff

This patch is based on dashboard main `ddbd313d91fb6b6ee6a6602699210ac3205b0d53`. Integrate it with the current reporting release before publishing; do not overwrite concurrent reporting fixes. It includes the normal patch-version increment and asset cache keys. If another release has already used exec3.98.50, increment to the next available patch consistently in VERSIONING.md and dashboard/index.php.

The actual owner screenshot was inspected. It showed the generic verification banner, with account details collapsed. It does not establish which account or sync condition caused the warning. The dashboard asks for all enabled Shopee accounts, and the ingestion registry includes Jenang Gemi, ZERO and ZFIT. The approved collector setup covers Jenang Gemi and ZERO. A separate ZFIT baseline can therefore affect the old aggregate warning; this is a code-path finding, not a verified production diagnosis. Do not silently remove that coverage warning or add an unapproved ZFIT job.

Production per-account verification remains pending in the worker that has an authenticated hosted session. This development worker's normal dashboard URL opens the sign-in page. The connected reporting tool inventory does not expose the advertised ingestion report tool. No credentials were extracted and no blocked direct proxy route was retried.

A small passive query for the already-authorized Hostinger database viewer can identify the saved condition without exposing campaign payloads, credentials, errors or customer data:

```sql
SELECT account_key,
       JSON_UNQUOTE(JSON_EXTRACT(state_json, '$.sync_state')) AS sync_state,
       JSON_UNQUOTE(JSON_EXTRACT(state_json, '$.last_attempt_at')) AS last_attempt_epoch,
       JSON_UNQUOTE(JSON_EXTRACT(state_json, '$.last_success_at')) AS last_success_epoch,
       JSON_LENGTH(JSON_EXTRACT(state_json, '$.campaigns')) AS campaign_count
FROM shopee_ad_monitor
WHERE account_key IN ('jenang-gemi-shopee', 'zero-shopee', 'zfit-shopee')
ORDER BY account_key;
```

An absent row means no stored baseline for that account. Retain the numeric epoch times for unambiguous comparison. Do not infer successful source collection from deployed assets or cron registration alone. Confirm timestamps advance after a normal scheduled attempt and check the complete/incomplete/failed condition. The two monitor tables were created separately with approval; this UI release contains no migration. The old deployment note's assumption of an existing ads cron is superseded: the previously inspected Hostinger cron list contained fulfillment only, and the parent deployment worker is coordinating the approved new account jobs using the deployed zero-DDL collector.

## Validation

- `node tests/ad-monitor-status-test.cjs`: saved conditions, server freshness, monitored unknowns, intentional/terminal exclusions, conservative expiration, preserved incidents and independent account health.
- `php tests/ad-monitor-security-test.php`: existing auth/CSRF, header transport, passive reads, safe text and preview invariants.
- `PHP_BINARY=php python3 tests/ad-monitor-permissions.py`: 401/405/403/422 gates without upstream calls.
- `node --check ad-monitor.js`, PHP lint and `git diff --check`.
- Actual component browser fixture on localhost: separate account baselines, incomplete/failed reason, ignored/ended exclusions, unknown pause attribution, desktop and 390px mobile, no horizontal overflow, close/reopen and unchanged-warning persistence, new-incident resurfacing. All fixture data is fictional.

No live ad mutations, monitoring selections, manual sync, credential/scope changes, database writes or deployment were performed for this patch.
