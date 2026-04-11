# ISSUES.md

This file tracks known issues and implementation direction for future plugin development.

## Priority Legend

- `P0` = must fix first (functional correctness / major UX regression)
- `P1` = important (extensibility / reliability)
- `P2` = quality improvement

## Current Issues

### 1) `P1` Settings save can fail for unselected providers

`sanitize_settings()` validates credentials for all registered providers, not only the currently selected provider.  
If a provider is registered via filter but not selected, save can still fail because its credentials are empty/invalid.

- Impact: settings page can become unusable in multi-provider setups
- Expected fix: validate only the selected `translation_service` credentials during save
- File references:
  - `simula-friendly-slugs-for-arabic-sites.php:624`
  - `simula-friendly-slugs-for-arabic-sites.php:640`

### 2) `P1` Provider UI is hard-coded and diverges from runtime provider registry

The settings UI uses a hard-coded provider list, while runtime/validation use filtered provider definitions.  
This can create config states that are valid internally but not representable in the UI.

- Impact: broken extensibility and inconsistent admin behavior
- Expected fix: build provider UI from `get_translation_providers_definitions()`
- File references:
  - `simula-friendly-slugs-for-arabic-sites.php:543`
  - `simula-friendly-slugs-for-arabic-sites.php:608`

### 3) `P2` API key is exposed in plain text in admin form

The Google API key input is currently rendered as plain text and fully visible.

- Impact: accidental credential disclosure (screen share, screenshots, shoulder surfing)
- Expected fix: use password-style input + masked display pattern, and keep existing value unless explicitly replaced
- File reference:
  - `simula-friendly-slugs-for-arabic-sites.php:566`

### 4) `P2` Hidden/unsupported method accepted in sanitizer

`custom_transliteration` is accepted in sanitization but not shown in the method UI.

- Impact: hidden option state and confusion in troubleshooting
- Expected fix: either expose it in UI or remove it from allowed method list
- File references:
  - `simula-friendly-slugs-for-arabic-sites.php:596`
  - `simula-friendly-slugs-for-arabic-sites.php:510`

### 5) `P0` Plugin overrides manually edited slugs

Current behavior can overwrite user intent when users manually edit `post_name`.

- Impact: major editorial UX issue; users lose explicit slug decisions
- Required behavior:
  - For new posts/pages: auto-generate plugin slug only as an initial suggestion
  - For manual user edits: once user changes slug manually, mark post as “manual slug chosen”
  - After manual choice: plugin must stop auto-overwriting that slug
  - For explicit regeneration: provide UI action (`Generate friendly slug` / `Regenerate plugin slug`) that intentionally replaces current slug
  - Before publishing: show a clear notice if current slug differs from plugin suggestion, with one-click actions:
    - `Keep current slug`
    - `Use friendly slug`

## Implementation Plan (Phased)

### Phase 1: Protect user intent (`P0`)

1. Add post meta flags
   - `_simula_slug_locked_manual` (bool): user chose manual slug
   - `_simula_last_generated_slug` (string): latest plugin-generated slug
2. Update save flow logic
   - In `maybe_generate_slug_on_save()`, skip auto-generation when manual lock is true
   - Auto-generate only when slug is empty/default or when explicit regenerate action is requested
3. Detect manual edits
   - On save, compare incoming slug vs plugin-generated suggestion and previous stored values
   - If user changed slug explicitly, set manual lock
4. Add explicit regenerate action
   - Admin-side control that regenerates slug and updates `_simula_last_generated_slug`
   - Regenerate action should clear manual lock only when user explicitly confirms regeneration

### Phase 2: Improve editor UX (`P0`)

1. Add pre-publish/editor notice
   - If current slug differs from plugin suggestion, show non-blocking warning
2. Add one-click actions
   - `Keep current slug` sets/keeps manual lock
   - `Use friendly slug` applies generated slug and updates tracking meta

### Phase 3: Provider consistency fixes (`P1`)

1. Validate only selected provider credentials in settings sanitizer
2. Generate provider settings UI from provider definitions filter
3. Keep provider-specific fields conditional and consistent with selected service

### Phase 4: Security and cleanup (`P2`)

1. Change API key input UX to avoid full plain-text exposure
2. Resolve `custom_transliteration` mismatch (show in UI or remove from accepted list)
3. Add/update tests around settings save and slug generation behavior

## Acceptance Criteria

- Manual slug edits are never overwritten automatically after user intent is detected.
- New posts still get a friendly initial slug suggestion.
- Regeneration is explicit and user-triggered.
- Settings save works even when additional providers are registered but not selected.
- Provider list in UI always matches runtime provider definitions.
