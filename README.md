# Chain Checkout

WordPress / WooCommerce payment gateway for accepting cryptocurrency **directly to your own wallets** — no third-party processor, no license keys, no phone-home.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-10.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)

## Features

- Direct-to-wallet payments (BTC, ETH, LTC, DOGE, SOL, TRX, XMR, XRP, BNB, MATIC/POL, ARB, OP, and more)
- USDT & USDC on multiple networks with separate wallet fields
- Token support (LINK, UNI, CAKE, AVAX, …) including multi-chain variants
- Coin picker at checkout + thank-you page with amount, address, and QR code
- Configurable payment window (default 60 minutes)
- Automatic on-chain verification (Etherscan API V2, mempool.space, TronGrid, Helius, and other public APIs) — can be disabled
- Wallet rotation across multiple addresses per coin
- Unique payment amounts for reliable matching on shared addresses
- WooCommerce **Checkout Blocks** + **HPOS** compatible
- Checkout branding: custom title, icon upload/replace, width/height, icon and/or text label
- Dedicated admin: **General**, **Coins**, **Wallets**, **Prices & APIs**

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.9+ (tested up to 7.0) |
| WooCommerce | 10.0+ (tested up to 10.8) |
| PHP | 7.4+ (8.3+ recommended) |

HTTPS is strongly recommended.

## Installation

1. Download the latest release ZIP (exclude `tests/` and `.git` from wordpress.org packages), or clone into `wp-content/plugins/chain-checkout`.
2. Activate **Chain Checkout** in WordPress.
3. Go to **Chain Checkout → Coins** and enable the assets you accept.
4. Go to **Chain Checkout → Wallets** and add receiving addresses (`+ Add address` for rotation).
5. Configure rates & explorer keys under **Prices & APIs**.
6. Enable the gateway under **WooCommerce → Settings → Payments → Chain Checkout**.
7. (Optional) Under **Chain Checkout → General**, set checkout title, icon, size, and whether to show icon, text, or both.

## External services

This plugin does **not** send data to the author’s servers. It may contact public price/blockchain APIs when crypto checkout or auto-verify is used. Full disclosure (purpose, data, Terms/Privacy links) is in [`readme.txt`](readme.txt) under **External services**. Suggested privacy text is registered with WordPress via `wp_add_privacy_policy_content()`.

## Checkout branding

On **Chain Checkout → General**:

- **Checkout title** — e.g. “Pay with Cryptocurrency”
- **Checkout description** — shown under the method when selected
- **Label style** — Icon and text / Icon only / Text only
- **Checkout icon** — upload or replace; reset to the bundled default
- **Icon size** — width & height in px (16–128, default 32)

## Security notes

- Only public receiving addresses are stored — never private keys
- Admin actions require `manage_woocommerce` + nonces
- Custom checkout icons must be Media Library image attachments (no arbitrary remote URLs)
- AJAX endpoints are nonce-protected and rate-limited where applicable

## Development

```bash
# Offline smoke tests (requires PHP CLI) — not shipped in wordpress.org ZIP
php tests/smoke-test.php
```

## Changelog

See [readme.txt](readme.txt).

### 1.4.0

- Author: xorro; payment expiry, CoinGecko Demo/Pro, Polygon POL price ID, EVM/Solana/Cosmos verify fixes, packaging

### 1.3.6

- SVG icons for all remaining catalog coins; multi-network token badges

### 1.3.5

- Admin settings shell styled like Cryptoniq (dark header, sidebar tabs, slate wallets)

### 1.3.4

- Cryptoniq-style checkout coin tiles and payment paybox (icons, status bar, instructions)

### 1.3.3

- Payment QR URIs: BIP-21, EIP-681 (with chain IDs + wei), Solana Pay, TRON/XRP/XLM/XMR — larger QR + address fallback

### 1.3.2

- wordpress.org readiness: external services disclosure, privacy policy content, longer CSS/ID prefixes, packaging hygiene

### 1.3.1

- Fixed wordpress.org header validation: Author URI set to GitHub profile (distinct from Plugin URI)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## wordpress.org submission notes

Before uploading to [Add Plugin](https://wordpress.org/plugins/developers/add/):

1. `Contributors:` in `readme.txt` is set to **xorro** (your WordPress.org username).
2. Upload a ZIP of the `chain-checkout` folder **without** `.git/` or `tests/`.
3. Confirm Plugin URI ≠ Author URI (already set).
