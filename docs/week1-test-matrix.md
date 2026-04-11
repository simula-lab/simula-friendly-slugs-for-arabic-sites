# Week 1 Test Matrix: Slug Ownership (P0)

Status: Approved (Week 1)
Scope: Validate P0 behavior contract across Classic + Block editor flows.
Approval date: 2026-04-12
References:

- `docs/week1-slug-ownership-spec.md`
- `docs/ISSUES.md` (`P0` manual slug ownership)

## Matrix Columns

- `ID`: scenario identifier
- `Editor`: `Block`, `Classic`, `Quick Edit`, or `System`
- `Context`: lifecycle stage
- `Starting State`: lock/meta/slug baseline
- `User Action`: explicit user behavior
- `Expected Slug Result`: final slug behavior
- `Expected Meta`: expected values for `_simula_slug_locked_manual` and `_simula_last_generated_slug`

## Scenario Matrix

| ID    | Editor     | Context                        | Starting State                                                                  | User Action                                         | Expected Slug Result                                                  | Expected Meta                                                         |
| ----- | ---------- | ------------------------------ | ------------------------------------------------------------------------------- | --------------------------------------------------- | --------------------------------------------------------------------- | --------------------------------------------------------------------- |
| W1-01 | Block      | New draft first save           | `lock=false`, `last_generated` empty, Arabic title, method `wp_transliteration` | Save draft without editing slug                     | Plugin applies initial friendly suggestion                            | `lock=false`, `last_generated=<applied_slug>`                         |
| W1-02 | Classic    | New draft first save           | `lock=false`, `last_generated` empty, Arabic title, method `arabizi`            | Save draft without editing slug                     | Plugin applies initial friendly suggestion                            | `lock=false`, `last_generated=<applied_slug>`                         |
| W1-03 | Block      | New draft first save           | `lock=false`, `last_generated` empty, Arabic title                              | User types custom slug before first save            | User slug remains; no plugin overwrite                                | `lock=true`, `last_generated=<computed_or_previous>`                  |
| W1-04 | Classic    | Existing draft update          | `lock=false`, slug currently plugin-generated, `regenerate_on_change=1`         | Change title only, no explicit regenerate           | Slug updates to newly generated plugin slug                           | `lock=false`, `last_generated=<applied_slug>`                         |
| W1-05 | Block      | Existing draft update          | `lock=true`, custom slug present                                                | Change title and save                               | Custom slug remains unchanged                                         | `lock=true`, `last_generated` may update for comparison only          |
| W1-06 | Classic    | First publish transition       | `lock=true`, slug custom, divergence exists                                     | Click publish without choosing "Use friendly slug"  | Custom slug remains                                                   | `lock=true`, `last_generated` preserved                               |
| W1-07 | Block      | First publish transition       | `lock=true`, divergence exists                                                  | Choose `Use friendly slug` from notice then publish | Friendly slug applied                                                 | `lock=false`, `last_generated=<applied_slug>`                         |
| W1-08 | Classic    | First publish transition       | `lock=false`, divergence exists                                                 | Choose `Keep current slug`                          | Current slug kept                                                     | `lock=true`, `last_generated` retained/updated                        |
| W1-09 | Block      | Published post update          | `lock=true`, custom slug                                                        | Update content/title only                           | No automatic slug overwrite                                           | `lock=true`, `last_generated` unchanged or refreshed-for-compare      |
| W1-10 | Classic    | Published post update          | `lock=false`, plugin slug exists                                                | Explicit `Regenerate friendly slug`                 | Slug replaced with newly generated friendly slug                      | `lock=false`, `last_generated=<applied_slug>`                         |
| W1-11 | Quick Edit | Inline edit save               | existing post, any prior lock                                                   | User edits slug in quick edit                       | User slug remains, treated as manual                                  | `lock=true`, `last_generated` unchanged or refreshed-for-compare      |
| W1-12 | Quick Edit | Inline edit save               | `lock=true`, divergence exists                                                  | Save quick edit without slug change                 | No slug replacement                                                   | `lock=true`, `last_generated` unchanged                               |
| W1-13 | System     | Autosave                       | any state                                                                       | Autosave tick fires                                 | No slug mutation                                                      | No meta state transition                                              |
| W1-14 | System     | Revision restore               | `lock=true`, custom slug                                                        | Restore prior revision                              | No auto-regenerate; slug ownership preserved                          | `lock=true`, `last_generated` preserved                               |
| W1-15 | Block      | Non-Arabic title               | any state                                                                       | Save/update                                         | Plugin does not generate or replace slug                              | Lock unchanged unless user edited slug                                |
| W1-16 | Classic    | Method `none`                  | any state                                                                       | Save/update                                         | Plugin never replaces slug                                            | Lock unchanged unless explicit slug manual edit                       |
| W1-17 | Block      | Explicit regenerate security   | editable post                                                                   | Trigger regenerate with invalid nonce               | No slug/meta mutation; error notice                                   | No meta state transition                                              |
| W1-18 | Classic    | Explicit regenerate security   | user cannot edit post                                                           | Trigger regenerate                                  | No slug/meta mutation; permission error                               | No meta state transition                                              |
| W1-19 | Block      | Pre-publish notice visibility  | divergence exists                                                               | Open editor and modify title causing divergence     | Notice appears with both actions                                      | Meta unchanged until action                                           |
| W1-20 | Block      | Pre-publish notice suppression | slug equals suggestion                                                          | Open editor/save                                    | Notice not shown                                                      | Meta unchanged                                                        |
| W1-21 | Classic    | Translation failure fallback   | method `translation`, invalid provider creds                                    | Explicit use/regenerate action                      | Fallback behavior applied, no silent override outside explicit action | On success: `lock=false`, `last_generated=<applied_or_fallback_slug>` |
| W1-22 | Block      | Empty generation result        | forced edge case where sanitize yields empty                                    | Explicit use/regenerate action                      | Current slug unchanged; actionable error                              | Lock unchanged, `last_generated` unchanged                            |

## Minimum Execution Set (Week 1 sign-off)

Run at least these before Week 2 starts:

- `W1-01`, `W1-03`, `W1-05`, `W1-07`, `W1-08`, `W1-11`, `W1-13`, `W1-14`, `W1-17`, `W1-19`

## Pass Criteria

- No case violates manual slug ownership after lock is true.
- Explicit actions are the only path that can replace an owned slug.
- Autosave/revision flows produce zero ownership transitions.
- Block and Classic outcomes are behaviorally equivalent for matching scenarios.
