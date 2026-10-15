# Security policy

This package handles payments, webhooks and stored card tokens, so we take security reports seriously and
appreciate them.

## Supported versions

| Version | Security fixes |
| ------- | -------------- |
| 2.3.x   | Yes            |
| 2.2.x and earlier | No. Please upgrade to the latest 2.x release. |

Fixes are released as a new patch version of the latest minor release.

## Reporting a vulnerability

**Please don't open a public issue, discussion or pull request for a security problem.**

Report it privately through GitHub instead:

1. Open the repository's [Security tab](https://github.com/bajoski34/Laravel/security).
2. Click **Report a vulnerability**.
3. Describe the problem, the affected versions, and how to reproduce it. A failing test or a minimal Laravel
   app is ideal.

If you can't use GitHub, email [developers@flutterwavego.com](mailto:developers@flutterwavego.com) with
"Security: abraham-flutterwave/laravel-payment" in the subject.

Never include real API keys, secret hashes, card numbers or customer data in a report. Use test keys and
placeholder values. If a live key was exposed while you were testing, roll it on the Flutterwave dashboard.

## What happens next

- We'll acknowledge your report within 3 working days.
- We'll confirm whether it's a vulnerability, agree a fix with you, and keep you updated.
- Once a fix is released, we'll publish a GitHub security advisory and credit you, unless you'd rather not be
  named.

## Scope

In scope: code in this repository, including webhook signature verification, transaction re-verification,
the checkout checksum, saved-card token storage, the admin routes and their `flutterwave.manage` check, and the
published Blade, React, Vue and Svelte components.

Out of scope: Flutterwave's API, dashboard and hosted checkout. Report problems with those to Flutterwave
directly, not here.
