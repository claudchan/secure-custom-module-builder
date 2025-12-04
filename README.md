# Secure Custom Module Builder (SCMB)

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

### 1.0.0
* Initial stable release.
* Added core module creation API.
* Implemented security nonces on all form submissions.

## Contributing

We welcome contributions! Please read our `CONTRIBUTING.md` (once you create it) for details on our code of conduct and the process for submitting pull requests to us.

## License

This project is licensed under the **GNU General Public License v2.0 or later** (GPL-2.0+). See the `LICENSE` file for full details.