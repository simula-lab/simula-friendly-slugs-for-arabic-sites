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
- PHP: not executed for Week 3 in this document yet
- Editor contexts exercised: pending
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

| ID    | Status | Notes |
| ----- | ------ | ----- |
| W1-07 | N/A    |       |
| W1-08 | N/A    |       |
| W1-10 | N/A    |       |
| W1-17 | N/A    |       |
| W1-18 | N/A    |       |
| W1-19 | N/A    |       |
| W1-20 | N/A    |       |
| W1-21 | N/A    |       |
| W1-22 | N/A    |       |

## Meta Assertions (must hold)

For all applicable scenarios, verify:

1. `Keep current slug` preserves the current slug and sets or keeps `_simula_slug_locked_manual=true`.
2. `Use friendly slug` applies the friendly slug and sets `_simula_slug_locked_manual=false`.
3. Explicit regenerate updates `_simula_last_generated_slug` only on success.
4. Invalid nonce or insufficient capability causes zero slug/meta mutation.
5. Divergence notice appears only when the current slug differs from a valid plugin suggestion.
6. Empty or failed generation does not silently overwrite the current slug.

## Workspace Verification Completed

Pending.

Suggested checks once implementation exists:

1. `php -l simula-friendly-slugs-for-arabic-sites.php`
2. Manual QA in Block editor
3. Manual QA in Classic editor
4. Confirmation that Quick Edit behavior from Week 2 still passes

## Week 3 Pass Criteria

Week 3 regression coverage passes only when:

1. All required scenarios are `PASS` or explicitly deferred with reason.
2. No scenario shows manual slug ownership being bypassed by a Week 3 action.
3. Divergence notice is neither missing when required nor shown when suppressed conditions apply.
4. Security failures leave slug and ownership meta unchanged.
5. Block and Classic outcomes are behaviorally equivalent for matching flows.

Current status:

- Not executed yet.
