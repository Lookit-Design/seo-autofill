# Lookit SEO Autofill

[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Lint](https://github.com/Lookit-Design/seo-autofill/actions/workflows/lint.yml/badge.svg)](../../actions/workflows/lint.yml)
[![Coding Standards](https://github.com/Lookit-Design/seo-autofill/actions/workflows/coding-standards.yml/badge.svg)](../../actions/workflows/coding-standards.yml)
[![Plugin Check](https://github.com/Lookit-Design/seo-autofill/actions/workflows/plugin-check.yml/badge.svg)](../../actions/workflows/plugin-check.yml)
[![Tests](https://github.com/Lookit-Design/seo-autofill/actions/workflows/test.yml/badge.svg)](../../actions/workflows/test.yml)

Automatically fills Yoast focus keyphrase, meta description, and related keyphrases on publish, using per-post-type rules and the post's own content.

Supports `WordPress >= 5.9` on `PHP >= 7.4`.

## Table of Contents

- [Getting Started](#getting-started)
  - [Installation](#installation)
  - [Configuration](#configuration)
- [Features](#features)
- [Security and Privacy](#security-and-privacy)
- [Development](#development)
  - [Setup](#setup)
  - [Running the Test Suite](#running-the-test-suite)
  - [Coding Standards](#coding-standards)
  - [Continuous Integration](#continuous-integration)
- [Contributing](#contributing)
- [License](#license)

## Getting Started

### Installation

This plugin is installed from GitHub, not from WordPress.org.

1. Clone or copy this repository into `/wp-content/plugins/lookit-seo-autofill`.
2. Activate **Lookit SEO Autofill** through the **Plugins** menu in WordPress.
3. Yoast SEO should be active; the plugin writes Yoast meta fields.

### Configuration

Open **Auto SEO Templates** (under Yoast SEO when Yoast is active, otherwise under **Settings**). Enable the post types you want, choose how the focus keyphrase is derived, and set a meta-description template.

## Features

* Per-post-type rules for focus keyphrase, meta description, and related keyphrases.
* Keyphrase sources include the post title, slug, and the top content word.
* Related keyphrases from post content, expanded with the free [Datamuse](https://www.datamuse.com/api/) API.
* A per-post lock so later publishes do not overwrite SEO you have already set.
* A Test & Reprocess tool for posts published before the rules were enabled.

## Security and Privacy

* A stored OpenRouter API key is **never** sent back to the browser, is **not autoloaded**, and is **removed on uninstall**.
* Submitting a blank key keeps the saved value.

Datamuse is called only when related-keyphrase generation is enabled. See Datamuse's [API notes](https://www.datamuse.com/api/).

## Development

### Setup

Install the development dependencies with [Composer](https://getcomposer.org/):

```bash
composer install
```

### Running the Test Suite

The integration tests run against a real WordPress test install and a MySQL database. Install the test suite once, then run PHPUnit:

```bash
# bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

composer test
```

### Coding Standards

This project follows the WordPress Coding Standards and checks PHP cross-version compatibility:

```bash
composer phpcs    # check coding standards
composer phpcbf   # auto-fix what can be fixed
composer compat   # check PHP 7.4+ compatibility
composer lint     # php -l syntax check on all files
```

### Continuous Integration

Every push and pull request runs the following GitHub Actions workflows:

| Workflow | Purpose |
| --- | --- |
| [Lint](../../actions/workflows/lint.yml) | `php -l` syntax check across the supported PHP versions |
| [Coding Standards](../../actions/workflows/coding-standards.yml) | WordPress Coding Standards (PHPCS) |
| [Plugin Check](../../actions/workflows/plugin-check.yml) | Official WordPress Plugin Check |
| [Test](../../actions/workflows/test.yml) | PHPUnit across a broad WordPress × PHP matrix |

A scheduled [Version Monitor](../../actions/workflows/version-monitor.yml) workflow watches for new PHP and WordPress releases so compatibility can be reviewed proactively.

## Contributing

Bug reports and pull requests are welcome on [GitHub](../../issues).

## License

This plugin is available as open source under the terms of the [GPL-2.0-or-later License](https://www.gnu.org/licenses/gpl-2.0.html).

---

_Lookit&reg; is a registered trademark of ZENOVA CORP. Yoast is a trademark of its respective owner; this plugin is an independent integration and is not affiliated with, sponsored by, or endorsed by Yoast._
