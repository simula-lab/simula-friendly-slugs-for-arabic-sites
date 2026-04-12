# Week 3 Execution Plan (P0 Editor UX + Explicit Actions)

Date: 2026-04-12
Target Week: Apr 27-May 3, 2026
Depends on: `docs/week2-regression-coverage.md`
Source documents:

- `docs/week-by-week-plan.md`
- `docs/week1-implementation-tickets.md`
- `docs/week1-test-matrix.md`
- `docs/ISSUES.md`

## Objective

Complete the P0 user-facing slug ownership flow by adding explicit friendly-slug actions, divergence notices, and cross-editor validation without weakening the Week 2 ownership protections.

## Scope Lock

### In Scope

1. Explicit actions for:
   - `Generate/Regenerate friendly slug`
   - `Use friendly slug`
   - `Keep current slug`
2. Divergence detection between current slug and plugin suggestion.
3. Non-blocking notice shown only when divergence conditions are met.
4. Block editor and Classic editor behavior parity.
5. Regression coverage for Week 3 P0 scenarios.

### Out of Scope

1. P1 provider consistency fixes.
2. P2 security and cleanup work.
3. Any change that weakens Week 2 slug ownership guarantees.
4. Release/versioning tasks scheduled for later weeks.

## Preconditions

Before starting Week 3 implementation, confirm Week 2 remains the baseline:

1. Manual lock prevents automatic slug replacement.
2. `_simula_last_generated_slug` is updated only through generation logic.
3. Uniqueness-stage generation does not bypass manual lock.
4. Autosave/revision/auto-draft paths do not mutate ownership state.

## Step-by-Step Tasks

### 1. Reconfirm behavioral contract

Review the approved Week 3 tickets:

- `TKT-P0-W3-01`
- `TKT-P0-W3-02`
- `TKT-P0-W3-03`
- `TKT-P0-W3-04`

Implementation must continue to respect the P0 rules defined in `docs/ISSUES.md` and `docs/week1-slug-ownership-spec.md`.

### 2. Define the explicit action contract

For each action, document:

1. Trigger source.
2. Required nonce and capability checks.
3. Slug mutation behavior.
4. `_simula_slug_locked_manual` transition.
5. `_simula_last_generated_slug` update rules.
6. Failure behavior when generation is invalid, empty, or denied.

Required actions:

- `regenerate_friendly_slug`
- `use_friendly_slug`
- `keep_current_slug`

### 3. Choose editor integration points

Decide the implementation path for each editor context:

1. Classic editor:
   - admin notice/action links or equivalent admin hooks
2. Block editor:
   - editor notice, plugin panel, sidebar control, or equivalent UX surface
3. Quick Edit:
   - no new Week 3 UI required unless needed, but Week 2 ownership behavior must remain intact

The chosen approach must produce equivalent state outcomes across editors.

### 4. Implement backend explicit-action handlers

Build the server-side handler(s) that execute explicit slug actions.

Acceptance target:

1. Invalid nonce causes no slug or meta mutation.
2. Missing capability causes no slug or meta mutation.
3. Successful regenerate/use-friendly applies the new friendly slug.
4. Successful keep-current preserves the current slug.

### 5. Implement divergence detection

Add deterministic logic that compares:

1. current slug
2. plugin suggestion
3. current ownership state

Notice should not appear when:

1. current slug already matches the plugin suggestion
2. method is `none`
3. title is non-Arabic
4. generated suggestion is empty or invalid

### 6. Implement the notice UX

Add a non-blocking notice for divergence cases.

The notice must expose:

1. `Keep current slug`
2. `Use friendly slug`

Optional regenerate control may be separate, but it must remain explicit and user-triggered.

### 7. Implement `Keep current slug`

This action must:

1. preserve the current slug
2. set or keep `_simula_slug_locked_manual=true`
3. avoid any automatic replacement side effect

### 8. Implement `Use friendly slug`

This action must:

1. apply the plugin-generated slug
2. set `_simula_slug_locked_manual=false`
3. update `_simula_last_generated_slug`

### 9. Implement `Generate/Regenerate friendly slug`

This action must:

1. be explicit and user-triggered
2. compute and apply a fresh plugin slug
3. clear manual lock only on success
4. leave slug and lock unchanged on failure

### 10. Validate editor parity

Confirm that Block and Classic editors produce equivalent behavior for:

1. divergence notice visibility
2. keep-current action
3. use-friendly action
4. explicit regenerate action
5. locked-slug protection during normal saves

### 11. Execute Week 3 regression coverage

Run and record the Week 3 scenarios mapped from `docs/week1-test-matrix.md`:

1. `W1-07` First publish transition using `Use friendly slug`
2. `W1-08` First publish transition using `Keep current slug`
3. `W1-10` Explicit regenerate on existing post
4. `W1-17` Explicit regenerate security with invalid nonce
5. `W1-18` Explicit regenerate security with insufficient capability
6. `W1-19` Pre-publish notice visibility when divergence exists
7. `W1-20` Pre-publish notice suppression when slug matches suggestion
8. `W1-21` Translation failure fallback during explicit action
9. `W1-22` Empty generation result during explicit action

### 12. Add Week 3 verification artifact

Create a Week 3 regression/QA tracking document similar to `docs/week2-regression-coverage.md` with:

1. scenario list
2. pass/fail status
3. notes for unresolved gaps

## Completion Gate

Week 3 is complete only when all of the following are true:

1. Explicit regenerate/use-friendly/keep-current actions work with proper security checks.
2. Divergence notice appears only under the intended conditions.
3. Manual ownership is preserved unless the user explicitly chooses a plugin action.
4. Block and Classic editors are behaviorally equivalent for the mapped Week 3 cases.
5. The Week 3 regression checklist is documented and all required scenarios are passing or explicitly deferred with reason.
