# Week 1 Output: Implementation Tickets for Week 2 and Week 3

Status: Approved for engineering handoff
Approval date: 2026-04-12
Source documents:

- `docs/1.2.x/week1-slug-ownership-spec.md`
- `docs/1.2.x/week1-test-matrix.md`
- `docs/1.2.x/ISSUES.md` (`P0` only)

## Ticket Format

- `ID`
- `Title`
- `Target Week`
- `Scope`
- `Affected Functions/Hooks`
- `Acceptance Criteria`
- `Mapped Test Cases`

## Week 2 Tickets (Core Save Logic, P0)

### TKT-P0-W2-01

- Title: Add slug ownership metadata contract and helpers
- Target Week: Week 2
- Scope:
  - Introduce ownership meta usage in save pipeline:
    - `_simula_slug_locked_manual` (bool)
    - `_simula_last_generated_slug` (string)
  - Add internal helper methods for read/write/normalize behavior.
- Affected Functions/Hooks:
  - `simula-friendly-slugs-for-arabic-sites.php`:
    - `maybe_generate_slug_on_save(...)`
    - `generate_friendly_slug(...)`
  - New helper methods in plugin class (meta get/set + slug normalization).
- Acceptance Criteria:
  - Missing meta is treated as unlocked/no-generated state.
  - Meta read/write paths are centralized and deterministic.
  - No behavior regression for non-Arabic titles and method `none`.
- Mapped Test Cases:
  - `W1-15`, `W1-16`

### TKT-P0-W2-02

- Title: Implement ownership-first save decision engine in `maybe_generate_slug_on_save`
- Target Week: Week 2
- Scope:
  - Replace implicit slug-presence logic with ownership-first decision rules.
  - Enforce context guards (autosave/revision/auto-draft exclusions).
  - Keep initial suggestion behavior for eligible new/default states.
  - Restore `regenerate_on_change` for unlocked plugin-owned slugs on title change.
- Affected Functions/Hooks:
  - Hook: `wp_insert_post_data`
  - Function: `maybe_generate_slug_on_save(...)`
- Acceptance Criteria:
  - Manual lock blocks all automatic slug replacement.
  - New eligible posts still get initial friendly suggestion.
  - Unlocked posts with `regenerate_on_change=1` auto-refresh slug on title change only while current slug still matches `_simula_last_generated_slug`.
  - Autosave/revision contexts do not mutate slug or ownership state.
- Mapped Test Cases:
  - `W1-01`, `W1-02`, `W1-04`, `W1-05`, `W1-09`, `W1-13`, `W1-14`

### TKT-P0-W2-03

- Title: Add manual-edit detection and lock transition logic
- Target Week: Week 2
- Scope:
  - Implement Task-5 algorithm using incoming slug, DB slug, and last generated slug.
  - Set manual lock on explicit user slug divergence.
  - Preserve user-entered slug in same save request when manual edit is detected.
- Affected Functions/Hooks:
  - Hook: `wp_insert_post_data`
  - Function: `maybe_generate_slug_on_save(...)`
  - Potential helper: request/context extraction utilities.
- Acceptance Criteria:
  - Manual slug edits set `_simula_slug_locked_manual=true`.
  - Title-only edits do not set manual lock.
  - Quick Edit slug edits are treated as manual and lock.
- Mapped Test Cases:
  - `W1-03`, `W1-11`, `W1-12`

### TKT-P0-W2-04

- Title: Align uniqueness filter behavior with ownership lock
- Target Week: Week 2
- Scope:
  - Ensure `generate_friendly_slug(...)` does not bypass ownership protections.
  - Prevent uniqueness-time overrides when manual lock is active and no explicit action is present.
- Affected Functions/Hooks:
  - Hook: `wp_unique_post_slug`
  - Function: `generate_friendly_slug(...)`
- Acceptance Criteria:
  - No path remains where manual-owned slug is replaced at uniqueness stage.
  - Behavior remains stable for unlocked initial suggestion and `regenerate_on_change` flow.
