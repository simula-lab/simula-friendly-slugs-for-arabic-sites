# Week 5 Execution Plan (P2 Security + Cleanup)

Date: 2026-04-13
Target week: May 11-May 17, 2026
Source documents:

- `docs/week-by-week-plan.md`
- `docs/ISSUES.md`
- `docs/week4-closeout-note.md`

## Objective

Complete the planned `P2` cleanup work so provider credentials are no longer exposed in plain text and unsupported slug-generation methods cannot remain active through hidden settings states.

## Week 5 Scope

1. Change API key field UX to a masked replacement flow.
2. Preserve saved API keys unless an admin explicitly submits a replacement.
3. Remove the hidden `custom_transliteration` method from supported runtime/settings behavior.
4. Update admin descriptions and QA coverage to match the new behavior.

## Out of Scope

- New provider features or provider-constructor changes
- Additional slug-generation methods
- Week 6 release-candidate and compatibility work

## Step-by-Step Tasks

### TKT-P2-W5-01

- Title: Audit Week 5 security and cleanup touchpoints
- Target Week: Week 5
- Scope:
  - Trace provider field rendering for `api_key` types.
  - Trace sanitization and validation paths for provider credentials.
  - Trace all places where the saved `method` value is consumed at runtime.
- Affected Functions/Hooks:
  - `field_translation_provider_settings_html()`
  - `build_provider_validation_payload()`
  - `apply_provider_validation_result()`
  - `sanitize_settings()`
  - runtime method consumers
- Acceptance Criteria:
  - All API key render/save touchpoints are identified.
  - Hidden-method activation paths are identified before edits begin.

### TKT-P2-W5-02

- Title: Replace plain-text API key rendering with masked replacement UX
- Target Week: Week 5
- Scope:
  - Render `api_key` provider fields as password-style inputs.
  - Do not prefill the stored key in the form.
  - Add admin guidance that blank means “keep existing key”.
- Affected Functions/Hooks:
  - `field_translation_provider_settings_html()`
- Acceptance Criteria:
  - Saved API keys are not displayed in full on the settings page.
  - Admin text explains the replacement behavior clearly.

### TKT-P2-W5-03

- Title: Preserve existing API keys unless a replacement is explicitly submitted
- Target Week: Week 5
- Scope:
  - Use the previously saved credential when a submitted `api_key` field is blank.
  - Keep selected-provider validation working under the masked field flow.
  - Store a new key only when a non-empty replacement value is submitted.
- Affected Functions/Hooks:
  - `build_provider_validation_payload()`
  - `apply_provider_validation_result()`
  - `sanitize_settings()`
- Acceptance Criteria:
  - Saving settings with a blank API key field does not clear the stored key.
  - Replacing a key still validates and persists correctly.

### TKT-P2-W5-04

- Title: Remove unsupported `custom_transliteration` from active settings/runtime behavior
- Target Week: Week 5
- Scope:
  - Normalize method values against the supported UI contract.
  - Prevent hidden/legacy `custom_transliteration` values from remaining active after save.
  - Ensure runtime slug generation also treats unsupported methods as `none`.
- Affected Functions/Hooks:
  - method rendering and sanitization
  - slug-generation runtime helpers
- Acceptance Criteria:
  - `custom_transliteration` is no longer accepted as a supported settings value.
  - Existing unsupported saved values degrade safely to `none`.

### TKT-P2-W5-05

- Title: Add Week 5 verification artifacts
- Target Week: Week 5
- Scope:
  - Run workspace syntax validation.
  - Add a manual QA guide for the new settings behavior.
  - Record regression-coverage status and remaining runtime QA needs.
- Affected Files:
  - `docs/week5-manual-qa-guide.md`
  - `docs/week5-regression-coverage.md`
- Acceptance Criteria:
  - A concrete verification checklist exists for API key masking and method cleanup.
  - Workspace verification status is documented.

## Recommended Execution Order

1. `TKT-P2-W5-01` audit
2. `TKT-P2-W5-02` masked field UX
3. `TKT-P2-W5-03` replacement-safe sanitization
4. `TKT-P2-W5-04` method cleanup
5. `TKT-P2-W5-05` verification docs

## Week 5 Exit Criteria

Week 5 is complete only when:

1. API keys are no longer rendered in plain text.
2. Leaving an API key field blank preserves the existing stored key.
3. Hidden `custom_transliteration` can no longer remain active in settings/runtime.
4. A verification artifact exists for both workspace checks and WordPress admin QA.
