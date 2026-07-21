=== Xorro Direct Wallet Payments for WooCommerce ===
Contributors: xorro
Tags: woocommerce, cryptocurrency, bitcoin, ethereum, payments, usdt, crypto checkout
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments directly to your own wallets — no third-party payment processor.

== Description ==

Xorro Direct Wallet Payments for WooCommerce is a WooCommerce payment gateway that lets customers pay with cryptocurrency straight to wallets you control. There is no payment processor holding funds, no license key, and no phone-home licensing.

This plugin contacts public price and blockchain APIs to quote amounts and (optionally) verify payments. See **External services** below for details, data shared, and privacy links.

= Features =

* Direct-to-wallet payments (no payment processor holds your funds)
* BTC, BCH, ETH (incl. Arbitrum/Optimism/Base), LTC, DOGE, SOL, TRX, XMR, XRP, BNB, MATIC/POL, AVAX, ARB, OP, and more
* USDT, USDC & DAI on multiple networks with separate wallet fields
* Token support (WBTC, LINK, UNI, AAVE, MKR, LDO, CRV, COMP, APE, SHIB, PEPE, CAKE, and others) including multi-chain variants
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

1. Upload the `xorro-direct-wallet-payments-woocommerce` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload.
2. Activate **Xorro Direct Wallet Payments for WooCommerce**.
3. Go to **Xorro Wallet Payments → Coins** and enable the assets you accept.
4. Go to **Xorro Wallet Payments → Wallets** and add receiving addresses (use **+ Add address** for multiple / rotation).
5. Add API keys under **Prices & APIs** (Etherscan V2 recommended; TronGrid/Helius/Subscan/ViewBlock optional).
6. Under **Xorro Wallet Payments → General**, set the checkout title, icon, size, and whether to show icon, text, or both.
7. Enable the gateway under **WooCommerce → Settings → Payments → Xorro Wallet Payments**.

== Frequently Asked Questions ==

= Does this use a third-party payment processor? =

No. Customers pay your wallet addresses directly. Public APIs are used only for exchange rates and blockchain verification. Nothing is sent to the plugin author’s servers.

= How do I change the checkout icon or title? =

Go to **Xorro Wallet Payments → General**. You can edit the title (e.g. “Pay with Cryptocurrency”), upload or reset the icon, set width/height (16–128px), and choose Icon and text, Icon only, or Text only.

= Which free API keys should I add? =

* **Etherscan API V2** — one key for ETH, BNB, Polygon, Arbitrum, Optimism, Base, Avalanche, and other EVM chains
* **CoinGecko** — optional, for higher rate limits on price conversion
* **TronGrid** — optional, for TRX / USDT-TRC20 reliability
* **Helius** — optional, for more stable Solana verification
* **Subscan** — optional, for Polkadot (DOT) rate limits
* **ViewBlock** — optional, for Zilliqa (ZIL) reliability

Bitcoin uses mempool.space (Blockstream fallback) with no key required. ALGO, HBAR, NEAR, ATOM, EGLD, FIL, EOS use free public endpoints. Monero (XMR) stays manual.

= Are private keys stored? =

Never. Only public receiving addresses are stored.

= Will it work with Checkout Blocks? =

Yes. This plugin registers a Blocks payment method and declares cart/checkout blocks compatibility.

= Will it work with my theme? =

Yes. It uses the WooCommerce payment gateway API and scoped CSS classes.

= What third-party services does this plugin use? =

See the **External services** section below. Automatic verification can be turned off under Xorro Wallet Payments → General. Disabling the gateway stops checkout-related API calls.

== External services ==

This plugin does **not** phone home to the plugin author. It may contact the following third-party services when crypto checkout or automatic verification is used. Optional API keys you configure are sent only to the matching provider. Automatic verification can be disabled under Xorro Wallet Payments → General.

= CoinGecko (exchange rates) =

* Purpose: Convert order totals to cryptocurrency amounts.
* Data: Coin identifiers and fiat currency codes (no customer personal data required by the request).
* When: Checkout quotes, optional product price display, scheduled price refresh.
* Site: https://www.coingecko.com/
* Terms: https://www.coingecko.com/en/terms
* Privacy: https://www.coingecko.com/en/privacy

= Etherscan API V2 (EVM verification) =

