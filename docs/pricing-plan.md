# CMPly Pricing Plan Recommendation

## Summary

The current pricing direction is good, but the Free tier is too generous and may reduce upgrade pressure or create unnecessary infrastructure cost.

Recommended positioning:

- Keep CMPly cheaper and simpler than established competitors.
- Make Free useful for testing and small sites, but not generous enough for serious production usage.
- Make Pro the obvious best-value plan.
- Avoid hard "unlimited pageviews" promises unless they are backed by enterprise controls.

## Recommended Tiers

### Free

```text
$0/month
1 site
10k pageviews/month
CMPly branding
Basic analytics, 30 days
Basic banner customization
```

Notes:

- Current 50k pageviews/month is likely too generous.
- 10k still feels more generous than many competitors, while keeping upgrade pressure.
- If infrastructure cost becomes sensitive, reduce to 5k.

### Starter

```text
$9/month
1 site
200k pageviews/month
Remove branding
Full customization
Custom categories
Advanced analytics, 60 days
Export data
```

Notes:

- $5/month is attractive but may be too low for a SaaS with SDK traffic, consent logs, scanner, and analytics.
- $9/month is still inexpensive compared with CookieYes/Cookiebot-style competitors.

### Pro

```text
$19/month
Up to 3 sites
1M pageviews/month
Everything in Starter
1 year analytics retention
Daily cookie scanner
Webhook integrations
Priority support
```

Notes:

- Keep Pro at $19 initially.
- This should be the best-value plan and the main upgrade target from the WordPress plugin.
- Could be increased to $29 later after product maturity.

### Enterprise

```text
From $99/month
Custom pageviews
Unlimited or custom site allowance
Everything in Pro
Auto cookie scanner
White-label option
Dedicated support
SLA 99.9% uptime
Custom contract
```

Notes:

- Prefer "From $99/month" over a fixed hard promise.
- Avoid "Unlimited pageviews" unless there is fair-use language or account-level controls.
- Enterprise should support custom limits, invoices, SLA, and white-label terms.

## Suggested Pricing Table Copy

Use:

```text
Enterprise
From $99/month
For agencies and high-volume organizations
```

Instead of:

```text
Enterprise
$99/month
Unlimited page views
```

## WordPress Plugin CTA

The WordPress plugin should continue to promote:

```text
Try Pro for free
```

Top-bar usage copy can start with:

```text
Current plan: Free
Pageviews used: 0/10,000 (0%)
```

When real plan usage is available through CMPly API, replace the static display with live plan data.

## Competitive Context

CookieYes commonly positions Free around low pageview limits and paid tiers around Basic/Pro/Ultimate pricing. CMPly can compete by offering:

- More generous Starter and Pro pageview limits.
- Developer-friendly script blocking.
- Cleaner WordPress onboarding.
- Better value for multi-site Pro users.

Cookiebot often prices by subpages/domains instead of pageviews, which can become confusing or expensive. CMPly should keep pricing predictable and easy to understand.

## Implementation Tasks For cmply.app

- Update pricing cards in the public pricing page.
- Update Paddle/checkout price IDs if pricing changes from $5 to $9.
- Update subscription limit constants.
- Update plan enforcement for pageviews, sites, scanner frequency, analytics retention, and branding.
- Expose current plan and pageview usage to the WordPress plugin later.
- Add fair-use language for Enterprise/high-volume customers.

## Current Pageview Limit Status In cmply.app

Pageview limits are not just marketing text. The service already has real limit constants and usage tracking:

- `lib/subscription.ts` defines `PLAN_LIMITS.maxPageViews`.
- `lib/subscription.ts` exposes `hasExceededPageViews(plan, currentPageViews)`.
- `/api/sites/[siteId]/view` increments `DailyStats.pageViews`.
- `/api/auth/me` recalculates `subscription.usage.pageViewsThisMonth` from `DailyStats`.
- Billing UI displays current usage versus plan limits.

However, enforcement is currently incomplete:

- `/api/sites/[siteId]/view` tracks pageviews but does not block or alter behavior after the limit.
- `/api/consent` checks the pageview limit and can return `403`.
- Blocking consent logging at the pageview limit is risky because it can break the consent flow exactly when a high-traffic customer exceeds their plan.

## Recommended Pageview Enforcement Behavior

Do not break the public consent banner when a customer exceeds their pageview limit.

Recommended behavior after pageview limit is exceeded:

1. Continue serving the CMPly SDK.
2. Continue showing the banner and collecting essential consent choices.
3. Continue necessary compliance behavior.
4. Mark account/site as `limitExceeded`.
5. Show upgrade warnings in dashboard and WordPress plugin.
6. Restrict premium features instead of breaking consent:
   - advanced analytics
   - export data
   - extended retention
   - scanner scheduling
   - webhooks
   - white-label/custom branding depending on plan
7. Optionally stop counting/reporting analytics beyond the plan limit, but do not fail the consent API with a hard `403`.

## Suggested cmply.app Tasks For Pageview Enforcement

- Move hard pageview-limit behavior out of `/api/consent`.
- Add a shared helper, for example:

```ts
getPlanUsageState(user): {
  plan: PlanType
  pageViewsThisMonth: number
  maxPageViews: number
  limitExceeded: boolean
  usagePercent: number
}
```

- Update `/api/sites/[siteId]/view` to:
  - keep counting pageviews;
  - optionally return `{ limitExceeded: true }`;
  - avoid expensive user lookups on every hit later by using cache/Redis if traffic grows.

- Update `/api/consent` to:
  - always accept essential consent records if the site exists;
  - include `limitExceeded: true` in the response when applicable;
  - avoid returning `403` just because pageviews are exceeded.

- Update dashboard and billing UI to show:
  - warning at 80%;
  - stronger upgrade prompt at 100%;
  - plan-specific next recommended upgrade.

- Add an API endpoint for the WordPress plugin later:

```text
GET /api/sites/{siteId}/plan-usage
```

Suggested response:

```json
{
  "ok": true,
  "plan": "free",
  "pageViewsThisMonth": 8123,
  "maxPageViews": 10000,
  "usagePercent": 81.23,
  "limitExceeded": false,
  "upgradeUrl": "https://cmply.app/pricing"
}
```

- Update WordPress plugin top bar from static:

```text
Pageviews used: 0/5,000 (0%)
```

to live CMPly API data once `/plan-usage` exists.
