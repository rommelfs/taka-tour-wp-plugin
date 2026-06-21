# TAKA Platform Branding Migration

## Executive Summary

TAKA Platform is the public product name for the plugin that originally grew out of the TAKA Tour website. This migration keeps the public identity and new internal entry points aligned with **TAKA Platform** while preserving the legacy identifiers that existing WordPress installs, custom templates, shortcodes and deployment scripts may still depend on.

This is intentionally not a full search-and-replace. Persistent WordPress identifiers and externally consumed APIs remain stable unless a future migration provides explicit compatibility handling.

## What Was Renamed

- Public documentation now points readers to the TAKA Platform branding and compatibility rules.
- New canonical helper functions are available alongside the legacy helper names:
  - `taka_platform_render_template()`
  - `taka_platform_allowed_html()`
  - `taka_platform_translate()`
  - `taka_platform_current_language()`
- Internal bundled templates now call canonical `TAKA_Platform_*` classes where this does not affect stored data or public APIs.
- The bundled config PHPDoc package name was updated from the old tour label to `TAKA_Platform`.

## What Was Intentionally Preserved

The following identifiers are compatibility-sensitive and remain unchanged:

- Main plugin file path: `taka-tour-website-builder.php`
- Legacy constants: `TAKA_TOUR_VERSION`, `TAKA_TOUR_PLUGIN_FILE`, `TAKA_TOUR_PLUGIN_DIR`, `TAKA_TOUR_PLUGIN_URL`
- Legacy class aliases: `Taka_Tour_Data`, `Taka_Tour_Renderer`, `Taka_Tour_I18n`, `Taka_Tour_Plugin`, `Taka_Tour_Admin`, `Taka_Tour_Ticket_Providers`
- Legacy helper functions: `taka_tour_render_template()`, `taka_tour_allowed_html()`, `taka_tour_translate()`, `taka_tour_current_language()`
- Existing shortcodes, including `[taka_homepage]`, `[taka_tour_schedule]`, `[taka_tickets]`, `[taka_sponsor]` and `[taka_language_switcher]`
- Custom post type slugs such as `taka_event`, `taka_organizer`, `taka_venue` and `taka_content_block`
- Existing option names, post meta keys, AJAX actions, nonces, admin slugs, script handles, style handles and CSS selectors
- Translation text domain and translation file names
- Existing REST namespace `/wp-json/taka-platform/v1/events`

## Compatibility Shims

The bootstrap already maps legacy constants and `Taka_Tour_*` classes to the current `TAKA_PLATFORM_*` constants and `TAKA_Platform_*` classes. This migration adds canonical `taka_platform_*` helper aliases while keeping the legacy helper functions as the underlying stable compatibility layer.

Bundled templates can gradually move to the canonical helpers and classes, but third-party code using legacy names should continue to work.

## Compatibility-Sensitive Identifiers Left Unchanged

| Identifier area | Examples | Reason |
| --- | --- | --- |
| Persistent data | `_taka_*` meta keys, option names such as `taka_tour_media_settings` | Renaming would orphan saved WordPress data without a migration. |
| Public integration points | shortcodes, action names, nonces, AJAX actions | Existing pages, forms and custom integrations may call these directly. |
| Asset contracts | `.taka-tour-*` CSS classes, `taka-tour-*` handles | Themes or custom CSS may target these selectors and handles. |
| File paths | `taka-tour-website-builder.php`, `config/tour-events.php`, compatibility include files | WordPress plugin activation and deployment automation may depend on paths. |
| Historical content | changelog entries, seed/demo tour content, translation keys | These describe past releases or current reference data. |

## Remaining Old-Name Occurrences

Remaining occurrences are expected and classified as follows:

- **Compatibility shims:** legacy constants, class aliases and thin `includes/class-taka-tour-*.php` files.
- **Persistent/API identifiers:** option names, shortcode names, admin slugs, nonce names and helper functions retained for existing installs.
- **CSS and template contracts:** `taka-tour-page`, `taka-tour-overview` and `taka-tour-station-link` classes are preserved because changing them could break theme overrides.
- **Reference deployment content:** `config/tour-events.php`, tour schedule templates and TAKA European Tour copy remain as seed/demo/fallback content for the first deployment.
- **Operational scripts:** deployment scripts still reference the historical repository and plugin directory names and should only be changed with deployment coordination.
- **Historical documentation:** changelog and migration notes mention TAKA Tour compatibility intentionally.

## Manual Follow-Up Items

- Decide whether a future major version should offer a guided migration from the legacy plugin directory/main file name to a `taka-platform` package path.
- Consider a deprecation policy for legacy helper functions once external usage is known.
- If CSS class names are ever renamed, ship aliases for at least one major release.
- Review deployment scripts before changing repository or server plugin directory names.
- Keep seed/demo content separate from generic platform defaults as the white-label architecture matures.

## Validation Performed

- PHP syntax checks on changed PHP files.
- Full repository PHP syntax check.
- Merge conflict marker scan.
- `git diff --check`.
- Legacy-name search after the migration to confirm remaining occurrences are compatibility-sensitive, reference content or historical documentation.

