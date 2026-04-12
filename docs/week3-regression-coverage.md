# Week 3 Regression Coverage (P0 Editor UX + Explicit Actions)

Date: 2026-04-12
Branch: `week3-p0-editor-ux`
Scope: `TKT-P0-W3-01..04`

## Purpose

Track execution of Week 3 regression scenarios mapped from `docs/week1-test-matrix.md` for:

- explicit use-friendly actions and backend-supported regenerate behavior
- keep-current ownership confirmation
- divergence notice behavior
- Block and Classic editor parity

## Environment Baseline

- WordPress: not executed in this workspace
- PHP: `php -l simula-friendly-slugs-for-arabic-sites.php` passes
- Editor contexts exercised:
  - static code review in this workspace
  - manual browser QA partially executed outside this workspace (`W1-07` confirmed pass; `W1-08` initially failed, then implementation was patched and needs retest)
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
4. `Keep current slug` suppresses the current divergence notice after acknowledgement and does not force a page navigation.
5. Explicit regenerate cannot bypass nonce/capability checks in any path where it is exposed.
6. Quick Edit still respects Week 2 manual lock behavior and is not regressed by Week 3 changes.

## Execution Checklist

Mark each row as `PASS` / `FAIL` / `N/A` and include notes.

| ID    | Status | Notes                                                                                                                                                                                                                                      |
| ----- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| W1-07 | PASS   | Manual QA confirmed in Gutenberg: after saving a manually changed slug, the divergence warning appears without full reload; `Use friendly slug` remains the intended explicit apply action. |
| W1-08 | FAIL   | Manual QA found two issues in Classic: `Keep current slug` initially forced navigation and the notice remained visible. Implementation was patched to use AJAX and acknowledged-divergence suppression. Retest required. |
| W1-10 | N/A    | Backend regenerate path still exists, but it is no longer shown in the divergence notice UI. Execute only if a separate regenerate control is exposed; otherwise defer this scenario for the current Week 3 UX. |
| W1-17 | N/A    | Invalid nonce coverage for regenerate is deferred unless a user-facing regenerate entry point is exposed in the current build. Backend nonce enforcement remains in place for admin-post flow. |
| W1-18 | N/A    | Capability test for regenerate is deferred unless a user-facing regenerate entry point is exposed in the current build. Backend capability enforcement remains in place for explicit actions. |
| W1-19 | N/A    | Browser-level Gutenberg notice visibility needs manual confirmation after the reactive refresh fix. Static review coverage is present. |
| W1-20 | N/A    | Browser-level Gutenberg notice suppression needs manual confirmation after the acknowledgement and reactive refresh changes. |
| W1-21 | N/A    | Translation failure path remains unverified manually. Current implementation should preserve slug/meta on generation failure. |
| W1-22 | N/A    | Empty-generation edge case remains unverified manually. Current implementation should preserve slug/meta on failure. |

## Meta Assertions (must hold)

For all applicable scenarios, verify:

1. `Keep current slug` preserves the current slug and sets or keeps `_simula_slug_locked_manual=true`.
2. `Use friendly slug` applies the friendly slug and sets `_simula_slug_locked_manual=false`.
3. `Keep current slug` preserves `_simula_last_generated_slug` and acknowledges the current divergent suggestion for notice suppression.
4. Explicit regenerate updates `_simula_last_generated_slug` only on success when that action is exposed.
5. Invalid nonce or insufficient capability causes zero slug/meta mutation.
6. Divergence notice appears only when the current slug differs from a valid plugin suggestion and that divergence has not already been acknowledged.
7. Empty or failed generation does not silently overwrite the current slug.

## Workspace Verification Completed

1. `php -l simula-friendly-slugs-for-arabic-sites.php` passed after Week 3 backend/UI integration edits.
2. Static review confirmed both Classic and Block editor notices consume the same divergence-state helper.
3. Static review confirmed explicit slug actions use one shared backend execution path with nonce and capability enforcement.
4. Week 3 review fix: `Keep current slug` no longer overwrites `_simula_last_generated_slug` with the manual slug.
5. Week 3 review fix: Classic admin notices are now suppressed on Block editor screens to avoid mixed-surface duplication.
6. Week 3 review fix: Gutenberg divergence notice now refreshes reactively after post creation and save completion instead of relying on a page-load snapshot.
7. Week 3 review fix: `Keep current slug` is now designed to acknowledge the current divergence and remove the warning without forced navigation.
8. Week 3 review fix: the divergence notice now shows only `Keep current slug` and `Use friendly slug`; redundant regenerate action was removed from the notice UI.

Still required outside this workspace:

1. Retest `W1-08` in Classic after the AJAX/acknowledgement fix
2. Manual QA for `W1-19` and `W1-20` in Gutenberg
3. Confirmation that Quick Edit behavior from Week 2 still passes
4. End-to-end translation-provider failure QA
5. Empty-generation edge-case QA

## Week 3 Pass Criteria

Week 3 regression coverage passes only when:

1. All required scenarios are `PASS` or explicitly deferred with reason.
2. No scenario shows manual slug ownership being bypassed by a Week 3 action.
3. Divergence notice is neither missing when required nor shown when suppressed conditions apply.
4. `Keep current slug` removes the current warning and does not force page navigation in the supported editor flows.
5. Security failures leave slug and ownership meta unchanged.
6. Block and Classic outcomes are behaviorally equivalent for matching flows.

Current status:

- Manual QA has started.
- `W1-07` is confirmed `PASS`.
- `W1-08` was observed `FAIL`, then patched, and now requires retest.
- Remaining browser-level WordPress QA is still pending.
