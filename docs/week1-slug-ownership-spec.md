# Week 1 Spec: Slug Ownership and User Intent (P0)

Status: Approved (Week 1)
Scope: P0 only (manual slug ownership)
Source of truth: `docs/ISSUES.md` (Issue #5, Acceptance Criteria, Phase 1 and Phase 2)
Approval date: 2026-04-12

## Goal

Protect editorial intent so manually chosen slugs are never overwritten automatically, while still providing friendly slug generation for Arabic titles.

## Required P0 Behavior

### 1) Initial auto-suggestion (new posts/pages)

- For new posts/pages, plugin-generated friendly slug is an initial suggestion.
- Auto-generation is allowed when slug is empty/default and no manual lock exists.
- If `regenerate_on_change=1`, unlocked posts may auto-refresh slug on title change only while the current slug still matches `_simula_last_generated_slug`.
- This must not be treated as a user-forced replacement.

### 2) Manual lock after user slug edit

- If user manually edits the slug (`post_name`), mark post as manual-owned.
- Persist ownership in post meta: `_simula_slug_locked_manual = true`.
- Once manual lock is active, plugin must stop auto-overwriting slug on normal saves.

### 3) Explicit regenerate action

- Regeneration must be intentional and user-triggered (e.g. `Generate friendly slug` / `Regenerate plugin slug`).
- Regenerate action may replace current slug with plugin suggestion.
- Manual lock must only be cleared/changed when user explicitly confirms regeneration.
- Passive events (autosave, routine update, status transition) must not clear manual lock.

### 4) Pre-publish difference notice

- Before publish/update, if current slug differs from plugin suggestion, show a clear non-blocking notice.
- Notice must provide one-click actions:
  - `Keep current slug`
  - `Use friendly slug`
- `Keep current slug` preserves/sets manual lock.
- `Use friendly slug` applies generated slug and updates plugin tracking meta.

## Post Meta Contract (P0)

- `_simula_slug_locked_manual` (bool): whether user selected/kept manual slug ownership.
- `_simula_last_generated_slug` (string): latest plugin-generated suggestion used for comparison and UX actions.

## State Model (Task 2)

### `_simula_slug_locked_manual`

Default:

- Missing meta or empty value is treated as `false` (unlocked).

Allowed values:

- `false`: plugin may auto-suggest in allowed states.
- `true`: manual ownership is active; no automatic replacement allowed.

Who can change it:

- Plugin save logic when manual edit intent is detected.
- Explicit user actions from plugin UI (`Keep current slug`, `Use friendly slug`, `Regenerate` confirm flow).
- Not changed by autosave/revision background activity.

When it changes:

- Set to `true` when user explicitly edits slug and save flow confirms manual intent.
- Kept `true` on normal updates, publish transitions, and title-only edits.
- May be set to `false` only by explicit regenerate/use-friendly action that user triggers.

### `_simula_last_generated_slug`

Default:

- Missing meta means no plugin suggestion has been recorded yet.

Allowed values:

- Empty/missing for not-yet-generated state.
- Non-empty sanitized slug string for latest plugin-generated suggestion.

Who can change it:

- Plugin generation logic only (initial suggestion, regenerate action, use-friendly action).
- Not written by unrelated settings operations or passive editor events.

When it changes:

- Set on first successful plugin slug generation for a post.
- Updated whenever plugin computes and applies a newer friendly slug.
- Can be updated when plugin computes a new suggestion for comparison, even if user keeps manual slug, as long as current slug is not force-overwritten.

### Combined Ownership States

State A: `locked=false`, `last_generated` missing/empty

- Meaning: no prior plugin generation recorded.
- Expected behavior: plugin may generate initial suggestion when eligible.

State B: `locked=false`, `last_generated=<slug>`

- Meaning: plugin suggestion exists; post is not manually locked.
- Expected behavior: plugin may apply/update friendly slug only under allowed auto-generation rules, including `regenerate_on_change` refreshes while the current slug remains plugin-owned.

State C: `locked=true`, `last_generated=<slug|empty>`

- Meaning: user intent is manual ownership.
- Expected behavior: plugin never auto-overwrites slug; only explicit user-triggered action can replace slug.

### Transition Rules

1. New post with Arabic title and eligible save:

- `A -> B` by generating initial friendly slug.

2. User manually edits slug and saves:

- `A/B -> C` by setting `locked=true`.

3. User chooses `Keep current slug` from notice/action:

- `A/B/C -> C` (ensure lock remains true).

4. User chooses `Use friendly slug` or confirms regenerate:

- `C -> B` or `B -> B` by applying generated slug and setting `locked=false`.

5. Autosave/revision background events:

- No state transition.

## Current Plugin Flow Map (Task 3)

### Slug-related entry points

1. Hook registration:

- `wp_unique_post_slug` -> `generate_friendly_slug(...)`
- `wp_insert_post_data` -> `maybe_generate_slug_on_save(...)`
- Source: `simula-friendly-slugs-for-arabic-sites.php:355-357`

2. Uniqueness-time override path:

- `generate_friendly_slug(...)` can replace slug candidate using selected method when title is Arabic and method is not `none`.
- Source: `simula-friendly-slugs-for-arabic-sites.php:701-723`

3. Pre-insert save mutation path:

- `maybe_generate_slug_on_save(...)` mutates `$data['post_name']` before insert/update.
- Guard conditions:
  - skip `auto-draft`
  - skip empty title
  - skip non-Arabic title
  - skip when `regenerate_on_change` is off and `post_name` already exists
  - skip when `regenerate_on_change` is on but title did not change
- Source: `simula-friendly-slugs-for-arabic-sites.php:742-795`

4. Method configuration that drives conversion:

- Method options UI (`none`, `wp_transliteration`, `arabizi`, `hash`, `translation`)
- `regenerate_on_change` checkbox affects save behavior.
- Sources:
  - `simula-friendly-slugs-for-arabic-sites.php:507-517`
  - `simula-friendly-slugs-for-arabic-sites.php:529-537`

5. Converter pipeline used by both slug paths:

- `convert_wp_transliteration(...)`
- `convert_custom_transliteration(...)` (present in code but not in method UI)
- `convert_arabizi(...)`
- `convert_hash(...)`
- `convert_translation(...)`
- Sources:
  - `simula-friendly-slugs-for-arabic-sites.php:725-732`
  - `simula-friendly-slugs-for-arabic-sites.php:801-886`

### Observed behavior vs P0 target

- No slug ownership post meta currently exists:
  - `_simula_slug_locked_manual` missing
  - `_simula_last_generated_slug` missing
- No explicit regenerate action exists in editor flow.
- No pre-publish notice/actions (`Keep current slug` / `Use friendly slug`) currently exist.
- Current mutation logic is based on `post_name`/title change checks, not explicit manual ownership tracking.

### Task 3 output

Current flow is centered on two save-time filters (`wp_insert_post_data` and `wp_unique_post_slug`) plus option-driven converter selection.
Week 2 implementation must anchor the P0 ownership model in these two entry points without relying on implicit slug-presence checks alone.

## Save-Context Decision Rules (Task 4)

Decision priority (apply top to bottom):

1. If event is autosave/revision/auto-draft -> never mutate slug ownership state.
2. If manual lock is `true` -> never auto-replace slug.
3. If explicit user action requests regenerate/use-friendly -> allow replacement and update tracking meta.
4. Otherwise, allow initial-suggestion behavior for eligible new/default slug states, plus unlocked auto-refresh when `regenerate_on_change=1` and the current slug still matches `_simula_last_generated_slug`.

### Context A: New draft creation

Conditions:

- New post (`ID` absent or no persisted slug yet)
- Arabic title
- method != `none`
- manual lock is `false`

Rule:

- Generate initial friendly slug suggestion.
- Persist `_simula_last_generated_slug`.
- Keep `_simula_slug_locked_manual=false`.

### Context B: Existing draft update (normal editor save)

Conditions:

- Existing post ID
- Non-autosave
- Non-revision restore

Rule:

- If manual lock is `true`: keep current slug unchanged.
- If manual lock is `false` and no explicit action:
  - do not force-replace existing non-default manual slug;
  - may auto-refresh slug on title change when `regenerate_on_change=1` and current slug still equals `_simula_last_generated_slug`;
  - otherwise may refresh `_simula_last_generated_slug` for comparison only.
- If explicit regenerate/use-friendly action is present:
  - replace slug with generated slug;
  - set lock to `false`;
  - update `_simula_last_generated_slug`.

### Context C: First publish transition (`draft/pending/private -> publish`)

Rule:

- Follow the same ownership rules as draft update; publishing itself is not permission to override manual slug.
- If notice action selected:
  - `Keep current slug` -> set/keep lock `true`, no slug replace.
  - `Use friendly slug` -> apply friendly slug, set lock `false`, update last-generated.

### Context D: Published post update (republish)

Rule:

- Never auto-overwrite a manual-owned slug solely because post is updated.
- Title change alone must not replace slug when manual lock is `true`.
- If manual lock is `false` and `regenerate_on_change=1`, plugin-owned slugs may still auto-refresh on title change.
- Explicit regenerate/use-friendly remains the only valid replacement path.

### Context E: Quick Edit / Inline Edit

Rule:

- Treat as normal explicit user edit context.
- If inline slug field is changed by user -> set manual lock `true`.
- Without explicit regenerate signal, plugin must not replace slug during inline save.

### Context F: Autosave

Rule:

- No slug replacement.
- No ownership-meta transitions.
- Ignore regenerate/use-friendly actions unless they come from explicit non-autosave save request.

### Context G: Revision restore

Rule:

- Restoring revision must not silently clear manual lock.
- Do not auto-regenerate slug during restore event.
- If restored content changes title, slug replacement still requires explicit user action afterward.

### Context H: Non-Arabic title

Rule:

- Plugin does not generate/replace slug.
- Existing lock state remains unchanged unless user explicitly changes slug (manual ownership still applies).

### Task 4 output

Slug mutation permissions are now context-driven and ownership-first:

- publish/update status transitions do not grant overwrite permission,
- only explicit user regeneration actions can intentionally replace an owned slug.

## Manual-Edit Detection Algorithm (Task 5)

### Inputs used in detection

- `incoming_slug`:
  - slug submitted in current save request (`post_name` from request payload / `$postarr` / `$data`)
- `current_db_slug`:
  - existing persisted slug before save (`WP_Post->post_name`) for existing posts
- `last_generated_slug`:
  - `_simula_last_generated_slug` meta (latest plugin suggestion)
- `manual_lock`:
  - `_simula_slug_locked_manual` meta (bool)
- `explicit_action`:
  - one of `none`, `keep_current_slug`, `use_friendly_slug`, `regenerate_friendly_slug`

### Pre-conditions and exclusions

Detection does not run (no lock transition) when:

- autosave request
- revision save/restore event
- auto-draft
- no post context available

### Normalization step

Before comparisons:

- normalize each slug candidate with `sanitize_title(..., '', 'save')`
- treat missing values as empty string

Comparison values:

- `incoming_norm`
- `current_norm`
- `last_generated_norm`

### Decision algorithm

1. Honor explicit action first:

- If `explicit_action=use_friendly_slug` or `regenerate_friendly_slug`:
  - do not mark manual edit;
  - set `manual_lock=false` after applying plugin slug.
- If `explicit_action=keep_current_slug`:
  - set `manual_lock=true`;
  - stop further manual-edit inference.

2. If lock already true and no explicit regenerate/use action:

- keep `manual_lock=true`;
- skip overwrite and skip lock re-evaluation.

3. Determine whether user provided an explicit slug change in this save:

- `has_incoming_slug = incoming_norm != ''`
- `changed_vs_db = (current_norm != '' && incoming_norm != current_norm)`
- `changed_vs_last_generated = (last_generated_norm != '' && incoming_norm != last_generated_norm)`

User manual edit is detected when either condition is true:

- `has_incoming_slug && changed_vs_db`
- `has_incoming_slug && last_generated_norm != '' && changed_vs_last_generated`

4. Apply lock on manual intent:

- If manual edit detected:
  - set `manual_lock=true`
  - keep `incoming_slug` as chosen slug
  - do not auto-replace in same request

5. No manual edit detected:

- Keep existing lock state (typically false for unlocked flow).
- Continue normal eligibility checks for initial suggestion or unlocked `regenerate_on_change` refresh behavior.

### Edge-case handling

- First save where `current_db_slug` is empty:
  - if user typed a custom slug before first save and `incoming_slug` is non-empty and differs from plugin suggestion, treat as manual edit and lock.
- Title-only changes with unchanged slug:
  - never treated as manual edit.
- Slug equals plugin suggestion:
  - not a manual edit; remains/unlocks according to explicit action path.
- Quick Edit slug change:
  - treated as manual edit and locks.

### Task 5 output

Manual intent is inferred from explicit user slug divergence (`incoming` vs persisted/plugin-generated references), with explicit UI actions taking priority over heuristic inference.

## Explicit Regenerate Flow (Task 6)

### User-facing triggers

Provide explicit regenerate/use actions from editor contexts:

- `Generate friendly slug` (when no prior generated slug is tracked)
- `Regenerate friendly slug` (when prior generated slug exists)
- `Use friendly slug` (from pre-publish notice when current slug differs)

These are intentional actions; they are the only non-manual-lock path allowed to replace a manually owned slug.

### Request contract

Each explicit action request must include:

- `post_id`
- `action_type` (`regenerate_friendly_slug` or `use_friendly_slug`)
- `_wpnonce` tied to plugin action and post scope
- optional editor context hint (`classic`, `block`, `quick_edit`) for UX response routing

### Security checks (must pass before mutation)

1. Nonce validation:

- Verify nonce with `check_admin_referer` / `wp_verify_nonce` against action-specific token.

2. Capability validation:

- Require `current_user_can( 'edit_post', $post_id )`.

3. Post validity:

- Post exists and is editable post type supported by plugin behavior.

4. Intent validation:

- `action_type` must be in allowed action list.

If any check fails:

- do not mutate slug or ownership meta;
- return standard admin error/notices path.

### Regenerate execution steps

1. Load post and plugin options (`method`, translation settings).
2. If method is `none` or title is non-Arabic:

- do not replace current slug;
- optionally refresh notice state with explanation.

3. Compute plugin suggestion using active converter.
4. Sanitize suggestion and ensure WP uniqueness handling.
5. Apply slug to post.
6. Persist `_simula_last_generated_slug` with applied value.
7. Set `_simula_slug_locked_manual=false` only because this was explicit user action.
8. Return success notice/UI refresh.

### Lock reset semantics

- Lock reset is allowed only inside successful explicit action path.
- Failed regenerate/use attempts must leave lock unchanged.
- Passive saves, autosave, and publish transitions must never clear lock.
- Title changes may auto-refresh slug only while the post remains unlocked and plugin-owned.

### Failure and fallback behavior

- If translation provider/credentials are invalid at generation time:
  - follow existing plugin fallback behavior (hash/text fallback as implemented),
  - still treat operation as explicit if user initiated it.
- If generated slug is empty after sanitization:
  - keep current slug unchanged,
  - keep lock unchanged,
  - surface actionable error notice.

### Auditability and traceability

On successful explicit regenerate/use action:

- update `_simula_last_generated_slug`,
- optionally set admin notice flag for one-time success messaging.

No hidden lock transitions are allowed outside explicit action or manual edit detection paths defined in Task 5.

### Task 6 output

Regeneration is now a secured, explicit workflow with validated intent and capability checks, and with lock reset permitted only on confirmed user-triggered replacement.

## Pre-Publish Notice Specification (Task 7)

### When the notice appears

Show notice in editor (classic and block) when all are true:

- post is editable by current user
- plugin method is not `none`
- title is Arabic-eligible for plugin conversion
- a plugin suggestion is available (freshly computed or `_simula_last_generated_slug`)
- current post slug differs from plugin suggestion

Do not show notice when:

- autosave/revision context
- suggestion cannot be computed
- slug already equals plugin suggestion

### Notice intent and tone

Notice is non-blocking and informational:

- explains current slug differs from plugin friendly suggestion
- asks user to explicitly choose behavior before publish/update

Suggested message contract:

- Primary text: current slug differs from suggested friendly slug.
- Secondary text: keeping current slug preserves manual ownership; using friendly slug replaces it intentionally.

### Actions in notice

1. `Keep current slug`

- Effect:
  - keep current slug unchanged
  - set/keep `_simula_slug_locked_manual=true`
  - keep/update `_simula_last_generated_slug` for future comparisons
- Should dismiss current conflict notice until next meaningful divergence.

2. `Use friendly slug`

- Effect:
  - apply suggested friendly slug immediately
  - set `_simula_slug_locked_manual=false`
  - set `_simula_last_generated_slug` to applied value
- Show confirmation message after successful apply.

### Action transport and validation

- Actions are explicit requests and must include post-scoped nonce + `edit_post` capability checks.
- Failed validation must not mutate slug/meta and should show error notice.

### Display timing and persistence

- Evaluate notice state on editor load and on title/slug changes that alter divergence.
- If user chooses `Keep current slug`, do not keep re-prompting in the same unchanged state.
- If title later changes and suggestion diverges again, notice may reappear to request a new explicit decision.

### Accessibility and UX constraints

- Action controls must be keyboard reachable and labeled clearly.
- Notice must not block publish button flow, but actions should be one click.
- Avoid destructive wording; emphasize reversibility via explicit regenerate action.

### Task 7 output

Pre-publish behavior is now defined as an explicit choice point whenever current slug diverges from plugin suggestion, with deterministic state transitions for both actions.

## Editor Coverage Expectations (Task 8)

### Coverage objective

`P0` slug-ownership behavior must be functionally consistent across:

- Block Editor (Gutenberg)
- Classic Editor

Consistency requirement:

- same ownership rules
- same lock transitions
- same explicit action semantics
- no silent overwrite in either editor

### Block Editor expectations

Required behavior:

- Detect slug divergence while editing title/slug in Gutenberg context.
- Surface non-blocking notice with actions:
  - `Keep current slug`
  - `Use friendly slug`
- Support explicit regenerate/use action without requiring page reload where feasible.
- Ensure autosave cycles do not trigger lock transitions or silent slug replacement.

Implementation constraints:

- Editor state may update through REST-driven autosaves and async save flows.
- Must avoid race conditions between client-side slug edits and server save filters.
- Action endpoints must enforce nonce/capability server-side regardless of client context.

### Classic Editor expectations

Required behavior:

- Detect divergence during standard edit screen lifecycle.
- Show non-blocking notice with same two actions and same outcomes.
- Support explicit regenerate/use action via standard admin request flow.
- Respect manual lock during normal save/update and publish transitions.

Implementation constraints:

- Slug edits can happen via permalink editor UI and form submit payloads.
- Quick Edit/Inline Edit path must still apply manual-edit detection rules.
- Action links/forms must include valid nonce and post scope.

### Cross-editor parity rules

- Manual slug edit in either editor sets manual lock.
- Explicit `Use friendly slug`/`Regenerate` in either editor clears lock only after successful replace.
- `Keep current slug` in either editor sets/keeps lock.
- `_simula_last_generated_slug` semantics are identical in both editors.
- Autosave/revision exclusions are identical in both editors.

### Non-goals in Week 1

- Perfectly identical visual UI components between editors.
- Advanced real-time preview behavior beyond required divergence notice/action workflow.

### Task 8 output

Editor support boundaries are now explicit: behavioral parity is mandatory across Gutenberg and Classic flows, even if implementation mechanics differ.

## Regression Risks and Mitigations (Task 11)

### Risk A: Autosave/revision side effects

- Risk:
  - background autosave or revision restore could trigger unintended slug/meta transitions.
- Mitigation:
  - hard exclusion guards in save decision engine for autosave/revision contexts.
  - explicit tests for no-op behavior in autosave/revision scenarios.
- Verification:
  - `W1-13`, `W1-14`.

### Risk B: Backward compatibility with existing posts

- Risk:
  - legacy posts without ownership meta may be interpreted incorrectly and get overwritten.
- Mitigation:
  - default missing meta to `lock=false`, `last_generated` empty.
  - treat existing non-empty slugs conservatively; do not force overwrite without eligibility + explicit action rules.
- Verification:
  - `W1-04`, `W1-06`, `W1-09`, `W1-16`.

### Risk C: Non-Arabic title regressions

- Risk:
  - new ownership logic accidentally mutates non-Arabic post slugs.
- Mitigation:
  - retain Arabic-title eligibility guard before generation.
  - no slug generation path for non-Arabic titles.
- Verification:
  - `W1-15`.

### Task 11 output

Top regression risks are now explicitly documented with mitigation and test coverage mapping.

## Design Review Record (Task 12)

Review date: 2026-04-12  
Review scope: Week 1 `P0` design package  
Artifacts reviewed:

- `docs/week1-slug-ownership-spec.md`
- `docs/week1-test-matrix.md`
- `docs/week1-implementation-tickets.md`

Decisions:

1. Approved:

- Ownership-first model (`_simula_slug_locked_manual`, `_simula_last_generated_slug`) is accepted as the canonical behavior.
- Explicit action requirement for slug replacement after manual ownership is accepted.
- Cross-editor parity requirement (Block + Classic + Quick Edit) is accepted.

2. Rejected:

- Any publish/update-time implicit overwrite behavior without explicit user intent.

3. Open:

- Exact UX component implementation choice in block editor (notice placement pattern) to be finalized in Week 3 implementation, without changing behavior contract.

### Task 12 output

Design decisions are recorded with approved/rejected/open status and tied to Week 2/3 execution scope.

## Scope Freeze Policy (Task 13)

Week 1 scope is frozen as of 2026-04-12.

Rules:

- No net-new behavior changes to the approved `P0` contract after freeze.
- Only allowed changes before Week 2 start:
  - clarity edits,
  - typo fixes,
  - mapping corrections that do not alter behavior.
- Behavior changes after freeze require documented `P0 blocker` justification and explicit re-approval note in this spec.

### Task 13 output

Week 1 scope is formally locked, with a narrow exception path for true `P0` blockers.

## Week 1 Final Outputs (Task 14)

Delivery set:

1. Approved technical design:

- `docs/week1-slug-ownership-spec.md` (this file)

2. Implementation-ready ticket backlog:

- `docs/week1-implementation-tickets.md`

3. Signed-off test matrix:

- `docs/week1-test-matrix.md`

Sign-off status:

- Technical design: Approved
- Ticket backlog: Approved
- Test matrix: Approved

### Task 14 output

All required Week 1 deliverables are present and marked approved.

## Week 2 Start Gate (Task 15)

Gate criteria:

1. Approved technical design exists.
2. Approved implementation tickets exist with acceptance criteria.
3. Signed-off test matrix exists and is mapped to tickets.
4. No unresolved ambiguity that blocks Week 2 coding start.

Gate evaluation (2026-04-12):

1. Pass
2. Pass
3. Pass
4. Pass (one open UX placement item is implementation-detail only; behavior contract is unambiguous)

Gate result:

- Week 2 start status: `GO`

### Task 15 output

Week 2 kickoff gate is passed based on approved and unambiguous Week 1 artifacts.

## Guardrails

- Manual slug edits are never overwritten automatically after intent is detected.
- New posts still receive friendly initial suggestion behavior.
- Unlocked plugin-owned slugs may auto-refresh on title change when `regenerate_on_change=1`.
- Regeneration is always explicit.
- Behavior must be consistent across post/page flow and not rely on fragile editor-only assumptions.

## Out of Scope for Task 1

- Provider settings fixes (`P1`) and security cleanup (`P2`).
- UI styling details beyond required notice/actions behavior.
- Final implementation details for hooks and data migration.

## Week 1 Deliverable (Task 1)

This document is the initial behavior contract for implementation planning.
It should be used to derive:

- save-flow state logic,
- editor UX flows,
- test matrix scenarios,
- implementation tickets for Week 2 and Week 3.

Related Week 1 outputs:

- `docs/week1-test-matrix.md` (Task 9)
- `docs/week1-implementation-tickets.md` (Task 10)

## Week 1 Task Coverage Index

1. Task 1 -> this file (`Week 1 Deliverable (Task 1)`)
2. Task 2 -> this file (`State Model (Task 2)`)
3. Task 3 -> this file (`Current Plugin Flow Map (Task 3)`)
4. Task 4 -> this file (`Save-Context Decision Rules (Task 4)`)
5. Task 5 -> this file (`Manual-Edit Detection Algorithm (Task 5)`)
6. Task 6 -> this file (`Explicit Regenerate Flow (Task 6)`)
7. Task 7 -> this file (`Pre-Publish Notice Specification (Task 7)`)
8. Task 8 -> this file (`Editor Coverage Expectations (Task 8)`)
9. Task 9 -> `docs/week1-test-matrix.md`
10. Task 10 -> `docs/week1-implementation-tickets.md`
11. Task 11 -> this file (`Regression Risks and Mitigations (Task 11)`)
12. Task 12 -> this file (`Design Review Record (Task 12)`)
13. Task 13 -> this file (`Scope Freeze Policy (Task 13)`)
14. Task 14 -> this file (`Week 1 Final Outputs (Task 14)`)
15. Task 15 -> this file (`Week 2 Start Gate (Task 15)`)
