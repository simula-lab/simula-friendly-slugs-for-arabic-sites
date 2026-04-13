# Week 7 Execution Plan (WordPress.org Release and Publish)

Date: 2026-04-13
Target week: May 25-May 31, 2026
Release target: `1.2.4`
Source documents:

- `docs/1.2.x/week-by-week-plan.md`
- `docs/1.2.x/week6-execution-plan.md`
- `checklist-before-review.txt`
- `readme.txt`
- `simula-friendly-slugs-for-arabic-sites.php`
- `build-zip.sh`

## Objective

Complete the WordPress.org release workflow for version `1.2.4` by freezing release metadata, validating the distributable package, and preparing the exact SVN publish steps and post-publish checks.

## Week 7 Scope

1. Finalize release metadata in `readme.txt` and plugin headers.
2. Confirm version consistency for the `1.2.4` release target.
3. Build the final ZIP package and verify required shipped assets/files.
4. Prepare the WordPress.org SVN publish procedure for `/trunk` and `/tags/1.2.4`.
5. Record the post-publish smoke checks required after release.

## Out of Scope

- New feature work or non-release-blocking refactors
- Runtime QA that belongs to Week 6 sign-off
- Marketing copy, screenshots redesign, or unrelated README cleanup

## Step-by-Step Tasks

### TKT-REL-W7-01

- Title: Freeze release target and version inventory
- Target Week: Week 7
- Scope:
  - Confirm the release target is `1.2.4` across the workspace.
  - Inventory every user-facing version reference that must match before publish.
  - Note any stale planning references from prior weeks so they do not leak into release notes.
- Affected Files:
  - `simula-friendly-slugs-for-arabic-sites.php`
  - `readme.txt`
  - release-planning docs
- Acceptance Criteria:
  - A single release target version is used consistently for Week 7.
  - Any stale `1.2.0` planning references are updated or explicitly excluded from release work.

### TKT-REL-W7-02

- Title: Finalize WordPress.org readme metadata and changelog
- Target Week: Week 7
- Scope:
  - Verify `Stable tag`, `Tested up to`, `Requires at least`, and `Requires PHP`.
  - Ensure the changelog includes the release line intended for `1.2.4`.
  - Check formatting against the WordPress.org readme expectations noted in `checklist-before-review.txt`.
- Affected Files:
  - `readme.txt`
- Acceptance Criteria:
  - `readme.txt` metadata matches the version being published.
  - Changelog content is ready for the release tag.
  - Readme structure is ready for WordPress.org validation.

### TKT-REL-W7-03

- Title: Verify distributable package contents and asset/header readiness
- Target Week: Week 7
- Scope:
  - Build the final ZIP using `build-zip.sh`.
  - Verify the package includes the main plugin file, `readme.txt`, `languages/`, and required `assets/`.
  - Confirm screenshot filenames and plugin headers are suitable for the release package.
- Affected Files:
  - `build-zip.sh`
  - `assets/`
  - generated ZIP artifact
- Acceptance Criteria:
  - The ZIP artifact builds cleanly from the workspace.
  - Required release files are present in the package.
  - No asset/header mismatch remains before SVN publish.

### TKT-REL-W7-04

- Title: Prepare WordPress.org SVN publish steps
- Target Week: Week 7
- Scope:
  - Document the exact publish sequence for updating `/trunk` and creating `/tags/1.2.4`.
  - Ensure trunk/tag contents come from the same validated workspace state.
  - Include a pre-commit checklist for version/readme consistency.
- Deliverables:
  - `/trunk` publish checklist
  - `/tags/1.2.4` publish checklist
- Acceptance Criteria:
  - The SVN workflow is explicit enough to execute without guesswork.
  - Trunk/tag version drift is prevented by checklist steps.

### TKT-REL-W7-05

- Title: Define post-publish verification and rollback notes
- Target Week: Week 7
- Scope:
  - Verify the plugin page reports the intended stable version after publish.
  - Verify the public download installs and activates cleanly in a smoke-test environment.
  - Record the rollback trigger conditions if the published package is inconsistent or broken.
- Acceptance Criteria:
  - A concrete post-publish smoke test exists.
  - Release owners know what to verify immediately after the SVN push.
  - Basic rollback conditions are documented.

## Recommended Execution Order

1. `TKT-REL-W7-01` release target freeze
2. `TKT-REL-W7-02` readme and metadata finalization
3. `TKT-REL-W7-03` package verification
4. `TKT-REL-W7-04` SVN publish preparation
5. `TKT-REL-W7-05` post-publish checks

## Publish Checklist

1. Confirm `Version:` in `simula-friendly-slugs-for-arabic-sites.php` is `1.2.4`.
2. Confirm `Stable tag:` in `readme.txt` is `1.2.4`.
3. Confirm the release changelog entry for `1.2.4` is present.
4. Run the packaging script and verify the ZIP contents.
5. Copy the validated workspace contents to WordPress.org SVN `/trunk`.
6. Create SVN `/tags/1.2.4` from the same release state.
7. Commit SVN changes with a release-specific message.
8. Verify the WordPress.org plugin page and public download after propagation.

## Week 7 Exit Criteria

Week 7 is complete only when:

1. Release metadata is consistent for version `1.2.4`.
2. The final distributable package has been built and verified.
3. WordPress.org `/trunk` and `/tags/1.2.4` are prepared from the same validated state.
4. A post-publish smoke test confirms the released package is downloadable and functional.
