# Week 5 Manual QA Guide (Security + Cleanup)

Date: 2026-04-13
Scope: Week 5 `P2` security and cleanup changes
Related documents:

- `docs/week5-execution-plan.md`
- `docs/week5-regression-coverage.md`

## Goal

Validate that provider credentials are no longer exposed in the admin UI and that the unsupported hidden method state is resolved safely.

## Test Environment

1. Use a WordPress admin account with permission to manage plugin settings.
2. Activate the plugin.
3. Open:
   `Settings -> Friendly Slugs`

## Scenario List

### W5-MQ-01 API key field is masked

Steps:

1. Open the settings page.
2. Set `Method -> Translation`.
3. Select `Google Translate`.
4. Inspect the `Google API Key` field.

Expected result:

- The field renders as a password-style input.
- The saved key is not visible in full in the form.

### W5-MQ-02 Blank API key submission preserves stored key

Steps:

1. Start with a known saved Google API key.
2. Open the settings page.
3. Leave the `Google API Key` field blank.
4. Save settings.
5. Reload the page and use translation behavior or follow-up save flow that depends on the same key.

Expected result:

- Save succeeds or fails only based on the existing saved key state.
- The blank field does not wipe the stored key.

### W5-MQ-03 Entering a replacement key updates the stored key

Steps:

1. Open the settings page with `Google Translate` selected.
2. Enter a new Google API key value.
3. Save settings.

Expected result:

- The new key replaces the previously stored key.
- Validation behavior still applies to the newly submitted key.

### W5-MQ-04 Blank masked field guidance is present

Steps:

1. Open the settings page.
2. Locate the description text under the API key field.

Expected result:

- The description explains that leaving the field blank keeps the existing key.

### W5-MQ-05 Non-API provider fields still render normally

Steps:

1. Select `Mock Provider`.
2. Inspect the `Mock Token` and `Mock Endpoint` fields.

Expected result:

- Non-API-key fields still render with their normal visible input types.
- Existing provider switching behavior remains intact.

### W5-MQ-06 Unsupported method cannot be re-saved as active

Steps:

1. If possible, seed plugin options so `method=custom_transliteration`.
2. Open the settings page.
3. Save settings without changing the method.

Expected result:

- The saved method degrades to a supported value, currently `none`.
- The plugin no longer behaves as if `custom_transliteration` were active.

## Pass Criteria

Week 5 manual QA passes when:

1. API keys are not visible in plain text in admin.
2. Blank API key submissions preserve the stored key.
3. Explicit API key replacements still save correctly.
4. Non-secret provider fields are unaffected.
5. Unsupported hidden method states are normalized safely.
