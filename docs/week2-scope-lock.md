# Week 2 Scope Lock (P0 Core Save Logic)

Date: 2026-04-12
Branch: `week2-p0-core-save-logic`
Source: `docs/week1-implementation-tickets.md`

## In Scope (Week 2 only)

1. `TKT-P0-W2-01` - Add slug ownership metadata contract and helpers.
2. `TKT-P0-W2-02` - Implement ownership-first save decision engine in `maybe_generate_slug_on_save`.
3. `TKT-P0-W2-03` - Add manual-edit detection and lock transition logic.
4. `TKT-P0-W2-04` - Align uniqueness filter behavior with ownership lock.

## Behavior Constraints

1. New posts may receive an initial friendly slug suggestion when eligible.
2. Manual lock always blocks automatic slug replacement.
3. Unlocked posts may auto-refresh slug on title change only when:
   - `regenerate_on_change=1`
   - the current slug still matches `_simula_last_generated_slug`
4. Autosave, revision restore, and auto-draft contexts must not mutate slug or ownership state.
5. Week 2 does not include explicit regenerate UI or pre-publish notice actions.

## Execution Order

1. Add centralized helpers for `_simula_slug_locked_manual` and `_simula_last_generated_slug`, including normalization rules.
2. Refactor `maybe_generate_slug_on_save(...)` around context guards and ownership-first branching.
3. Add manual-edit detection using incoming slug, persisted slug, and last generated slug.
4. Reintroduce `regenerate_on_change` only for unlocked plugin-owned slugs.
5. Align `wp_unique_post_slug` behavior so uniqueness-time generation cannot bypass manual lock.
6. Run the required Week 2 regression set in `docs/week2-regression-coverage.md`, with `W1-04` treated as a required auto-refresh case.

## Out of Scope (deferred)

- Week 3 editor UX and explicit action endpoints (`TKT-P0-W3-01..04`).
- P1 provider consistency fixes.
- P2 security and cleanup work.

## Completion Gate

Week 2 is complete only when all four Week 2 tickets above meet acceptance criteria and mapped tests in `docs/week1-test-matrix.md` pass.
