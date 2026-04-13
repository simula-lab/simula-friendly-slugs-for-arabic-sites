# Week 5 Regression Coverage (P2 Security + Cleanup)

Date: 2026-04-13
Scope: `TKT-P2-W5-01..05`
Source documents:

- `docs/week5-execution-plan.md`
- `docs/ISSUES.md`

## Purpose

Track verification for the Week 5 security and consistency cleanup:

- masked API key admin UX
- safe credential replacement flow
- unsupported `custom_transliteration` method cleanup

## Environment Baseline

- WordPress runtime: not executed in this workspace
- PHP CLI: workspace lint available
- Automated test suite: not present in this repository
- Verification mode in workspace: static code review + syntax validation

## Required Scenario Set (Week 5)

1. `W5-01` API key fields render as masked/password-style inputs
2. `W5-02` Blank API key submissions preserve existing saved keys
3. `W5-03` Non-empty API key submissions replace the saved key
4. `W5-04` Selected-provider validation still works with masked API key flow
5. `W5-05` Non-API provider fields keep their existing render behavior
6. `W5-06` Hidden `custom_transliteration` is no longer accepted as a supported method
7. `W5-07` Existing unsupported method values degrade safely to `none`

## Execution Checklist

Mark each row as `PASS` / `FAIL` / `N/A` and include notes.

| ID    | Status | Notes                                                                                                          |
| ----- | ------ | -------------------------------------------------------------------------------------------------------------- |
| W5-01 | PASS   | `api_key` provider fields now render as `type="password"` with blank default value.                            |
| W5-02 | PASS   | Blank submitted `api_key` values now fall back to the previously saved option value before validation/storage. |
| W5-03 | PASS   | Non-empty submitted replacement values still flow through validation and persistence.                          |
| W5-04 | PASS   | Selected-provider validation receives the effective key value even when the replacement field is left blank.   |
| W5-05 | PASS   | Only `api_key` fields were changed; `text` and `url` provider fields keep their existing render paths.         |
| W5-06 | PASS   | Supported methods are now normalized through a single allow-list that excludes `custom_transliteration`.       |
| W5-07 | PASS   | Runtime consumers now normalize unsupported saved methods to `none`.                                           |

## Workspace Verification Completed

1. `php -l simula-friendly-slugs-for-arabic-sites.php` passes after the Week 5 changes.
2. API key fields no longer render with prefilled visible secret values.
3. Blank `api_key` submissions now reuse previously saved values for selected-provider validation and storage.
4. Method selection and runtime slug generation now normalize unsupported values through the same supported-method list.

## Manual QA Checklist

1. Confirm the Google API key field is masked in admin.
2. Save settings with the API key field left blank and confirm the existing key is preserved.
3. Save settings with a replacement key and confirm the new key persists.
4. Switch to `Mock Provider` and confirm non-secret fields still render normally.
5. Seed an unsupported `custom_transliteration` value, save settings, and confirm it is normalized away.

## Week 5 Pass Criteria

Week 5 regression coverage passes only when:

1. API keys are no longer exposed in plain text.
2. Secret replacement is explicit and blank submissions are safe.
3. Hidden unsupported methods cannot remain active after save.
4. WordPress admin QA confirms the workspace findings.

Current status:

- Workspace verification completed.
- WordPress runtime QA remains required for end-to-end admin confirmation.

- `W5-MQ-01` is confirming `PASS
- `W5-MQ-02` is confirming `PASS`
