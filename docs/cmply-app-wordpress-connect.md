# CMPly WordPress Connect Flow

This document describes what must be implemented in `cmply.app` for the WordPress plugin's **Connect to CMPly** button.

## Goal

Allow a WordPress admin to connect the CMPly plugin without manually copying a Site ID.

The plugin already opens:

```text
https://cmply.app/integrations/wordpress/connect
```

with query parameters:

```text
return_url
site_url
admin_url
plugin_version
source=wordpress
connection_state
```

## Required cmply.app Route

Create a public authenticated route:

```text
GET /integrations/wordpress/connect
```

Expected behavior:

1. Read and validate query parameters.
2. If the user is not logged in, redirect to login/register and preserve the original connect URL.
3. After login, show a WordPress connection page.
4. Let the user choose an existing CMPly site or create a new site for `site_url`.
5. Create a ten-minute, single-use connection code.
6. Redirect back with `site_id`, `connection_id`, `connection_code`, and the fixed CMPly `exchange_url`.
7. WordPress exchanges the code server-to-server and stores the returned API key as a non-autoloaded option.
8. The exchange response includes the current account email, plan, pageviews used this month, and plan pageview limit.

## Query Parameters From Plugin

`return_url`

The WordPress callback URL. CMPly must redirect back here after the site is selected.

Example:

```text
https://example.com/wp-admin/admin-post.php?action=cmply_connect_callback&_wpnonce=...
```

`site_url`

The WordPress site home URL.

Example:

```text
https://example.com
```

`admin_url`

The CMPly settings page in WordPress.

Example:

```text
https://example.com/wp-admin/options-general.php?page=cmply
```

`plugin_version`

Installed WordPress plugin version.

Example:

```text
1.0.0
```

`source`

Should be:

```text
wordpress
```

`connection_state`

A signed, administrator-bound WordPress callback-state token that expires after 30 minutes. CMPly must return this value unchanged as `cmply_state`; it does not need to validate the signature.

## Callback Back To WordPress

After the user chooses or creates a site, redirect to:

```text
{return_url}&cmply_state={connectionState}&site_id={siteId}&connection_id={connectionId}&connection_code={oneTimeCode}&exchange_url=https%3A%2F%2Fcmply.app%2Fapi%2Fintegrations%2Fwordpress%2Fconnect%2Fexchange
```

Optional parameters:

```text
email={userEmail}
plan={planName}
```

Example:

```text
https://example.com/wp-admin/admin-post.php?action=cmply_connect_callback&cmply_state={connectionState}&site_id=site_123&connection_id=connection_123&connection_code={oneTimeCode}&exchange_url=https%3A%2F%2Fcmply.app%2Fapi%2Fintegrations%2Fwordpress%2Fconnect%2Fexchange
```

The plugin exchanges the one-time code and then saves:

- `site_id`
- `connection_id`
- `api_key` in a separate non-autoloaded option
- `account_email` if present
- `plan` if present

Then it redirects to:

```text
/wp-admin/options-general.php?page=cmply&cmply_connected=1
```

## Validation Requirements

Validate `return_url` before redirecting.

Recommended checks:

- Must be a valid URL.
- Must use `https` in production.
- Host should match `site_url` host or an allowed WordPress admin host.
- Path should be `/wp-admin/admin-post.php`.
- Query should include `action=cmply_connect_callback`.

Do not redirect to arbitrary untrusted URLs.

## Suggested UI In cmply.app

Page title:

```text
Connect WordPress to CMPly
```

Show:

- WordPress site URL.
- Current CMPly account email.
- Existing sites matching the domain.
- Button to create a new site.
- Button to connect selected site.

Recommended buttons:

- `Connect site`
- `Create new site`
- `Cancel`

Cancel should redirect to `admin_url` if valid.

## Site Creation Behavior

If the user creates a new site from this flow:

- Use `site_url` as the site domain.
- Normalize protocol and trailing slash according to existing CMPly site rules.
- Create standard default banner settings.
- Return the new site's `siteId` to WordPress.

## Security Notes

The WordPress plugin sends a signed callback-state token separately from `return_url`. CMPly must preserve it and return it unchanged as `cmply_state`. WordPress validates its signature, administrator ID, and expiration when the callback returns.

The plugin also includes a standard WordPress nonce in `return_url`. If that nested nonce survives the redirect, WordPress validates it as an additional check. The signed, administrator-bound `cmply_state` remains mandatory and is sufficient for the callback when the web connection flow drops the nested nonce.

CMPly must still prevent open redirects by validating `return_url`.

The permanent API key must never be placed in a URL, browser storage, HTML, JavaScript, or logs. Only the short-lived one-time code may travel through the callback URL.

## Optional Future Improvement

Implement a server-to-server WordPress integration token later if CMPly needs to push scan status, plan data, or disconnect events into WordPress.

Possible future fields:

- `connection_id`
- `integration_token`
- `site_public_key`

For now, only `site_id` is required.
