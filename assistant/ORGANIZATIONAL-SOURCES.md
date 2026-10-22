# Prepared organizational diagnostic approval

This is local preparation on the safe-metadata branch. It does not activate a production grant, create credentials, change OAuth scopes, or make global ingestion reads available. `ingestReportsArguments()` and `ingestReportsProject()` still deny global sources. The saved candidate `edbb85ae6fcc723d4b5eb85da29b3aaf567079db` is unchanged.

## Exact boundary

Only these resource IDs can be selected:

- `marketplace_sync_runs`: organization-wide stored status, processing counts and timestamps, including retired/unlisted accounts. Processing counts are not distinct orders or proof of complete sync.
- `marketplace_fulfillment_runtime`: organization-wide worker heartbeat timestamps and staleness. This does not validate credentials or prove operational readiness.

Neither all configured marketplace account keys nor the `jg:dashboard:read` scope alone authorizes these unpartitioned records. The new binding is an explicit resource ACL attached to the current owner grant, not a new bearer token or OAuth scope. Raw-event metadata and staff counts are not covered by it.

The trusted configuration option `organizational_diagnostics_approval_enabled` must be the boolean `true` before the application offers or accepts approval. It defaults to disabled when absent. No configuration file was changed. An active owner-approved dashboard scope and the existing approved internal company dashboard mode are required. A separate upstream/source release gate remains independent of this option.

## Owner flow, when separately approved for deployment

The existing `/assistant/settings/` page identifies the configured subject and client. A separate form lists the two sources, initially unchecked. It requires an authenticated dashboard session, same-origin POST, CSRF, the Branch-tier owner password, and explicit confirmation. The owner performs this action personally. It stores source ID, current base-grant ID, subject, client, approval timestamp and revocation timestamp in existing private adapter state.

Saving selected sources does not replace tokens, create credentials, alter recipient selection, or change OAuth scopes. Removing a selection immediately revokes that resource for existing tokens; the separate revoke button removes both diagnostic resources while keeping account-scoped reporting intact. Revocation remains possible if the deployment capability is disabled later. Replacing or revoking the base owner grant invalidates all old resource bindings.

`requireOrganizationalSource()` checks the current server-side token subject, client, audience, revocation epoch, expiry, dashboard scope, current grant and exact source binding. Cached request/grant data cannot approve access. `readOrganizationalSource()` checks before and after a passive callback and discards a result if access was revoked during the read. Audit entries contain bounded source IDs and approved/revoked/read outcomes, using the existing retention and private storage policy.

## Deliberately unfinished activation

Connecting the two global ingestion projections requires a separate reviewed integration after the pending owner decision. Keep the existing global denial until that change is implemented and tested. The future projector must accept only producer-defined modes/statuses, validate the global response envelope, return no raw errors/identities/secrets, and verify heartbeat ages against read time. The upstream reader, its source-release gate, the deployment capability and the actual owner source selection are independent requirements. Do not infer owner approval from a code deployment.

## Validation

`tests/organizational-sources.php`: 56 synthetic checks cover disabled deployment, absent/incorrect bindings, all six current accounts without a binding, exact identity/audience/epoch/expiry, source-specific revocation, in-flight revocation, base-grant replacement/revoke, unchanged credentials and bounded audit.

`tests/organizational-settings-http.py`: 18 synthetic HTTP checks use an ephemeral loopback listener and disposable configuration/state. They verify the default-hidden form, server rejection of crafted approval while disabled, exact opt-in selection, CSRF/origin checks, unchanged scopes/no credentials, source-only revocation with deployment disabled, and explicit owner confirmation. No production or external request occurs.
