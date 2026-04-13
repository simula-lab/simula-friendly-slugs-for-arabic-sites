# Week 4 Execution Plan (P1 Provider Fixes)

Date: 2026-04-13
Target week: May 4-May 10, 2026
Source documents:

- `docs/1.2.x/week-by-week-plan.md`
- `docs/1.2.x/ISSUES.md`

## Objective

Complete the `P1` provider/settings work so settings save remains stable in multi-provider setups and the admin UI reflects the runtime provider registry.

## Week 4 Scope

1. Validate only the selected provider credentials during settings save.
2. Refactor provider settings UI to use provider definitions as the single source of truth.
3. Verify third-party provider extension behavior still works after the refactor.

## Out of Scope

- P0 slug ownership/editor UX work from Weeks 2-3
- P2 API key masking and `custom_transliteration` cleanup
- Release prep and WordPress.org packaging

## Step-by-Step Tasks

### TKT-P1-W4-01

- Title: Audit current provider settings flow and lock the provider data contract
- Target Week: Week 4
- Scope:
  - Trace how provider definitions are registered and consumed.
  - Identify the exact shape required for each provider definition:
    - provider key
    - label
    - credentials/fields
    - optional help text or capability flags
  - Confirm where UI and sanitization currently diverge.
- Affected Functions/Hooks:
  - `get_translation_providers_definitions()`
  - `sanitize_settings()`
  - settings field rendering methods in `simula-friendly-slugs-for-arabic-sites.php`
- Acceptance Criteria:
  - A documented provider definition contract exists in code comments or implementation notes.
  - All later Week 4 tasks use the same definition shape.
  - Any missing field assumptions are resolved before refactor work starts.

### TKT-P1-W4-02

- Title: Restrict sanitizer validation to the selected provider
- Target Week: Week 4
- Scope:
  - Update `sanitize_settings()` so it validates credentials only for the submitted `translation_service`.
  - Preserve unrelated stored credentials for unselected providers.
  - Ensure unknown provider keys fail safely without corrupting saved settings.
- Affected Functions/Hooks:
  - `sanitize_settings()`
  - provider credential validation helpers
- Acceptance Criteria:
  - Saving settings with provider `A` selected does not fail because provider `B` credentials are empty.
  - Invalid credentials for the selected provider still block save with the expected error path.
  - Existing saved values for unselected providers are not cleared accidentally.
  - Unknown or filtered-out provider values fall back safely.

### TKT-P1-W4-03

- Title: Refactor provider settings UI to render from provider definitions
- Target Week: Week 4
- Scope:
  - Remove hard-coded provider UI branching where feasible.
  - Build provider selector options from `get_translation_providers_definitions()`.
  - Render provider-specific settings fields from the same definitions used by runtime validation.
  - Keep current behavior and labels for built-in providers unless the definition contract requires a correction.
- Affected Functions/Hooks:
  - settings page rendering methods
  - `get_translation_providers_definitions()`
- Acceptance Criteria:
  - Every runtime-registered provider appears in the settings selector automatically.
  - Provider-specific fields shown in admin match the selected provider definition.
  - No built-in provider becomes unconfigurable after the refactor.
  - The UI no longer depends on a separate hard-coded provider list.

### TKT-P1-W4-04

- Title: Add compatibility handling for third-party provider extensions
- Target Week: Week 4
- Scope:
  - Test filtered provider definitions with at least one mocked third-party provider shape.
  - Ensure unsupported or incomplete third-party definitions fail gracefully in admin.
  - Confirm selected-provider-only validation works for providers added through filters.
- Affected Functions/Hooks:
  - provider definition filter consumers
  - settings sanitization and rendering paths
- Acceptance Criteria:
  - A third-party provider can be added through the existing filter and appears in the UI.
  - Saving settings for a built-in provider ignores missing credentials on the third-party provider.
  - Incomplete provider definitions do not trigger fatal errors or unusable settings screens.

### TKT-P1-W4-05

- Title: Add regression coverage for provider settings behavior
- Target Week: Week 4
- Scope:
  - Add or update automated coverage where practical for sanitizer and provider-definition rendering logic.
  - Write a manual QA checklist for settings save scenarios if automated coverage is limited.
- Affected Functions/Hooks:
  - `sanitize_settings()`
  - provider settings renderers
  - provider definition helpers
- Acceptance Criteria:
  - The selected-provider validation path is covered by tests or explicit manual QA steps.
  - The provider-definition-driven UI path is covered for built-in and filtered providers.
  - Week 4 exit notes can point to a concrete verification artifact.

## Recommended Execution Order

1. `TKT-P1-W4-01` audit and data-contract lock
2. `TKT-P1-W4-02` sanitizer fix
3. `TKT-P1-W4-03` provider-definition UI refactor
4. `TKT-P1-W4-04` third-party extension compatibility pass
5. `TKT-P1-W4-05` regression coverage and QA notes

## Manual QA Checklist

1. Save settings with `google_translate` selected and valid credentials.
2. Save settings with `google_translate` selected while a second registered provider has empty credentials.
3. Switch providers and confirm only the selected provider fields are required.
4. Confirm the provider dropdown includes any provider added through filter.
5. Confirm selecting a filtered provider renders the expected fields without PHP warnings/fatals.
6. Confirm returning to a built-in provider preserves previously saved unrelated provider values.
7. Confirm invalid selected-provider credentials still show a save error and do not partially corrupt settings.

## Week 4 Exit Criteria

Week 4 is complete only when:

1. Settings save works in a multi-provider environment with unselected providers present.
2. The settings UI is driven by provider definitions instead of a separate hard-coded list.
3. Third-party provider registration is verified for both rendering and sanitization paths.
4. A regression checklist or automated coverage exists for the new provider behavior.
