# Week 4 Manual QA Guide (Provider Settings)

Date: 2026-04-13
Scope: Week 4 `P1` provider/settings fixes
Related documents:

- `docs/1.2.x/week4-execution-plan.md`
- `docs/1.2.x/week4-regression-coverage.md`
- `docs/1.2.x/week4-task4-provider-compatibility.md`

## Goal

Validate that provider settings now behave correctly with more than one provider present.

This guide assumes the plugin now exposes:

- `Google Translate`
- `Mock Provider`

The mock provider is QA-only and does not require external API access.

## Test Environment

1. Use a WordPress admin account with permission to manage plugin settings.
2. Activate the plugin.
3. Open:
   `Settings -> Friendly Slugs`
4. For translation-method-specific checks, set:
   `Method -> Translation`

## Test Data

Use the following values during QA:

- Valid mock token: `mock123`
- Invalid mock token: `abc`
- Valid mock endpoint: `https://example.com/mock`
- Invalid mock endpoint: `not-a-url`
- Invalid Google key example: `invalid-google-key`

## Scenario List

### W4-MQ-01 Provider selector shows both providers

Steps:

1. Open the settings page.
2. Locate `Translation Service`.
3. Confirm both `Google Translate` and `Mock Provider` are listed.

Expected result:

- Both providers are visible as selectable options.

### W4-MQ-02 Provider settings switch immediately

Steps:

1. Select `Google Translate`.
2. Observe the `Provider Settings` section.
3. Select `Mock Provider`.
4. Observe the `Provider Settings` section again.
5. Switch back to `Google Translate`.

Expected result:

- The visible provider fields change immediately on radio change.
- No page reload is required.
- Google shows only its Google key field.
- Mock shows `Mock Token` and `Mock Endpoint`.

### W4-MQ-03 Google save is not blocked by mock provider fields

Steps:

1. Select `Google Translate`.
2. Leave mock fields untouched or empty.
3. Enter a Google key value.
4. Save settings.

Expected result:

- Save behavior depends only on the Google field.
- Empty mock fields do not trigger validation errors.

Note:

- If you do not have a real valid Google key, this scenario is still useful with an invalid key because the failure should be a Google-only failure, not a mock-provider failure.

### W4-MQ-04 Invalid Google credentials still fail correctly

Steps:

1. Select `Google Translate`.
2. Enter `invalid-google-key`.
3. Save settings.

Expected result:

- Save is rejected.
- The error refers to the Google provider.
- There is no error about mock-provider fields.

### W4-MQ-05 Mock provider requires only its own fields

Steps:

1. Select `Mock Provider`.
2. Leave `Mock Token` empty.
3. Save settings.

Expected result:

- Save is rejected.
- Error says mock token cannot be empty.
- No Google validation error appears.

### W4-MQ-06 Mock provider rejects a short token

Steps:

1. Select `Mock Provider`.
2. Enter `abc` in `Mock Token`.
3. Save settings.

Expected result:

- Save is rejected.
- Error says mock token must be at least 6 characters.

### W4-MQ-07 Mock provider accepts valid values

Steps:

1. Select `Mock Provider`.
2. Enter `mock123` in `Mock Token`.
3. Leave `Mock Endpoint` empty.
4. Save settings.

Expected result:

- Save succeeds.
- Selected translation service remains `Mock Provider`.
- `Mock Token` value persists after reload.

### W4-MQ-08 Mock provider validates endpoint format

Steps:

1. Select `Mock Provider`.
2. Enter `mock123` in `Mock Token`.
3. Enter `not-a-url` in `Mock Endpoint`.
4. Save settings.

Expected result:

- Save is rejected.
- Error says mock endpoint must be a valid URL.

### W4-MQ-09 Mock provider persists endpoint field

Steps:

1. Select `Mock Provider`.
2. Enter `mock123` in `Mock Token`.
3. Enter `https://example.com/mock` in `Mock Endpoint`.
4. Save settings.

Expected result:

- Save succeeds.
- Both values persist after reload.

### W4-MQ-10 Provider values remain preserved when switching providers

Steps:

1. Save valid mock provider values.
2. Switch to `Google Translate`.
3. Save settings again.
4. Return to `Mock Provider`.

Expected result:

- Previously saved mock values are still present.
- Saving Google settings does not wipe mock values.

## Pass Criteria

Week 4 manual QA passes when:

1. Provider selector shows both `google` and `mock`.
2. Provider settings switch immediately in the UI.
3. Only the selected provider is validated on save.
4. Mock provider values persist correctly across saves and provider switches.
5. Google and mock provider failures remain isolated to the selected provider.

## Notes

1. Google validation uses a live API request, so a fully successful Google save requires a real valid key.
2. The mock provider is the main tool for verifying the multi-provider Week 4 behavior without external dependencies.
