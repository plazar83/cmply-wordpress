# CMPly WordPress Plugin Endpoint Requirements

This document lists the CMPly service endpoints needed for the WordPress plugin to move beyond SDK installation and become a full integration.

## Current Plugin Behavior

The plugin can use a manually entered Site ID without private service endpoints. Automatic connection uses the exchange and verification endpoints below.

It stores local settings in WordPress and prints the public CMPly SDK:

```html
<script src="https://cmply.app/sdk/init.js" data-site-id="SITE_ID"></script>
```

The SDK already uses public browser endpoints such as:

```text
GET  /api/sites/{siteId}
GET  /api/sites/{siteId}/cookies-list
GET  /api/sites/{siteId}/providers
POST /api/sites/{siteId}/view
POST /api/consent
```

These endpoints are enough for banner rendering, script blocking, consent storage, pageview tracking, and public visitor behavior.

## Integration Authentication

Any endpoint that lets WordPress read private analytics or update site settings needs the plugin API key returned by the one-time exchange.

The browser callback returns only:

```text
site_id
connection_id
connection_code
exchange_url
email
plan
```

WordPress exchanges the short-lived code server-to-server and stores the returned API key. Future private endpoints should authenticate server-side, for example with:

```http
Authorization: Bearer API_KEY
```

The CMPly service must verify:

- token is valid and active
- token belongs to the requested `siteId`
- token has the required scope
- token can be revoked when the site is disconnected

Recommended token scopes:

```text
wordpress:read
wordpress:settings:write
wordpress:gcm:write
wordpress:analytics:read
```

## Required Endpoints

### One-time connection exchange

The connect page redirects WordPress with a ten-minute, single-use code.

```text
GET /integrations/wordpress/connect
```

After the user selects a site, redirect to:

```text
{return_url}&site_id={siteId}&connection_id={connectionId}&connection_code={code}&exchange_url={fixedExchangeUrl}&email={email}&plan={plan}
```

WordPress then calls `POST /api/integrations/wordpress/connect/exchange` with `connectionId`, `connectionCode`, and `siteUrl`. CMPly stores only the code hash, consumes the code atomically, and returns the permanent API key only in the HTTPS response body.

### Verify connection

```text
POST /api/integrations/wordpress/connect/verify
```

WordPress sends `connectionId`, `siteId`, `apiKey`, and `siteUrl` server-to-server. The API verifies all identifiers and records the latest successful verification time.

### Connection Status

Used by the WordPress plugin diagnostics panel.

```text
GET /api/integrations/wordpress/sites/{siteId}/status
```

Auth:

```text
Authorization: Bearer CONNECTION_TOKEN
```

Response:

```json
{
  "ok": true,
  "siteId": "site_123",
  "domain": "example.com",
  "plan": "Free",
  "accountEmail": "owner@example.com",
  "sdk": {
    "baseUrl": "https://cmply.app",
    "recommendedVersion": "3"
  },
  "features": {
    "analytics": true,
    "gcm": true,
    "scanner": true,
    "translations": true
  }
}
```

### Disconnect Integration

Used when the WordPress admin clicks Disconnect.

```text
DELETE /api/integrations/wordpress/sites/{siteId}/connection
```

Auth:

```text
Authorization: Bearer CONNECTION_TOKEN
```

Behavior:

- revoke the connection token
- mark the WordPress integration as disconnected
- do not delete the CMPly site or consent records

Response:

```json
{
  "ok": true
}
```

### Google Consent Mode Settings

Used by the editable WordPress GCM tab. Requests are server-to-server and include the saved connection credentials in the JSON body.

```text
POST /api/integrations/wordpress/sites/{siteId}/gcm
PUT /api/integrations/wordpress/sites/{siteId}/gcm
```

Auth:

```text
siteId, connectionId, apiKey, siteUrl
```

Suggested response/body:

```json
{
  "enabled": true,
  "mode": "advanced",
  "defaults": [
    {
      "region": "all",
      "analytics_storage": "denied",
      "ad_storage": "denied",
      "functionality_storage": "denied",
      "security_storage": "granted",
      "ad_user_data": "denied",
      "ad_personalization": "denied"
    }
  ]
}
```

Validation:

- `mode` must be `basic` or `advanced`
- consent values must be `granted` or `denied`
- region must be `all` or valid ISO country/region codes

### Analytics Summary

Used to populate a small WordPress dashboard card without exposing the full CMPly dashboard.

```text
GET /api/integrations/wordpress/sites/{siteId}/analytics-summary?period=7d
```

Auth:

```text
Authorization: Bearer CONNECTION_TOKEN
```

Supported periods:

```text
7d
30d
90d
```

Response:

```json
{
  "ok": true,
  "period": "7d",
  "overview": {
    "totalConsents": 120,
    "acceptRate": 73,
    "totalPageViews": 1540,
    "rejected": 22
  },
  "timeline": [
    {
      "date": "2026-05-28",
      "pageViews": 210,
      "accepted": 18,
      "rejected": 3
    }
  ]
}
```

This can reuse the existing CMPly dashboard logic from:

```text
GET /api/sites/{siteId}/analytics?period=7d
```

but it must use plugin token authorization instead of dashboard session authorization.

### Scan Summary

Used by WordPress to show simple cookie scan state without embedding the whole scanner UI.

```text
GET /api/integrations/wordpress/sites/{siteId}/scan-summary
```

Auth:

```text
Authorization: Bearer CONNECTION_TOKEN
```

Response:

```json
{
  "ok": true,
  "lastScanAt": "2026-05-28T08:00:00.000Z",
  "status": "completed",
  "pagesScanned": 24,
  "cookiesFound": 18,
  "providersFound": 7
}
```

### Plan Usage Summary

Used by the WordPress plugin header if it should show real plan/pageview usage.

```text
GET /api/integrations/wordpress/sites/{siteId}/usage
```

Auth:

```text
Authorization: Bearer CONNECTION_TOKEN
```

Response:

```json
{
  "ok": true,
  "plan": "Free",
  "pageViews": {
    "used": 1240,
    "limit": 5000,
    "percent": 25
  }
}
```

## Optional Endpoints

### Open Dashboard Deep Links

WordPress can keep using plain links to CMPly, but a deep-link helper could send admins to the right page.

```text
GET /api/integrations/wordpress/sites/{siteId}/links
```

Response:

```json
{
  "dashboard": "https://cmply.app/dashboard/sites/site_123",
  "settings": "https://cmply.app/dashboard/sites/site_123/settings",
  "analytics": "https://cmply.app/dashboard/sites/site_123/analytics",
  "scanner": "https://cmply.app/dashboard/sites/site_123/scan-results"
}
```

### Token Rotation

Used if the WordPress admin wants to refresh the integration secret.

```text
POST /api/integrations/wordpress/sites/{siteId}/connection/rotate-token
```

Response:

```json
{
  "ok": true,
  "connection_token": "new_token"
}
```

## WordPress Plugin Storage

Once private endpoints exist, WordPress should store:

```text
site_id
connection_id
api_key
account_email
plan
```

The token should be treated as a secret:

- never print it in admin HTML
- never expose it to frontend JavaScript
- send it only from server-side WordPress HTTP requests
- delete it on disconnect

## Recommended Implementation Order

1. Complete and test the one-time exchange and verification flow.
2. Add connection status and remote revocation endpoints.
3. Add disconnect endpoint.
4. Add GCM read/write endpoints. (Complete)
5. Add analytics summary endpoint.
6. Add scan summary and usage endpoints.

Until these endpoints exist, the WordPress plugin should keep advanced settings and analytics as links to the CMPly web app.
