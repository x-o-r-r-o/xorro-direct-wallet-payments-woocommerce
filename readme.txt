=== Chain Checkout ===
Contributors: xorro
Tags: woocommerce, cryptocurrency, bitcoin, ethereum, payments, usdt, crypto checkout
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments directly to your own wallets — no third-party payment processor.

== Description ==

Chain Checkout is a WooCommerce payment gateway that lets customers pay with cryptocurrency straight to wallets you control. There is no payment processor holding funds, no license key, and no phone-home licensing.

This plugin contacts public price and blockchain APIs to quote amounts and (optionally) verify payments. See **External services** below for details, data shared, and privacy links.

= Features =

* Direct-to-wallet payments (no payment processor holds your funds)
* BTC, ETH, LTC, DOGE, SOL, TRX, XMR, XRP, BNB, MATIC/POL, ARB, OP, and more
* USDT & USDC on multiple networks with separate wallet fields
* Token support (LINK, UNI, CAKE, AVAX, and others) including multi-chain variants
* Coin picker at checkout + payment page with amount, address, and QR code
* 60-minute payment window (configurable)
* Automatic on-chain verification via public explorers/RPCs (can be disabled)
* Wallet rotation across multiple addresses
* Unique payment amounts for reliable matching
* Checkout branding: custom title, upload/replace icon, icon width & height, show icon and/or text
* WooCommerce Checkout Blocks + HPOS compatible
* Compatible with WordPress 7.0 and WooCommerce 10.x
* Dedicated admin menu: General, Coins, Wallets, Prices & APIs

= Requirements =

* WordPress 6.9+ (tested up to 7.0)
* WooCommerce 10.0+ (tested up to 10.8)
* PHP 7.4+ (8.3+ recommended)
* HTTPS recommended

== Installation ==

1. Upload the `chain-checkout` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload.
2. Activate **Chain Checkout**.
3. Go to **Chain Checkout → Coins** and enable the assets you accept.
4. Go to **Chain Checkout → Wallets** and add receiving addresses (use **+ Add address** for multiple / rotation).
5. Add API keys under **Prices & APIs** (Etherscan V2 recommended; TronGrid/Helius/Subscan/ViewBlock optional).
6. Under **Chain Checkout → General**, set the checkout title, icon, size, and whether to show icon, text, or both.
7. Enable the gateway under **WooCommerce → Settings → Payments → Chain Checkout**.

== Frequently Asked Questions ==

= Does this use a third-party payment processor? =

No. Customers pay your wallet addresses directly. Public APIs are used only for exchange rates and blockchain verification. Nothing is sent to the plugin author’s servers.

= How do I change the checkout icon or title? =

Go to **Chain Checkout → General**. You can edit the title (e.g. “Pay with Cryptocurrency”), upload or reset the icon, set width/height (16–128px), and choose Icon and text, Icon only, or Text only.

= Which free API keys should I add? =

* **Etherscan API V2** — one key for ETH, BNB, Polygon, Arbitrum, Optimism, Avalanche, and other EVM chains
* **CoinGecko** — optional, for higher rate limits on price conversion
* **TronGrid** — optional, for TRX / USDT-TRC20 reliability
* **Helius** — optional, for more stable Solana verification
* **Subscan** — optional, for Polkadot (DOT) rate limits
* **ViewBlock** — optional, for Zilliqa (ZIL) reliability

Bitcoin uses mempool.space (Blockstream fallback) with no key required. ALGO, HBAR, NEAR, ATOM, EGLD, FIL, EOS use free public endpoints. Monero (XMR) stays manual.

= Are private keys stored? =

Never. Only public receiving addresses are stored.

= Will it work with Checkout Blocks? =

Yes. Chain Checkout registers a Blocks payment method and declares cart/checkout blocks compatibility.

= Will it work with my theme? =

Yes. It uses the WooCommerce payment gateway API and scoped CSS classes.

= What third-party services does this plugin use? =

See the **External services** section below. Automatic verification can be turned off under Chain Checkout → General. Disabling the gateway stops checkout-related API calls.

== External services ==

