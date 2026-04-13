# Week 6 Execution Plan (QA, Compatibility, Release Candidate)

Date: 2026-04-13
Target Week: May 18-May 24, 2026
Source documents:

- `docs/1.2.x/week-by-week-plan.md`
- `docs/1.2.x/week3-regression-coverage.md`
- `docs/1.2.x/week3-closeout-note.md`
- `docs/1.2.x/week4-closeout-note.md`
- `docs/1.2.x/week5-closeout-note.md`
- `docs/1.2.x/week5-manual-qa-guide.md`
- `docs/1.2.x/ISSUES.md`

## Objective

Complete release-candidate validation for the planned `1.2.4` release by closing remaining runtime QA gaps, verifying compatibility across supported WordPress/PHP environments, and preparing an internal RC package for sign-off.

## Week 6 Scope

1. Run full manual QA across post and page lifecycle flows.
2. Reconfirm Weeks 3-5 behavior together in real WordPress admin sessions.
3. Add or update automated verification where practical in this repository.
4. Validate compatibility on supported WordPress and PHP versions.
5. Prepare `1.2.4-rc1` and record release-sign-off evidence.

## Out of Scope

1. New feature work beyond release-blocking bug fixes discovered during QA.
2. WordPress.org final release tasks scheduled for Week 7.
3. Provider feature expansion or new translation methods.

## Week 6 Step-by-Step Tasks

### TKT-RC-W6-01

- Title: Freeze release-candidate scope and assemble verification baseline
- Target Week: Week 6
- Scope:
  - Reconfirm that Weeks 3, 4, and 5 are the intended RC feature set.
  - Collect the open manual-QA gaps still called out by prior docs.
  - Define the exact WordPress/PHP matrix to be used for RC validation.
- Inputs:
  - `docs/1.2.x/week3-regression-coverage.md`
  - `docs/1.2.x/week4-manual-qa-guide.md`
  - `docs/1.2.x/week5-manual-qa-guide.md`
  - current plugin header/version state
- Acceptance Criteria:
  - A single Week 6 checklist exists for all remaining RC checks.
  - Deferred or runtime-only scenarios from prior weeks are explicitly listed.
  - Supported environment matrix is written down before testing starts.

### TKT-RC-W6-02

- Title: Execute end-to-end slug lifecycle QA for posts and pages
- Target Week: Week 6
- Scope:
  - Run manual QA for both `post` and `page` content types.
  - Cover: new draft, first save, title edits, manual slug edits, publish, update, and reopen/edit flows.
  - Validate behavior in both Block and Classic editors where applicable.
- Scenario minimum:
  - initial friendly suggestion on first save
  - manual slug lock on direct slug edit
  - unlocked auto-refresh on title change when plugin owns slug
  - locked slug preservation on later title/content updates
  - publish transition with divergence notice handling
  - update flow after publish
- References:
  - `W1-01..10`, `W1-15`, `W1-16` from `docs/1.2.x/week1-test-matrix.md`
- Acceptance Criteria:
  - No post/page lifecycle flow auto-overwrites a manual slug.
  - Divergence notice behavior matches the Week 3 contract.
  - Both post types behave the same for the same ownership state.

### TKT-RC-W6-03

- Title: Close remaining deferred Week 3 and Week 2 runtime QA gaps
- Target Week: Week 6
- Scope:
  - Re-run Quick Edit ownership checks.
  - Reconfirm autosave and revision-restore safety if reproducible in runtime testing.
  - Validate translation-failure and empty-generation fallback behavior.
  - Record whether regenerate-specific coverage remains deferred or is testable in the current UI.
- Required cases:
  - `W1-11` Quick Edit manual slug change
  - `W1-12` Quick Edit save without slug change while locked
  - `W1-14` Revision restore no-op for ownership
  - `W1-21` Translation failure fallback
  - `W1-22` Empty generation result
