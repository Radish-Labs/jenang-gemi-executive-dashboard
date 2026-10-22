# Saved API health failure history

`api_health_history` is a fixed `jg_business_report` read under the existing `jg:dashboard:read` grant. It reads only `data/api-health-log.json`; it does not run the API Health page, checks, probes, database connections, writes, credentials checks or marketplace refreshes. Registry presence and fixture tests do not establish live tool availability.

The dashboard writes **failed checks only**, retaining the latest 120 failures. A valid empty log means no retained failure records; it does not prove successful checks or current service health. Successful probes, full incident history and the remote `marketplace_sync_runs` ledger are absent from this source.

## Integration contract

- Load `assistant/lib/read-services/health-metrics.php`.
- Add `healthMetricRegistry()` to the fixed report registry.
- Validate arguments using `healthMetricArguments($report, $args)`; only `limit` (1–120, default 80) and `offset` (0–119, default 0) are allowed besides the dispatcher’s report ID.
- Dispatch `healthMetricRead($report, $args, $now)` **before any PDO connection**.
- Project its standard `values`, `provenance`, `freshness`, `completeness`, `status` and limitations envelope. Preserve explicit source-unavailable states; do not replace them with zeros.
- Existing dashboard authorization belongs to the central dispatcher. The report is shared application operational history, not a scoped account health calculation; source URLs and account details are omitted.

Example request: `{"report":"api_health_history","limit":20,"offset":0}`.

## Safe projection and source limits

The reader reuses the extracted canonical log path/decoder in `api-health-log-read.php`. The existing API Health endpoint retains its ordinary read/write behavior; the new report imports only the passive helper.

The reporting reader accepts a regular nonsymlink file, at most 1 MiB, at most 120 object records, and JSON depth 16. It validates allowed status codes and explicit-offset timestamps. It exports static approved check IDs, static labels/categories, status codes and UTC failure-record timestamps. Stored URLs, labels, descriptions, errors, response excerpts and arbitrary nested payloads never leave the server. Credential-status checks and unknown IDs are omitted, with an omitted count. Invalid approved records make the entire source unavailable; no partial list is presented as complete.

Missing, unreadable, nonregular, oversized, malformed JSON, invalid schema and excessive-record sources have distinct safe reasons. Unavailable rows/counts are `null`, separate from a valid empty list. Status code 0 preserves the canonical absence of a status; it is not invented as a particular network error.

Pages follow newest append order, matching the dashboard, even if clocks were out of order. The latest failure time is the maximum permitted timestamp across the saved file, not the first page or file modification time. A future failure timestamp has null age and an explicit future-clock flag. File modification and failure observations do not verify sync. Separate calls can observe appended/rotated records, so offsets are not an atomic cross-request snapshot.

## Verification

`php assistant/tests/health-metrics.php` uses temporary synthetic files only. It checks missing versus empty states, strict schema/size/depth/record bounds, unsafe-field and credential-check exclusion, paging, UTC conversion, future timestamps, source immutability, and consistency with canonical check IDs. It never reads the production log, config or credentials and never executes the API endpoint.
