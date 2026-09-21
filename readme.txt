=== Xorro Direct Wallet Payments for WooCommerce ===
Contributors: xorro
Tags: woocommerce, cryptocurrency, bitcoin, ethereum, payments, usdt, crypto checkout
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.27.0
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
* Pay from a browser wallet (MetaMask, TronLink and others) or by address and QR code
* Coin picker at checkout with search and coin names + payment page with amount, address, QR code and an "Open in wallet app" button
* Optional minimum and maximum order value per coin, and confirmations per coin
* "Payment spotted" notice while the network confirms, and a one-click re-quote for expired orders
* 60-minute payment window (configurable)
* Automatic on-chain verification via public explorers/RPCs (can be disabled)
* Wallet rotation across multiple addresses, or a fresh address per order from your own extended public key
* Unique payment amounts for reliable matching
* Payment details and a pre-expiry reminder in customer emails
* Partial payments (customer is asked for the remainder), overpayment notes, and late-payment alerts for the store owner
* Checkout branding: custom title, upload/replace icon, icon width & height, show icon and/or text
* WooCommerce Checkout Blocks compatible, and works with either order storage (HPOS or the older posts table)
* Compatible with WordPress 7.1 and WooCommerce 11.x, on PHP 7.4 through 8.5
* Refunds by claim link — the customer names an address they control, you send it from your own wallet
* Signed webhooks and Telegram alerts, plus a daily summary of what was paid and what needs you
* Reports by coin: taken, typical wait, abandonment, underpayment
* Per-coin discount or surcharge, shown on the order as its own line
* Confirmations by order value — quick on small orders, careful on large ones
* Settings backup and restore, with API keys held back unless you ask for them
* Search your orders by transaction id or receiving address, and a timeline on every order
* Dedicated admin menu: General, Payments, Coins, Wallets, Prices & APIs, Alerts, Help

= Requirements =

* WordPress 6.9+ (tested up to 7.1)
* WooCommerce 10.0+ (tested up to 11.1)
* PHP 7.4 – 8.5 (8.2+ recommended); every one of those versions is tested on each release
* Either WooCommerce order storage — High-Performance Order Storage or the older posts table
* HTTPS recommended

== Installation ==

1. Upload the `xorro-direct-wallet-payments-woocommerce` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload.
2. Activate **Xorro Direct Wallet Payments for WooCommerce**.
3. Go to **Xorro Wallet Payments → Coins** and enable the assets you accept.
4. Go to **Xorro Wallet Payments → Wallets** and add receiving addresses (use **+ Add address** for multiple / rotation).
5. Add API keys under **Prices & APIs** (Etherscan V2 recommended; TronGrid, Helius, Aptos and Blockchair optional).
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

Bitcoin uses mempool.space (Blockstream fallback) with no key required. ALGO, HBAR, NEAR, ATOM, EGLD, FIL, EOS use free public endpoints. Monero (XMR) stays manual.

= What happens if a customer sends too little, too much, or too late? =

* **Too little (at least half):** the order becomes "partially paid". The customer is emailed and shown the remaining amount, the payment window restarts, and the order completes automatically when the rest arrives. You get an email too.
* **Up to 10% too much:** the order completes and a note shows the excess so you can refund it.
* **After the window closed:** orders that expired in the last 7 days are re-checked; if money arrives you are emailed and can complete the order with "Mark payment received" (the transaction ID is filled in) or refund.

These emails can be switched off or edited under WooCommerce → Settings → Emails.

= Are private keys stored? =

Never. Only public receiving addresses are stored.

= Will it work with Checkout Blocks? =

Yes. This plugin registers a Blocks payment method and declares cart/checkout blocks compatibility.

= Will it work with my theme? =

