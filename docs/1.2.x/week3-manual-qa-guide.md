# Week 3 Manual QA Guide (P0 Editor UX + Explicit Actions)

Date: 2026-04-12
Scope: Manual QA for `TKT-P0-W3-01..04`
References:

- `docs/1.2.x/week3-regression-coverage.md`
- `docs/1.2.x/week1-test-matrix.md`
- `docs/1.2.x/week3-execution-plan.md`

## Goal

Verify the new Week 3 user-facing slug ownership flow in real WordPress editor sessions:

1. Explicit slug actions work correctly.
2. Divergence notices appear only when intended.
3. `Keep current slug` acknowledges the current divergence and suppresses the same warning until the plugin suggestion changes again.
4. Classic and Block editors behave the same for matching scenarios.

## Test Environment

Before starting, confirm:

1. The plugin is activated.
2. Slug method is set to a visible deterministic mode such as `wp_transliteration` or `arabizi`.
3. `regenerate_on_change` is enabled for cases that depend on plugin-owned auto-refresh.
4. You have one admin user and one lower-privilege user if capability testing is possible.
5. Permalink structure is enabled in WordPress.

Recommended content:

- Arabic title example 1: `مرحبا بالعالم`
- Arabic title example 2: `تجربة عنوان جديد`
- Manual slug example: `custom-editor-slug`

## General Setup

For each test:

1. Create or open a post.
2. Confirm the title contains Arabic text unless the case says otherwise.
3. Save once if needed so the initial plugin-generated slug exists.
4. Open the slug/permalink UI and record:
   - current slug
   - expected friendly slug suggestion
   - whether the notice appears
5. After each explicit action, confirm:
   - final slug
   - whether the notice disappears or remains
   - whether future normal saves preserve the expected ownership behavior

Current Week 3 UI note:

1. The divergence notice now exposes only:
   - `Keep current slug`
   - `Use friendly slug`
2. `Regenerate friendly slug` is no longer shown in the notice because it duplicates the primary decision flow.
3. If a separate regenerate control is not present in the UI you are testing, mark regenerate-specific scenarios as `DEFERRED`.

## Scenarios

### W1-07 Block first publish transition using `Use friendly slug`

1. Open Gutenberg for a post with Arabic title.
2. Change the slug manually so it differs from the plugin suggestion.
3. Confirm the divergence notice appears.
4. Click `Use friendly slug`.
5. Publish or update the post.

Expected:

1. Slug changes to the plugin-friendly suggestion.
2. Notice no longer shows divergence after reload.
3. A success notice is shown.
4. A later title change with plugin-owned slug behavior should act as unlocked behavior, not manual lock.

### W1-08 Classic first publish transition using `Keep current slug`

1. Open Classic editor for a post with Arabic title.
2. Change the slug manually so it differs from the plugin suggestion.
3. Confirm the divergence notice appears.
4. Click `Keep current slug`.
5. Publish or update the post.

Expected:

1. Current manual slug remains unchanged.
2. The action should not force a full page navigation.
3. A success notice is shown.
4. The yellow divergence warning disappears for the current suggestion.
5. Reloading the editor does not auto-replace the slug.
6. Changing the title later without explicit action still preserves the manual slug.
7. If the title changes enough to produce a different plugin suggestion later, the warning may reappear.

### W1-10 Classic explicit regenerate on existing post

Run this only if a dedicated regenerate control exists in the UI being tested.

1. Open an existing Classic-editor post with Arabic title.
2. Ensure the current slug differs from the newest plugin suggestion or title-derived result.
3. Trigger the dedicated regenerate control.
4. Save or reload the post screen.

Expected:

1. Slug updates to the newly generated friendly slug.
2. A success notice is shown.
3. Divergence notice is gone after reload if current slug now matches suggestion.

If no dedicated regenerate control exists, record `DEFERRED` and note that regenerate remains backend-supported but is not exposed in the current notice UI.

### W1-17 Block explicit regenerate security with invalid nonce

Run this only if a dedicated regenerate entry point is exposed in the UI being tested.

1. Open Gutenberg on a qualifying post.
2. Copy the regenerate action URL if visible.
3. Modify or remove the nonce parameter manually.
4. Open the tampered URL in the browser.

Expected:

1. WordPress rejects the request.
2. Slug remains unchanged.
3. No ownership/meta behavior changes are observed afterward.

If no regenerate entry point is exposed, record `DEFERRED`.

### W1-18 Classic explicit regenerate security with insufficient capability

Run this only if a dedicated regenerate entry point is exposed in the UI being tested.

1. Log in as a user who cannot edit the target post, if available.
2. Attempt to open a valid explicit action URL for regenerate.

Expected:

1. Request is denied.
2. Slug remains unchanged.
3. No ownership/meta behavior changes occur.

If lower-privilege testing is not available, or if no regenerate entry point is exposed, mark this scenario as deferred.

### W1-19 Block pre-publish notice visibility when divergence exists

1. Open Gutenberg for an Arabic-title post.
2. Manually set the slug so it differs from the plugin suggestion.
3. Do not click any explicit action yet.

Expected:

1. Divergence notice appears.
2. Notice includes:
   - `Keep current slug`
   - `Use friendly slug`

### W1-20 Block pre-publish notice suppression when slug matches suggestion

1. Open Gutenberg for an Arabic-title post.
2. Make sure current slug already matches the plugin suggestion.
3. Reload the editor if needed.

Expected:

1. Divergence notice does not appear.
2. No warning is shown for matching slug state.

### W1-21 Classic translation failure fallback during explicit action

1. Switch method to `translation`.
2. Use invalid provider credentials or otherwise force translation failure.
3. Open a Classic-editor post with Arabic title.
4. Trigger `Use friendly slug`.

Expected:

1. If no valid friendly slug can be generated, the current slug remains unchanged.
2. An error notice is shown.
3. Manual ownership is not silently cleared by a failed explicit action.

### W1-22 Block empty generation result during explicit action

1. Use a title/setup that causes generation to sanitize to an empty result, if reproducible.
2. Open Gutenberg and trigger `Use friendly slug`.

Expected:

1. Current slug remains unchanged.
2. Error notice is shown.
3. No silent overwrite occurs.

If this edge case cannot be reproduced in the UI, mark it deferred and note the setup gap.

## Cross-Editor Parity Checks

After the main scenarios, confirm:

1. The same divergence condition produces a notice in both editors.
2. `Keep current slug` preserves manual ownership in both editors.
3. `Use friendly slug` applies the plugin suggestion in both editors.
4. `Keep current slug` removes the current warning without forced navigation in both editors.
5. Classic notices do not appear on Gutenberg screens.
6. If regenerate is exposed elsewhere, verify it behaves as an explicit action and not as a passive save side effect.

## Evidence To Record

For each scenario, capture:

1. Editor type
2. Title used
3. Slug before action
4. Slug after action
5. Notice shown or suppressed
6. Result: `PASS`, `FAIL`, or `DEFERRED`
7. Short note if behavior differs from expectation

## Recording Results

Copy results into `docs/1.2.x/week3-regression-coverage.md`:

1. Update the execution checklist row status.
2. Add concise notes for failures or deferrals.
3. Keep manual QA evidence short and specific.