Chain Checkout does **not** phone home to the plugin author. It may contact the following third-party services when crypto checkout or automatic verification is used. Optional API keys you configure are sent only to the matching provider.

= CoinGecko (exchange rates) =

* Purpose: Convert order totals to cryptocurrency amounts.
* Data: Coin identifiers and fiat currency codes (no customer personal data required by the request).
* When: Checkout quotes, optional product price display, scheduled price refresh.
* Site: https://www.coingecko.com/
* Terms: https://www.coingecko.com/en/terms
* Privacy: https://www.coingecko.com/en/privacy

= Etherscan API V2 (EVM verification) =

* Purpose: Detect inbound payments on Ethereum and other EVM networks.
* Data: Wallet addresses, optional transaction IDs, your API key if configured.
* When: Automatic verification (can be disabled).
* Site: https://etherscan.io/
* Terms: https://etherscan.io/terms
* Privacy: https://etherscan.io/privacyPolicy

= mempool.space / Blockstream (Bitcoin) =

* Purpose: Detect Bitcoin payments.
* Data: Bitcoin addresses / transaction data needed for matching.
* When: Automatic verification for BTC.
* Sites: https://mempool.space/ , https://blockstream.info/
* mempool.space terms/privacy: https://mempool.space/about
* Blockstream: https://blockstream.com/

= Blockchair (Litecoin / Dogecoin) =

* Purpose: Detect LTC/DOGE payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for LTC/DOGE.
* Site: https://blockchair.com/
* Privacy: https://blockchair.com/privacy

= TronGrid (TRON) =

* Purpose: Detect TRX / TRC-20 payments.
* Data: Addresses / transactions; optional API key.
* When: Automatic verification for TRON assets.
* Site: https://www.trongrid.io/
* Docs/terms: https://developers.tron.network/

= Solana RPC / Helius =

* Purpose: Detect SOL / SPL payments.
* Data: Addresses / signatures; optional Helius API key.
* When: Automatic verification for Solana assets.
* Sites: https://solana.com/ , https://www.helius.dev/
* Helius privacy: https://www.helius.dev/privacy-policy

= Other public explorers / RPCs (auto-verify) =

Used only for the matching coin when automatic verification is enabled: XRPSCan (XRP), Stellar Horizon (XLM), AlgoNode (ALGO), Hedera Mirror (HBAR), NearBlocks (NEAR), PublicNode Cosmos REST (ATOM), MultiversX API (EGLD), Filfox (FIL), Greymass EOS history (EOS), Subscan (DOT), ViewBlock (ZIL). Requests typically include public addresses and transaction identifiers.

* XRPSCan: https://xrpscan.com/
* Stellar Horizon: https://developers.stellar.org/
* AlgoNode: https://algonode.cloud/
* Hedera Mirror: https://docs.hedera.com/
* NearBlocks: https://nearblocks.io/
* PublicNode: https://publicnode.com/
* MultiversX: https://multiversx.com/
* Filfox: https://filfox.info/
* Greymass: https://greymass.com/
* Subscan: https://www.subscan.io/
* ViewBlock: https://viewblock.io/

Suggested privacy policy text is also added under **Settings → Privacy** when the plugin is active.

== Third-party libraries ==