* Purpose: Detect inbound payments on Ethereum and other EVM networks (BNB Chain, Polygon, Arbitrum, Optimism, Base, Avalanche, Fantom, Cronos, Ethereum Classic, and related chains).
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
* mempool.space about/privacy: https://mempool.space/about
* Blockstream: https://blockstream.com/

= Blockchair (Bitcoin Cash / Litecoin / Dogecoin) =

* Purpose: Detect BCH/LTC/DOGE payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for BCH/LTC/DOGE.
* Site: https://blockchair.com/
* Privacy: https://blockchair.com/privacy

= TronGrid (TRON) =

* Purpose: Detect TRX / TRC-20 payments (including USDT/USDC on TRON).
* Data: Addresses / transactions; optional API key.
* When: Automatic verification for TRON assets.
* Site: https://www.trongrid.io/
* Docs: https://developers.tron.network/
* Terms: https://www.tron.network/legal#termsOfUse
* Privacy: https://www.tron.network/legal#privacyPolicy

= Solana RPC / Helius =

* Purpose: Detect SOL / SPL payments.
* Data: Addresses / signatures; optional Helius API key.
* When: Automatic verification for Solana assets.
* Sites: https://solana.com/ , https://www.helius.dev/
* Solana terms: https://solana.com/tos
* Helius privacy: https://www.helius.dev/privacy-policy
* Helius terms: https://www.helius.dev/terms-of-service

= XRPSCan (XRP) =

* Purpose: Detect XRP payments.
* Data: XRP account addresses and payment transaction data.
* When: Automatic verification for XRP.
* Site: https://xrpscan.com/
* Terms: https://docs.xrpscan.com/help/terms-of-service
* Privacy: https://docs.xrpscan.com/help/privacy-policy

= Stellar Horizon (XLM) =

* Purpose: Detect Stellar payments.
* Data: Stellar account addresses and payment records.
* When: Automatic verification for XLM.
* Site: https://developers.stellar.org/
* Horizon: https://horizon.stellar.org/
* Terms: https://www.stellar.org/terms-of-service
* Privacy: https://www.stellar.org/privacy-policy

= AlgoNode (Algorand) =

* Purpose: Detect ALGO payments.
* Data: Algorand account addresses and payment transactions.
* When: Automatic verification for ALGO.
* Site: https://algonode.cloud/
* Docs: https://algonode.io/
* Privacy: https://algonode.io/privacy-policy/

= Hedera Mirror Node (HBAR) =

* Purpose: Detect HBAR payments.
* Data: Hedera account IDs and crypto transfer records.
* When: Automatic verification for HBAR.
* Site: https://docs.hedera.com/
* Mirror node: https://mainnet-public.mirrornode.hedera.com/
* Terms: https://hedera.com/terms
* Privacy: https://hedera.com/privacy

= NearBlocks (NEAR) =

* Purpose: Detect NEAR payments.
* Data: NEAR account IDs and transaction lists.
* When: Automatic verification for NEAR.
* Site: https://nearblocks.io/
* API: https://api.nearblocks.io/
* Privacy: https://nearblocks.io/privacy

= PublicNode Cosmos REST (ATOM) =

* Purpose: Detect Cosmos Hub (ATOM) payments.
* Data: Cosmos addresses and transaction queries.
* When: Automatic verification for ATOM.
* Site: https://publicnode.com/
* Endpoint used: https://cosmos-rest.publicnode.com/
* Terms: https://publicnode.com/terms
* Privacy: https://publicnode.com/privacy

= MultiversX API (EGLD) =

* Purpose: Detect MultiversX (EGLD) payments.
* Data: Account addresses and successful transactions.
* When: Automatic verification for EGLD.
* Site: https://multiversx.com/
* API: https://api.multiversx.com/
* Terms: https://multiversx.com/legal/terms-of-use
* Privacy: https://multiversx.com/legal/privacy-policy

= Filfox (Filecoin) =

* Purpose: Detect FIL payments.
* Data: Filecoin addresses and message/transaction lists.
* When: Automatic verification for FIL.
* Site: https://filfox.info/
* Privacy / about: https://filfox.info/en

= Greymass EOS history (EOS) =

* Purpose: Detect EOS token transfers.
* Data: EOS account names and transfer history.
* When: Automatic verification for EOS.
* Site: https://greymass.com/
* Endpoint used: https://eos.greymass.com/
* Privacy: https://greymass.com/en/privacy

