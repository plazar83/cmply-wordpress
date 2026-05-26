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
