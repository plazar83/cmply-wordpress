# CMPly WordPress Plugin

CMPly connects WordPress to [CMPly.app](https://cmply.app) for cookie consent, consent records, and consent-based script blocking.

The plugin is built for the WordPress.org plugin directory with the public plugin name **CMPly** and the WordPress slug `cmply`.

## Features

- Cookie consent SDK injection in the public `<head>`.
- Early script loading without `defer` or `async` so CMPly can block third-party scripts before they run.
- WordPress admin dashboard inspired by CookieYes-style plugin screens.
- `Connect to CMPly` flow with a short-lived signed callback state, single-use authorization code, and server-side credential exchange.
- Current plan and monthly pageview usage synchronized on connect and verification.
- Manual Site ID fallback.
- Google Consent Mode overview screen.
- Auto-inject or manual embed mode for the CMPly SDK.
- Official HTTPS SDK URL, optional SDK version query, language override, and excluded paths.
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

If the SDK is already inserted manually in the theme or a header manager, turn off **Auto-inject SDK** in Settings > CMPly and keep only one CMPly SDK script on the page.

## Connect Button Backend

The WordPress plugin already points the connect button to:

```text
https://cmply.app/integrations/wordpress/connect
```

WordPress creates a signed callback-state token that expires after 30 minutes. The CMPly web app separately creates a ten-minute single-use authorization code. WordPress exchanges that code server-to-server; the permanent API key is never placed in a callback URL. See:

[docs/cmply-app-wordpress-connect.md](docs/cmply-app-wordpress-connect.md)

## Future Plugin API Requirements

The current connection endpoints and future endpoints for editable WordPress-side settings, GCM controls, analytics summaries, and usage are documented here:

[docs/plugin-endpoints.md](docs/plugin-endpoints.md)

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

Before release, check PHP syntax with PHP 8.2 or newer for:

- `cmply.php`
- `includes/class-cmply.php`
- `uninstall.php`

No local WordPress server is required to work on the source, but the final flow should be tested on a real WordPress install before submission to WordPress.org.
