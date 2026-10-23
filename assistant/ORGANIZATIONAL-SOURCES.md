# Organizational diagnostic approval and passive transport

Release 0.5.1 implements the two bounded global diagnostic readers and their separate owner approval. Code deployment does not activate a production grant, create credentials or change OAuth scopes. Release 0.5.2 opens only the ten account-source readers after authenticated hosted contract acceptance; the separate organizational source-release gate remains closed. The saved candidates `edbb85ae6fcc723d4b5eb85da29b3aaf567079db` and `4c0eb2d151efbbf899971beb6475e64907882021` remain intact in history.

## Exact boundary

Only these resource IDs can be selected:

- `marketplace_sync_runs`: organization-wide stored status, processing counts and timestamps, including retired/unlisted accounts. Processing counts are not distinct orders or proof of complete sync.
- `marketplace_fulfillment_runtime`: organization-wide worker heartbeat timestamps and staleness. This does not validate credentials or prove operational readiness.

Neither all configured marketplace account keys nor the `jg:dashboard:read` scope alone authorizes these unpartitioned records. The new binding is an explicit resource ACL attached to the current owner grant, not a new bearer token or OAuth scope. Raw-event metadata and staff counts are not covered by it.

The released approval capability defaults on only for the configured subject `branch-personal-assistant`. This offers the existing Settings confirmation form; it does not create a binding. The optional trusted configuration key `organizational_diagnostics_approval_enabled` takes precedence: only boolean `true` enables it, and false or malformed values fail closed. Other subjects remain disabled by default. No private configuration file was changed. An active owner-approved dashboard scope, all reviewed table families, no partner restriction and the existing approved internal company dashboard mode are required. A separate upstream/source release gate remains independent of this capability.

## Personal owner flow

The existing `/assistant/settings/` page identifies the configured subject and client. A separate form lists the two sources, initially unchecked. It requires an authenticated dashboard session, same-origin POST, CSRF, the Branch-tier owner password, and explicit confirmation. The owner performs this action personally: select both diagnostic sources, enter the password, check the confirmation and choose **Save diagnostic permissions**. A chat approval does not substitute for this application action. It stores source ID, current base-grant ID, subject, client, approval timestamp, approval revision and revocation timestamp in existing private adapter state.

Saving selected sources does not replace tokens, create credentials, alter recipient selection, or change OAuth scopes. Removing a selection immediately revokes that resource for existing tokens; the separate revoke button removes both diagnostic resources while keeping account-scoped reporting intact. Revocation remains possible if the deployment capability is disabled later. Replacing or revoking the base owner grant invalidates all old resource bindings.

`requireOrganizationalSource()` checks the current server-side token subject, client, audience, revocation epoch, expiry, dashboard scope, current grant and exact source binding. Cached request/grant data cannot approve access. `readOrganizationalSource()` pins that authorization generation before transport, then performs the final authorization comparison and audit under one state lock. It discards results after expiry, revocation, base-grant replacement or same-source revocation/reapproval, even within one second. Audit entries contain bounded source IDs and approved/revoked/read outcomes, using the existing retention and private storage policy.

## Independent source readiness

The integrated `jg_ingest_report` accepts `account_key=global` only for the two named sources with an exact active binding. Authorization precedes service credential resolution and covers the complete transport and projection. The projector accepts only reviewed producer modes/statuses, validates the global response envelope and bounded counts/timestamps, excludes internal record IDs and raw errors/identities/secrets, and rechecks heartbeat age at read time. Worker heartbeat never proves account readiness or complete sync. The upstream reader, authenticated hosted contract acceptance, source-release gate, deployment capability and actual owner source selection remain independent requirements. Do not infer owner approval or source availability from code deployment.

## Validation

`tests/organizational-sources.php`: 98 synthetic checks cover capability defaults/overrides, absent/incorrect bindings, all six current accounts without a binding, exact identity/audience/epoch/expiry, source-specific revocation, in-flight revoke/reapprove, base-grant replacement/revoke, unchanged credentials and bounded audit.

`tests/ingest-organizational.php`: 231 synthetic checks exercise both global projections and actual dispatch/transport authorization, hostile fields, finite mode/status validation, coverage consistency, clock skew, heartbeat ageing and expiry/revocation during transport. Existing account-source contract and dispatch suites remain intact.

`tests/organizational-settings-http.py`: 18 synthetic HTTP checks use an ephemeral loopback listener and disposable configuration/state. They verify the default-hidden form, server rejection of crafted approval while disabled, exact opt-in selection, CSRF/origin checks, unchanged scopes/no credentials, source-only revocation with deployment disabled, and explicit owner confirmation. No production or external request occurs.