- Acceptance Criteria:
  - All remaining P0 runtime gaps are marked `PASS`, `FAIL`, or justified `DEFERRED`.
  - Any failed case is classified as release-blocking or non-blocking with rationale.

### TKT-RC-W6-04

- Title: Reconfirm provider/settings and security behavior in WordPress admin
- Target Week: Week 6
- Scope:
  - Re-run the critical Week 4 provider scenarios in admin.
  - Re-run the critical Week 5 masked-key and hidden-method scenarios in admin.
  - Confirm existing stored values survive provider switching and safe blank-key saves.
- Scenario minimum:
  - selected-provider-only validation
  - provider field switching
  - mock provider success/failure cases
  - masked API key rendering
  - blank API key preserving stored secret
  - replacement API key save
  - unsupported method normalization after save
- Acceptance Criteria:
  - Provider settings remain usable in multi-provider setups.
  - Security cleanup works in real admin sessions, not just static review.
  - No regression appears when Week 4 and Week 5 changes are exercised together.

### TKT-RC-W6-05

- Title: Add/update automated verification for RC-critical behavior
- Target Week: Week 6
- Scope:
  - Audit what automated coverage exists today in this repository.
  - Add lightweight automated checks where feasible, prioritizing pure PHP logic and sanitizer behavior.
  - At minimum, keep syntax/lint verification in the Week 6 artifact.
- Priority targets:
  - method normalization
  - selected-provider validation path
  - masked API key preservation flow
  - slug ownership helpers or deterministic decision logic that can be tested outside full WordPress browser QA
- Acceptance Criteria:
  - Automated verification is improved where practical, or the constraint is documented clearly.
  - RC sign-off includes explicit workspace-level verification commands and results.

### TKT-RC-W6-06

- Title: Validate supported WordPress and PHP compatibility
- Target Week: Week 6
- Scope:
  - Install or run the plugin on the supported WordPress versions used for release validation.
  - Execute smoke tests on each supported PHP version.
  - Verify activation, settings save, post save, and explicit slug actions do not produce fatals/warnings.
- Minimum smoke checks per environment:
  - plugin activation
  - settings page loads
  - save settings with selected provider
  - create/update Arabic post
  - manual slug edit remains preserved
- Acceptance Criteria:
  - No fatal errors on supported WordPress/PHP combinations.
  - Any version-specific warning or incompatibility is documented and triaged before RC approval.

### TKT-RC-W6-07

- Title: Build and document `1.2.4-rc1` candidate for internal sign-off
- Target Week: Week 6
- Scope:
  - Confirm plugin versioning strategy for RC packaging.
  - Prepare the RC build/package from the validated workspace state.
  - Record verification evidence, known issues, and go/no-go recommendation.
- Deliverables:
  - `1.2.4-rc1` package or tagged build artifact
  - Week 6 regression/QA status document
  - internal sign-off note with blockers/non-blockers
- Acceptance Criteria:
  - RC artifact is reproducible from the documented workspace state.
  - Sign-off recommendation clearly states whether Week 7 release work may start.

## Recommended Execution Order

1. `TKT-RC-W6-01` scope freeze and baseline
2. `TKT-RC-W6-02` post/page lifecycle QA
3. `TKT-RC-W6-03` deferred runtime gap closure
4. `TKT-RC-W6-04` provider/security admin reconfirmation
5. `TKT-RC-W6-05` automated verification updates
6. `TKT-RC-W6-06` compatibility matrix execution
7. `TKT-RC-W6-07` RC build and sign-off

## Week 6 Exit Criteria

Week 6 is complete only when:

1. Manual slug ownership remains correct across post/page lifecycle flows.
2. Week 3 deferred runtime checks are resolved or explicitly accepted as deferred.
3. Week 4 and Week 5 admin behavior is reconfirmed in live WordPress testing.
4. Supported WordPress/PHP environments show no release-blocking fatals.
5. An internal `1.2.4-rc1` artifact and sign-off record exist.
