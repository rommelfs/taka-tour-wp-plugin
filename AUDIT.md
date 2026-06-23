# TAKA Platform Audit and Hardening

Audit version: v2.2.2

## Scope

This pass paused feature work and reviewed the current plugin surface for safe cleanup and security hardening.

Checked areas:

- plugin bootstrap and compatibility shims
- admin menu pages and admin-post actions
- CPT save handlers
- organizer dashboard POST handling
- import/export and translation package import
- Events Manager export integration
- frontend templates and Pretix widget rendering
- translation and dynamic text resolution
- CSS/JS assets for obvious debug output

## Fixed Issues

### Content Block references on homepage sections audited

Homepage content sections were traced from shortcode rendering through `get_homepage_sections()`,
`get_content_sections()`, `resolve_content_source()` and the shared `partials/content-section.php`
renderer.

Findings:

- homepage content sections are data-driven and are not limited to predefined section IDs
- unknown section keys use the generic `content_section` renderer
- referenced Content Blocks are resolved through the same content-source mechanism used by event descriptions
- the local happy path resolves a section key `instructor` referencing Content Block slug `takafumi-nakayama`
- the likely live failure class is now narrowed to stored data shape, Content Block status/enabled state, slug mismatch or empty block fields

Fix:

- Content Block loading now falls back from `_taka_block_title` to the WordPress post title
- Content Block body loading now falls back from `post_content` to legacy body/text/description meta and post excerpt
- the admin Diagnostics page now shows final homepage section source data, including reference slug, block lookup result, block status/enabled state and final title/body excerpt

Files touched:

- `includes/Data/class-repository.php`
- `includes/Admin/class-admin.php`
- `docs/content-blocks.md`

### Uploaded PHP config execution removed

The Import / Export screen previously accepted uploaded PHP config files and loaded them with `require`. That meant a user with import access could execute uploaded PHP.

Fix:

- uploaded config import is now JSON-only
- uploaded files are checked for `.json`
- uploaded JSON is decoded and validated as data
- bundled local `config/tour-events.php` fallback remains available because it is trusted repository code, not uploaded user input

Files touched:

- `includes/Admin/class-admin.php`
- `README.md`

### Debug-style import summary output removed

The import result notice used a debug-style array dump inside escaped admin output. This was not exploitable, but it looked like temporary diagnostic output and triggered audit scans.

Fix:

- replaced the dump with a small escaped HTML summary renderer

Files touched:

- `includes/Admin/class-admin.php`

### Version and documentation updated

The patch version was bumped to `2.2.1`, and the README changelog documents this hardening pass.

Files touched:

- `taka-tour-website-builder.php`
- `README.md`

## Findings Kept for Backward Compatibility

### Legacy Taka_Tour aliases

Legacy classes, constants and shortcode aliases are still present. They are compatibility shims for existing deployments and should not be removed without a migration plan.

Examples:

- `Taka_Tour_Data`
- `Taka_Tour_Renderer`
- `Taka_Tour_I18n`
- `TAKA_TOUR_VERSION`
- `[taka_homepage]`
- `[taka_tickets]`

Recommendation:

- keep for now
- document a deprecation policy before removing any shim

### Bundled PHP config import remains

The bundled `config/tour-events.php` import remains because it is repository-controlled seed/fallback data. Uploaded PHP execution was removed.

Recommendation:

- keep bundled config support until config seeding has a JSON-only replacement

## Remaining Risks and Recommendations

### Pretix asset URLs are still deployment-specific

The Pretix widget script/style registration still contains hardcoded Pretix URLs. This is existing behavior and was not changed in this audit to avoid breaking ticket rendering.

Recommendation:

- move Pretix widget asset URLs into provider configuration
- keep the current defaults as fallback values only

### Organizer dashboard supports legacy primary-organizer permission checks

The frontend organizer dashboard checks the event primary organizer when deciding whether an organizer user may edit an event. The admin-side event relationship save path filters organizer relationships against assigned organizer IDs.

Recommendation:

- in a later permission-focused pass, align the dashboard permission helper with normalized organizer relationships
- add tests for organizer users attempting to POST foreign organizer IDs

### REST event feed is public by design

The `/wp-json/taka-platform/v1/events` endpoint is intentionally public and exposes normalized public event data for integrations. It should not include private notes or credentials.

Recommendation:

- review new event fields before adding them to the normalized export payload
- keep sensitive admin-only data out of integration payloads

### Historical current-tour defaults remain

Some bundled demo/fallback data and historical text still reference the current TAKA Tour deployment. That is acceptable for seed data, but application logic should continue moving toward configuration.

Recommendation:

- prefer configurable defaults for any new frontend-visible content
- avoid adding new current-tour hardcoding

## Security Checklist Result

- Admin forms/actions reviewed for nonces and capability checks.
- CPT save handlers reviewed for nonce/capability checks.
- Organizer relationship save path reviewed for non-admin filtering.
- Saved input reviewed for sanitization helpers.
- Frontend/admin templates reviewed for escaping patterns.
- Translation package import reviewed as JSON-only data import.
- Uploaded PHP config execution removed.
- Pretix widget output remains isolated through scoped templates/CSS.
- No broad risky rewrites were performed.

## Verification Commands

Run during this pass:

PHP syntax was checked for every PHP file. Merge marker, diagnostic-output and early-termination scans were also run across the repository, excluding `.git`. `git diff --check` was run for whitespace safety.

Notes:

- early-termination scans report legitimate WordPress permission failures, direct-access guards and admin download/redirect exits.
- merge-marker scans report the grep patterns inside lint scripts themselves.
