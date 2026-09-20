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
- **Backup exchange-rate sources** (Coinbase, Kraken, Binance) when CoinGecko is unavailable, with a 5% agreement check
- **Stablecoins priced 1:1** with your store currency (optional)
- Payment window, expiry grace period, underpayment tolerance and minimum confirmations
- **Wallet rotation** — several addresses per coin, used in turn, or a **fresh address per order** from your own extended public key (BTC, LTC, DOGE)
- Optional crypto price next to product prices
- Checkout branding: title, description, custom icon and size, icon/text/both
- Manual **"Mark payment received"** on the order screen, with transaction-ID reuse protection
- **Coin search and per-coin order limits** at checkout, with coin names under each icon
- **Confirmations suited to each chain**, overridable per coin
- **Destination tag / memo per order** on the chains that carry one
- **Re-quote an expired order** — the customer gets a fresh amount, you keep the order
- **No lost payments:** payment details in customer emails, a reminder before the window closes, partial-payment handling (customer is asked for the rest), overpayment notes, and a late-payment scan that alerts you when money arrives after an order expired
- **Payments screen** listing every crypto order, with a "needs you" filter, plus a crypto payment column on the orders list
- Admin alerts when an explorer API rejects requests (e.g. a missing or limited API key), so verification never fails silently
- **Help screen** in the plugin, and translations for sixteen languages
- Automatic updates from GitHub Releases — every package must carry the maintainer's Ed25519 signature

### Supported coins

**Native coins (71):** BTC, BCH, ETH, LTC, DOGE, DASH, ZEC, XEC, BNB, SOL, TRX, XMR, XRP, POL (MATIC), AVAX, XLM, DOT, ATOM, SCRT, SEI, INJ, EOS, ETC, ZIL, FIL, ALGO, HBAR, CRO, FTM, EGLD, NEAR, ADA, APT, KAS, TON, BTG, FIRO, RVN, PIVX, NEO, GAS, THETA, TFUEL, DGB, KMD, QTUM, ARK, AE, ICX, ONT, KLV, TET, XEM, XYM, RUNE, IOTX, STRAX, IOTA, ONE, PLS, SYS (NEVM), BRISE, XDC, XTZ, XNO, WAVES, KAIA and more.

**Tokens (164):** stablecoins (USDT, USDC, DAI, TUSD, USDP, GUSD, PYUSD, USDe, USDD, EURT, XAUT…) and popular tokens such as LINK, UNI, AAVE, SHIB, PEPE, FLOKI, APE, LDO, GRT, 1INCH, CAKE, Notcoin, DOGS, Hamster Kombat and LayerZero.

The full list, with each coin's network and auto-verify status, is on **Xorro Wallet Payments → Coins**.

**Manual-only coins:** Monero (XMR), IoTeX (IOTX) and Kaia (KAIA) have no free, key-less way to detect
incoming payments. You can still accept them; confirm each payment with **Mark payment received** on
the order. Monero is a property of the protocol — incoming payments are visible only to the holder of
the private view key, which this plugin will never ask for. IoTeX and Kaia are waiting on an API key:
both have explorers that require one.

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

Version 1.19.0 was tested end to end on WordPress 7.1 and WooCommerce 11.1 — guest checkout on both
classic and block checkout, live quote, order placement, payment page, status polling, the "I have
sent the payment" path, and a PHP error-log check on every single run.

**Themes — 44 of 44 clean, on both checkouts:** Astra, Avada (and its child theme), Beratung, Betheme,
Blocksy, Bricks, Divi, Dokan, Electro, Enfold, Fixera, Flatsome, GeneratePress, Hello Elementor,
Impreza, Kadence, Martfury, Neve, Newspaper, OceanWP, Ohio, Porto, Saira, Salient, Savoy (and child),
Shoptimizer, Storefront, The7, Thrive Theme, Uncode (and child), Vault, Wolmart, Woodmart, X, XStore,
asri, bb-theme, and Twenty Twenty-One / Three / Four / Five.