= Subscan (Polkadot) =

* Purpose: Detect DOT transfers.
* Data: Addresses / transfers; optional API key.
* When: Automatic verification for DOT.
* Site: https://www.subscan.io/
* Terms: https://www.subscan.io/privacy
* Docs: https://support.subscan.io/

= ViewBlock (Zilliqa) =

* Purpose: Detect ZIL payments.
* Data: Addresses / transactions; optional API key.
* When: Automatic verification for ZIL.
* Site: https://viewblock.io/
* Terms: https://viewblock.io/terms
* Privacy: https://viewblock.io/privacy

Suggested privacy policy text is also added under **Settings → Privacy** when the plugin is active.

== Third-party libraries ==

* QR Code generator (`assets/js/qrcode.min.js`) — MIT-licensed library by davidshimjs (https://github.com/davidshimjs/qrcodejs). Source is publicly available; the bundled file is minified for production use.

== Changelog ==

= 1.5.2 =
* Hardened confirmation gating for BTC, Blockchair UTXO, Solana, TRON, ALGO, DOT, and other non-EVM verifiers (fail closed when depth/success cannot be verified)
* Checkout quotes now reserve the exact unique-dust amount used on the thank-you payment page
* Clarified minimum-confirmations setting applies beyond EVM explorers

= 1.5.1 =
* Security: Blockchair verifier no longer falls back to an unrelated address payload
* Hardened EVM/TRON confirmation checks; payment page keeps polling through expiry grace
* Checkout quote failures show an error instead of going blank; approx rate labeled clearly
* Fixed Checkout Blocks payment method missing when the plugin boots after woocommerce_blocks_loaded
* Install upgrade writes settings only when the plugin version changes

= 1.5.0 =
* Renamed all internal identifiers, files, CSS, JS, options, and gateway ID to xdwp
* Fixed intermittent missing crypto quote when switching coins; Blocks checkout shows live amount

= 1.4.7 =
* Fixed intermittent missing crypto quote when switching coins at checkout (AJAX race + price cache clobber)
* Show live crypto amount on Checkout Blocks; keep stale rates for fallback when CoinGecko flakes

= 1.4.6 =
* Fixed admin settings URLs after rename; enqueue wallets.js (no raw script tag)
* Unique dust spaced to avoid match-band overlap; atomic amount sequence via LAST_INSERT_ID
* Fixed Blocks JS text domain

= 1.4.5 =
* Renamed to Xorro Direct Wallet Payments for WooCommerce (distinctive slug/text domain for wordpress.org)
* Enqueue CSS/JS via WordPress APIs only (removed raw admin <link>/<style> and payment-page <script>)
* Removed load_plugin_textdomain() (WP.org loads translations automatically)
* Expanded External services documentation with per-service purpose, data, terms, and privacy links

= 1.4.4 =
* Expanded auto-verify catalog: BCH, ETH on Arbitrum/Optimism/Base, USDT/USDC/DAI on Polygon/Avalanche/Base (+ USDC TRON), and major ERC-20s (WBTC, AAVE, MKR, LDO, CRV, COMP, APE, SHIB, PEPE, …)
* Fixed Bitcoin Cash CashAddr matching on Blockchair; accept `ethereum` verifier in wallet validation (USDT/USDC/DAI/ERC-20 addresses were rejected); Base (8453) EVM verify
* Added DAI admin section and SVG icons for new tokens/networks

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
* Document-level click handling via data-xdwp-action attributes

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

= 1.5.2 =
Stronger multi-chain confirmation gating and exact checkout quotes matching the payment page. Update recommended.

= 1.5.1 =
Payment matching, checkout quote hardening, and Checkout Blocks registration fix. Update recommended for all stores.

= 1.5.0 =
Internal rename to xdwp (gateway ID, options, order meta, assets). Fresh installs and updates use xdwp keys only.

= 1.4.5 =
wordpress.org compliance: new distinctive name/slug, proper asset enqueue, fuller external-service disclosure. Request slug xorro-direct-wallet-payments-woocommerce when uploading.

= 1.4.4 =
Adds major auto-verifiable coins/tokens (BCH, Base, more stables/ERC-20s) and CashAddr matching fixes. Enable new assets under Coins, then add wallets.

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
