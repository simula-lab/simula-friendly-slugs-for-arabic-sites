# Week 4 Task 1: Provider Settings Audit and Contract

Date: 2026-04-13
Scope: `TKT-P1-W4-01`
Source files:

- `simula-friendly-slugs-for-arabic-sites.php`
- `docs/ISSUES.md`
- `docs/week4-execution-plan.md`

## Objective

Document the current provider settings flow, identify where runtime and admin behavior diverge, and define the provider data contract that Week 4 Tasks 2-5 must use.

## Current Flow Audit

### 1. Runtime provider registry

Runtime providers are built in `setup_providers()` from `get_translation_providers_definitions()`.

- Registry source:
  - `simula-friendly-slugs-for-arabic-sites.php:377`
  - `simula-friendly-slugs-for-arabic-sites.php:768`
- Current definition shape used there:
  - provider key => array with:
    - `label`
    - `class`
- Current runtime instantiation rule:
  - provider class must exist
  - constructor currently receives the saved API key for that provider

### 2. Admin settings registration

The settings page registers:

- translation service selector
- Google API key field
- regenerate-on-change checkbox

Relevant code:

- `simula-friendly-slugs-for-arabic-sites.php:800`
- `simula-friendly-slugs-for-arabic-sites.php:833`
- `simula-friendly-slugs-for-arabic-sites.php:843`

Current issue:

- provider fields are not definition-driven
- only Google is represented in the registered UI fields

### 3. Translation service UI

`field_translation_service_html()` uses a hard-coded provider map instead of the runtime provider registry.

Relevant code:

- `simula-friendly-slugs-for-arabic-sites.php:910`

Current hard-coded shape:

- `google` => `label`, `desc`

Current issue:

- a provider added via filter can exist at runtime and in sanitization, but still not be selectable in admin

### 4. Credential field UI

`field_api_key_html()` is also hard-coded to `api_keys[google]`.

Relevant code:

- `simula-friendly-slugs-for-arabic-sites.php:932`

Current issue:

- provider-specific fields are not generated from provider definitions
- non-Google providers have no canonical field rendering path

### 5. Sanitization path

`sanitize_settings()` validates the selected `translation_service`, but then iterates over all registered provider keys and validates each provider’s credentials.

Relevant code:

- `simula-friendly-slugs-for-arabic-sites.php:948`
- `simula-friendly-slugs-for-arabic-sites.php:978`
- `simula-friendly-slugs-for-arabic-sites.php:992`

Current issue:

- an unselected provider can block settings save if its credentials are empty or invalid
- this is the direct cause of `ISSUES.md` item 1

### 6. Half-wired custom-provider path

`field_custom_endpoint_html()` exists, but it is not registered in `register_settings()`.

Relevant code:

- `simula-friendly-slugs-for-arabic-sites.php:1044`

Current issue:

- there is an incomplete custom-provider UI path in the file
- the refactor should not assume that `custom_api_endpoint` is currently part of the active settings page contract

## Confirmed Divergence Points

1. Runtime registry uses `get_translation_providers_definitions()`, but the translation service selector does not.
2. Sanitization uses the runtime registry, but the visible credentials UI does not.
3. Credential validation is provider-wide, not selected-provider-only.
4. A custom endpoint field renderer exists without being part of the registered settings screen.

## Provider Contract For Week 4

Week 4 implementation should treat each provider definition as the single source of truth with this minimum shape:

```php
[
    'label' => 'Human readable provider name',
    'class' => 'Provider class name',
    'description' => 'Optional short admin description',
    'fields' => [
        [
            'type' => 'api_key',
            'option_path' => [ 'api_keys', '<provider-key>' ],
            'label' => 'Field label',
            'description' => 'Optional field help text',
        ],
    ],
]
```

## Required Contract Rules

1. `label` is required for admin display.
2. `class` is required for runtime provider instantiation.
3. `description` is optional and is used only for admin presentation.
4. `fields` is optional but, when present, must fully describe provider-specific admin inputs.
5. The selected provider’s `fields` array becomes the canonical source for credential rendering.
6. The selected provider’s class remains the canonical source for credential validation via `validate_settings()`.
7. Definitions missing `label` or `class` must be ignored safely in admin/runtime, not fatal.

## Design Constraints For Follow-up Tasks

1. Task 2 must validate only the selected provider and must preserve previously saved values for unselected providers.
2. Task 3 must build the provider selector and provider-specific inputs from the definition contract above.
3. Task 4 must verify that filtered third-party providers can participate in both rendering and validation without requiring hard-coded UI changes.
4. If a provider needs extra fields beyond `api_keys[provider]`, those fields must be declared in `fields` rather than added as special-case UI branches.

## Open Assumptions Resolved For Week 4

1. The current runtime contract is too small for a definition-driven UI, so Week 4 is expected to expand it.
2. Existing built-in behavior should remain centered on the `google` provider during the refactor.
3. `custom_api_endpoint` should be treated as legacy/incomplete until a provider definition explicitly declares it.
4. The provider class constructor currently receives one string value, so any richer provider configuration should be handled in the settings layer first unless constructor changes are made deliberately.

## Task 1 Exit Status

Task 1 is complete when this contract is used as the implementation baseline for:

1. selected-provider-only validation
2. provider-definition-driven settings rendering
3. third-party provider compatibility checks