**Plugins — 236 tested one at a time, 230 clean.** Every plugin on the test site was activated on its
own alongside the gateway and taken through a full crypto checkout. The list spans caching and
performance (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, WP Fastest Cache, Autoptimize,
Perfmatters, Breeze, Cache Enabler, Hummingbird, SiteGround Optimizer, Jetpack Boost, RabbitLoader,
SpeedyCache, Seraphinite, Asset CleanUp Pro, WP-Optimize), security (Wordfence, Solid Security,
All-In-One Security, Sucuri, SecuPress, Shield, Defender, SiteGuard, Loginizer, Limit Login Attempts
Reloaded, WPS Hide Login, Really Simple SSL), the WooCommerce ecosystem (Subscriptions, Bookings,
Product Add-ons, Product Vendors, Warranty, Order Barcodes, Services, Payments, Stripe, PayPal
Payments, AutomateWoo, CartFlows, CommerceKit, Checkout Field Editor, Flexible Checkout Fields, CURCY,
FOX, Google Listings & Ads, WooLoyalty, Easy Digital Downloads), marketplaces (Dokan Lite and Dokan
Pro), page builders (Elementor and Elementor Pro, WPBakery, Beaver Builder, Fusion/Avada Builder,
Kadence Blocks Pro, Royal/Premium/Essential/Sky addons, Element Pack, ElementsKit), multilingual and
SEO (WPML and its add-ons, Polylang, TranslatePress, GTranslate, Loco Translate, Yoast Premium +
Video + WooCommerce SEO, Rank Math Pro, AIOSEO), forms and membership (Gravity Forms, Contact Form 7,
WPForms, Fluent Forms, Forminator, Ninja Forms, SureForms, ARMember, Indeed Membership Pro,
MasterStudy LMS, FluentCommunity), and site tooling (ACF Pro, Meta Box, Redux, JetPack, UpdraftPlus,
Duplicator Pro, All-in-One WP Migration, WPvivid, Backuply, MainWP Child, Query Monitor, Code
Snippets, WP File Manager, TablePress, The Events Calendar, Smush, EWWW, Imagify, and more).

**Stacks — all clean:** cache-heavy (WP Rocket + Perfmatters + Autoptimize + WP-Optimize),
security-heavy (Wordfence + Solid Security + Shield + Limit Login Attempts), builder-heavy (Elementor
+ WPBakery + Slider Revolution + LayerSlider + Kirki), store-heavy (Subscriptions + Bookings + Product
Add-ons + AutomateWoo + CartFlows), and translation + multi-currency (Polylang + TranslatePress +
CURCY + FOX). A 30-plugin stack was also run on Woodmart, Divi, Betheme and Flatsome.

**Coins — all 238 priced and quoted** against live market data, and 50+ of the chain readers were
driven against their real explorers to confirm a payment in that coin can actually be detected.

Problems found in other products while testing, none of them caused by this plugin:

- **Dokan Pro's Booking module** takes the whole site down with a fatal error (`DependencyNotice` class
  missing from the package). Verified with this plugin fully deactivated — the site still fails. Keep
  that module off until Dokan fix it.
- **Ultimate Affiliate** queries a `wp_uap_referrals` table its own installer never created, logging a
  database error on every order from any gateway.
- **Essential Addons for Elementor (Lite)** redirects the site's front page on its own, with this
  plugin deactivated.
- **Avada's child theme** enqueues `child-style` against an unregistered `avada-stylesheet`, which
  WordPress 6.9+ logs as an incorrect-usage notice. Present with this plugin deactivated.
- **LiteSpeed Cache "JS Combine"**, and **Autoptimize with "Also optimize for checkout"**, break
  WooCommerce's own block checkout. Leave both off on checkout pages.
- A 30-plugin stack on Woodmart needs more than 256 MB of PHP memory, with or without this plugin.

Three plugins could not be tested because their free base plugin is not installed and they refuse to
activate without it (Bit Integrations Pro, Bit Social Pro, Presto Player Pro), and one (Stellar)
refuses to activate at all.

Built-in compatibility handling:

