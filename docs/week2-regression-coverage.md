# Week 2 Regression Coverage (P0 Core Save Logic)

Date: 2026-04-12  
Branch: `week2-p0-core-save-logic`  
Scope: `TKT-P0-W2-01..04`

## Purpose

Track execution of Week 2 regression scenarios mapped from `docs/week1-test-matrix.md` for:

- metadata contract
- ownership-first save logic
- manual-edit lock transitions
- uniqueness-stage lock enforcement

## Environment Baseline

- WordPress: \***\*\_\_\*\***
- PHP: \***\*\_\_\*\***
- Plugin method under test: `wp_transliteration` / `arabizi` / `translation` / `hash`
- Editor contexts exercised: Block / Classic / Quick Edit

## Required Scenario Set (Week 2)

1. `W1-01` Block new draft initial suggestion
2. `W1-02` Classic new draft initial suggestion
3. `W1-03` Block first-save manual slug lock
4. `W1-04` Classic title-only update (unlocked plugin-owned slug, `regenerate_on_change=1`)
5. `W1-05` Block title update while locked (no overwrite)
6. `W1-06` Classic publish transition while locked (no overwrite)
7. `W1-09` Block published update while locked (no overwrite)
8. `W1-11` Quick Edit manual slug change (sets lock)
9. `W1-12` Quick Edit save without slug change while locked
10. `W1-13` Autosave no-op
11. `W1-14` Revision restore no-op for ownership
12. `W1-15` Non-Arabic title no generation
13. `W1-16` Method `none` no generation

## Execution Checklist

Mark each row as `PASS` / `FAIL` / `N/A` and include notes.

| ID    | Status | Notes |
| ----- | ------ | ----- |
| W1-01 | PASS   |       |
| W1-02 | PASS   |       |
| W1-03 | PASS   |       |
| W1-04 |        |       |
| W1-05 |        |       |
| W1-06 |        |       |
| W1-09 |        |       |
| W1-11 |        |       |
| W1-12 |        |       |
| W1-13 |        |       |
| W1-14 |        |       |
| W1-15 |        |       |
| W1-16 |        |       |

## Meta Assertions (must hold)

For all applicable scenarios, verify:

1. `_simula_slug_locked_manual` transitions to `true` only on detected manual edits.
2. `_simula_slug_locked_manual=true` prevents automatic slug replacement.
3. `_simula_last_generated_slug` is normalized and updated only by generation logic.
4. Manual slug edits are preserved in the same request.
5. Unlocked plugin-owned slugs may auto-refresh on title change only when `regenerate_on_change=1`.
6. `wp_unique_post_slug` path does not override manual-owned slugs.

## Week 2 Pass Criteria

Week 2 regression coverage passes only when:

1. All required scenarios are `PASS`.
2. No scenario shows automatic overwrite after lock is `true`.
3. Autosave/revision scenarios show zero ownership-state mutation.
