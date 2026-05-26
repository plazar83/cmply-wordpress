# CMPly WordPress Plugin

CMPly connects WordPress to [CMPly.app](https://cmply.app) for cookie consent, consent records, and consent-based script blocking.

The plugin is built for the WordPress.org plugin directory with the public plugin name **CMPly** and the WordPress slug `cmply`.

## Features

- Cookie consent SDK injection in the public `<head>`.
- Early script loading without `defer` or `async` so CMPly can block third-party scripts before they run.
- WordPress admin dashboard inspired by CookieYes-style plugin screens.
- `Connect to CMPly` flow for web-app authorization and automatic Site ID return.
- Manual Site ID fallback.
- Google Consent Mode overview screen.
- Optional SDK base URL, SDK version query, language, and excluded paths.
- `[cmply_revisit]` shortcode for reopening visitor preferences.
- WordPress.org-ready `readme.txt` with external service disclosure.

## Installation

1. Upload the `cmply` plugin folder to `/wp-content/plugins/cmply`.
2. Activate **CMPly** in WordPress.
3. Go to `Settings > CMPly`.
4. Click **Connect to CMPly** or paste a Site ID manually.
5. Save settings and clear any page cache.

## How The Plugin Connects

The plugin stores configuration in the WordPress database and prints the CMPly SDK on public pages:

```html
<script src="https://cmply.app/sdk/init.js" data-site-id="YOUR_SITE_ID"></script>
```

The SDK then loads CMPly settings and consent data from `cmply.app` in the visitor's browser.

## Connect Button Backend Requirement

The WordPress plugin already points the connect button to:

```text
https://cmply.app/integrations/wordpress/connect
```

The CMPly web app still needs to implement that route. See:

[docs/cmply-app-wordpress-connect.md](docs/cmply-app-wordpress-connect.md)

## Pricing Recommendation

The current recommended commercial packaging for CMPly is documented here:

[docs/pricing-plan.md](docs/pricing-plan.md)

## WordPress.org Package

The plugin source is structured as:

```text
cmply.php
assets/admin.css
includes/class-cmply.php
docs/cmply-app-wordpress-connect.md
readme.txt
uninstall.php
LICENSE
```

For WordPress.org, the installed folder/slug should be:

```text
cmply
```

## Development Notes

- Main plugin file: `cmply.php`
- Main plugin class: `includes/class-cmply.php`
- Admin UI styles: `assets/admin.css`
- WordPress.org metadata: `readme.txt`
- License: GPL-2.0-or-later

## Validation

PHP syntax has been checked with PHP 8.2 for:

- `cmply.php`
- `includes/class-cmply.php`
- `uninstall.php`

No local WordPress server is required to work on the source, but the final flow should be tested on a real WordPress install before submission to WordPress.org.