- Checkout and payment pages are never page-cached (WooCommerce's no-cache rules are respected).
- Payment-page scripts opt out of "delay JavaScript", defer and combine features (LiteSpeed, WP Rocket,
  Perfmatters, SiteGround Optimizer, Jetpack Boost, Cloudflare Rocket Loader), so the QR code and
  countdown appear without the customer having to touch the page.
- The checkout script stays out of combined JS bundles, so an error in another plugin's script can't
  break the coin picker.
- Customer-facing requests use WooCommerce's `?wc-ajax=` endpoint, which security plugins and admin
  redirects don't interfere with.
- No inline event handlers — works with strict Content-Security-Policy headers.

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
| `xdwp_payment_detected` ( `$order, $txid, $amount` ) | action | A matching transfer is seen on chain, before it has the confirmations required |
| `xdwp_payment_renewed` ( `$order` ) | action | A customer re-quotes an expired order |
| `xdwp_refund_address_given` ( `$order, $address` ) | action | A customer gives an address for their refund |
| `xdwp_refund_sent` ( `$order, $txid` ) | action | The shop records the refund it sent |
| `xdwp_coins` | filter | The coin list is built |
| `xdwp_payment_window_minutes` ( `$minutes, $order, $coin` ) | filter | The payment window is set for an order |
| `xdwp_confirmations_required` ( `$confirmations, $coin` ) | filter | Confirmations for a coin are resolved |
| `xdwp_order_confirmations_required` ( `$confirmations, $coin, $order` ) | filter | Confirmations are resolved for one order, after any value tier |
| `xdwp_coin_adjustment` ( `$percent, $coin_id` ) | filter | A coin's discount or surcharge is read |
| `xdwp_notification_payload` ( `$payload, $event, $order` ) | filter | An alert is about to be queued |
| `xdwp_wallet_add_chain` ( `$params, $coin, $chain_id` ) | filter | A browser wallet does not know the chain (empty by default: no RPC is supplied) |
| `xdwp_coin_allowed_for_total` ( `$allowed, $coin_id, $total` ) | filter | A coin is offered (or hidden) for an order total |
| `xdwp_order_memo` ( `$memo, $order, $coin` ) | filter | A destination tag / memo is generated |
| `xdwp_payment_uri` ( `$uri, $coin_id, $address, $amount, $memo` ) | filter | The wallet link / QR code is built |
| `xdwp_rate_limit_client_ip` | filter | Rate limiting identifies the client IP |

The refund claim page can be overridden at `yourtheme/xorro-direct-wallet-payments-woocommerce/xdwp-refund-claim.php`. Email templates can be overridden in your theme under `woocommerce/emails/` (`xdwp-payment-details.php`, `xdwp-payment-reminder.php`, `xdwp-partial-payment.php`, `xdwp-payment-alert.php`, plus `plain/` versions).

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

[`docs/DEVELOPMENT-HISTORY.md`](docs/DEVELOPMENT-HISTORY.md) is the engineering record: what every
release put into the code, which planned work is still outstanding and why, where each piece of
machinery lives, and the invariants a change must not break. Read it before adding a feature —
several things that look missing were decided against for a stated reason.

```bash
# Offline smoke tests (PHP CLI; not shipped in the release ZIP)
php tests/smoke-test.php
php tests/matching-tests.php
php tests/hd-tests.php

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

### 1.19.0 — refunds, alerts, and settings you can carry

- Refunds by claim link: the customer gives an address they control, you send it from your own wallet, the plugin records it. Nothing is ever sent by the plugin itself
- Signed webhooks (HMAC-SHA256 over timestamp + body) and Telegram alerts for part payments, overpayments, late money, vanished payments and refunds waiting to go out
- A daily summary of what was paid and what still needs you
- Settings export and restore, with API keys held back unless you ask for them
- A discount or surcharge per coin, shown on the order as its own line
- Confirmations tiered by order value — quick on small orders, careful on large ones
- Bundled translations refreshed across all sixteen locales

### 1.18.0 — find any payment, and see how crypto is doing

- Orders can be searched by transaction id or receiving address, in WooCommerce's own order search
- Every order keeps a timeline of what happened to its payment, shown on the order screen
- "Check now" and "Give another hour" actions beside each payment on the Payments screen
- A summary of the last 7, 30 or 90 days: taken, typical wait, walked away, sent too little — overall and per coin
- Fixed: the countdown read "1431:41" on a stablecoin order with a day to pay; long windows now read "23h 51m"
- Fixed: the payment page named the network "TRX · trc20" where it should have said "TRON (TRC-20)"

### 1.17.0 — pay from a wallet in the browser

- **"Pay with MetaMask"** (or Rabby, Coinbase Wallet, Brave, TronLink…) appears when the customer actually has one — discovered via EIP-6963 / TIP-6963 rather than fighting over `window.ethereum`
- Chain is switched **and re-checked** before sending, so a payment cannot leave on the wrong network
- Every wallet refusal is explained: cancelled, already prompting, insufficient gas, wrong network, network not added
- Token payments use `transfer` only — **never** an approval prompt
- The address and QR stay visible; a reported transaction hash is recorded, never trusted — the chain still confirms the order

### 1.16.0 — amounts people can actually send

- **Stablecoins are quoted in cents.** Asking for 17.340010 USDT is asking for a payment most exchanges cannot make; orders are now spaced a cent apart and still told apart
- The uniqueness surcharge is **capped at 1% of the order**
- **Vanished payments**: a transfer that leaves the mempool (replaced or too cheap) no longer leaves the page claiming "payment detected" — the order returns to waiting with a note, and a fee-bumped replacement is followed
- **What happened** to a payment — part paid, overpaid, paid late, vanished — is recorded beside its status and shown on the Payments screen
- **Pegged coins get a day to pay**: no price can move, so no reason to hurry

### 1.15.0 — accessibility and mobile

- **Keyboard**: the coin picker had no visible focus at all; it does now
- **Screen readers**: status changes are announced, and the countdown speaks at thresholds rather than every second
- **Right-to-left**: addresses and amounts isolated from page direction, QR never mirrored
- **Phones**: wallet button first, QR behind a toggle
- **Stubborn wallets**: an address-only QR for apps that refuse a code containing an amount
- Polling pauses in background tabs and resumes on return

### 1.14.0 — prove the setup works before money is involved

- **"Test this coin"** on the Wallets tab: checks the address, the rate, whether this site can read that chain, and the confirmations in force — and names the failing step (including "no Etherscan key saved") rather than failing silently
- Store-wide problems surface on the Payments screen and in **WooCommerce → Status**
- With an extended public key, the **next three addresses** are shown to check against your own wallet
- Payment page now warns which network to send on, says how long confirmation takes for that coin, and tells the customer they can close the page
- Coins tab loads icons lazily

### 1.13.0 — fixes from an independent security audit

Audit of everything added since 1.6.1. Two critical, one high and several medium findings, all fixed and covered by tests:

- **Litecoin/Dogecoin addresses from an `xpub`** were written with Bitcoin's version byte. Address encoding is now decided by the coin, not the key
- **Master keys were accepted** where an account key was expected; only depth-3 account keys are taken now, and the Wallets tab shows the next address to check against your wallet
- **Confirmations above 1 on instant-finality chains** silently disabled verification for that coin; those values are refused with an explanation
- **CSV formula injection** through customer names in the payments export
- **Re-quoting an expired order** could orphan a payment already in flight, and left stale "payment detected" state behind
- **"Needs you" only searched recent orders**, so an old late payment could disappear; the flag is now written when it happens
- **HD addresses could run past a wallet's gap limit** through abandoned orders; expired orders give their address back and the gap is surfaced
- **Destination-tag collisions** could credit one customer's payment to another's order
- Detection now shares the store-wide explorer budget; quote rate limiting is scoped per shopper, not per proxy IP

### 1.12.1 — Help, tidied

- Help moved to the end of the menu, after Prices & APIs
- Search box that filters the page as you type, a contents list that tracks the section you are reading, and one card per topic
- Portuguese (Brazil) completed; Arabic and Polish improved

### 1.12.0 — a Help screen, and sixteen languages

- **Help tab** covering every setting, how payments are matched, the customer's view, troubleshooting and the developer hooks
- **Bundled translations** for sixteen locales (machine-translated; a site's own translation always takes precedence)

### 1.11.0 — a fresh address for every order (optional)

- Paste your wallet's **receiving account key** (xpub / ypub / zpub, Ltub, dgub) on the Wallets tab and every order gets an address of its own, which removes shared-address ambiguity entirely
- The key is public: it derives addresses and cannot spend. Private keys and malformed keys are refused on save
- Derivation is verified against the published BIP32 / BIP44 / BIP49 / BIP84 vectors in CI
- Leave it empty to keep using your saved addresses with rotation

### 1.10.1 — Payments screen on a phone

- Tabs move above the content and each order becomes a labelled card, instead of a table scrolling sideways
- The table fits the panel at every width, with the order count beside the page links

### 1.10.0 — reassurance for the customer, less hunting for the shop owner

- **"I have sent the payment"** on the payment page: checks the chain immediately, then watches closely for five minutes
- The waiting message now names the number of confirmations still needed
- **Count on the Payments menu** for orders that need you
- **CSV export** of the Payments screen, following the filters on screen
- Payments table rebuilt to fit the panel: five columns, full transaction ids, sticky header, and pagination showing the order count

### 1.9.1 — explorer links and a tidier Payments table

- Transaction ids link to the chain's public explorer (Payments screen and order screen), with a Copy button
- Full transaction ids, a table that scrolls instead of clipping, and pagination that says how many orders there are

### 1.9.0 — every crypto payment in one place

- **Payments screen**: every crypto order with the amount expected, the amount received, the coin, the state and the transaction, filtered by state or coin
- **"Needs you"** list for the orders that will not finish by themselves — late money, overpayments, and part payments left after the window closed
- **Crypto payment column** on WooCommerce → Orders
- More developer hooks: `xdwp_payment_window_minutes`, `xdwp_confirmations_required`, `xdwp_coin_allowed_for_total`, `xdwp_order_memo`, `xdwp_payment_uri`

### 1.8.0 — confirmations that fit the chain, payments that identify themselves

- **Confirmations per chain**: each coin waits for a number suited to its own chain (Bitcoin 2, Ethereum 12, TRON 20…) instead of one number for all 238 coins. Your store-wide number stays the floor, so this can only make verification stricter; chains that finalise outright (XRP, Stellar, Cosmos, TON…) still verify at one confirmation
- **Confirmations per coin** on the Coins tab, with the number currently in force shown in grey
- **Destination tag / memo per order** on XRP, Stellar, Cosmos, Secret, Sei, Injective, EOS, Hedera and TON — shown on the payment page, in customer emails, in the wallet link and on the order screen
- A transfer carrying **another order's** reference is never credited here; one with **no** reference still matches on its amount; one with **this order's** reference is credited even on a shared address

### 1.7.0 — a clearer wait, and coins that fit the order

- Payment page reports **"payment spotted, waiting for confirmations"** as soon as the transfer appears on chain (display only — an order is still only paid once it has the confirmations you require)
- Customers can **re-quote an expired order** with one button; the order is kept and a fresh amount is issued at today's rate. Orders with a partial or late payment already received cannot be re-quoted
- **Coin search** in the picker (name, symbol or network) and coin names under each icon
- Optional **minimum / maximum order value per coin** (Coins tab) — hide coins whose network fees make small orders impractical
- **"Open in wallet app"** button on the payment page

### 1.6.1 — price reliability
- Backup rate sources (Coinbase, Kraken, Binance) when CoinGecko fails; two sources must agree within 5% or no rate is used.
- Stablecoins tracking your store currency are priced 1:1 (17.34 order = 17.34 USDT), optional.
- Warning when the store currency isn't supported by the rate sources.

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
