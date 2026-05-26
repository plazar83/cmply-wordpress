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
5. Redirect back to the plugin `return_url` with the selected `site_id`.

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

## Callback Back To WordPress

After user chooses or creates a site, redirect to:

```text
{return_url}&site_id={siteId}
```

Optional parameters:

```text
email={userEmail}
plan={planName}
```

Example:

```text
https://example.com/wp-admin/admin-post.php?action=cmply_connect_callback&_wpnonce=abc123&site_id=site_123&email=user@example.com&plan=Free
```

The plugin will save:

- `site_id`
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

The WordPress plugin includes a WordPress nonce in `return_url`.

CMPly does not need to verify the nonce. WordPress verifies it when the callback returns.

CMPly must still prevent open redirects by validating `return_url`.

No secret API key should be exposed in the browser or stored in WordPress for the initial plugin version.

## Optional Future Improvement

Implement a server-to-server WordPress integration token later if CMPly needs to push scan status, plan data, or disconnect events into WordPress.

Possible future fields:

- `connection_id`
- `integration_token`
- `site_public_key`

For now, only `site_id` is required.