Yes. It uses the WooCommerce payment gateway API and scoped CSS classes (every selector is prefixed `.xdwp-`, so it won't leak style onto your theme or other plugins). The customer-facing payment box template can also be overridden from your theme, the same way WooCommerce's own templates can: copy `templates/payment.php` to `yourtheme/xorro-direct-wallet-payments-woocommerce/payment.php`.

= Which PHP versions does it support? =

PHP 7.4 through 8.5. Every release runs the full test suite on 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5, and the build is failed if any of them raises even a single deprecation or warning. 8.2 or newer is recommended, but nothing here requires you to move off an older one.

= Does it need High-Performance Order Storage? =

No — it works with either. WooCommerce can keep orders in its own tables (HPOS) or in the older posts table, and this plugin is tested against both on every change. If you are still on the posts table, update to 1.19.6 or later: before that, WooCommerce quietly dropped the order filters this plugin relies on, which could leave real payments unconfirmed.

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

= Blockchair (Bitcoin Cash / Dogecoin / Zcash / Dash / eCash) =

* Purpose: Detect BCH/DOGE/ZEC/DASH/XEC payments.
* Data: Addresses / transactions for matching; optional API key.
* When: Automatic verification for those coins.
* Site: https://blockchair.com/
* Privacy: https://blockchair.com/privacy

= Litecoin Space (Litecoin) =

* Purpose: Detect LTC payments when Blockchair is out of free allowance.
* Data: Addresses / transactions for matching. No key.
* When: Automatic verification for LTC, only after Blockchair does not answer.
* Site: https://litecoinspace.org/

= XRP Ledger public cluster (XRP) =

* Purpose: Detect XRP payments.
* Data: Addresses / transactions for matching. No key.
* When: Automatic verification for XRP.
* Site: https://xrplcluster.com/

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

= toncenter.com (TON, native + Jetton tokens) =

* Purpose: Detect TON and Jetton-token payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for TON and its Jetton tokens (Notcoin and others).
* Site: https://toncenter.com/

= Koios (Cardano) =

* Purpose: Detect ADA payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for ADA.
* Site: https://www.koios.rest/

= Aptos Indexer GraphQL (Aptos) =

* Purpose: Detect APT payments.
* Data: Addresses / transactions; requires a free API key you configure under Prices & APIs.
* When: Automatic verification for APT.
* Site: https://aptoslabs.com/

= api.kaspa.org (Kaspa) =

* Purpose: Detect KAS payments on Kaspa's BlockDAG.
* Data: Addresses / transactions for matching.
* When: Automatic verification for KAS.
* Site: https://kaspa.org/

= TzKT (Tezos) =

* Purpose: Detect XTZ payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for XTZ.
* Site: https://tzkt.io/

= Nano RPC proxy and Waves nodes =

* Purpose: Detect Nano (XNO) and Waves payments.
* Data: Addresses / transactions for matching.
* When: Automatic verification for XNO and Waves.
* Sites: https://nano.org/ , https://waves.tech/

= Blockbook-family UTXO explorers (BTG, Firo/Zcoin, Ravencoin, PIVX) =

* Purpose: Detect payments on independently-hosted Trezor Blockbook explorer instances.
* Data: Addresses / transactions for matching.
* When: Automatic verification for BTG, FIRO, XZC, RVN, and PIVX.
* Software: https://github.com/trezor/blockbook

= api.coz.io (NEO, GAS) and explorer-api.thetatoken.org (Theta, TFuel) =

* Purpose: Detect NEP-17 transfers on NEO N3 and native transfers on Theta Network.
* Data: Addresses / transactions for matching.
* When: Automatic verification for NEO, GAS, THETA, and TFUEL.
* Sites: https://coz.io/ , https://www.thetatoken.org/

= Additional UTXO and standalone-chain explorers (Batch 2/3 coins) =

* Purpose: Detect payments for DigiByte, Komodo, Verge, Qtum, Ark, Aeternity, ICON, Ontology, Klever, Tectum, NEM, Symbol, THORChain, Lisk, Stratis (StratisEVM/"Xertra"), and IOTA.
* Data: Addresses / transactions for matching; each chain uses its own free, keyless public explorer or node API (or, for THORChain/NEM/Symbol, a community-run mirror node, since the officially-documented endpoints for those networks are not reliably available).
* When: Automatic verification for DGB, KMD, XVG, QTUM, ARK, AE, ICX, ONT, KLV, TET, XEM, XYM, RUNE, LSK, STRAX, and IOTA.
* Sites: https://digibyte.org/ , https://komodoplatform.com/ , https://vergecurrency.com/ , https://qtum.org/ , https://ark.io/ , https://aeternity.com/ , https://icon.foundation/ , https://ont.io/ , https://klever.org/ , https://tectum.io/ , https://nem.io/ , https://symbol.io/ , https://thorchain.org/ , https://lisk.com/ , https://stratisplatform.com/ , https://iota.org/

= Blockscout-family EVM explorers (small/legacy EVM chains) =

* Purpose: Detect native and token payments on EVM-compatible chains not covered by Etherscan API V2 (Harmony, PulseChain, Syscoin NEVM, Boba, Bitgert, Lisk L2, StratisEVM/"Xertra").
* Data: Addresses / transactions for matching.
* When: Automatic verification for ONE, PLS, SYSEVM, BOBA, BRISEMAINNET, LSK, and STRAX.
* Software: https://github.com/blockscout/blockscout

Suggested privacy policy text is also added under **Settings → Privacy** when the plugin is active.

== Third-party libraries ==

* QR Code generator (`assets/js/qrcode.min.js`) — MIT-licensed library by davidshimjs (https://github.com/davidshimjs/qrcodejs). Source is publicly available; the bundled file is minified for production use.

== Changelog ==

= 1.27.0 =
* New: a setup wizard. There are seven tabs of settings here and almost all of them have a sensible default — exactly three things do not, and a shop cannot take a single payment until all three are done. The wizard asks for those three, in order, and nothing else: pick a coin, give it an address, switch the gateway on
* The last step runs the real checks against your address, the live rate and the chain before offering to switch anything on, so "ready" means it was tested rather than assumed
* It writes into the ordinary settings at each step rather than keeping a draft of its own, so leaving halfway simply means the shop is set up as far as you got. An address it is given goes through exactly the same validation as the Wallets tab — a wizard that accepted what the settings form refuses would be worse than no wizard
* The invitation can be waved away, and it only appears on WooCommerce screens, the plugins list, and this plugin's own pages

= 1.26.0 =
* Security: a payout address changing now also goes out by webhook and Telegram, not only by email, and the warning says which IP address the change came from. Somebody who has taken over an admin account usually controls the mailbox that account can reset, so an emailed warning is the one they can be sure of intercepting — this gives it a second road out. It cannot be switched off with the ordinary alerts
* New: two figures on the Payments report that shops ask for by name — how many of the people quoted a coin actually paid, and what a paid order was worth on average. Both come from numbers already counted, so they cost nothing
* New: an unfinished crypto payment is now reachable from the customer's own Orders list in My Account, as "Finish paying", or "Get a new amount" once the window has closed. Somebody who closed the tab otherwise has to go hunting through their email. WooCommerce's own Pay button is replaced rather than sat beside it, because that one sends them back through checkout and would quote a second time for an order that already has a quote

= 1.25.1 =
* Fixed: vendor payouts never activated on Dokan. The function the plugin looked for does not exist — the real one is named differently and returns an object unless you ask it for an id. Because every such call is guarded, the result was that Dokan shops saw the feature do nothing at all rather than break, but it did nothing
* Fixed: on WC Vendors, a line item whose product had been deleted could have been attributed to user 1 — usually the administrator — because that plugin answers 1 for a missing post and -1 for something that is not a product. Neither is a vendor. The answer is now confirmed to be a vendor before it is used, so a customer can never be quoted the wrong person's address
* Both were found by checking the three marketplaces' actual source rather than working from memory, before anyone had a chance to run into them

= 1.25.0 =
* New, for marketplaces only: pay a vendor directly. When a customer buys a vendor's products and pays in crypto, the money goes straight from the customer to that vendor's own wallet. Nobody holds it in between, so there is no float, no payout queue and nothing owed. Works with Dokan, WCFM and WC Vendors; vendors enter their own addresses on their profile
* Off unless you switch it on, and the setting only appears if a marketplace plugin is actually installed. If you run a single store, nothing about this release changes anything for you — no filter is registered, no lookup happens, and the address a customer is given is the same one as before. There is a test suite that exists mainly to keep that true
* An order with items from more than one vendor is paid to you, because a single crypto transfer cannot be split between several people — settle those the way you already do. A vendor who has not saved an address for the coin the customer chose is skipped, so the sale still completes and you are paid instead. Both are written on the order as a note
* Your commission is not deducted from the transfer. The vendor receives the full amount and your marketplace plugin accounts for commission exactly as it did before
* An address that is not valid for the coin is never quoted, wherever it came from — a vendor's profile, a marketplace, or anything else. It falls back to your own address rather than sending a customer's money somewhere unrecoverable

= 1.24.0 =
* New, and off unless you ask for it: pair a customer's phone wallet with their desktop browser through WalletConnect. Paste a free Reown project ID under Prices & APIs to switch it on. Leave it empty and the feature does not exist — nothing is offered and nothing is loaded
* This is the one part of the plugin that runs code written by somebody else, so it is arranged to be as small a thing as possible. It is fetched only when a customer presses the button, never on an ordinary payment page; the library is pinned to one exact version, named in the plugin rather than in a setting, so the code on a payment page cannot change unless you install a signed update; the address and amount still come from this plugin, not from anything loaded; the customer's wallet still shows them the destination before they approve; and the order is still confirmed only by reading the chain
* You do not need it for customers already on a phone. The "Open in wallet app" link and the QR code both do that job already, and neither loads anything from anywhere
* A shop that would rather serve the library itself can point the plugin at its own copy with the xdwp_walletconnect_src filter

= 1.23.1 =
* New: the payment email now carries a "Pay from a wallet on this device" link, opening the customer's wallet with the address, amount and memo already filled in. That email is usually read on a phone, which is exactly where the payment page's QR code is no use — nobody can scan their own screen. The address and amount stay printed in full, because some email clients strip links of that kind
* The payment page button is still there for anyone reading on a desktop, where the QR is the right answer

= 1.23.0 =
* New: "Rehearse a payment" at the foot of General. It creates one order, quotes it against your real wallet and today's rate, then confirms it as though a payment had been found — through the same code that confirms a real one. Use it to check the order reaches the right status, the email arrives, and your webhook and Telegram alerts fire. No money, no chain, a few seconds
* It is deliberately the other half of test mode: test mode proves the chain reading works but needs a faucet and a wallet, while most of what worries a shop happens after the money is seen and involves no blockchain at all
* Only one rehearsal exists at a time, it is addressed to your own email rather than a customer's, and it never appears in your payments list or in what the shop took. Delete it when you are done and nothing is left behind — including the unique amount it was holding, which is given back so a real customer cannot be refused it
* Confirming a rehearsal marks an order paid without a payment, so it will only ever act on an order it created and flagged as its own. That is checked in the code itself, not only in the screen that offers it

= 1.22.0 =
* New: test mode. Point the shop at test networks and rehearse a payment with coins that are free and worth nothing — a real quote, a real address, a real payment page, a transfer read off a live chain, a confirmed order, an email, a webhook. Until now the only way to know a shop would take a payment was to send one, and the wrong address or the wrong network is an expensive way to find out
* Bitcoin testnet3, Ethereum Sepolia and TRON Nile, and only those. A test network has to be one this plugin can genuinely read, with a faucet you can get coins from today; the alternative is shipping endpoints that quietly fail. Your other coins are hidden from checkout while test mode is on rather than left half-working, and tokens are left out because a token has a different contract on every test network
* The Wallets tab accepts only test addresses while test mode is on, and refuses them again when it is off. A real Bitcoin address saved during a rehearsal would sit on a chain nothing was watching. Your real addresses are kept, not overwritten
* Test mode is never quiet about itself: a banner on every admin screen that cannot be dismissed, a warning on the readiness check, and a notice on the payment page itself saying real coins sent to that address are lost. A shop left in test mode looks completely normal from the outside

= 1.21.0 =
* New: "Money with no order" on the Payments screen. It reads your receiving addresses and subtracts every transfer the plugin can already account for; what is left is money you have that no order explains — a customer paying from an address they saved, a second payment for an order already settled, or a payment that arrived after an order was cancelled. None of that is visible to the ordinary payment check, which only ever asks whether one expected amount has turned up
* It will not tell you what it does not know. Bitcoin, Bitcoin Cash, Litecoin, Dogecoin, Dash, Zcash, eCash, Ethereum and every EVM chain, Bitcoin Gold, Firo, Ravencoin, PIVX, Harmony, PulseChain, Syscoin, Boba and Bitgert can be read this way. The rest cannot, and the screen names them rather than letting an empty result read as an all-clear. A coin whose explorer refused to answer is reported separately again, because that is not the same as finding nothing
* Nothing on that screen changes an order. It reads chains and reports; what an unexplained transfer means is your decision, and a scan only runs when you press the button, because it spends your explorer allowance
* Amounts are read with integer arithmetic throughout, so an 18-decimal balance larger than PHP can hold as a number is still exact

= 1.20.0 =
* New: a customer who has paid now gets a straight answer instead of a spinner. Pressing "I have sent the payment" reads the chain and says what was found — confirmed, arrived and waiting for confirmations, part-paid with the exact amount still owed, or nothing yet. The "waiting for confirmations" answer is the one that stops somebody paying a second time
* New: a box beside that button for the transaction id, if the customer's wallet gave them one. It is not proof and it is not used to match the payment — only a transfer to the shop's address for the right amount does that — but it is what you need to trace a transfer that went astray, and it is recorded on the order and in the timeline
* New: when nothing has arrived yet, the answer names the two mistakes that actually cause it — sending on the wrong network, and leaving out the destination tag or memo — instead of saying "not found"
* New: an order where the customer said they paid, gave a transaction id, and the window then closed with nothing found now appears in "Needs you". Only once the window has closed, so an impatient customer cannot fill that queue
* New: two checks on the Coins tab for the things that break a working setup from outside it. One catches WP_HTTP_BLOCK_EXTERNAL in wp-config.php, which stops every chain and rate lookup while making it look like nobody has paid. The other asks the site the same question a customer's browser asks, which is how a security plugin, firewall or coming-soon mode intercepting the payment page shows up
* New: eight columns in the payments export for whoever has to account for the money — the rate the order was quoted at, what the coins that arrived were worth at that rate, the price adjustment applied, when the order was quoted, seen and confirmed, how many confirmations it needed, and any transaction id the customer reported
* The rate an order is quoted at is now recorded on the order. It could not be recovered afterwards — by the time anyone exports, the market has moved. Orders placed before this release fall back to working it out from what was charged, which is the same number

= 1.19.9 =
* New: you can now export and restore the configuration in parts, not just all at once. Choose everything, the wallet addresses and extended keys on their own, or the API keys on their own. Moving wallets to a staging site no longer carries that shop's limits and alerts with them, and handing someone the API keys no longer means handing over everything else
* New: those controls now appear at the bottom of Wallets and Prices & APIs as well as General, each opening on the part of the configuration that tab is about — because nobody looking for "move my addresses to the new site" thinks to look under settings backup
* A restore now changes only what the file contains. Anything the file leaves out is kept exactly as it is on this site, and the message afterwards says which kind of file it was
* Choosing "API keys and tokens only" always includes the keys — that is what the file is for — and the screen says to treat it as a password. Choosing "wallet addresses only" never includes them, even if the box is ticked
* New: WooCommerce now shows "Set up" instead of an enable toggle until at least one coin has somewhere to receive, so the gateway cannot be switched on in front of customers before it can take a payment
* New: the transaction id on the order screen is now a link to that coin's block explorer, instead of text to copy and paste somewhere else

= 1.19.8 =
* The plugin folder now holds one readme instead of two. readme.txt is the one WordPress reads for the "View details" screen; README.md was a second copy of the same information written for GitHub, and it no longer ships
* Documentation: the supported PHP range is now stated as 7.4 through 8.5, with every one of those versions tested on each release, in place of the old "7.4+"
* Documentation: the plugin works with either WooCommerce order storage — High-Performance Order Storage or the older posts table — which is now said plainly in the requirements and answered in the FAQ. If you are still on the posts table, 1.19.6 is the release that matters to you

= 1.19.7 =
* Fixed: on PHP 8.4 and 8.5 the Payments CSV export could download as a corrupt file. PHP 8.4 deprecated leaving the CSV escape character unstated, and on a shop with error display switched on that deprecation was printed into the download itself, in front of the spreadsheet. The escape character is now always given, which is also the behaviour a spreadsheet expects
* Fixed: an amount from a block explorer was read with hexdec(), which silently drops characters it does not recognise instead of failing. A malformed or hostile answer therefore became a plausible-looking number rather than no answer at all — the word "nineteen" was read as the amount 921312. Anything that is not a clean 256-bit hex integer is now treated as no amount, so the payment simply does not match
* Fixed: the same conversion ran one calculation per character with no limit on length, so a large enough reply from an explorer could tie up the site. Values wider than any chain can express are refused outright
* Hardened: a coin's decimal places are now bounded before being used to build padding, a coin identifier that is not text is no longer used to look one up, and a coin definition supplied by another plugin's filter may leave fields out without causing warnings. Wallet addresses are normalised before they are measured and matched
* Fixed: uninstalling left one cached option behind. The record of how far along your extended public key addresses have been handed out is still kept deliberately — deleting it would send a reinstall back to the first address and re-use addresses that already belong to past orders
* Tested and released against PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5. Every supported version now runs the full test suite on each change, and a new check fails the build if any of them raises a single deprecation or warning

= 1.19.6 =
* Fixed: on a shop that still stores orders as posts rather than in WooCommerce's own order tables, none of the plugin's order lookups were filtered at all. WooCommerce drops the `meta_query` on that store and, since WooCommerce 9.2, logs "Order query argument (meta_query) is not supported on the current order datastore" as it does so. The queries then answered far wider questions than they asked: "has another order already claimed this transaction?" became "does this shop have any other order?", which is true on every real shop, so payments were rejected as duplicates and never confirmed
* Fixed, same cause: the Payments screen listed every order in the shop instead of the crypto ones, its filters and counts did nothing, and the badge beside the menu counted orders that needed no attention
* Fixed, same cause: an expired order could lend its wallet address back to a new order even when it had no address to lend — an empty index reads as index 0, the very first address, handed out a second time. The index now has to be present and numeric before it is reused
* Shops already on High-Performance Order Storage were never affected; HPOS understands these queries natively and is left exactly as it was

= 1.19.5 =
* New: Kaia (KAIA) payments are confirmed on chain automatically once you add a free Kaiascan API key under Prices & APIs. Kaia publishes no free index of its own, so without a key it stays a manual coin — and the Coins tab and "Test this coin" both say which key is missing rather than failing quietly
* New: Polkadot (DOT) is payable again, confirmed by hand with "Mark payment received" like Monero. Subscan is the only service that will list an address's transfers and its key is a paid product, so it is offered honestly as manual rather than left out
* IoTeX stays manual. Its only address-history API answers HTTP 500 in production — to this plugin and to IoTeX's own block explorer — so no key would help

= 1.19.4 =
* Fixed: pressing Copy beside the address made the address appear to change. The button grew as it said "Copied!", which squeezed the box next to it and re-wrapped the address across its two lines. Copy buttons now keep their width while they confirm — on the payment page, the Wallets tab and the Payments screen
* Fixed: on a phone the "Open in wallet app" button hung past the edge of the payment box and was wider than the button below it. Both are now the same width and sit inside the box
* Fixed: on a narrower admin window the order date painted over the customer's email address on the Payments screen, and long amounts were cut off. The columns now keep to themselves, and the table scrolls sideways inside its panel rather than dragging the whole admin page with it
* Fixed: the reports table could stretch the admin page sideways on a narrow window for the same reason

= 1.19.3 =
* Removed: Polkadot (DOT) and Zilliqa (ZIL). Neither can be checked on chain without a key that is not freely available — Subscan is a paid product and ViewBlock no longer issues keys — so both could only ever be confirmed by hand. The Subscan and ViewBlock key fields are gone with them
* If you had either enabled, nothing breaks: the checkout stops offering them, your settings save cleanly, and any old order in one says its details are unavailable rather than failing

= 1.19.2 =
The payment page, read as a customer sees it.
* Fixed: an order you completed or set to processing in WooCommerce yourself still showed the customer a countdown and an address, and asked them to pay. They could pay a second time for an order you had already finished. Any settled, cancelled or refunded order now says plainly that there is nothing to pay
* Fixed: a confirmed payment kept counting down beside "Payment confirmed"
* Fixed: after a part payment the page showed the remaining amount beside the full order total, which read as though the whole order was owed again. It now shows what is left to pay
* The amount and address now come first, with the buttons beside them — the instructions follow. On a phone the amount used to be more than half a screen below the top of the box
* A settled order says "Paid with Bitcoin" rather than "Pay with Bitcoin"
* New: "Dealt with" on the Payments screen takes an order off the "Needs you" list. Late money and overpayments never stop being late or over, so that list could only ever grow. Anything that happens to the payment afterwards puts it back

= 1.19.1 =
Fixes found while auditing 1.19.0 against live chains, with every coin tested.
* Fixed: Algorand payments were never confirmed automatically. The plugin asked the indexer for a node-only endpoint, got a 404, and gave up before looking at a single transaction
* Fixed: XRP payment checks downloaded up to a hundred megabytes and timed out. The explorer answered an unknown address with a redirect to a bulk data file. XRP now uses the XRP Ledger's own public API — no key, and an empty address answers in under 200 bytes
* Fixed: "Test this coin" wrongly reported that the explorer "was not contacted" for Solana, TON, Cardano and Nano. Those chains answer over JSON-RPC, and the plugin was not recording what those calls did
* Fixed: "Test this coin" reported a failure for a brand-new receiving address on XRP and Stellar. Those chains have no record of an address until something is sent to it, which is normal, and it now says so
* Fixed: a paid CoinGecko key was sent to the free host and refused. Demo and Pro keys look identical, so the plugin now reads CoinGecko's own answer, uses the right host, and remembers it
* Fixed: Litecoin had a single source of chain data with no fallback, so a busy shop stopped seeing payments once that service's free allowance ran out. It now falls back to a second, key-less source — and only when the first does not answer
* Fixed: no exchange rate was available for Polygon when the main price source was unreachable. It was never misquoted; there was simply no backup. Now covered
* Fixed: an order screen action answered "500 server error" where it meant "you are not allowed to do that"
* Every explorer answer is now size-limited and redirect-limited, so no third-party service can stall a customer's payment page again
* New: an optional Blockchair API key under Prices & APIs. Dogecoin, Bitcoin Cash, Zcash, Dash and eCash are read through Blockchair, which stops answering once the day's free allowance is used — a free key raises that limit. Leave it empty and nothing changes
* Removed: Casper and Starknet. Neither has any way to detect an incoming payment without a paid or registered key, so they could only ever be confirmed by hand. Verge is removed too — its explorer no longer exists at any address we could find
* Monero, IoTeX and Kaia stay, and stay manual. Monero's is a property of the protocol; the other two are waiting on a free API key

= 1.19.0 =
Refunds that actually work in crypto, alerts on your phone, and your settings in one file.
* New: refunds. A crypto payment cannot be sent back the way it came — the address it arrived from is usually an exchange, and money returned there is gone. Instead you create a link, the customer opens it and gives an address they control on the right network, and you send it from your own wallet. The plugin carries the address and records the transaction; it never holds or moves your money
* New: alerts. Send what happens to a payment to a webhook of your own or a Telegram chat — part payments, overpayments, late money, a payment that vanished, a refund waiting to go out. Webhook requests are signed (HMAC-SHA256 over a timestamp and the exact body) so your endpoint can prove they came from your shop and reject an old one replayed at it
* New: a daily summary — what was paid, what it came to, and how many orders still need you
* New: backup and restore. Every setting in one file: coins, addresses, extended keys, limits, confirmations, prices. API keys are left out unless you ask for them, and a restored file is checked exactly as if you had typed it in
* New: a discount or surcharge per coin. Enter -2 on Bitcoin to take 2% off orders paid in it. It shows on the order as its own line, so the total the customer sees is the total they pay
* New: confirmations by order value. Take a small order as soon as it is seen, and hold a large one for longer. The higher tier can only ever raise the number a coin already waits for, never lower it
* Sixteen bundled translations brought up to date — everything added since 1.12.1, including the setup checks, the wallet buttons, the accessibility wording and the new screens

= 1.18.0 =
Find any payment again, see what happened to it, and know how crypto is actually doing.
* New: search your orders by transaction id or receiving address. "Where did this payment go?" stops being a support ticket — paste the hash the customer sent you and the order comes up
* New: every order records what happened to its payment, in order and with times — quoted, seen on chain, confirmed, part paid, expired, re-quoted, paid from a wallet — shown on the order itself
* New: "Check now" and "Give another hour" beside each payment, so a stuck order can be dealt with without waiting for the next scheduled check
* New: a summary on the Payments screen — what you took, how long customers typically waited, how many were quoted and never paid, how many sent too little — for the last 7, 30 or 90 days, and the same figures per coin. A coin quoted fifty times and paid twice is costing you checkouts, and now you can see it
* Fixed: the countdown showed "1431:41" on a stablecoin order given a day to pay. Long windows now read "23h 51m", and a screen reader hears hours or days rather than a count of minutes
* Fixed: the payment page listed the network as "TRX · trc20" while the warning above it said "TRON (TRC-20)". Both now use the name wallets and exchanges use

= 1.17.0 =
Pay from MetaMask or TronLink without copying anything.
* New: when a customer has a wallet in their browser, the payment page offers "Pay with MetaMask" (or whichever wallets they have) and fills in the address, amount and network for them
* Works with any wallet that announces itself the modern way — MetaMask, Rabby, Coinbase Wallet, Brave and others — on Ethereum, Arbitrum, Optimism, Base, BNB Chain, Polygon, Avalanche, Fantom, Cronos and Ethereum Classic, plus TronLink for TRX and TRC-20 tokens
* The wallet is put on the right network first, and checked again afterwards rather than trusting it — so a payment cannot go out on the wrong chain
* Every refusal is explained in plain words: cancelled, wallet already asking you something, not enough for fees, wrong network, network not set up
* A token payment is always a plain transfer. This plugin will never ask a customer to approve spending, which is what a scam looks like
* The address and QR code stay on the page throughout, and the transaction a wallet reports is only ever recorded — the order is still confirmed by checking the blockchain, exactly as before

= 1.16.0 =
Amounts customers can actually send, and honesty about payments that vanish.
* Fixed: stablecoin orders asked for amounts like 17.340010 USDT. Exchanges often only send to the cent, so that payment could not be made exactly — and what did arrive matched nothing. Stablecoins are now quoted in cents (17.36, 17.37), which any exchange can send, and orders are still told apart
* The extra few units that make each order's amount unique are now capped at one per cent of the order, so a busy shop never asks a customer for a noticeably odd amount
* New: a payment seen on chain that later disappears — replaced by the sender or dropped for too low a fee — no longer leaves the page claiming "payment detected" forever. The order goes back to waiting, the shop owner gets a note explaining it, and a fee-bumped replacement is followed rather than given up on
* New: what happened to a payment (part paid, overpaid, paid late, vanished) is recorded beside its status and shown on the Payments screen, instead of a bare "Check"
* New: coins pegged to your shop's currency get a full day to pay instead of an hour. There is no price to move, so there was never a reason to hurry

= 1.15.0 =
Usable with a screen reader, a keyboard, and one hand on a phone.
* Fixed: the coin picker showed no focus at all when moving through it with a keyboard
* Fixed: the payment page never told a screen reader that anything had changed — "payment detected" and "paid" now announce themselves, and the countdown speaks only at ten, five, two and one minute instead of every second
* Fixed: addresses, amounts and tags are isolated from the page's text direction, so they read correctly in Arabic and Hebrew shops, and the QR code is never mirrored
* New: on a phone the wallet button comes first and the QR code is tucked behind "Show QR code" — nobody scans the screen they are holding
* New: "My wallet will not scan this" shows a code carrying only the address, for wallets that refuse a code with an amount in it
* The page stops polling while it is in a background tab and checks immediately when you return, which saves the customer's battery and the shop's API budget

= 1.14.0 =
Prove your setup works before a customer pays, and tell them what they need to know.
* New: "Test this coin" on the Wallets tab checks the receiving address, the price, whether this site can read that blockchain, and the confirmations you are waiting for — and names the step that fails. No money moves
* New: anything that would stop payments working (gateway off, scheduler not running, no exchange rate, missing API key) is listed on the Payments screen and in WooCommerce → Status, where support looks first
* New: with an extended public key saved, the Wallets tab shows the next three addresses so you can check them against your own wallet before taking money
* New: the payment page warns, in plain words, to send on the right network — "Send USDT on TRON (TRC-20) only" — because that mistake cannot be undone
* New: the payment page says how long confirmation usually takes for that coin, and that the customer can close the page and wait for the email
* The Coins tab now loads its icons only as you scroll to them

= 1.13.0 =
Fixes from an independent security audit of everything added since 1.6.1.
* Fixed: a Bitcoin-style extended key (xpub) saved for Litecoin or Dogecoin produced Bitcoin addresses. Each coin now writes its own addresses, and the derivation tests cover all three coins
* Fixed: a wallet's master key was accepted where an account key was expected, which would have sent customers to a branch your wallet never scans. Only account-level keys are accepted now, and the Wallets tab shows the exact next address to check against your wallet
* Fixed: setting confirmations above 1 for a chain that settles on validation (XRP, Stellar, Cosmos, TON, EOS, Hedera and others) silently stopped verification for that coin. Those numbers are now refused with an explanation
* Fixed: a customer name containing a spreadsheet formula could run when the payments CSV was opened. Exported cells are neutralised
* Fixed: re-quoting an expired order could abandon a payment already on its way. An order whose payment has been seen on chain can no longer be re-quoted, the new quote forgets the old one's detection marks, and the previous address is recorded on the order
* Fixed: orders needing attention are now marked when it happens, so one from months ago is still listed instead of dropping off the end of the recent orders
* Fixed: abandoned orders no longer march an extended key's addresses past the point your wallet scans — an expired order gives its address back, and the Wallets tab warns when the gap grows
* Fixed: two orders on one address can no longer be given the same destination tag
* Payment detection now shares the store-wide limit on explorer lookups, and quote rate limiting no longer treats every shopper behind a proxy as one visitor

= 1.12.1 =
* Help now sits last in the menu, after Prices & APIs
* Help redesigned: a search box that filters the page as you type, a contents list that follows you down the page, and each topic in its own card — on a phone the contents fold above the text
* Portuguese (Brazil) translation completed, Arabic and Polish improved

= 1.12.0 =
A Help screen, and the plugin in sixteen languages.
* New: Help tab — what every setting does, how a payment is recognised, what the customer sees, what to do when something looks wrong, and the hooks a developer can use
* New: bundled translations for Arabic, Chinese (simplified), Dutch, French, German, Hindi, Indonesian, Italian, Japanese, Polish, Portuguese (Brazil), Russian, Spanish, Turkish, Ukrainian and Vietnamese. These are machine translations, so a native speaker's corrections are welcome; a site's own translation always wins over the bundled one

= 1.11.0 =
A fresh address for every order, if you want one.
* New: paste your wallet's receiving account key (xpub, ypub or zpub for Bitcoin; Ltub for Litecoin; dgub for Dogecoin) on the Wallets tab and each order is quoted its own address, so two payments can never be confused — no shared address, no guesswork
* This is a public key. It can only create addresses, never spend, and no private key is ever asked for: a private key (xprv/yprv/zprv) or anything malformed is refused on save with a message
* Address derivation is checked against the published BIP32, BIP44, BIP49 and BIP84 test vectors on every build
* Leaving the field empty keeps the existing behaviour (your saved addresses, with rotation)

= 1.10.1 =
* Fix: the Payments screen is usable on a phone — the tabs move above the content and each order becomes a labelled card instead of a table that scrolls sideways
* Fix: the table now fits an ordinary admin panel at every width, and its footer says how many orders there are next to the page links

= 1.10.0 =
Reassurance for the customer, less hunting for the shop owner.
* New: an "I have sent the payment" button on the payment page — the network is checked straight away and the page then watches more closely for a few minutes, instead of the customer waiting and wondering
* New: while a payment is visible but not yet confirmed, the page says how many confirmations it is waiting for
* New: the Payments menu carries a count of the orders that need you, like WordPress does for comments
* New: Download CSV on the Payments screen, exporting whatever is filtered on screen (coin, amounts, state, address, tag/memo, transaction)
* Payments table rebuilt to fit the screen: five columns, full transaction ids that wrap instead of being cut off, a sticky header, and pagination that says how many orders there are

= 1.9.1 =
* New: transaction ids on the Payments screen and the order screen link straight to that chain's public explorer, with a Copy button
* Fix: the Payments table no longer cuts transaction ids off, and scrolls instead of clipping columns on narrow screens
* Fix: pagination at the foot of the Payments table now shows how many orders there are and is styled like the rest of WordPress

= 1.9.0 =
See every crypto payment in one place.
* New: a Payments screen listing every crypto order — what was expected, what arrived, which coin, which transaction — with filters by state and coin
* New: a "Needs you" list of the orders that will not finish on their own: money that arrived late, more than was due, or a part payment left after the window closed
* New: a Crypto payment column on WooCommerce → Orders showing the coin amount and payment state at a glance
* New developer hooks: xdwp_payment_window_minutes, xdwp_confirmations_required, xdwp_coin_allowed_for_total, xdwp_order_memo and xdwp_payment_uri filters, plus the xdwp_payment_detected and xdwp_payment_renewed actions

= 1.8.0 =
Confirmations that fit the chain, and payments that identify themselves.
* New: each coin waits for the confirmations its own chain deserves — Bitcoin 2, Ethereum 12, TRON 20 and so on — instead of one number for all 238 coins (General → Confirmations per chain, on by default). Your store-wide number is still the floor, so this only ever makes verification stricter
* New: set your own confirmations per coin on the Coins tab; the grey number shows what the coin waits for now
* New: orders on XRP, Stellar, Cosmos, Secret, Sei, Injective, EOS, Hedera and TON get their own destination tag / memo, shown on the payment page, in emails and in the wallet link
* A payment carrying another order's tag or memo is never credited to this one; a payment with no tag still matches on its amount as before
* A payment that carries this order's own tag is treated as certain, so it is no longer held back as "could belong to another order" on a shared address

= 1.7.0 =
A clearer wait, and coins that fit the order.
* New: the payment page says "Payment spotted, waiting for confirmations" as soon as the transfer appears on chain, so customers are not left staring at an unchanged page while the network confirms
* New: expired orders can be re-quoted by the customer with one button — the order is kept and a new amount at today's rate is issued, instead of the order being lost
* New: search box in the coin picker (by coin name, symbol or network) and coin names under each icon, so picking from 238 coins is no longer guesswork
* New: optional minimum and maximum order value per coin (Coins tab), for coins whose network fees make small orders impractical
* New: "Open in wallet app" button on the payment page for customers paying on the same device
* Orders that already received a partial or late payment cannot be re-quoted, so amounts already sent are never orphaned

= 1.6.1 =
Price reliability.
* New: backup exchange-rate sources. If CoinGecko is rate-limited or down, rates come from public exchange tickers (Coinbase, Kraken, Binance) instead of checkout failing. When two sources answer they must agree within 5% or no rate is used — a wrong rate would quote a wrong amount
* New: stablecoins that track your store currency are priced 1:1, so a 17.34 order asks for exactly 17.34 USDT instead of 17.3465 (Prices & APIs → Stablecoin pricing, on by default; USDT, USDC, DAI, TUSD, USDP, GUSD, PYUSD, USDD, USDe, USDJ in USD stores and EURT in EUR stores)
* New: warning in the plugin settings when your store currency is not supported by the rate sources, instead of checkouts failing with no explanation
* Backup lookups are cached for 2 minutes, a failing source is skipped for 5 minutes, and every fallback is recorded in WooCommerce → Status → Logs

= 1.6.0 =
"No lost payments" release.
* New: crypto payment details in customer emails — the WooCommerce on-hold, pending and invoice emails now include the amount, network, wallet address, deadline and a button back to the payment page (QR code), so a customer who closes the tab can still pay
* New: payment reminder email to the customer about 15 minutes before the payment window closes (payment windows of 30 minutes or more; sent once)
* New: partial payments. A transfer of at least 50% of the amount due (typically an exchange deducting its withdrawal fee) marks the order "partially paid" instead of letting it silently expire: the customer is emailed the remaining amount, the payment page shows it with a new QR code, the payment window restarts, and the order completes automatically when the rest arrives. Further partial top-ups are added up. If the window closes first, the order fails with a note and the store owner is alerted to refund or complete it manually
* New: overpayments of up to 10% complete the order, with an order note and owner alert showing the excess to refund. Larger unexpected transfers are never accepted automatically
* New: late-payment scan — orders that expired in the last 7 days are re-checked (at most every 6 hours) and the store owner is emailed if a payment arrives; the order is left for the owner to complete with "Mark payment received" (transaction ID pre-filled) or refund. Can be switched off under General → Late payments
* New: "Crypto payment needs attention" store-owner email (partial, over and late payments), with its own recipient setting. All three new emails are standard WooCommerce emails: enable/disable, edit subject and heading, or override templates in your theme
* New developer hooks: `xdwp_order_paid`, `xdwp_order_underpaid`, `xdwp_order_overpaid`, `xdwp_order_expired`, `xdwp_late_payment_detected`, `xdwp_send_payment_reminder`
* Safety: a partial or late transfer is never taken if it could be another open order's exact payment, is never counted twice, and top-ups only count if sent after the partial was detected. Scans are throttled (one extra explorer lookup per order at most every 5 minutes)
* Fix: lock, claim and amount-reservation checks read straight from the database. They are written with raw SQL, and get_option() could return a stale cached value — with a persistent object cache (Redis/Memcached) across requests — which could make checkouts fail when an amount was already reserved
* Fix: when an amount is still reserved by an abandoned order, checkout now picks the next free amount instead of retrying the same one
* Admin order box shows received / still due / overpaid amounts, partial-payment transaction IDs and late-payment warnings
* Security (independent audit of this release): a non-exact transfer (partial, over or late) is credited only when it can belong to one order. On a shared address, a fee-short payment from one customer could otherwise be credited to a different, older unpaid order; such transfers are now left uncredited and the store owner gets one "unassigned payment to check" email. Exact payments of any order from the last week (including expired and cancelled ones) are always kept for that order; for partial/over windows only live orders and ones that expired within a day count. New setting "Partial and over payments" (General tab, on by default) — turn it off if your payment addresses also receive unrelated money
* Fixes from the audit: a final top-up processed twice by overlapping checks is counted once (no false "overpaid" note); the expiry check no longer expires an order it has just recorded a partial payment on; top-ups sent before the partial was detected, or larger than the remainder (exchange minimums), now complete the order; a failed order that already received crypto can't be paid again in full (customer is asked to contact the store); every new payment attempt starts with clean payment meta; cancelling a partially paid order stops checks; partially paid orders show on My Account → View order, with a "contact us" message if the window closes; the late-payment scan now reaches older expired orders and runs lighter (5 per minute); "Mark payment received" accepts Hedera/TON/Aptos transaction IDs; the payment lock is released only by its owner; privacy export/erase and uninstall cover the new data and email settings
* Payment page: the amount and address stay dark and monospaced even when a theme restyles `code` text (Divi showed them in light grey); the "Copy payment link" control is a clean text link on every theme
* Tested on 20 themes (incl. Divi, Woodmart, Betheme, The7, Flatsome, Porto) and 70+ plugins, individually and as a 30-plugin stack
* Fix: with some themes (seen with The7) WordPress logged "translation loaded too early" for this plugin because its cron-schedule label was translated before `init`

= 1.5.38 =
* Fix: updated coin icons did not show after updating the plugin. Icon URLs had no version, so browsers, CDNs and cache plugins kept serving the old cached SVG under the same URL (often for weeks). Coin and gateway icon URLs now carry the plugin version, like enqueued CSS/JS, so every update loads fresh icons
* Admin: the Wallets tab now shows each coin's icon (with its network badge for multi-network tokens such as USDT on TRON) on every wallet card, matching the Coins tab

= 1.5.37 =
* Security: signed updates. Every release ZIP is now signed with the maintainer's Ed25519 release key (a `.sig` asset produced by the release workflow from a key that is not stored in the repository), and the built-in GitHub updater only offers and installs releases whose signature verifies against the public key shipped in the plugin. The signed message covers the plugin slug, version and ZIP SHA-256, so a compromised GitHub account or release can neither push a modified package nor re-publish an old release as a newer version to roll sites back. Verification uses libsodium (PHP 7.2+) or WordPress core's bundled sodium_compat, and fails closed
* The Coins tab lists every manual-only coin (Monero, IoTeX, Casper, Kaia, Starknet) instead of Monero alone; tested-up-to raised to WordPress 7.1 / WooCommerce 11.1

= 1.5.36 =
Full audit on a live WordPress 7.1 / WooCommerce 11.1 test store: 13 themes, 53 plugins (cache, security, SEO, page builders, subscriptions, multi-currency, other gateways), classic and block checkout, plus a line-by-line security review mapped to the OWASP Top 10.
* Fix (critical): correct payments on 18-decimal assets (BSC USDT, DAI, larger ETH/BNB/MATIC/AVAX orders) were never detected above ~4 units — amount matching used floats that could not represent the match band. Matching now uses exact fixed-point integers.
* Fix (critical): the unique "dust" added to amounts could reach ~0.005 BTC (hundreds of dollars) on a small order. Each order now gets the smallest free unique amount (usually 10 base units, e.g. 10 sats).
* Fix: one expired order (unique amounts off, or 4-decimal coins) or 500 abandoned orders could permanently block checkout and detection for an address. Only recent orders on the same coin are considered, and collisions retry with a fresh amount / next address instead of failing checkout.
* Fix: request rate limits never reset under steady traffic, eventually refusing every visitor on an IP (all customers behind a CDN/proxy). Now fixed one-minute windows.
* Fix: other plugins' activation redirects could turn the quote/status response into an HTML page. Frontend requests now use WooCommerce's ?wc-ajax= endpoint (admin-ajax.php still accepted).
* Security: NEM verification switched to HTTPS-only nodes (plain HTTP allowed forged payments on the network path); Symbol checks the recipient; TON jettons re-check the token master per transfer; TRON native requires SUCCESS; Waves/Stellar/EOS fail closed on missing fields; per-coin exchange-rate freshness; pre-order quotes held 15 minutes max; site-wide cap on payment-page-triggered chain checks; cron lock can no longer be released by a stale run.
* Compatibility: payment-page scripts opt out of "delay JS" / defer / combine (LiteSpeed, WP Rocket, Perfmatters, SiteGround Optimizer, Jetpack Boost, Cloudflare Rocket Loader) so the QR code and countdown appear immediately; checkout script kept out of Autoptimize's combined bundle and waits for jQuery if loaded late; no inline onclick handlers (strict CSP).
* Checkout UX: exchange rates for all enabled coins fetched in one request with backoff (the free CoinGecko limit was hit after ~9 checkouts); friendly customer error with the real reason logged and noted on the order.
* Payment page UX: "complete your payment below" link at the top of the thank-you page; styled Copy buttons next to the amount and address; labelled order total.
* Admin UX: notices no longer render inside the settings header; Etherscan errors (e.g. chain not in the free tier) are shown instead of failing silently; "Mark payment received" shows a success notice and error pages have a back link.

= 1.5.35 =
* Fix: the last 12 coins still showing a lettered placeholder (CATS, CNS, HMSTR, HOTCROSS, MRSOON, MYRO, NOW, NTVRK, PLX, QUACK, SUPER, XYM) now show their official project logos, taken from each token's own on-chain metadata or the project's listing. Every coin in the registry now has a real icon

= 1.5.34 =
* Fix: 105 coins added since 1.5.23 (TON, Kaspa, Starknet, Kaia, Aptos, Casper, Stratis, THORChain, Sei, PulseChain, ZKsync, Notcoin, Dogs, Floki, LayerZero and more) were still showing a lettered placeholder circle instead of their real logo in the checkout coin picker and admin Coins list. They now use real brand icons from openly-licensed sources (cryptocurrency-icons CC0, web3icons MIT, Trust Wallet Assets MIT — see `assets/svg/coins/ATTRIBUTION.txt`). 12 tokens with no openly-licensed logo available (CATS, CNS, HMSTR, HOTCROSS, MRSOON, MYRO, NOW, NTVRK, PLX, QUACK, SUPER, XYM) keep their placeholder

= 1.5.33 =
* Full security/correctness/compatibility re-audit against a rewritten, comprehensive audit checklist covering the entire 238-coin plugin (financial-correctness logic, admin fields, theme/plugin compatibility, dead code, and automated test coverage — see `AUDIT_PROMPT.md`, updated as part of this pass to reflect the plugin's current scale after the last three coin-addition batches). Fixes:
  * **Theme compatibility**: the customer-facing payment box (`templates/payment.php`) was loaded via a raw PHP `include`, which meant no theme or child theme could override it — the standard WooCommerce extension convention (used by WooCommerce itself and most other WooCommerce extensions) lets a theme copy the template to `yourtheme/xorro-direct-wallet-payments-woocommerce/payment.php` to customize it. Switched to `wc_get_template()`. Verified both that existing rendering is unchanged and that a theme override is now correctly picked up
  * **Test suite drift**: `tests/smoke-test.php` had three assertions hardcoding a stale plugin version (last updated many releases ago) that were failing on every run, and a Windows path-separator bug that made its own "no legacy identifiers in source" self-exclusion never match on Windows (backslash-separated paths), causing a false-positive failure on that platform. Both fixed; the suite now passes cleanly cross-platform
  * **Test coverage gap**: the smoke test predated the ~58 coins added across the three most recent batches and exercised none of them — added coin-catalog presence, `supports_auto_verify()` correctness (including the 5 deliberately manual-only coins), verifier-function-existence, and icon-file assertions for representative coins from every newly-added chain family, growing the suite from roughly 250 to over 410 passing assertions
  * **Documentation drift**: `readme.txt`'s `== External services ==` disclosure section hadn't been updated since well before the last three coin batches and was missing roughly 20 third-party API hosts now actually contacted (every Blockbook/Insight/Esplora-family UTXO explorer, api.coz.io, Theta's explorer, the Ark/Aeternity/ICON/Ontology/Klever/Tectum/NEM/Symbol/THORChain/Lisk/Stratis/IOTA endpoints, TON/Cardano/Aptos/Kaspa/Tezos/Nano/Waves, and the Blockscout-family small-EVM-chain explorers). Brought current; also documented the new template-override capability in the FAQ
* Verified via automated cross-checks (no drift found, confirming prior batches' work held): `Xdwp_Wallets::is_plausible_address()` and `assets/js/wallets.js`'s pattern map cover the same verifier groups with no gaps; `Xdwp_Coins::supports_auto_verify()`'s allowlist exactly matches `find_payment()`'s switch cases with no silent no-ops (every coin the UI marks "auto-verify: yes" actually has a working code path, and every deliberately-manual coin is correctly excluded); every one of the 238 coins resolves to a real, well-formed icon file with zero orphaned SVGs; no dead/unreachable functions; every newly-added verifier function properly `rawurlencode()`s addresses/txids before URL interpolation (no SSRF-adjacent gaps); every chain-specific timestamp-scaling constant (NEM's network epoch, Symbol's epoch, ICON's microseconds, THORChain's nanoseconds, Aeternity's milliseconds) is correct
* No functional payment-verification logic changes in this release — this was a re-audit and hygiene pass, not a new-coin batch

= 1.5.32 =
* New: Lisk (LSK), Stratis (STRAX), and IOTA — the three chains flagged in earlier research as "bigger jobs" needing architecture investigation rather than a routine integration. All three turned out to have undergone major migrations since general knowledge of them was formed, verified live rather than assumed:
  * Lisk moved off its own standalone SDK chain onto an OP Stack Ethereum L2 (chain id 1135) — LSK today is an ERC-20 there (ETH is the L2's actual gas currency), verified via Lisk's own Blockscout instance. Needed a new `check_blockscout_v2_token()` verifier since Blockscout's modern `/api/v2/*` token-transfer list doesn't carry confirmation depth on the list rows themselves — a per-transaction detail call fills that in
  * Stratis's original C#/.NET chain and Cirrus sidechain are being retired; CoinGecko's own "stratis" listing (renamed "Xertra" there) now tracks a brand-new standalone StratisEVM chain (chain id 105105) with STRAX as its native gas currency, verified via its Blockscout instance — reuses the existing `check_blockscout_v2_native()` verifier with zero new code
  * IOTA fully migrated off the old Tangle/coordinator model onto "IOTA Rebased" (May 2025), a Move-VM DPoS ledger forked from Sui's architecture — no longer fits any UTXO/account/DAG pattern already in the plugin. The old "MIOTA" unit and Trinity's 81-char/Bech32 address format are both retired; addresses are now Sui-style 32-byte object addresses. New `check_iota()` verifier queries the official public Move-VM JSON-RPC and reads `balanceChanges[]` for a positive-amount entry owned by the merchant's address
* Registry now covers 238 coins/tokens

= 1.5.31 =
* New: 17 more coins across 13 new chain integrations, every one live-verified against real API responses (real addresses, real transactions, real field shapes) before writing any code
  * DigiByte (DGB), Komodo (KMD), and Verge (XVG) — three UTXO chains that turned out to run three genuinely different explorer APIs despite superficially similar addresses: DGB uses an Esplora/electrs-style API (`check_esplora()`), KMD uses a classic Insight API (`check_insight()`), and XVG runs a fully bespoke explorer (`check_verge_explorer()`) — none of them Blockbook, so none could reuse the existing `check_blockbook()` helper
  * Qtum (QTUM) — a UTXO+EVM hybrid verified via its own Insight-derived API (`check_qtum()`); close to KMD's shape but with different field names (`outputs` vs `vout`, bare `address` vs an `addresses[]` array), so it gets its own function rather than a forced shared abstraction
  * Ark (ARK), Aeternity (AE), ICON (ICX), Ontology (ONT), Klever (KLV), Tectum (TET), NEM (XEM), Symbol (XYM), and THORChain (RUNE) — nine standalone chains, each verified via its own free keyless public API. Several had real gotchas caught by live-testing rather than trusting docs: ICON's amounts are wei-style hex parsed via bcmath (never trusting the API's own convenience float, which can silently lose precision at 18 decimals); Ontology's amounts are already human-decimal strings that sometimes omit the decimal point entirely on whole numbers (must `floatval()` directly, never re-divide by decimals); NEM's `timeStamp` is relative to the network's own 2015 epoch, not Unix time, and a transaction carrying a `mosaics` key is a mosaic transfer where `amount` is not a plain-XEM value; THORChain's officially-documented API host (`midgard.ninerealms.com`) has no DNS record at all today (confirmed dead, not just unreachable) — routed through a public gateway mirror instead
  * LGCY Network — its own "Supernova" mainnet and explorer are dead (DNS gone, confirmed from two independent network paths); the coin as actually held and traded today is its leftover Ethereum ERC-20 token, added as such with zero new verifier code
  * IoTeX (IOTX) and Casper (CSPR) added as payable with manual confirmation only (like Monero) — IoTeX's only Etherscan/Blockscout-clone explorer API is dead in production (confirmed live: every call errors or 502s) with no free address-history alternative; Casper's only "list address transactions" API (CSPR.cloud) requires a registered key on every request (confirmed live via 401), and the public node RPC has no address-history index at all
  * Decred (DCR) investigated but not added — its only real explorer (dcrdata.decred.org) timed out on every attempt from two independent networks (this audit's research sandbox and this project's own dev machine), on both IPv4 and IPv6 — likely anti-scraping IP blocking. Revisit once reachability is confirmed from the actual production host
* Dual address validation (server-side `Xdwp_Wallets::is_plausible_address()` and the client-side `assets/js/wallets.js` pattern map) added for all 17 new coins, plus BIP-21 URIs for DGB/KMD/XVG
* Real CC0 icons added for DGB, KMD, XVG, QTUM, ARK, ICX, ONT, XEM, IOTX, and AE; KLV, XYM, RUNE, CSPR, LGCY, and TET ship with original monogram placeholders pending real brand assets
* Registry now covers 235 coins/tokens

= 1.5.30 =
* New: 9 more coins across 3 new chain integrations, all live-verified against real API responses before writing any code (a prior research pass's simplified description of Theta's API shape was caught and corrected this way — see below)
  * Bitcoin Gold (BTG), Firo (FIRO) and its legacy Zcoin ticker (XZC), Ravencoin (RVN), and PIVX — five independently-hosted UTXO chains that all run Trezor's Blockbook explorer software, so this ships one generalized `check_blockbook()` verifier rather than five near-duplicate ones
  * NEO and GAS — two native assets on the same NEO N3 chain, told apart purely by NEP-17 contract scripthash, verified via the community-hosted, keyless api.coz.io explorer
  * Theta Network (THETA) and Theta Fuel (TFUEL) — two native assets on the same chain, verified via Theta's own official explorer API. A single transaction can carry both a `thetawei` and a `tfuelwei` amount on the same output, so one `check_theta()` function serves both, parameterized only by which denom key to read. Verified the real JSON nesting directly against a live response (`data.outputs[].address` / `data.outputs[].coins.{denom}`) after an earlier research pass reported a simplified, incorrect flat shape
* Dual address validation (server-side `Xdwp_Wallets::is_plausible_address()` and the client-side `assets/js/wallets.js` pattern map) added for all 9 new coins, plus BIP-21 URIs for the 4 new UTXO chains
* Real CC0 icons added for BTG, FIRO/XZC, RVN, PIVX, NEO, GAS, and THETA; TFUEL ships with an original monogram placeholder pending a real brand asset
* ZEN (discontinued, migrated to an ERC-20 on Base), DIVI (no viable verification API found), and FTN (its Blockscout explorer is currently down) investigated and confirmed not viable — not added
* Registry now covers 219 coins/tokens

= 1.5.29 =
* Security/correctness: re-audited the full payment-verification engine against the project's security audit checklist, focused on every chain integration added since the last full pass (TON, Cardano, Aptos, Kaspa, Starknet, Kaia, the small-EVM-chain family, Tezos, Nano, Waves). Found and fixed a real destination-matching bug: `check_ton_native()`/`check_ton_jetton()` compared the transaction's destination against a client-side-normalized form of the merchant's address, but if that normalization ever failed to produce a value, the check silently degraded to "any non-empty destination passes" instead of rejecting — since toncenter's account-scoped transaction list includes both incoming and outgoing transfers, this could in principle let an outgoing send from the merchant's own wallet be misidentified as an incoming customer payment. Now fails closed (never matches) when normalization doesn't produce a comparable value
* Hardening: added TON and Waves to the admin Wallets page's shared-address/memo warning — both chains have a real comment/attachment field commonly used for exchange-style shared deposit addresses that this plugin's verifiers don't check, the same category of risk already flagged for XRP/EOS/XLM/HBAR/ATOM/SCRT/SEI/native INJ
* `AUDIT_PROMPT.md` updated to reflect the full current scope of the payment-verification engine (previously only listed the original ~17 chain integrations; now documents all 27+, including the address-normalization fail-closed requirement this pass's finding revealed)
* No other findings — AJAX, admin, updater, atomic counters, and order-state-machine code paths (unchanged since the last full audit) were spot-checked for regressions and remain sound

= 1.5.28 =
* New: 10 more coins across 8 new chain integrations
  * Harmony (ONE), PulseChain (PLS), Syscoin NEVM (SYSEVM), and Boba Network's BOBA token — small EVM-compatible chains not covered by Etherscan V2, verified via a new generalized "legacy Etherscan-clone" helper (the same field shape used by Blockscout and, for Boba, Routescan) rather than writing four near-identical one-off verifiers
  * Bitgert (BRISE) — same family, but its Blockscout deployment's legacy API path reliably times out, so this uses the newer v2 API shape instead
  * XDC Network — needed no new verifier at all: it's already covered by Etherscan V2 (chain id 50), just wired in with address normalization since XDC addresses are commonly written with an "xdc" prefix instead of "0x"
  * Tezos (XTZ), Nano (XNO), and Waves — verified via their own public, keyless APIs (TzKT, a public Nano RPC proxy, and a public Waves node respectively). Nano's decimals (30!) were confirmed precisely, not assumed, given how easy that figure is to get wrong
  * Kaia (KAIA, formerly Klaytn) — added as payable with manual confirmation only (like Monero): its only explorer API is credit-metered with an unconfirmed free daily allowance, not worth risking exhaustion under this plugin's repeated polling pattern
* Every auto-verified addition live-tested against a real address with real transaction history before shipping; all correctly rejected an impossible amount band and returned false rather than a false match
* EthereumPoW (ETHW) investigated and confirmed dead — its announced explorers are either domain-squatted or fail to resolve — not added
* Registry now covers 210 coins/tokens

= 1.5.27 =
* New: Starknet (STRK) — added as a payable coin with manual confirmation only, the same model as Monero. Detecting an incoming transfer automatically would require Voyager's paid block-explorer API (Starknet's own JSON-RPC can't filter Transfer events by recipient — that field isn't an indexed key on the standard Cairo ERC-20, and Voyager has no free tier), so this ships without wiring up a paid-only dependency. Customers can still pay; the merchant confirms via the existing "mark paid" flow
* Icon added for STRK (original monogram placeholder, pending a real brand asset)
* Registry now covers 200 coins/tokens

= 1.5.26 =
* New: Kaspa (KAS) — a new from-scratch verifier via Kaspa's official public REST API (no key required). Kaspa's GHOSTDAG BlockDAG has no simple linear block-confirmation count, so this gates on the API's own `is_accepted` flag plus its accepting block's blue score compared against the network tip — scaled for Kaspa's ~10-blocks/second rate so the shared "minimum confirmations" setting means a comparable real-world wait as on slower chains. Live-tested against a real address with real transaction history
* Icon added for KAS (original monogram placeholder, pending a real brand asset)
* Registry now covers 199 coins/tokens

= 1.5.25 =
* New: three brand-new blockchain integrations, each with its own from-scratch verifier (not just new tokens on an already-supported chain):
  * TON (The Open Network) — native Toncoin plus 8 Jetton tokens (Notcoin, Catizen, Hamster Kombat, Dogs, Cats, X Empire, JetTon Games, TON Station), verified via toncenter's public API. Accepts any TON address form (raw or friendly, bounceable or not) and normalizes correctly before matching
  * Cardano (ADA) — verified via Koios's public API (no key required), matching eUTXO outputs the same way BCH/LTC/DOGE already sum multi-output payments
  * Aptos (APT) — verified via the Aptos Indexer GraphQL API. Requires a free Aptos API key (new field under Prices & APIs) because Aptos's post-migration Fungible Asset balance model makes anonymous indexer access impractically rate-limited; deposit-event detection confirmed directly against Aptos's own indexer source code
* Fix: the admin Wallets tab's client-side address-format validation (assets/js/wallets.js) had fallen behind the server-side rules over several past releases — DOT, ATOM, ALGO, NEAR, FIL, HBAR, EGLD, ZIL, EOS, and every chain added this session had no dedicated client-side pattern and fell back to a generic length check. Not a functional break (the fallback still worked), but synced the full pattern list so format mistakes are caught immediately while typing, not just on save
* Icons added for all 10 new coins (real brand icon for ADA; original monogram placeholders for TON and its 8 Jettons, pending real brand assets)
* Registry now covers 198 coins/tokens

= 1.5.24 =
* Fix: the "Product price coin" dropdown under Prices & APIs labeled its USDT/USDC options "Tether (Ethereum)" / "USD Coin (Ethereum)" — reads like a different, missing coin. Since this setting only controls a price-reference display (shown as "/ 50.00 USDT" next to product prices, chain-agnostic), relabeled to plain USDT/USDC; the Coins and Wallets tabs, where the network distinction is meaningful, are unaffected

= 1.5.23 =
* Fix: 104 of 187 coins (everything added since the original ~80-coin catalog) had no bundled icon and rendered as a plain text tile in the checkout coin picker and admin Coins list instead of an icon
* Added 15 real brand icons from the project's existing CC0 icon source (cryptocurrency-icons) that were available but not yet bundled: 1INCH, BAT, BTT, CHZ, CVC, DASH, ENJ, FUN, GRT, HOT, KNC, OMG, REP, YFI, ZEC
* Added original, deterministically-colored monogram placeholder icons for the remaining ~83 tickers with no available brand asset, so every coin now renders as a proper icon tile — clearly documented in ATTRIBUTION.txt as placeholders, not official logos, and safe to swap out individually later

= 1.5.22 =
* New: Secret Network (SCRT), Sei (SEI), and Injective's native chain (INJ, alongside the existing Ethereum-bridged form) — three new Cosmos-SDK chains verified through a shared LCD REST helper generalized from the existing ATOM verifier. Confirmed live that Secret Network's privacy applies to CosmWasm/SNIP-20 contract state, not plain bank-transfer SCRT, so standard auto-verification is safe there; Sei's public LCD needed a different query parameter than every other chain here, now handled per-chain
* New: 9 more coins/tokens researched to close out remaining coverage gaps — FEG (BSC), TomoChain (Ethereum legacy ERC-20), Telos (BSC bridge, alongside the existing Ethereum form), Wrapped Bitcoin on Polygon (alongside the existing Ethereum form), Solidus Ai Tech, and ZKsync's ZK token (Ethereum) — every contract address verified against CoinGecko's platform data
* Hardening: wallet address format validation extended to the three new Cosmos-SDK chains
* Coin-coverage research completed: every remaining candidate from the original NowPayments coverage audit is now resolved to either implemented or explicitly not addable (delisted from CoinGecko, no verifiable contract, or an incompatible token standard) — see the coverage ledger for details
* Registry now covers 187 coins/tokens (up from 81 at the start of this audit)

= 1.5.21 =
* Security/correctness: eCash (XEC) payment matching compared destination addresses case-sensitively — XEC uses the same CashAddr scheme as Bitcoin Cash, which is case-insensitive by spec, so a legitimate payment could fail to match on a case difference and never mark the order paid
* Hardening: added dedicated wallet-address format validation for Dash, Zcash, and eCash (previously fell through to a generic length check) — also rejects Zcash shielded (z-address) addresses on save, since Blockchair's transparent-chain lookup can never see payments to one
* Hygiene: uninstall now also removes the cron mutex option, closing the one option key not already covered by the cleanup patterns
* Re-audited the full payment-verification, AJAX, admin, updater, and order-state-machine code paths against the project's security audit checklist — no other findings

= 1.5.20 =
* New: Telos (TLOS) — verified but accidentally left out of the 1.5.19 batch
* New: 12 more coins/tokens — BitTorrent, ChainGPT, Gravity, LayerZero, Rejuve.AI, and 1inch on a second network (BSC or Arbitrum) alongside their existing chain, plus Baby Doge Coin, Holo, Poolz Finance, Solar (Swipe), and the Ethereum-bridged form of Injective — every contract address verified against CoinGecko's platform data
* Registry now covers 178 coins/tokens (up from 81 at the start of this audit)

= 1.5.19 =
* Security: payment/txid/amount-slot locks no longer rely on add_option() (WP core has used INSERT...ON DUPLICATE KEY since 4.2, so two concurrent callers could both believe they held the same lock — verified against WP core and reproduced at the database level)
* Security: on-chain address matching for BTC/LTC/DOGE, TRON, XRP, and Polkadot is now exact (case-sensitive), matching how Base58Check/SS58 actually encode — previously case-insensitive, which is only correct for EIP-55 EVM addresses
* Fix: manual "Mark payment received" button did nothing — its form was nested inside WordPress's order-edit form (invalid HTML, silently dropped), so the click just saved the order instead of submitting; this is the only way to complete a Monero payment
* Fix: order note for manual mark-paid is now attributed to the acting admin instead of "system"
* Fix: trashing then restoring a stale order under HPOS (WooCommerce's default order storage) silently skipped the plugin's terminal-state safeguard, letting a dead order re-arm for auto-verification
* Fix: a customer's checkout coin selection could silently revert to a different coin due to an unsequenced update_order_review AJAX race
* Fix: cron polling now uses a real mutex (previous add_option()-based guard had the same non-exclusive issue as above) and stops after 50s instead of risking a timeout
* Fix: `wc_get_orders()` args in cron no longer trigger WooCommerce's "not supported on the current order datastore" notice under HPOS
* Hardening: emails the site admin when a wallet payout address is added, changed, or removed
* Hardening: registers a GDPR personal-data exporter/eraser so a customer's crypto order metadata is included in WordPress's own Export/Erase Personal Data tools
* Hardening: clear warning on XRP/EOS/XLM/HBAR/ATOM wallet fields that destination tags/memos aren't supported — don't use a shared or exchange-hosted address
* Hardening: plugin update downloads verify scheme (https-only) and are scoped to this plugin's own basename
* Fix: `_load_textdomain_just_in_time` notice on every page load (translated strings were being built before `init`)

= 1.5.19 =
* New: 68 additional coins/tokens across Ethereum, BNB Chain, Polygon, Base, Avalanche, and Solana — every contract/mint address verified against CoinGecko's platform data (1inch, ARPA, Banana Gun, BAT, Brett, Cartesi, ChainGPT, Chiliz, Chromia, Civic, Coin98, COTI, Cudos, Cult DAO, DAO Maker, Enjin, Ethena USDe, Euro Tether, Floki, Frontier, FunFair, GALA, Gate, Gravity, Hex, Hoge Finance, HotCross, Illuvium, JasmyCoin, JUST STPT-adjacent tokens, Kyber Network Crystal, LayerZero, Leash, MX Token, Netvrk, Ocean Protocol, OKB, OMG Network, Augur, PayPal USD, Pullix, Radio Caca, RichQUACK, Sidus, SparkPoint, SuperVerse, Tether Gold, The Graph, Bitgert, Bridge AI, Centric Swap, ChangeNOW, Ethereum (BSC), Hot Cross, Rejuve.AI, Seedify.fund, Shiba Inu (BSC bridge), SPACE ID, Tokocrypto, USD Coin Bridged (Polygon), Verse, XCAD Network, XYO, Yearn.finance, Travala, and more)
* Fix: caught and corrected before shipping — an internal typo would have added one wrong contract address; a full cross-check of every new address against its source verification data before commit is now standard practice

= 1.5.18 =
* New: Dash (DASH), Zcash transparent (ZEC), and eCash (XEC) — all verified via Blockchair
* New: TrueUSD, USDD, BitTorrent (BTT), JUST (JST), USDJ, Sun (SUN), WINkLink (WIN), and Sundog on TRON (TRC-20)
* New: Zebec (ZBC), Gari, cat in a dogs world (MEW), Ponke, and Myro on Solana (SPL)
* Fix: `check_blockchair()` now takes a per-chain decimals parameter — eCash rebased its display unit 1,000,000:1 in 2021, so the previous hardcoded /1e8 scaling would have under-counted every XEC payment 1,000x

= 1.5.16 =
* Security: XRP credits delivered_amount only (never Amount/DeliverMax — partial-payment underpay)
* Security: XRP object amounts treat value as drops (/1e6), matching XRPSCan (never whole-XRP mis-scale)
* Security: ATOM transfer events require recipient match (empty recipient cannot credit wallet)

= 1.5.15 =
* Security: manual mark-paid enforces same eligibility as admin UI (no txid squatting on cancelled/ineligible orders)
* Security: release txid claim if mark-paid fails after reserve; normalize txids to lowercase

= 1.5.14 =
* Security: soft_finality / 0-conf still requires successful/validated txs (never accept failed rows)
* Security: TRON rejects confirmed===false even at 0-conf
* Security: ZIL amounts always interpreted as Qa (no human-unit heuristic)
* Fix: release amount slots when orders are paid; shorten unpaid slot TTL to window+grace+1 day

= 1.5.13 =
* Security: cancelled/refunded/trashed orders stop auto-verify (xdwp_status=cancelled); verify/mark_paid require live WC payment statuses
* Security: XRP rejects validated===false even when tesSUCCESS is present
* Security: ZIL requires all present success flags (no OR bypass)
* Fix: admin mark-paid available for expired/failed late payments
* Hardening: shared-address peers include cancelled; cron self-heal on upgrade; notice when min_confirmations>1 blocks soft-finality chains

= 1.5.12 =
* Security: NEAR verification requires explicit success status (boolean true / SuccessValue); rejects missing/false
* Security: 0% underpayment tolerance is exact for low-decimal assets (no 50-unit absolute floor bypass on GUSD/etc.)
* Security: native TRX requires TransferContract type (fail closed when type missing)
* Hardening: NEAR deposit prefers non-zero actions[].deposit over zero actions_agg.deposit

= 1.5.11 =
* Security: Subscan/DOT requires explicit success+hash even when confirmations are present (failed transfers cannot mark paid)
* Hardening: wider explorer lookbacks (Etherscan/TronGrid/Solana) against deposit-address flooding delays

= 1.5.10 =
* Security: soft-finality chains (XRP, Stellar, EOS, EGLD, FIL, DOT, ZIL) require explicit success/finality flags — missing explorer fields no longer count as validated
* Fix: Helius RPC uses documented `?api-key=` plus `X-Api-Key` header (smoke tests aligned)

= 1.5.9 =
* Security: last-chance verify on expire + retain recent expired amounts to block payment reuse
* Security: atomic (address, amount) reservation; refuse checkout quote changes on collision
* Security: updater always requires SHA-256 (fail closed) with package-keyed checksum cache
* Security: TRON destination/contract checks; Helius key via Authorization header
* Security: order quotes fail closed on stale FX rates; soft-finality fail-closed when tip depth unavailable

= 1.5.8 =
* Security: shared-wallet peer matching paginates all awaiting orders (fail-closed) instead of a 25-order window
* Security: unique-dust cycle expanded; checkout refuses overlapping amount bands on the same address
* Security: txid claim and mark-paid locks use compare-and-swap (no TOCTOU reclaim races)
* Security: TRON verification fails closed without a resolvable block height
* Security: EVM token matcher requires matching contractAddress per row
* Security: manual mark-paid requires txid + confirmation and reserves the txid
* Security: API keys are never echoed in admin HTML; optional wp-config constants (XDWP_*_API_KEY)
* Cleanup: removed orphan usdt.svg, duplicate admin asset enqueue, and retired per-explorer API key fallbacks (migrated into Etherscan V2 key)

= 1.5.7 =
* Fixed Plugin URI vs Author URI: Author URI is the GitHub profile; Plugin URI remains the plugin repository (wordpress.org requirement)

= 1.5.6 =
* Security hardening: GitHub update ZIPs must match release SHA-256 and allowlisted download hosts
* Atomic wallet rotation index (LAST_INSERT_ID) under concurrent checkouts
* Admin warning when minimum confirmations is set to 0

= 1.5.5 =
* Auto-updates from GitHub Releases (Dashboard → Plugins “Enable auto-updates”)
* Update URI points at the GitHub repository so WordPress.org is not used as the update source

= 1.5.4 =
* Fixed TRC20 auto-verify with default confirmations (TronGrid only_confirmed + block lookup)
* Expanded unique-dust slots so more concurrent shared-wallet orders stay distinguishable
* Surface rejected wallet addresses and missing Etherscan key warnings in admin
* Hardened checkout.js against missing localized config

= 1.5.3 =
* Admin shell CSS is inlined via wp_add_inline_style so the settings UI cannot render unstyled if the stylesheet URL fails

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

= 1.5.17 =
Security: payment/txid locks were not exclusive under concurrent verification (add_option() on modern MySQL); address matching for BTC/LTC/DOGE/TRON/XRP/DOT is now case-sensitive. Also fixes a completely non-functional "Mark payment received" button (blocks Monero payments). Update recommended.

= 1.5.16 =
Security: XRP delivered_amount only + drops scale for XRPSCan objects; ATOM requires recipient. Update recommended.

= 1.5.15 =
Security: manual mark-paid eligibility matches admin UI; release txid claim on failed mark-paid; normalize txids to lowercase. Update recommended.

= 1.5.14 =
Security: soft_finality/0-conf never accepts failed txs; TRON confirmed===false rejected; ZIL amounts as Qa; amount slots released on paid. Update recommended.

= 1.5.13 =
Security: terminal orders stop auto-verify; XRP/ZIL success hardening; mark-paid for late expired payments. Update recommended.

= 1.5.12 =
Security: NEAR success status, exact 0% underpayment bands, native TRX TransferContract fail-closed. Update recommended.

= 1.5.11 =
Security: DOT/Subscan rejects failed transfers without success+hash; wider explorer lookbacks. Update recommended.

= 1.5.10 =
Security: soft-finality explorers require explicit success markers. Update recommended.

= 1.5.9 =
Security: blocks expired-order payment reuse, hardens updater checksums, and fails closed on stale rates / soft-finality chains. Update recommended.

= 1.5.8 =
Security hardening for shared-wallet matching, txid claims, TRON confirmations, token contract checks, and API key handling. Update recommended for all stores.

= 1.5.7 =
Plugin headers: Author URI now points to the GitHub profile so it differs from Plugin URI (wordpress.org requirement).

= 1.5.6 =
Hardens GitHub auto-updates with SHA-256 verification and fixes concurrent wallet rotation. Recommended update.

= 1.5.5 =
Adds GitHub Releases auto-updates. After installing 1.5.5 once, future versions can update from Dashboard → Plugins (enable auto-updates if desired).

= 1.5.4 =
Fixes TRON TRC20 auto-verify and expands unique payment amounts for concurrent checkouts. Update recommended if you accept USDT/USDC on TRON or reuse wallet addresses.

= 1.5.3 =
Admin settings UI stays styled even if the external admin.css request fails. Update if the Xorro admin shell looked unstyled.

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
