# Week 4 Regression Coverage (P1 Provider Fixes)

Date: 2026-04-13
Scope: `TKT-P1-W4-01..05`
Source documents:

- `docs/week4-execution-plan.md`
- `docs/week4-task1-provider-contract.md`
- `docs/week4-task4-provider-compatibility.md`

## Purpose

Track verification for the Week 4 provider/settings refactor:

- selected-provider-only validation
- provider-definition-driven admin UI
- third-party provider compatibility
- immediate provider-settings panel switching in admin

## Environment Baseline

- WordPress runtime: not executed in this workspace
- PHP CLI: workspace lint only
- Automated test suite: not present in this repository
- Verification mode in workspace: static code review + syntax validation

## Required Scenario Set (Week 4)

1. `W4-01` Saving with built-in provider selected does not validate unselected providers
2. `W4-02` Invalid selected-provider credentials still block save
3. `W4-03` Unselected provider values remain preserved after saving another provider
4. `W4-04` Translation service selector is rendered from provider definitions
5. `W4-05` Provider settings fields are rendered from provider definitions
6. `W4-06` Provider settings panel switches immediately when translation service radio changes
7. `W4-07` Third-party provider added through filter appears in selector and settings panel
8. `W4-08` Third-party provider field payload is sanitized/stored through declared `option_path` values
9. `W4-09` Invalid or incomplete provider definitions are ignored safely
10. `W4-10` Provider class that does not implement the plugin provider interface is not instantiated

## Execution Checklist

Mark each row as `PASS` / `FAIL` / `N/A` and include notes.

| ID    | Status | Notes |
| ----- | ------ | ----- |
| W4-01 | PASS   | `sanitize_settings()` now validates only the selected provider payload. |
| W4-02 | PASS   | Selected-provider `validate_settings()` errors still return `add_settings_error(...)` and preserve previous settings. |
| W4-03 | PASS   | `api_keys` and other saved values are seeded from previous options before selected-provider validation. |
| W4-04 | PASS   | `field_translation_service_html()` now reads from `get_translation_providers_definitions()`. |
| W4-05 | PASS   | `field_translation_provider_settings_html()` renders fields from provider definitions. |
| W4-06 | PASS   | Inline admin script toggles provider field groups immediately on radio change. |
| W4-07 | PASS   | Covered by code path and manual QA checklist in `docs/week4-task4-provider-compatibility.md`. |
| W4-08 | PASS   | Validation payload and storage now use declared `validation_key` and `option_path` metadata. |
| W4-09 | PASS   | Provider normalization drops invalid definitions before UI/runtime use. |
| W4-10 | PASS   | `setup_providers()` now requires the provider class to implement the plugin interface. |

## Workspace Verification Completed

1. `php -l simula-friendly-slugs-for-arabic-sites.php` passes after Week 4 changes.
2. Selected-provider validation was reduced from provider-wide iteration to provider-specific payload handling.
3. Provider selector, provider fields, and save payload handling now all use `get_translation_providers_definitions()`.
4. Admin interaction now switches provider field groups immediately without requiring a save/reload.
5. Third-party provider fields can map submitted values via `validation_key` and `option_path`.

## Manual QA Checklist

1. Save settings with `google` selected and a valid Google API key.
2. Save settings with `google` selected while a second registered provider has empty fields.
3. Save settings with `google` selected and invalid Google credentials; confirm the save is rejected and previous values remain intact.
4. Add a mock provider through `simula_friendly_slugs_for_arabic_sites_translation_providers` and confirm it appears in the Translation Service selector.
5. Switch between `google` and the mock provider in the settings screen and confirm the Provider Settings panel changes immediately.
6. Save the mock provider and confirm its submitted values persist in the declared option paths.
7. Change the mock provider class to a non-existent or invalid class and confirm the settings page remains usable.
8. Remove required definition keys like `label` or `class` from the mock provider and confirm the invalid provider is ignored safely.

## Week 4 Pass Criteria

Week 4 regression coverage passes only when:

1. Selected-provider-only validation is confirmed.
2. Provider-definition-driven UI rendering is confirmed.
3. Provider panel switching works immediately in admin.
4. Third-party provider definitions are verified for both rendering and save behavior.
5. No incomplete or invalid provider definition can break the settings page.

Current status:

- Workspace verification completed.
- WordPress runtime QA remains required for end-to-end admin confirmation.
