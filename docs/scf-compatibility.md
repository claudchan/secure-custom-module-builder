# ACF / SCF compatibility notes

SCMB's integration surface with Advanced Custom Fields / Secure Custom Fields is narrow. This doc lists exactly what to re-check whenever the installed ACF/SCF version is updated, plus which editions of ACF are actually supported, so a full re-read of the dependency's source isn't needed each time.

Last checked against **Secure Custom Fields 6.9.3** and **ACF (free) 6.8.6** (2026-08-01), using full copies of both plugins kept at `_backup/plugins/` for reference (untracked — not part of this repo).

**Supported plugin priority: Secure Custom Fields (SCF) is the primary target, ACF PRO is secondary.** The free Advanced Custom Fields plugin is not a supported dependency at all — see below. Keep SCF listed first in any user-facing messaging (deactivation notices, README, this doc).

## SCMB's actual touch points

- Hooks: `acf/init`, `acf/save_post` ([includes/class-scmb-post-type.php](../includes/class-scmb-post-type.php), [includes/class-scmb-blocks.php](../includes/class-scmb-blocks.php)).
- Block registration: `acf_register_block_type()` using a `render_callback` closure — never `render_template` (a file path). Hardening changes to file-path/stream-wrapper resolution for `render_template` (added in SCF 6.9.3) do not apply to SCMB.
- Field registration: `acf_add_local_field_group()` / `acf_add_local_fields()`. As of 6.9.3, local PHP-registered groups are not run through SCF's JSON-schema validator (that only applies to JSON import/export paths), so unknown/extra array keys are tolerated.
- Field reads/writes: `get_field()` / `update_field()`, with a raw `get_post_meta()`/`update_post_meta()` fallback for SCF compatibility.
- **Field types actually used by SCMB** (own field groups + the user-authored module-fields DSL): `text`, `textarea`, `wysiwyg`, `select`, `image`, `true_false`, `repeater`, `number`, `url`. SCMB does **not** use `post_object`, `relationship`, `user`, `gallery`, `google_map`, `flexible_content`, or ACF Options Pages — SCF changelog entries about those field types can usually be skipped without investigation.

## The free Advanced Custom Fields plugin is not a supported dependency

This is a permanent architectural fact about ACF's free/PRO split, not a regression from a recent update — confirmed by inspecting a full downloaded copy of **ACF (free) 6.8.6** at `_backup/plugins/advanced-custom-fields/` (untracked, not part of this repo).

- `acf_register_block_type()` — the function SCMB uses to register every module as a Gutenberg block ([includes/class-scmb-blocks.php:174](../includes/class-scmb-blocks.php#L174)) — does not exist anywhere in ACF Free. It's PRO/SCF-exclusive; ACF Free's own `readme.txt` lists "ACF Blocks" as a PRO-only feature.
- The `repeater` field type — which SCMB's own "Module Fields" configuration UI is built on ([includes/class-scmb-post-type.php:244](../includes/class-scmb-post-type.php#L244)) — has no implementing class in ACF Free's `includes/fields/` directory. It's also PRO/SCF-exclusive; ACF Free only lists it in the "Add Field" type picker as a locked/promotional entry.
- ACF Free **does** provide `get_field()` and `acf_add_local_field_group()`, and it **does** define the shared `class ACF`. Neither of those alone (nor together) distinguishes it from SCF/ACF PRO — all three are present in ACF Free too.

**Fixed 2026-08-01**: `scmb_has_acf_dependency()` in [secure-custom-module-builder.php](../secure-custom-module-builder.php) previously accepted any plugin exposing `get_field()` + `acf_add_local_field_group()` (or `class_exists('ACF')`), which ACF Free satisfies — so SCMB silently stayed active with ACF Free installed, never registered any blocks, and gave no explanation why. It now checks `function_exists('acf_register_block_type')` specifically — the same guard already used in `SCMB_Blocks::register_blocks()` — so ACF-Free-only installs get the "missing dependency" deactivation notice instead of a silent, unexplained failure. The notice and the Plugins-list tooltip ([assets/js/disable-activation.js](../assets/js/disable-activation.js)) now point users at Secure Custom Fields or ACF PRO instead of the free ACF plugin.

## ⚠️ Forward-looking risk: block API version defaults

`acf_register_block_type()` (in SCF's `includes/blocks.php`) decides the block's API version like this:

```php
$default_acf_block_version = apply_filters( 'acf/blocks/default_block_version', 1, $block );

if ( ! isset( $block['acf_block_version'] ) ) {
    $block['acf_block_version'] = $default_acf_block_version;
}

if ( ! isset( $block['api_version'] ) ) {
    if ( $block['acf_block_version'] >= 3 && version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) ) {
        $block['api_version'] = 3;
    } else {
        $block['api_version'] = 2;
    }
}
```

SCMB's `$block_args` in `register_single_block()` ([includes/class-scmb-blocks.php:137-159](../includes/class-scmb-blocks.php#L137-L159)) never sets `acf_block_version` or `api_version` — so every SCMB module block silently inherits whatever SCF's own default is. Today that default is `1` (legacy/V2 block behavior), so nothing changes in practice.

**The risk**: if a future SCF release changes that default (e.g. bumps `acf/blocks/default_block_version` to `3`), every SCMB module block would silently start registering as a V3 block with no code change on SCMB's side. Known V3-only side effects include `expanded_editor_buttons` defaulting to `true`, and likely other V3-specific editor/rendering defaults introduced alongside SCF's "Auto Inline Editing" feature.

**Not yet fixed — no edits have been made for this.** The suggested fix, when ready to apply it, is a one-line addition pinning the version explicitly:

```php
$block_args = [
    'name' => $block_slug,
    'acf_block_version' => 1, // Pin to V2 blocks regardless of SCF's own future default.
    ...
];
```

## Checklist for a new SCF/ACF release

1. Read the changelog for entries mentioning: block registration / `api_version` / `acf_block_version` defaults, `render_callback` behavior, `acf_add_local_field_group()` / `acf_add_local_fields()`, and the field types SCMB actually uses (listed above).
2. Skip changelog entries that only touch field types SCMB doesn't use (relationship, post_object, user, gallery, google_map, flexible_content, options pages).
3. Confirm `render_callback`-based blocks are still fully supported (not deprecated in favor of `render_template`/block.json-only registration).
4. Re-check whether the `acf/blocks/default_block_version` filter's default value has changed from `1` — this is the highest-risk item above.
5. Spot-check that `get_field()`'s return shape for `image` (array with `url`/`alt`) and `repeater` (array of associative row arrays) fields hasn't changed.
