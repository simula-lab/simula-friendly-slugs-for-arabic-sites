# Week-by-Week Delivery Plan (Target: publish new plugin version)

Assumption: start week is Monday, April 13, 2026.

## Week 1 (Apr 13-Apr 19): Scope lock + architecture for slug ownership (`P0`)

- Finalize behavior spec for manual slug protection (`_simula_slug_locked_manual`, `_simula_last_generated_slug`).
- Define editor flow for:
  - initial auto-suggestion
  - manual lock
  - explicit regenerate
  - pre-publish notice actions
- Create implementation tickets and test matrix (classic editor + block editor + draft/publish transitions).
- Output: approved technical design and task breakdown.

## Week 2 (Apr 20-Apr 26): Core save logic changes (`P0`)

- Refactor `maybe_generate_slug_on_save()` to stop overwriting manually chosen slugs.
- Implement manual-edit detection and meta persistence.
- Ensure auto-generation happens for initial/default slug states, and for unlocked plugin-owned slugs on title change when `regenerate_on_change` is enabled.
- Add regression handling for autosaves/revisions.
- Output: core logic merged behind stable behavior.

## Week 3 (Apr 27-May 3): Editor UX + explicit regenerate (`P0`)

- Add "Generate/Regenerate friendly slug" action.
- Add warning/notice when current slug differs from plugin suggestion.
- Add one-click actions: `Keep current slug` and `Use friendly slug`.
- Validate UI behavior in both editor contexts.
- Output: complete user-facing `P0` flow.

## Week 4 (May 4-May 10): Provider fixes (`P1`)

- Fix settings sanitizer to validate only selected provider credentials.
- Refactor provider settings UI to use provider definitions (single source of truth).
- Verify third-party provider extension behavior.
- Output: extensible and consistent provider configuration.

## Week 5 (May 11-May 17): Security + cleanup (`P2`)

- Change API key field UX (masked/password-style + safe replacement flow).
- Resolve `custom_transliteration` mismatch (either expose in UI or remove from allowed list).
- Update i18n strings and settings descriptions as needed.
- Output: security/consistency cleanup complete.

## Week 6 (May 18-May 24): QA, compatibility, release candidate

- Full manual QA across post/page lifecycle:
  - new draft, title edits, slug edits, publish, update, quick edit
- Add/update automated tests where possible.
- Test on supported WP and PHP versions; verify no fatal errors.
- Prepare RC (`1.2.0-rc1`) and internal sign-off.
- Output: release candidate approved.

## Week 7 (May 25-May 31): WordPress.org release and publish

- Finalize `readme.txt`:
  - `Stable tag`
  - changelog
  - tested-up-to
- Bump plugin version (main file + readme consistency).
- Build final package and verify headers/assets.
- Commit to WordPress.org SVN:
  - `/trunk` update
  - create `/tags/1.2.0`
- Verify plugin page reflects new version and download works.
- Output: version `1.2.0` published successfully on WordPress.org.

## Release Gates (must pass before publish)

1. No regression on manual slug ownership (`P0` acceptance criteria fully met).
2. Settings save works with extra providers present but unselected.
3. Readme/version/tag consistency verified before SVN tag.
4. Post-publish smoke test on a clean WP install.