- Mapped Test Cases:
  - `W1-05`, `W1-06`, `W1-09`

## Week 3 Tickets (Editor UX + Explicit Actions, P0)

### TKT-P0-W3-01

- Title: Implement explicit regenerate/use-friendly action endpoint
- Target Week: Week 3
- Scope:
  - Add explicit action handler(s) for:
    - `regenerate_friendly_slug`
    - `use_friendly_slug`
  - Enforce nonce and capability checks.
  - Apply slug + update tracking meta + lock reset only on successful explicit action.
- Affected Functions/Hooks:
  - New admin action hooks (e.g. `admin_post_*` and/or REST route for block editor flow).
  - Existing conversion methods:
    - `convert_wp_transliteration(...)`
    - `convert_arabizi(...)`
    - `convert_hash(...)`
    - `convert_translation(...)`
- Acceptance Criteria:
  - Invalid nonce/capability request causes no slug/meta mutation.
  - Successful explicit action applies friendly slug and sets lock `false`.
  - Failure path preserves lock and current slug.
- Mapped Test Cases:
  - `W1-10`, `W1-17`, `W1-18`, `W1-21`, `W1-22`

### TKT-P0-W3-02

- Title: Add pre-publish divergence notice with deterministic actions
- Target Week: Week 3
- Scope:
  - Show non-blocking notice when current slug differs from plugin suggestion.
  - Add one-click actions:
    - `Keep current slug`
    - `Use friendly slug`
  - Persist resulting ownership state transitions.
- Affected Functions/Hooks:
  - New admin/editor notice integration points:
    - classic admin notices hooks
    - block editor UI integration (plugin sidebar/notices or equivalent)
  - Save/action handlers for action execution.
- Acceptance Criteria:
  - Notice appears only under defined divergence conditions.
  - `Keep current slug` sets/keeps lock `true`.
  - `Use friendly slug` applies slug and sets lock `false`.
- Mapped Test Cases:
  - `W1-07`, `W1-08`, `W1-19`, `W1-20`

### TKT-P0-W3-03

- Title: Enforce cross-editor parity (Block + Classic + Quick Edit)
- Target Week: Week 3
- Scope:
  - Validate behavior parity for ownership rules and explicit actions across editors.
  - Ensure quick-edit path respects manual intent detection.
- Affected Functions/Hooks:
  - Shared save logic (`maybe_generate_slug_on_save(...)`).
  - Editor-specific action/notice wiring.
- Acceptance Criteria:
  - Matching scenarios produce equivalent state outcomes across editors.
  - No editor path silently overwrites manually owned slugs.
- Mapped Test Cases:
  - `W1-02`, `W1-07`, `W1-08`, `W1-11`, `W1-19`

### TKT-P0-W3-04

- Title: Add regression coverage for P0 slug ownership behavior
- Target Week: Week 3
- Scope:
  - Add/update automated tests where framework allows.
  - Add manual QA checklist aligned to the Week 1 minimum execution set.
- Affected Functions/Hooks:
  - Save filters and explicit action handlers added in W2/W3.
  - Test scaffolding and QA docs.
- Acceptance Criteria:
  - Minimum execution set passes before merge.
  - No critical regressions in publish/update/autosave flows.
- Mapped Test Cases:
  - Minimum set from matrix:
    - `W1-01`, `W1-03`, `W1-05`, `W1-07`, `W1-08`, `W1-11`, `W1-13`, `W1-14`, `W1-17`, `W1-19`

## Dependency Order

1. `TKT-P0-W2-01`
2. `TKT-P0-W2-02`
3. `TKT-P0-W2-03`
4. `TKT-P0-W2-04`
5. `TKT-P0-W3-01`
6. `TKT-P0-W3-02`
7. `TKT-P0-W3-03`
8. `TKT-P0-W3-04`

## Definition of Ready (for Week 2 kickoff)

- Spec sections for Tasks 1-8 are approved.
- Test matrix IDs are stable and referenced by tickets.
- Ticket scopes are implementation-sized and independently reviewable.
