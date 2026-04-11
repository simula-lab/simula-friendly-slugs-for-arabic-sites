# Week 2 Scope Lock (P0 Core Save Logic)

Date: 2026-04-12
Branch: `week2-p0-core-save-logic`
Source: `docs/week1-implementation-tickets.md`

## In Scope (Week 2 only)

1. `TKT-P0-W2-01` - Add slug ownership metadata contract and helpers.
2. `TKT-P0-W2-02` - Implement ownership-first save decision engine in `maybe_generate_slug_on_save`.
3. `TKT-P0-W2-03` - Add manual-edit detection and lock transition logic.
4. `TKT-P0-W2-04` - Align uniqueness filter behavior with ownership lock.

## Out of Scope (deferred)

- Week 3 editor UX and explicit action endpoints (`TKT-P0-W3-01..04`).
- P1 provider consistency fixes.
- P2 security and cleanup work.

## Completion Gate

Week 2 is complete only when all four Week 2 tickets above meet acceptance criteria and mapped tests in `docs/week1-test-matrix.md` pass.
