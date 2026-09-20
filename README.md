# CorvusPay for Craft Commerce

[CorvusPay](https://www.corvuspay.com/) hosted checkout as a Craft Commerce
gateway: cards with installments, refunds and partial refunds from the control
panel, Croatian control-panel translations.

## Requirements

Craft CMS 5.0+, Craft Commerce 5.0+, PHP 8.2+ with curl and SimpleXML, and a
CorvusPay merchant account (a test store from CorvusPay for staging).

## Installation

```sh
composer require wmd/craft-corvus-pay
php craft plugin/install corvus-pay
```

Then **Commerce → System Settings → Gateways → New gateway**, type
**CorvusPay**.

## Settings

**Settings → Plugins → CorvusPay**

| Setting | Notes |
|---|---|
| Store ID, Secret key | from the CorvusPay merchant portal; environment variables are supported |
| Checkout URL | `https://wallet.test.corvuspay.com/` while testing, `https://wallet.corvuspay.com/` in production |
| Merchant API URL | `https://testcps.corvus.hr/` while testing, `https://cps.corvus.hr/` in production |
| Certificate file, private key file, key password | the client certificate CorvusPay issued for the store; needed for status checks and refunds; keep the files outside the web root (`@root/certs/…`) |
| Installments mode | none, fixed (`number_of_installments`), flexible (`payment_all`), tiered by order amount, or dynamic per card brand (`payment_all_dynamic` + `payment_<brand>`) |
| Logo | shown next to the payment method, `craft.corvus.logo` in templates |
| Fallback success / cancel URL | used when the order carries no return or cancel URL |

In the CorvusPay merchant portal enter the two post-back URLs the settings
page prints:

```
Success URL: https://example.com/index.php?p=actions/corvus-pay/payment/success
Cancel URL:  https://example.com/index.php?p=actions/corvus-pay/payment/cancel
```

## How a payment runs

1. The customer picks the CorvusPay gateway and submits the Commerce payment
   form (`commerce/payments/pay`). The plugin builds the signed parameter set
   (amount, currency, order number, installments, cardholder data truncated to
   the manual's limits) and posts it to the hosted checkout. If the site sets
   Commerce's `gatewayPostRedirectTemplate`, that template renders the form.
2. CorvusPay posts the result to the success or cancel URL. The plugin verifies
   the HMAC-SHA256 signature, reads the transaction status back from the
   merchant API over the client certificate, records a purchase transaction on
   the order and redirects to the order's return or cancel URL.
3. Refunds from the order's Transactions tab: a full refund voids the
   transaction, a partial refund lowers the captured amount.

Currencies: the ISO 4217 codes CorvusPay lists (EUR, USD, GBP, CHF, BAM, RSD,
CZK, HUF, PLN, SEK, NOK, DKK, AUD, CAD).

## Testing

Use the test store credentials CorvusPay provides together with the test wallet
URLs above; the test cards are listed in the CorvusPay integration manual.

## License

MIT. Developed by [WMD](https://wmd.hr).