* QR Code generator (`assets/js/qrcode.min.js`) — MIT-licensed library by davidshimjs (https://github.com/davidshimjs/qrcodejs). Source is publicly available; the bundled file is minified for production use.

== Changelog ==

= 1.4.3 =
* Hardened payment matching so % tolerance cannot overwhelm unique dust on shared wallets
* Require destination address on NEAR/FIL/XRP/ZIL verify; EVM min confirmations; Solana finalized commitment
* Expiry grace period then fail (not cancel); atomic dust/rotation counters; POST-only mark-paid
* Order-bound status AJAX nonce + rate limit; cron FIFO up to 100 awaiting orders

= 1.4.2 =
* Fixed payment details Copy buttons (inline bootstrap + selection copy + capture-phase handlers)
* Forced admin settings CSS to load via admin_head link + inline stylesheet so the shell always styles

= 1.4.1 =
* Fixed checkout coin icons tiling at huge size (switched to sized img tags + critical inline CSS)
* Fixed admin settings shell styles not applying reliably (early enqueue, dashicons, stronger selectors)
* Reverted payment details page to the classic layout (Cryptoniq paybox removed)

= 1.4.0 =
* Author set to xorro; reliability fixes for QR payment page expiry, CoinGecko Demo/Pro keys, Polygon (POL) price ID
* EVM verify skips without Etherscan key; Solana skips failed txs; Cosmos LCD base64 attributes supported
* Blocks payment_data parsing + wallet Copy fallback; distribution packaging hygiene

= 1.3.6 =
* Added SVG icons for all remaining catalog coins (XMR, XRP, LINK, UNI, DOT, and more)
* Multi-network token tiles show chain badges (e.g. LINK on Arbitrum)

= 1.3.5 =
* Admin settings UI restyled to match Cryptoniq (dark header, sidebar tabs, slate wallets theme)

= 1.3.4 =
* Adopted Cryptoniq-style checkout coin tiles and payment paybox UI (icons, status bar, instructions panel)

= 1.3.3 =
* Fixed payment QR URIs for wallet compatibility (BIP-21, EIP-681 with chain IDs, Solana Pay, TRON/XRP/XLM/XMR)
* Larger QR rendering with address fallback and copyable payment link

= 1.3.2 =
* wordpress.org readiness: external services disclosure, privacy policy content, longer CSS/ID prefixes, packaging hygiene

= 1.3.1 =
* Fixed plugin headers: Author URI now points to GitHub (must differ from Plugin URI for wordpress.org)

= 1.3.0 =
* Fixed oversized checkout gateway icon (default 32×32, CSS-constrained)
* Added checkout branding: title, description, icon upload/replace/reset, width & height
* Added display mode: icon and text / icon only / text only (classic + Blocks)
* Improved docs (readme.txt + README.md)

= 1.2.4 =
* Fixed Add address with inline wallets script (works even if admin.js cache fails)
* Document-level click handling via data-chain-checkout-action attributes

= 1.2.3 =
* Fixed Wallets “+ Add address” button
* Wallets page lists only coins activated under Coins
* Mobile-friendly wallets UI with clearer cards, validation, and counters
* Admin assets load more reliably on plugin screens

= 1.2.0 =
* Extended auto-verify to ALGO, HBAR, NEAR, ATOM, EGLD, FIL, EOS, DOT, ZIL via free public APIs
* Optional Subscan + ViewBlock API keys for DOT/ZIL reliability
* Monero (XMR) remains manual (requires private view key)

= 1.1.1 =
* Security and reliability fixes: wallet merge on save, atomic txid claim, shared-address guards, Solana ATA lookup, AJAX verify throttling, BCMath amount matching, quote rate limit
* Cleaner uninstall of wallet index / txid claim options

= 1.1.0 =
* Migrated EVM verification to Etherscan API V2 (single key, multi-chain)
* Added mempool.space Bitcoin primary endpoint with Blockstream fallback
* Added optional TronGrid and Helius API key support
* Extended auto-verify to FTM, CRO, and ETC via Etherscan V2
* Declared compatibility with WordPress 7.0 and WooCommerce 10.x
* Simplified Prices & APIs settings UI

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.3 =
Important payment-safety update: tighter matching, confirmations, expiry grace, and AJAX hardening. Update before accepting live payments.

= 1.4.2 =
Fixes payment Copy buttons and admin settings styling. Reinstall/replace the plugin ZIP if styles or copy still look cached.

= 1.4.1 =
Fixes oversized/repeating coin icons at checkout, admin settings styling, and restores classic payment details page.

= 1.4.0 =
Payment reliability and API fixes. Add an Etherscan V2 API key for EVM auto-verify. Author is xorro.

= 1.3.3 =
Payment QR codes now use standard BIP-21 / EIP-681 / Solana Pay URIs so wallet apps scan and prefill correctly.

= 1.3.2 =
Documentation and privacy disclosures required for wordpress.org. No breaking setting changes.

= 1.3.1 =
Author URI updated for wordpress.org header validation.
