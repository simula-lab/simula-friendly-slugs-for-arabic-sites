# Week 3 Regression Coverage (P0 Editor UX + Explicit Actions)

Date: 2026-04-12
Branch: `week3-p0-editor-ux`
Scope: `TKT-P0-W3-01..04`

## Purpose

Track execution of Week 3 regression scenarios mapped from `docs/week1-test-matrix.md` for:

- explicit regenerate and use-friendly actions
- keep-current ownership confirmation
- divergence notice behavior
- Block and Classic editor parity

## Environment Baseline

- WordPress: not executed in this workspace
- PHP: `php -l simula-friendly-slugs-for-arabic-sites.php` passes
- Editor contexts exercised: static code review only in this workspace; Classic/Block browser QA still pending
- Depends on Week 2 baseline in `docs/week2-regression-coverage.md`

## Required Scenario Set (Week 3)

1. `W1-07` Block first publish transition using `Use friendly slug`
2. `W1-08` Classic first publish transition using `Keep current slug`
3. `W1-10` Classic explicit regenerate on existing post
4. `W1-17` Block explicit regenerate security with invalid nonce
5. `W1-18` Classic explicit regenerate security with insufficient capability
6. `W1-19` Block pre-publish notice visibility when divergence exists
7. `W1-20` Block pre-publish notice suppression when slug matches suggestion
8. `W1-21` Classic translation failure fallback during explicit action
9. `W1-22` Block empty generation result during explicit action

## Cross-Editor Parity Checks

These checks confirm that the same logical outcome is preserved across editor surfaces:

1. Divergence notice conditions are equivalent in Block and Classic.
2. `Keep current slug` results in `lock=true` and no slug replacement in both editors.
3. `Use friendly slug` results in friendly slug application and `lock=false` in both editors.
4. Explicit regenerate cannot bypass nonce/capability checks in either editor path.
5. Quick Edit still respects Week 2 manual lock behavior and is not regressed by Week 3 changes.

## Execution Checklist

Mark each row as `PASS` / `FAIL` / `N/A` and include notes.

| ID    | Status | Notes                                                                                                                                                                                                                                      |
| ----- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| W1-07 | FAIL   | Static code review: explicit `Use friendly slug` action applies generated slug via shared handler and clears manual lock. Manual Block editor publish QA still pending.                                                                    |
| W1-08 |        | Static code review: explicit `Keep current slug` action preserves current slug, sets lock `true`, and now preserves `_simula_last_generated_slug` instead of overwriting it with the manual slug. Manual Classic publish QA still pending. |
| W1-10 |        | Static code review: explicit regenerate path is user-triggered, applies generated slug on success, and sets lock `false`. Manual Classic editor QA still pending.                                                                          |
| W1-17 |        | Static code review: invalid nonce is rejected by `check_admin_referer(...)` before any slug/meta mutation path runs. Manual Block editor verification still pending.                                                                       |
| W1-18 |        | Static code review: insufficient capability is rejected by `current_user_can( 'edit_post', $post_id )` before mutation. Manual Classic editor verification still pending.                                                                  |
| W1-19 |        | Static code review: Block editor notice is driven by shared divergence state and shown only when `should_show_notice=true`. Browser-level Gutenberg rendering still pending.                                                               |
| W1-20 |        | Static code review: divergence notice is suppressed when current slug matches the generated suggestion or when no valid suggestion exists. Browser-level Gutenberg rendering still pending.                                                |
| W1-21 |        | Static code review: explicit action failure to produce a valid slug redirects with `generation_failed` and does not mutate slug/meta. End-to-end provider failure QA still pending.                                                        |
| W1-22 |        | Static code review: empty generation result exits through failure status and leaves current slug unchanged. Browser-level Block editor verification still pending.                                                                         |

## Meta Assertions (must hold)

For all applicable scenarios, verify:

1. `Keep current slug` preserves the current slug and sets or keeps `_simula_slug_locked_manual=true`.
2. `Use friendly slug` applies the friendly slug and sets `_simula_slug_locked_manual=false`.
3. Explicit regenerate updates `_simula_last_generated_slug` only on success.
4. Invalid nonce or insufficient capability causes zero slug/meta mutation.
5. Divergence notice appears only when the current slug differs from a valid plugin suggestion.
6. Empty or failed generation does not silently overwrite the current slug.

## Workspace Verification Completed

1. `php -l simula-friendly-slugs-for-arabic-sites.php` passed after Week 3 backend/UI integration edits.
2. Static review confirmed both Classic and Block editor notices consume the same divergence-state helper.
3. Static review confirmed explicit slug actions use one shared backend handler with nonce and capability enforcement.
4. Week 3 review fix: `Keep current slug` no longer overwrites `_simula_last_generated_slug` with the manual slug.
5. Week 3 review fix: Classic admin notices are now suppressed on Block editor screens to avoid mixed-surface duplication.

Still required outside this workspace:

1. Manual QA in Block editor
2. Manual QA in Classic editor
3. Confirmation that Quick Edit behavior from Week 2 still passes
4. End-to-end translation-provider failure QA

## Week 3 Pass Criteria

Week 3 regression coverage passes only when:

1. All required scenarios are `PASS` or explicitly deferred with reason.
2. No scenario shows manual slug ownership being bypassed by a Week 3 action.
3. Divergence notice is neither missing when required nor shown when suppressed conditions apply.
4. Security failures leave slug and ownership meta unchanged.
5. Block and Classic outcomes are behaviorally equivalent for matching flows.

Current status:

- Week 3 static code review coverage passed for the mapped scenarios above.
- Browser-level WordPress QA remains pending.
