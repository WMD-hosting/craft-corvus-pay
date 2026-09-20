# Release Notes for CorvusPay

## 1.0.1 - 2026-09-20

### Fixed
- Default test checkout URL is https://wallet.test.corvuspay.com/ (the former test-wallet host no longer resolves).

## 1.0.0 - 2026-09-20

First public release, after two years in production on Croatian stores.

- Hosted-checkout gateway for Craft Commerce 5 with signed POST redirect, post-back verification and status read-back over the merchant API.
- Installments: fixed, flexible, tiered by amount, dynamic per card brand.
- Refunds and partial refunds from the order's Transactions tab.
- Settings accept environment variables and aliases for the secret, store ID and certificate paths.
- Croatian translations for the control panel.
