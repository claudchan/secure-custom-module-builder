# ACF / SCF compatibility notes

SCMB's integration surface with Advanced Custom Fields / Secure Custom Fields is narrow. This doc lists exactly what to re-check whenever the installed ACF/SCF version is updated, so a full re-read of the dependency's source isn't needed each time.

Last checked against **Secure Custom Fields 6.9.3** (2026-08-01), using a full copy of the plugin kept at `_backup/plugins/secure-custom-fields/` for reference (untracked — not part of this repo).

## SCMB's actual touch points

- Hooks: `acf/init`, `acf/save_post` ([includes/class-scmb-post-type.php](../includes/class-scmb-post-type.php), [includes/class-scmb-blocks.php](../includes/class-scmb-blocks.php)).
- Block registration: `acf_register_block_type()` using a `render_callback` closure — never `render_template` (a file path). Hardening changes to file-path/stream-wrapper resolution for `render_template` (added in SCF 6.9.3) do not apply to SCMB.
- Field registration: `acf_add_local_field_group()` / `acf_add_local_fields()`. As of 6.9.3, local PHP-registered groups are not run through SCF's JSON-schema validator (that only applies to JSON import/export paths), so unknown/extra array keys are tolerated.
- Field reads/writes: `get_field()` / `update_field()`, with a raw `get_post_meta()`/`update_post_meta()` fallback for SCF compatibility.
- **Field types actually used by SCMB** (own field groups + the user-authored module-fields DSL): `text`, `textarea`, `wysiwyg`, `select`, `image`, `true_false`, `repeater`, `number`, `url`. SCMB does **not** use `post_object`, `relationship`, `user`, `gallery`, `google_map`, `flexible_content`, or ACF Options Pages — SCF changelog entries about those field types can usually be skipped without investigation.

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
