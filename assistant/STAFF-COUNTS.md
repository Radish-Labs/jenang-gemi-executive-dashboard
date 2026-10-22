# Store Ops staff profile counts: prepared, disabled

`assistant/lib/read-services/staff-counts.php` prepares the fixed `store_ops_staff_counts` aggregate and pure validation. The report is **disabled**, has no central registry/dispatcher entry, and performs no source reads. Calling its public wrapper with the current dashboard grant returns `blocked_scope_binding`, null counts and `store_ops_company_roster_binding_missing`. Unknown config flags cannot enable it.

## Why current authorization does not establish a roster boundary

The reviewed Store Ops schema declares `store_ops_employees_v2` with `id`, `display_name`, `pin_hash`, `active`, `created_at` and `updated_at`. It has no company, tenant, store, brand or marketplace account discriminator. No employee membership source providing such a boundary was found in the reviewed Store Ops code.

Evidence:

- Store Ops `store-ops-fulfillment-runtime.php::jg_store_ops_fulfillment_ensure_schema` owns the schema. `jg_store_ops_fulfillment_now` uses UTC writes. The matching runtime source was inspected in the existing `2026-10-21/order-recovery/store` checkout at `05fe7d0ce6a80ffb64acd75ca5f3333d8a385fcf`; no constructors or schema helpers were executed.
- Store Ops `api/employees/index.php` requires authenticated access and `jg_admin_can_manage_employee_profiles()` before reading/managing the roster. Its `active` projection means exactly stored integer `1`.
- Executive `store-ops-report.php::jg_exec_store_ops_employees` reads the complete roster. That interactive admin route does not establish the assistant's company authority.
- `assistant/lib/store-ops-scope.php::storeOpsSourcePredicate` authorizes fulfillment/events using reviewed `source_platform` and `source_account` conventions. These columns are absent from the employee table.
- Existing assistant configuration and owner-grant records have marketplace accounts, brands, partner scope, OAuth scopes and allocation recipient names, but no reviewed company-to-roster binding. Permission to read all configured accounts does not prove permission to read an entire staff roster.

Joining employees to fulfillment claims would count only people with activity and could mix companies or historical assignments. It would not establish membership and is deliberately unsupported. Caller-supplied company/account filters cannot repair missing source ownership.

## Minimum requirement before activation

Provide either:

1. Reviewed evidence that the exact configured SKU database's entire Store Ops employee table belongs to one authorized company/Store Ops installation, plus an explicit server-enforced binding of aggregate whole-roster counts to the relevant current owner grant. The dispatcher must verify normal token expiry/revocation and this binding on every read. Record the ownership/binding decision through the existing approval/audit workflow. Do not derive it from a user-supplied flag, marketplace accounts, free-form labels or allocation recipient names.
2. A real, reviewed employee-to-company/store membership source that permits enforcing the existing authorized boundary in SQL. Its current/historical membership semantics and duplicate handling must be verified before implementing the join.

Neither alternative is configured or approved by this change. No new OAuth scope, credential, source connection, schema, or grant is created. Activation needs a separate reviewed implementation; this is not a config-only switch. If the whole roster cannot be verified, keep the report unavailable rather than infer a zero or company-wide count.

## Prepared interfaces

```php
staffCountsMetricRegistry(): array;
staffCountsMetricArguments(string $report, array $args): array;
staffCountsAggregateSql(): string;
staffCountsFromAggregate(array $aggregate): array;
staffCountsMetricRead(?PDO $pdo, string $report, array $args, array $c, ?DateTimeImmutable $now = null): array;
```

The public wrapper checks the existing dashboard table grant and returns blocked before any PDO operation. Registry metadata has `enabled=false`, `status=blocked_scope_binding`, `required_sources=[]`; callers should not open a database to report this status. The aggregate SQL and pure projection are preparation/test seams, not authorization entry points, and must not be dispatched directly.

After a future binding is reviewed, the fixed query can provide only `total_profiles`, `active_profiles`, and `inactive_profiles` for the authorized complete roster. It reads only the `active` column, limits source inspection to 10,001 rows, and rejects counts above the 10,000-profile capacity. Invalid/null status values fail explicitly. Profile counts are not unique people, legal employees, attendance, shift availability or individual performance. No historical/date/group filters, worker identifiers, names, contacts, PIN/hash fields, preferences, timestamps or control settings are selected. Response generation time is not source freshness.

`staffCountsFromAggregate` validates the four exact aggregate fields, including an internal invalid-status count that never appears in valid output. It rejects malformed values, unknown fields, inconsistent totals and over-capacity sources. It cannot establish scope or availability itself.

## Verification

`assistant/tests/staff-counts.php` checks hard-disabled behavior before PDO use, absent/insufficient scope rejection, inability of request filters or invented config flags to activate access, aggregate-only SQL, canonical active/inactive semantics, missing/empty/invalid/oversized synthetic sources, strict response shape and omission of private fixture sentinels. The fixture query runs in a query-only transaction. It does not call Store Ops constructors, live endpoints or production databases.

Remaining blocker: whole-roster read activation is pending company/source and grant binding verification. This is a source authorization gap, not a tool approval denial. No attempted production operation has an uncertain outcome.
