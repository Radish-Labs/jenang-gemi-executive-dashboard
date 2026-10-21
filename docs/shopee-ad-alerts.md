# Executive homepage Shopee ad warnings — exec3.98.44

The homepage has a prominent persistent warning and an expandable account/campaign drilldown. It shows first detection and last-seen-running bounds, observation time, last successful sync, current freshness, expected state and resolution. It does not call a pause “system-caused”: the documented source does not provide the actor or reason.

Confirmed controls only change monitoring: acknowledge, expect running, intentional pause, or ignore. They cannot resume, pause, change the budget of, or otherwise mutate a Shopee ad. Resume stays unavailable pending verified account eligibility and a separately approved implementation. Product-level campaign coverage is disclosed; Shop GMV Max coverage is unverified.

The separate `/api/ad-monitor/` endpoint requires the existing Executive session; POST additionally requires CSRF. It calls ingest `/ads/monitor` using the existing credential in an HTTPS Bearer header, with redirects disabled. GET is passive and bypasses both the legacy summary route and analytics schema bootstrap. One browser request per minute reads stored state; hidden tabs do not poll. A failed/empty response downgrades prior observations to unknown while retaining incidents. Requests time out after 15 seconds.

## Deployment order and source readiness

1. Deploy the paired `Radish-Labs/jenang-gemi-api-ingest/main` release. See its `docs/ads-monitor.md` for the migration and state contract. The dashboard can deploy first safely: its isolated monitor reports source unavailable until ingest is ready.
2. Its existing 15-minute ads worker initializes the two additive monitor tables and collects the first full snapshot. Manual host CLI migration is optional; no new credential or scope is required.
3. Push this release to the established `Radish-Labs/jenang-gemi-executive-dashboard/main` Hostinger deployment. Do not include the separate unpushed comprehensive MCP work.
4. Verify hosted build `exec3.98.44`, `ad-monitor.js`, authentication rejection, and the authenticated monitor GET. Confirm source freshness per account after normal sync. “Deployed” does not imply a successful source sync.
5. Initially paused campaigns are unselected. An operator can choose Expect running through the preview/confirmation flow. No live selection is changed as a deployment test.

The new monitor uses header authentication only. The separate legacy summary/sync header-transport improvement is prepared locally on branch `prepared/ads-transport-header` at `59d86646d92c53c180bb021ebe8191780f490e8e`, but deferred from this release because hosted ingest header support is not yet verified. Existing legacy behavior remains; do not exercise it with a secret URL token as a verification workaround. Once ingest is verified, apply only that branch’s `ads-bootstrap.php` change and verify the transport separately. No credential rotation, new scope or broader access is included.

## Verification

Automated:

- `php tests/ad-monitor-security-test.php`
- `PHP_BINARY=php python tests/ad-monitor-permissions.py`
- `node --check ad-monitor.js`
- PHP lint of changed PHP files.

Local browser fixture: `PHP_BINARY=php node tests/ad-monitor-fixture.cjs`, at `http://127.0.0.1:4182/`. If child process execution is restricted, render dashboard HTML with an isolated test session first and pass its path as `AD_MONITOR_RENDERED_HTML`. The fixture uses actual rendered component markup/styles/script and fictional data only. It is a local Node test server, not a deployed authentication bypass.

Browser checks completed: prominent warning; account/campaign details; unknown attribution; literal rendering of a malicious-looking ad name; acknowledgement stays unresolved; intentional-pause confirmation clears the incident; stale/failed and unavailable-source warnings; retained prior incident after a failed read. Responsive check at a 390px embedded viewport showed no horizontal overflow, 44px action controls and a confirmation dialog within the viewport. The main app's wider business-data functionality is outside this fixture's scope.

The paired ingest suite includes real MariaDB tests of persistence, concurrent locks, stale revisions, idempotency, JSON key reordering, audit rollback, account isolation and sync failure/recovery. Browser mutations were fixture-only; no live ads or monitoring preferences were changed.
