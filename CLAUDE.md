# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Secure Custom Module Builder (SCMB) is a WordPress plugin that lets site builders define custom Gutenberg blocks ("modules") through an admin UI, backed by Advanced Custom Fields (ACF) or Secure Custom Fields (SCF). A module is a `scmb_module` post with an HTML template (`{{field}}` mustache-style syntax), CSS, and JS; SCMB dynamically registers each active module as a real ACF/Gutenberg block at runtime. There is no build system — plain PHP/JS/CSS, no composer.json, no package.json, no bundler. CodeMirror is vendored directly under `assets/lib/codemirror` rather than installed as a dependency.

## 1. Stack & architecture

[secure-custom-module-builder.php](secure-custom-module-builder.php) is the bootstrap: a singleton that requires every `includes/class-scmb-*.php` file and wires each into WordPress hooks on `plugins_loaded`, gated on ACF/SCF being present (`scmb_has_acf_dependency()` duck-types either plugin rather than hard-depending on one). It also self-registers `SCMB_GitHub_Updater`, which checks a GitHub releases API instead of WordPress.org for updates.

- **Module schema** — `includes/class-scmb-post-type.php` registers the private `scmb_module` CPT and its three ACF field groups (Module Configuration, Module Template, Module Fields repeater), and assigns every module a stable unique `module_key` on save.
- **Block engine** — `includes/class-scmb-blocks.php` (~2200 lines, the core file). On `acf/init` it registers each active module as a real ACF/Gutenberg block, building the block's own ACF field group from the user-authored fields repeater (including a nested-repeater sub-field mini-DSL), then renders it through a small hand-rolled mustache-like template engine: `{{field}}` interpolation, `{{#field}}...{{/field}}` repeater sections with `__first`/`__last`/`__index`/`__position`/`name__count` helpers, and `{{#if expr}}...{{else}}...{{/if}}` conditionals supporting `&&`/`||`/`!`/comparisons.
- **Import/export** — `includes/class-scmb-import-export.php` handles JSON export/import keyed by `module_key` (portable across sites), with a two-step preview→confirm flow backed by transients.
- **Admin editor UI** — `includes/class-scmb-admin.php` + `assets/js/admin.js` enqueue CodeMirror for HTML/CSS/JS fields on `scmb_module` edit screens, add a Module Builder > Settings submenu, and drive a live "Field Snippets" panel by re-parsing the fields repeater client-side.
- **Editor decoration** — `assets/js/block-editor.js` adds a cosmetic label/icon header to SCMB blocks in the Gutenberg canvas, driven by a `window.scmbBlockLabels` map injected server-side.
- **Frontend** — `includes/class-scmb-renderer.php` is a near-empty stub; actual frontend CSS/JS enqueueing happens per-block in `class-scmb-blocks.php`'s `enqueue_block_assets`. `templates/admin-module-builder.php` is an intentional legacy compatibility stub — don't add logic there.

## 2. Coding conventions

Standard WordPress-core style: `snake_case` functions/variables/hooks, `Upper_Snake_Case` class names prefixed `SCMB_` (e.g. `SCMB_Post_Type`), one class per file named `class-scmb-<name>.php`. Each class is a singleton exposed via a static `get_instance()`. Methods carry PHPDoc blocks (`@param`/`@return`) in the newer files (import-export, github-updater, blocks); older/simpler methods are terser — match whichever style the surrounding file already uses. Indentation is 4 spaces, not tabs (observed pattern — differs from stock WPCS, confirm with team before running an auto-formatter). All user-facing strings go through the `secure-custom-module-builder` text domain. There's no exception-based error handling anywhere in the codebase — failures return `WP_Error`, call `wp_die()` with a translated message, or just early-return silently; follow that pattern rather than introducing try/catch.

## 3. Build, lint, and test commands

None are configured (no composer.json/package.json/phpcs.xml). Before committing: run `php -l` on every changed PHP file to catch syntax errors, and manually verify in a local WordPress install with ACF or SCF active (create/edit a `scmb_module`, check the block in the editor and on the frontend). The changelog references keeping the plugin passing the WordPress.org **Plugin Check** plugin — re-check it if you touch escaping, input handling, or text domains.

## 4. Security & validation patterns

This plugin's whole premise is being a "secure" alternative to page builders, so escaping discipline is load-bearing, not incidental. Module template output is never trusted raw: field values are escaped per field type in `build_template_context()`, and the fully-rendered HTML is always passed through `wp_kses()` using `get_allowed_template_html()` (WordPress's `post` allowed-tags list, extended to include form elements and `canvas`). Module CSS/JS are separately sanitized (`sanitize_css`/`sanitize_javascript`, with a JS syntax check) before being enqueued, and JS is wrapped in an IIFE that self-initializes once on `DOMContentLoaded`. Admin actions (settings save, manual update check, import/export) check `current_user_can()` plus `check_admin_referer()`/`wp_verify_nonce()` before doing anything; GET-triggered notices are nonce-verified too. Any new path that injects module-authored content into HTML must go through the same `wp_kses()` + `get_allowed_template_html()` gate.

## 5. Platform-specific rules

Follow WordPress/ACF conventions already in place rather than generic PHP idioms: register behavior through action/filter hooks in each class's constructor (don't call rendering/registration logic directly), use `get_field()`/`update_field()` (ACF API) with a raw `get_post_meta()`/`update_post_meta()` fallback for SCF compatibility, and prefer WP escaping/sanitizing functions (`esc_html`, `esc_attr`, `esc_url`, `sanitize_title`, `wp_kses*`) over hand-rolled equivalents. When adding a block-level ACF field, register it through `acf_add_local_field_group` with a `location` targeting `param => 'block'`, matching the pattern in `register_block_fields()`.

## 6. Project-specific context

The repeater sub-field syntax (`field_name|Field Label|field_type|options`, with indentation for nesting) and the field-name/module-key normalization rules are implemented independently in three places: `class-scmb-blocks.php` (authoritative, drives ACF/rendering), `class-scmb-import-export.php` (sanitizes imported payloads), and `assets/js/admin.js` (live snippet-panel preview). Changing the DSL or normalization rules in one place requires updating all three or the editor preview will silently drift from actual rendering behavior. The plugin version is duplicated across the `Version:` header and `SCMB_VERSION` constant in [secure-custom-module-builder.php](secure-custom-module-builder.php) and the `Stable tag:`/changelog in [README.md](README.md) — keep all three in sync on release.
