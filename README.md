# Xorro Direct Wallet Payments for WooCommerce

Accept cryptocurrency in WooCommerce **straight into your own wallets** — no payment processor, no custody, no license keys, no phone-home. 238 coins and tokens across 70+ blockchains, with automatic on-chain payment detection.

[![Release](https://img.shields.io/github/v/release/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce)](https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce/releases/latest)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-10.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)

## How it works

1. At checkout the customer picks a coin and sees the exact amount due, live.
2. The order gets a payment page with the amount, your wallet address, a wallet-app QR code, and a countdown for the payment window.
3. The plugin watches the blockchain through public explorer APIs and marks the order paid as soon as a matching payment arrives. The page updates on its own.
4. Funds go directly from the customer's wallet to yours. The plugin only ever stores **public receiving addresses** — never private keys or seed phrases.

Each order gets a slightly unique amount (usually a few base units, e.g. 10 satoshis), so many customers can pay to the same address at the same time and every payment is still matched to the right order.

## Features

- **238 coins and tokens** — 74 native coins plus ERC-20, BEP-20, TRC-20, SPL (Solana) and TON jetton tokens
- **USDT and USDC on 9 networks each** — Ethereum, Arbitrum, Optimism, BNB Chain, Polygon, Avalanche, Base, Solana, TRON — plus DAI on 5
- **Automatic payment detection** on every coin except five manual-only ones (see below), with configurable confirmations
- **Classic and block checkout** (WooCommerce Checkout Blocks) and **HPOS** compatible
- Live crypto quote at checkout; payment page with Copy buttons, QR code (BIP-21, EIP-681, Solana Pay and other wallet URI formats) and countdown
- Payment window, expiry grace period, underpayment tolerance and minimum confirmations
- **Wallet rotation** — several addresses per coin, used in turn
- Optional crypto price next to product prices
- Checkout branding: title, description, custom icon and size, icon/text/both
- Manual **"Mark payment received"** on the order screen, with transaction-ID reuse protection
- **No lost payments:** payment details in customer emails, a reminder before the window closes, partial-payment handling (customer is asked for the rest), overpayment notes, and a late-payment scan that alerts you when money arrives after an order expired
- Admin alerts when an explorer API rejects requests (e.g. a missing or limited API key), so verification never fails silently
- Automatic updates from GitHub Releases — every package must carry the maintainer's Ed25519 signature

### Supported coins

**Native coins (74):** BTC, BCH, ETH, LTC, DOGE, DASH, ZEC, XEC, BNB, SOL, TRX, XMR, XRP, POL (MATIC), AVAX, XLM, DOT, ATOM, SCRT, SEI, INJ, EOS, ETC, ZIL, FIL, ALGO, HBAR, CRO, FTM, EGLD, NEAR, ADA, APT, KAS, TON, BTG, FIRO, RVN, PIVX, NEO, GAS, THETA, TFUEL, DGB, KMD, XVG, QTUM, ARK, AE, ICX, ONT, KLV, TET, XEM, XYM, RUNE, IOTX, STRAX, IOTA, CSPR, ONE, PLS, SYS (NEVM), BRISE, XDC, XTZ, XNO, WAVES, KAIA, STRK and more.

**Tokens (164):** stablecoins (USDT, USDC, DAI, TUSD, USDP, GUSD, PYUSD, USDe, USDD, EURT, XAUT…) and popular tokens such as LINK, UNI, AAVE, SHIB, PEPE, FLOKI, APE, LDO, GRT, 1INCH, CAKE, Notcoin, DOGS, Hamster Kombat and LayerZero.

The full list, with each coin's network and auto-verify status, is on **Xorro Wallet Payments → Coins**.

**Manual-only coins:** Monero (XMR), IoTeX (IOTX), Casper (CSPR), Kaia (KAIA) and Starknet (STRK) have no free, key-less way to detect incoming payments. You can still accept them; confirm each payment with **Mark payment received** on the order.

## Requirements

| | Version |
|---|---|
| WordPress | 6.9+ (tested up to 7.1) |
| WooCommerce | 10.0+ (tested up to 11.1) |
| PHP | 7.4+ (8.2+ recommended) |

HTTPS is strongly recommended.

## Installation

1. Download `xorro-direct-wallet-payments-woocommerce-x.y.z.zip` from the [latest release](https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce/releases/latest) and upload it under **Plugins → Add New → Upload Plugin** (or clone this repository into `wp-content/plugins/`).
2. Activate **Xorro Direct Wallet Payments for WooCommerce**.
3. **Xorro Wallet Payments → Coins** — tick the coins you accept.
4. **Xorro Wallet Payments → Wallets** — add a receiving address for each coin (**+ Add address** for rotation).
5. **Xorro Wallet Payments → Prices & APIs** — add API keys (recommended, see below).
6. **WooCommerce → Settings → Payments** — enable **Pay with Cryptocurrency**.
7. Optional: **Xorro Wallet Payments → General** — payment window, order status after payment, confirmations and checkout branding.

### API keys

Everything works without keys, but free keys raise rate limits and some chains need one for automatic detection.

| Key | Needed for | Get one |
|---|---|---|
| CoinGecko | Exchange rates. Recommended for busy stores — the keyless API is rate-limited. | [coingecko.com/en/api](https://www.coingecko.com/en/api) |
| Etherscan (API V2) | Automatic detection on Ethereum and other EVM chains. Some chains (e.g. BNB Chain, Base) may need a paid plan; the plugin tells you if Etherscan rejects them. | [etherscan.io/apis](https://etherscan.io/apis) |
| TronGrid | Higher TRON limits | [trongrid.io](https://www.trongrid.io/) |
| Helius | Solana and SPL tokens | [helius.dev](https://www.helius.dev/) |
| Subscan | Polkadot | [subscan.io](https://www.subscan.io/) |
| ViewBlock | Zilliqa | [viewblock.io](https://viewblock.io/api) |
| Aptos | Aptos (APT) | [aptoslabs.com](https://aptoslabs.com/developers) |

Keys can also be set in `wp-config.php` so they are never stored in the database: `XDWP_COINGECKO_API_KEY`, `XDWP_ETHERSCAN_API_KEY`, `XDWP_TRONGRID_API_KEY`, `XDWP_HELIUS_API_KEY`, `XDWP_SUBSCAN_API_KEY`, `XDWP_VIEWBLOCK_API_KEY`, `XDWP_APTOS_API_KEY`.

### Updates

The plugin checks this repository's [Releases](https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce/releases) and shows updates in the normal WordPress update screen. Turn on **Enable auto-updates** under **Plugins** to install them automatically. Draft and pre-release tags are ignored. From 1.5.37, an update is offered only if the release is signed with the maintainer's Ed25519 release key, and the downloaded ZIP is checked against both its SHA-256 and that signature before WordPress installs it. The signature covers the plugin name, version and file hash, so a hijacked GitHub account can't push a modified ZIP or re-label an old release as new.

## Compatibility

Version 1.6.0 was tested end to end — guest checkout on both classic and block checkout, live quote, order, payment page, status polling and a PHP error-log check — on WordPress 7.1 and WooCommerce 11.1 with:

- **Themes (20):** Divi, Woodmart, Betheme, The7, Flatsome, Porto, Martfury, Dokan, Astra, OceanWP, GeneratePress, Kadence, Blocksy, Hello Elementor, Neve, Storefront, Twenty Twenty-One / Three / Four / Five.
- **Cache and speed:** WP Rocket, Perfmatters, W3 Total Cache (page cache + minify), WP Super Cache, LiteSpeed Cache (including "delay JS"), WP Fastest Cache, Autoptimize, WP-Optimize, Breeze, Cache Enabler, Hummingbird, Jetpack Boost, SiteGround Optimizer, RabbitLoader.
- **Security:** Wordfence (firewall and rate limiting), Solid Security / iThemes Security Pro, All-In-One Security (6G firewall), Sucuri, Shield, Defender, Limit Login Attempts Reloaded, WPS Hide Login.
- **Store and site:** WooCommerce Subscriptions, WooCommerce Bookings, Product Add-ons, AutomateWoo, Stripe, PayPal Payments, CartFlows, Dokan (Lite + Pro), Checkout Field Editor, Flexible Checkout Fields, CURCY and FOX currency switchers, YITH Wishlist, Variation Swatches, Elementor + Pro, Element Pack, WPBakery, Revolution Slider, LayerSlider, ACF Pro, Yoast SEO (Premium + WooCommerce SEO), Rank Math Pro, TranslatePress, Polylang, Gravity Forms, Contact Form 7, ARMember, Indeed Membership Pro, Ultimate Affiliate, Real Estate Manager Pro, Ajax Search Pro, Redirection, WP Mail SMTP, UpdraftPlus, Duplicator Pro, All-in-One WP Migration, Query Monitor — each alone, plus a 30-plugin stack on Woodmart, Divi, Betheme and Flatsome.

Known issues found in other products while testing: Dokan Pro's Booking module crashes WordPress on its own (missing class in the package); Ultimate Affiliate needs its installer to create its tables; a 30-plugin stack on Woodmart needs more than 256 MB of PHP memory (with or without this plugin).

Built-in compatibility handling:

- Checkout and payment pages are never page-cached (WooCommerce's no-cache rules are respected).
- Payment-page scripts opt out of "delay JavaScript", defer and combine features (LiteSpeed, WP Rocket, Perfmatters, SiteGround Optimizer, Jetpack Boost, Cloudflare Rocket Loader), so the QR code and countdown appear without the customer having to touch the page.
- The checkout script stays out of combined JS bundles, so an error in another plugin's script can't break the coin picker.
- Customer-facing requests use WooCommerce's `?wc-ajax=` endpoint, which security plugins and admin redirects don't interfere with.
- No inline event handlers — works with strict Content-Security-Policy headers.

Known issues outside this plugin: LiteSpeed's **"JS Combine"** option, and Autoptimize with **"Also optimize for checkout"** turned on, break WooCommerce's own block checkout. Leave those off on checkout pages.

## Security

- Only public receiving addresses are stored — never private keys.
- A payment counts only if it goes to your address, is the right asset (token contract / mint / jetton master checked), is newer than the order, is a successful transaction with the required confirmations, and is within the amount band. Amounts are compared with exact integer math.
- Each transaction ID can pay only one order. Explorer errors, missing fields and unexpected responses always count as "not paid" (fail closed).
- All explorer and price requests use HTTPS to fixed endpoints — no user-supplied URLs.
- Admin actions require `manage_woocommerce` plus nonces. Customers can only see their own order (order key or account owner).
- Frontend endpoints are nonce-protected and rate-limited per IP. Use the `xdwp_rate_limit_client_ip` filter to trust a CDN's client-IP header.
- Payout-address changes are notified to the site admin.
- Updates are verified with an Ed25519 signature from a key that never touches GitHub releases (see [Release signing](#release-signing)).

Found a vulnerability? Please open a private [security advisory](https://github.com/x-o-r-r-o/xorro-direct-wallet-payments-woocommerce/security/advisories/new) rather than a public issue.

## Developer hooks

| Hook | Type | Fires when |
|---|---|---|
| `xdwp_order_paid` ( `$order, $txid` ) | action | An order is confirmed paid |
| `xdwp_order_underpaid` ( `$order, $received, $remainder, $txid` ) | action | A partial payment arrives |
| `xdwp_order_overpaid` ( `$order, $excess` ) | action | An order is paid with more than was due |
| `xdwp_order_expired` ( `$order, $previous_status` ) | action | The payment window closes unpaid |
| `xdwp_late_payment_detected` ( `$order, $txid, $amount` ) | action | Money arrives for an expired order |
| `xdwp_ambiguous_payment` ( `$order, $txid, $amount` ) | action | A non-exact transfer could belong to more than one order |
| `xdwp_send_payment_reminder` ( `$order` ) | action | The pre-expiry reminder is due |
| `xdwp_coins` | filter | The coin list is built |
| `xdwp_rate_limit_client_ip` | filter | Rate limiting identifies the client IP |

Email templates can be overridden in your theme under `woocommerce/emails/` (`xdwp-payment-details.php`, `xdwp-payment-reminder.php`, `xdwp-partial-payment.php`, `xdwp-payment-alert.php`, plus `plain/` versions).

## External services

The plugin never contacts the author's servers. It calls public price and blockchain APIs (CoinGecko, Etherscan, mempool.space, Blockchair, TronGrid, toncenter and others) only when a checkout quote is shown or a payment is being verified. The full list — purpose, data sent, terms and privacy links — is in [`readme.txt`](readme.txt) under **External services**. Suggested privacy-policy text is added under **Settings → Privacy**.

## Troubleshooting

| Symptom | Fix |
|---|---|
| "We could not prepare this crypto payment right now" at checkout | The order note says why. Usually the exchange-rate API is rate-limited — add a CoinGecko key. |
| EVM orders never confirm automatically | Add an Etherscan API V2 key. If the Prices & APIs page shows an Etherscan error for a chain, that chain needs a paid Etherscan plan. |
| A payment arrived but the order didn't update | Check **WooCommerce → Status → Logs** (source `xorro-wallet-payments`), make sure WP-Cron runs, then use **Mark payment received** with the transaction ID. |
| QR code or countdown missing on the payment page | Clear your cache/optimization plugin's cache. If the problem persists, exclude `xorro-direct-wallet-payments-woocommerce/assets/js/` from JS optimization. |

## Development

```bash
# Offline smoke tests (PHP CLI; not shipped in the release ZIP)
php tests/smoke-test.php

# Build a release ZIP
bin/build-zip.sh
```

Pushing a `vX.Y.Z` tag runs the Release workflow, which builds the ZIP, its SHA-256 file and an Ed25519 signature (`.sig`) and attaches all three to the GitHub release.

### Release signing

- The workflow signs `xdwp-release:1 / plugin / version / sha256` with the private key in the **`XDWP_SIGNING_KEY`** Actions secret (PKCS#8 PEM), then checks the signature against the public key(s) in `Xdwp_Updater::RELEASE_PUBLIC_KEYS` before publishing. A missing or wrong key fails the release instead of shipping a package sites would refuse.
- Keep an offline backup of the private key (e.g. a password manager). If it is lost, sites can only move to a new key through a release signed with the old one.
- **Rotating the key:** add the new public key to `RELEASE_PUBLIC_KEYS` and release that version signed with the old key; then replace the secret with the new key for later releases, and remove the old public key once sites have updated.
- Generate a key pair with OpenSSL 3: `openssl genpkey -algorithm ed25519 -out release.pem`; the public key for the plugin is `openssl pkey -in release.pem -pubout -outform DER | tail -c 32 | base64`.

## Changelog

Full details for every release are in [`readme.txt`](readme.txt).

### 1.6.0 — no lost payments
- Payment details (amount, address, network, deadline, payment-page button) in the customer on-hold / invoice emails, plus a reminder email before the window closes.
- Partial payments (≥ 50%): the order becomes "partially paid", the customer is emailed and shown the remaining amount, and it completes automatically when the rest arrives.
- Overpayments up to 10% complete the order and are noted for refund; late payments after expiry are detected and emailed to you.
- New WooCommerce emails you can switch on/off and customise: Crypto payment reminder, Crypto partial payment, Crypto payment needs attention (to you).
- Fixes: lock and reservation checks are now safe with Redis/Memcached object caches; checkout no longer retries an amount that is still reserved.
- Security: partial, over and late transfers are only credited when they can belong to a single order; otherwise you get an "unassigned payment" email (prevents another customer's fee-short payment being credited to the wrong order on a shared address). Plus fixes from an independent audit of the release (double counting, expiry races, early/large top-ups, re-paying partially paid orders).

### 1.5.38
- Fix: new coin icons now appear right after an update. Icon URLs are versioned, so browsers and CDNs stop serving old cached icons.
- Admin: the Wallets tab shows each coin's icon and network badge.

### 1.5.37
- Security: updates must be signed with the maintainer's Ed25519 release key. The signature binds the plugin, version and ZIP hash, so a compromised GitHub account or release can't push a modified package or roll sites back to an older one. Unsigned releases are never offered.

### 1.5.36
- Tested on a live store with 14 themes and 53 plugins, plus a security review against the OWASP Top 10.
- Fix: correct payments on 18-decimal assets (BSC USDT, DAI, larger ETH/BNB orders) weren't detected above ~4 units. Matching now uses exact integer math.
- Fix: the unique amount added to each order could reach ~0.005 BTC. Each order now gets the smallest free amount (usually 10 base units).
- Fix: one old abandoned order could block a coin; rate limits could stop resetting and refuse all visitors behind a CDN; other plugins' redirects could break the checkout quote.
- Security: HTTPS-only NEM nodes, Symbol recipient check, TON jetton master check, stricter TRON / Waves / Stellar / EOS checks, per-coin rate freshness, 15-minute quote hold.
- Compatibility: payment scripts opt out of delay/defer/combine; one CoinGecko request for all coins; no inline handlers.
- UX: "Complete your payment below" link on the thank-you page, styled Copy buttons, clearer customer errors, admin notices fixed, Etherscan error alerts, confirmation after "Mark payment received".

### 1.5.35
- Official logos for the last 12 coins that still used a lettered placeholder.

### 1.5.34
- Real logos for 105 coins that showed a lettered placeholder.

### 1.5.33
- Full re-audit: security, correctness, compatibility and documentation.

### 1.5.25 – 1.5.32
- New chains: TON, Cardano, Aptos, Kaspa, Starknet, Kaia, Harmony, PulseChain, Syscoin NEVM, Boba, Bitgert, XDC, Tezos, Nano, Waves, Bitcoin Gold, Firo, Ravencoin, PIVX, NEO/GAS, Theta/TFUEL, DigiByte, Komodo, Verge, Qtum, Ark, Aeternity, ICON, Ontology, Klever, Tectum, NEM, Symbol, THORChain, LGCY, IoTeX, Casper, Lisk, Stratis and IOTA.
- 1.5.29: re-audit; TON destination matching now fails closed.

### 1.5.17 – 1.5.24
- 100+ new coins and tokens on Ethereum, BNB Chain, Polygon, Base, Avalanche, Solana and TRON; Secret, Sei and native Injective; Dash, Zcash and eCash.
- Icons for every coin (1.5.23); exact address matching and real atomic locks (1.5.19); eCash matching fix (1.5.21).

<details>
<summary>Older releases (1.3.1 – 1.5.16)</summary>

### 1.5.16
- Security: XRP credits delivered_amount only; XRP object amounts treated as drops; ATOM transfers require a recipient match

### 1.5.15
- Security: manual mark-paid enforces the same eligibility as the admin UI; txid claim released if mark-paid fails; txids normalised

### 1.5.14
- Security: 0-conf still requires successful transactions; TRON rejects confirmed===false; ZIL amounts always in Qa
- Fix: amount slots released when orders are paid

### 1.5.13
- Security: cancelled/refunded/trashed orders stop auto-verify; XRP rejects validated===false; ZIL requires all success flags
- Fix: admin mark-paid available for expired/failed late payments

### 1.5.12
- Security: NEAR requires explicit success; 0% underpayment tolerance is exact; native TRX requires TransferContract

### 1.5.11
- Security: Subscan/DOT requires explicit success and hash; wider Etherscan/TronGrid/Solana lookbacks

### 1.5.10
- Security: soft-finality chains require explicit success flags; Helius documented auth

### 1.5.9
- Security: expiry last-chance verify, amount-slot locks, updater checksum fail-closed, fresh FX for orders

### 1.5.8
- Security: shared-wallet matching, dust collisions, txid/lock CAS, EVM contract checks, manual mark-paid txid, API key masking/constants

### 1.5.7
- Author URI set to the GitHub profile for wordpress.org header validation

### 1.5.6
- GitHub update ZIP SHA-256 verification and host allowlist; atomic wallet rotation; zero-conf admin warning

### 1.5.5
- Automatic updates from GitHub Releases

### 1.5.4
- TRC-20 auto-verify with default confirmations; admin warnings for rejected wallets and a missing Etherscan key

### 1.5.3
- Admin CSS inlined so settings never render unstyled

### 1.5.2
- Fail-closed confirmation depth; checkout reserves the same amount used after place-order

### 1.5.1
- Blockchair fail-closed matching; Checkout Blocks gateway registration fix

### 1.5.0
- Internal rename to xdwp; checkout quote race fixes; live crypto amount in block checkout

### 1.4.x
- Hardened payment attribution, expiry grace, Copy buttons, coin icons, Base chain, BCH CashAddr, wordpress.org packaging

### 1.3.x
- Wallet URI QR codes (BIP-21, EIP-681, Solana Pay…), coin tiles and payment box, SVG icons, external-services disclosure

</details>

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). Coin logo sources and licenses are listed in [`assets/svg/coins/ATTRIBUTION.txt`](assets/svg/coins/ATTRIBUTION.txt).
