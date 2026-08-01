# Secure Custom Module Builder (SCMB)

Contributors: claudchan
Tags: blocks, acf, gutenberg, module builder, custom blocks
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build custom Gutenberg blocks with a visual interface - like HubSpot modules for WordPress

## Description

This plugin provides a secure framework for developers to create custom modules without relying on bulky third-party page builders. It enforces strict data validation and uses nonces for enhanced security.

## Installation

### A. Manual Installation (The Developer Way)

1.  Download the latest release ZIP file.
2.  Unzip the file.
3.  Upload the `secure-custom-module-builder` folder to the `/wp-content/plugins/` directory.
4.  Activate the plugin through the 'Plugins' menu in WordPress.

### B. Git Clone (Recommended for Contribution)

1.  Navigate to your WordPress installation's plugins directory:
    `cd /path/to/wp-content/plugins/`
2.  Clone the repository:
    `git clone https://github.com/YOUR_USERNAME/secure-custom-module-builder.git`
3.  Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

Once activated, where do you find the plugin settings or functionality?

* **Example 1:** Navigate to **Settings > Custom Module Builder** to configure global options.
* **Example 2:** Use the shortcode `[scmb_show_module id="1"]` to display a module.

## Changelog

### 1.0.20
* Fixed the ACF dependency check incorrectly accepting the free Advanced Custom Fields plugin, which silently left the plugin active with no working blocks since ACF Free doesn't include the Blocks or Repeater features SCMB requires. The check now specifically requires Secure Custom Fields or ACF PRO, and the deactivation notice points users to the correct plugin.

### 1.0.19
* Replaced the multi-select list box in Module Builder Import/Export with a checkbox list for choosing specific modules to export, adding select all/none controls and a live selection count.

### 1.0.18
* Text and Textarea module fields now render `<span>`, `<i>`, `<br>`, `<em>`, and `<strong>` tags instead of escaping them as literal text; all other HTML in these fields is still stripped.
* Added a Module Builder Settings page for entering global custom CSS loaded inside the Gutenberg block editor.

### 1.0.17
* Fixed JSON module imports on Windows/LocalWP by preserving PHP upload temp paths.
* Relaxed JSON upload MIME validation while still validating package contents with JSON parsing.
* Improved imported ACF module metadata saving by using known field keys.

### 1.0.14
* Improved Plugin Check compatibility for private GitHub-distributed builds.
* Updated text domain usage, nonce/input handling, import validation, and escaping annotations.
* Switched module frontend JavaScript output to WordPress inline script APIs while preserving once-per-module execution.

### 1.0.13
* Added repeater count helpers for templates, including `repeater_name__count` and `repeater_name__has_multiple`.
* Added numeric comparisons in template conditionals, such as `{{#if market_list__count > 1}}`.

### 1.0.12
* Added nested repeater sub-fields using indentation, for example `item_tags|Tags|repeater|max=4` followed by indented child fields.

### 1.0.11
* Added select choices for top-level module fields using `value:Label` pairs separated by commas or new lines.
* Added Allow Null support for top-level select module fields.

### 1.0.9
* Added module preview thumbnail uploads for Gutenberg inserter previews.
* Added export/import support for preview thumbnail attachment IDs and single-instance settings.
* Improved replace imports so exact title matches can be overwritten when module keys differ.

### 1.0.7
* Added repeater row helpers for templates: `__first`, `__last`, `__index`, and `__position`.
* Normalized module field names to lowercase underscores while typing, pasting, importing, registering, and rendering.
* Added an optional Max Rows setting for repeater fields.
* Added select choices for repeater sub-fields using `field_name|Field Label|select|value:Label,other:Other Label`, with optional `|allow_null`.
* Added module field snippet Copy and Insert actions for HTML templates.

### 1.0.6
* Added a plugin-row "Check for update" action that clears SCMB and WordPress update caches.

### 1.0.5
* Added stable module keys for safer cross-site module identity.
* Added Module Builder > Import / Export with SCMB JSON package downloads.
* Added import preview and matching by module key to avoid same-name module conflicts.

### 1.0.4
* Allowed canvas elements in rendered module templates for interactive frontend modules.

### 1.0.3
* Fixed DOMContentLoaded wrapper stripping for arrow-function module scripts to prevent stray closing tokens in generated frontend JavaScript.

### 1.0.2
* Fixed dependency detection so SCMB remains active with ACF-compatible Secure Custom Fields installs.

### 1.0.1
* Fixed frontend JavaScript wrapper output to prevent malformed DOMContentLoaded initialization and duplicate module execution.

### 1.0.0
* Initial stable release.
* Added core module creation API.
* Implemented security nonces on all form submissions.

## Contributing

We welcome contributions! Please read our `CONTRIBUTING.md` (once you create it) for details on our code of conduct and the process for submitting pull requests to us.

## License

This project is licensed under the **GNU General Public License v2.0 or later** (GPL-2.0+). See the `LICENSE` file for full details.
