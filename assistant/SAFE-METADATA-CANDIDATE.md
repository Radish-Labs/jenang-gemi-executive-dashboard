# Safe metadata supplement — local candidate

Base: `edbb85ae6fcc723d4b5eb85da29b3aaf567079db`, preserved on `feat/reporting-local-parity`. This separate `feat/reporting-safe-metadata` change is not pushed or deployed and does not activate permissions, credentials, collectors or external readers. It supplements the prepared 0.5.0 candidate; no live version is claimed.

## Implemented additions

- `purchase_payment_proof_presence`: payment/proof counts, declared sizes and typed timestamps. Additional proof children are aggregated before joining, avoiding multiplied payment counts. Requires the existing explicitly approved internal company purchasing scope.
- `partner_label_presence`: current/deleted/expired label metadata and parent-order coverage under exact partner membership, without filenames, locators, contents or identities. Broken scoped relations fail unavailable.
- `hard_set_observations`: stored business booleans and selected audit/delivery counts, attempts and ages. Permission controls, source membership, actors, payloads and errors remain excluded. Missing sections remain explicit.

All three use fixed SELECTs in existing read-only snapshots. Physical preflights are bounded before aggregation. They are registered through the existing dashboard report tool, bringing the registry to 36 business reports plus three separately scoped finance reports. The generic raw-table allowlist is unchanged.

## Prepared, unavailable additions

- `raw_platform_event_metadata`: a separate disabled, unregistered aggregate consumer plus an exact upstream contract. It needs a passive account/platform-scoped upstream implementation, database fixtures, approved publication and authenticated contract verification. See `INGEST-EVENT-METADATA-CONTRACT.md`.
- Staff profile counts: disabled before PDO. Activation requires verified ownership of the whole roster plus a current owner-grant binding, or a real authorized membership join. Current marketplace account keys do not establish that boundary. See `STAFF-COUNTS.md`.
- Two organizational diagnostic resource bindings and a default-hidden Settings form: local preparation only. Existing global ingestion dispatch remains hard-denied, even if a synthetic fixture has a binding. Full integration, source/deployment readiness and actual personal owner approval are separate requirements. See `ORGANIZATIONAL-SOURCES.md`.

No scope is inferred from an all-inclusive feature request. Account/partner constraints, finance recipient checks, expiry, rotation and revocation remain in place. No document contents, identifiable staff/customer expansion, credential access, business write or control action is added.

## Validation after final capacity hardening

- All **37 assistant PHP suite entrypoints passed**, each in a separate process, with the source-inventory suite comparing the actual ingest checkout and its exact original twelve-source contract.
- Focused metadata/security/dispatch suites contain **932 checks**: document244, control379, event122, staff47, organizational56 and dispatch84.
- **55 synthetic localhost HTTP checks passed**: existing OAuth/Settings37 and new organizational Settings18. Both use disposable local state; the initial sandbox socket restriction was resolved through the supported narrowly scoped test permission route.
- **14 changed/new PHP files passed syntax validation**. Whitespace checks passed. This PHP repository has no package/composer/build manifest requiring a separate bundle build; no JavaScript or CSS changed.
- Independent review is recorded in `READ-REPORTS-REVIEW.md`. No remaining confirmed code blocker was found. Validation uses synthetic/local state; hosted source availability and production database execution remain unverified for these additions.

## Remaining source boundaries

`SOURCE-COVERAGE-MATRIX.json` reconciles 134 database sources: 107 have reviewed mappings (including five partial metadata subsets), thirteen await external-reader integration, and fourteen remain excluded or authorization-blocked. This is not a claim that every source is live or complete.

The original twelve-source ingestion bridge remains disabled. Ten sources preserve exact existing account scope; two global diagnostics still require the reviewed transport integration and actual owner-selected source bindings. The thirteenth external gap is the proposed raw-event aggregate above.

Additional existing boundaries remain explicit: the Store Ops inventory-commitment snapshot contract, the upstream affiliate-program reader, remote partner receipt fallback without backfills, nonsecurity upstream control observations requiring separate passive globally authorized contracts, and exact sales-proof components that were never stored. Source routing permissions, secrets, document contents, identities, internal migration markers and presentation assets remain deliberately excluded. The coverage documents distinguish these from missing business metrics.

Production deployment and activation are pending the parent-held owner decision. This local commit neither assumes that approval nor performs any portion of the production operation.
