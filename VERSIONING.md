# Jenang Gemi Dashboard Versioning

Current dashboard version: `exec3.98.42`

Versioning rule for future changes:
- Default behavior: increment the patch digit by `+1`.
- Example: `exec3.46.2` -> `exec3.46.3`
- If a change is large enough to justify a broader release, increment the middle digit and reset the patch digit to `0`.
- Example: `exec3.46.2` -> `exec3.47.0`
- If a change is radical enough to represent a major version shift, increment the first digit and reset the other digits to `0`.
- Example: `exec3.46.2` -> `exec4.0.0`

Operational rule for Codex:
- Whenever Codex makes a dashboard change that should affect the build/version badge, it should update the stored version automatically without waiting for the user to ask.
- Unless the user explicitly says otherwise, assume ordinary fixes and enhancements are patch-level changes.
