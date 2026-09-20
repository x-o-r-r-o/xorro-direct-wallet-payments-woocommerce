# Development history

`readme.txt` records what changed **for the shop owner**. This file records what exists **in the
code** — which planned work is done, which is not, where each piece lives, and which decisions are
deliberate rather than pending. It is the answer to "was that ever built, and if not, why not?"

**Keep it current.** Every release adds its row to §3 and moves its items in §1. An item that is
decided against goes to §2 with the reason, never silently dropped.

---

## 1. The research backlog

In September 2026 the plugin was compared against the paid and free competition (CodeCanyon,
wordpress.org, BTCPay, BitPay, CoinGate, OpenNode, CoinPayments, GoUrl, MyCryptoCheckout,
CryptoWoo) and against merchant complaints about those products. That produced 25 items, grouped
as correctness (A), customer experience (B) and administration (C). This is their status.

As of 1.19.0, 24 of the 25 are built. The one that is not — Solana Pay `reference` matching — is
an improvement on something that already works, not a gap; §2 says why it was left.

### A — Correctness

| # | Item | Status | Where |
|---|------|--------|-------|
| A1 | Per-coin amount rounding, so exchanges can actually send the quoted amount | **Done** 1.16.0 | `Xdwp_Coins::payable_decimals()`, `Xdwp_Prices::apply_unique_dust()` |
| A2 | Dropped / RBF-replaced transactions get their own state | **Done** 1.16.0 | `Xdwp_Verifier::transfer_vanished()`, `DROPPED_AFTER` |
| A3 | Solana Pay `reference` matching | **Not done** — see §2 | — |
| A4 | Two-field status: `status` + `flag`, instead of one growing enum | **Done** 1.16.0 | `Xdwp_Order::set_flag()`, `_xdwp_flag` |

### B — Customer experience

| # | Item | Status | Where |
|---|------|--------|-------|
| B1 | Pay from a wallet in the browser (EIP-6963 / TIP-6963) | **Done** 1.17.0 | `assets/js/wallet.js`, `Xdwp_Coins::wallet_payment()` |
| B2 | Mobile ordering: deep link above the QR, QR collapsed | **Done** 1.15.0 | `templates/payment.php` `#xdwp-qr-details` |
| B3 | Window length matched to price risk — a day for pegged coins, an hour otherwise | **Done** 1.16.0 | `Xdwp_Rates::pegged_rate()`, `Xdwp_Order::assign_payment()` |
| B4 | Network warning given real weight ("Send USDT on TRON (TRC-20) only") | **Done** 1.14.0 | `.xdwp-box__network`, `Xdwp_Coins::network_label()` |
| B5 | "You can close this page — we will email you" | **Done** 1.14.0 | `templates/payment.php` |
| B6 | Fiat-first hierarchy, rate + timestamp, per-coin ETA, address tail check | **Partly done** — see §2 | `Xdwp_Coins::wait_estimate()`, `≈ order total` line |
| B7 | After a part payment, the amount and QR carry only the shortfall | **Done** 1.15.0 | `Xdwp_Order::render_payment_box()` (`remainder`) |
| B8 | QR toggle: address-only, for wallets that choke on an amount in the URI | **Done** 1.15.0 | `renderQr(override)` in `assets/js/frontend.js` |
| B9 | Accessibility: focus rings, one polite live region, `role="timer"`, `<bdi>`, target size | **Done** 1.15.0 | `templates/payment.php`, `assets/css/frontend.css` |
| B10 | Stop polling in a hidden tab; derive the countdown from the server's expiry | **Done** 1.15.0 | `pollStatus()`, `updateTimer()` |

### C — Administration

| # | Item | Status | Where |
|---|------|--------|-------|
| C1 | Find an order by transaction id or address | **Done** 1.18.0 | `Xdwp_Payments_Admin::search_fields()` |
| C2 | "Test this coin" — address, rate, chain reachability, confirmations | **Done** 1.14.0 | `includes/class-xdwp-selftest.php` |
| C3 | Health panel, and the same facts in WooCommerce → Status | **Done** 1.14.0 | `Xdwp_Selftest::store_checks()`, `Xdwp_Admin::system_status_report()` |
| C4 | A timeline per order, including why a payment matched | **Done** 1.18.0 | `Xdwp_Order::log_event()` / `timeline()` |
| C5 | Refunds by claim link, the only non-custodial model | **Done** 1.19.0 | `includes/class-xdwp-refunds.php`, `templates/xdwp-refund-claim.php` |
| C6 | Row actions: check now, extend the window | **Done** 1.18.0 | `Xdwp_Payments_Admin::handle_row_action()` |
| C7 | Reporting: volume by coin, time to settle, abandonment, underpayment rate | **Done** 1.18.0 | `Xdwp_Payments_Admin::report()` |
| C8 | Webhooks (HMAC-SHA256), Telegram alerts, daily digest | **Done** 1.19.0 | `includes/class-xdwp-notify.php` |
| C9 | Settings backup/restore, per-coin discount or markup, risk-tiered confirmations | **Done** 1.19.0 | `Xdwp_Backup`, `Xdwp_Prices::adjusted_fiat()`, `Xdwp_Coins::confirmations_for_order()` |
| C10 | Show the next derived addresses so they can be checked against the wallet | **Done** 1.14.0 | `includes/admin/views/wallets-ui.php` |
| C11 | Coins tab weight: lazy icons, and grouping | **Done** 1.14.0 (lazy icons) | `includes/admin/views/settings-page.php` |

---

## 2. Not implemented, and why

Everything here is a decision or a queue position, not an oversight.

**A3 — Solana Pay `reference` matching.** Deferred at 1.17.0. A server-generated 32-byte
reference key would give exact per-order matching for SOL and SPL tokens with no unique-dust
amounts at all, which is strictly better than what SOL does today. It needs the transaction to be
found by that reference through `getSignaturesForAddress`, which is a different lookup path from
every other chain the verifier knows. SOL and USDC-SOL work correctly today through dust matching;
this is an improvement, not a fix.

**B6 — rate with a timestamp, and the "ends in …k2u4" address tail.** The fiat anchor
(`≈ $9.50 order total`) and the per-coin wait estimate shipped in 1.14.0. The other two did not.
The address tail exists in products that truncate the address in the middle; this plugin shows the
address in full, in a monospace box, so there is nothing to check a tail against. The rate line is
still worth adding.

**B1 — WalletConnect.** 1.17.0 covers wallets injected into the browser. A wallet on a phone,
paired by QR, needs a WalletConnect relay and a project id from a third party — an account the
merchant does not currently need. Mobile customers use the `Open in wallet app` deep link instead,
which works without any of that.

**Slack alerts.** 1.19.0 does webhooks and Telegram. Slack needs no code of its own — a Slack
incoming webhook is an ordinary HTTPS endpoint — but it expects `{"text": "…"}` rather than this
plugin's JSON, so it needs a few lines in between. Worth adding as a first-class option if anyone
asks for it.

**Emailing the refund link to the customer.** The shop sends it. A link that names where a
refund goes is a credential, and mailing it automatically to whatever address is on the order is
a worse default than letting the shop use the channel it already trusts for that customer.

**Some strings stay in English in every locale.** The bundled translations were refreshed in
1.19.0 and now cover 95–99.9% of the 799 strings, against roughly 40% before. What is left is
deliberate: where a machine translation dropped or reordered a `%s`, `%1$d` or an HTML tag, the
English is kept, because a translation that loses a placeholder prints a broken sentence.

Three attempts were made at each of those: the string as written, then with placeholders hidden
behind `{0}` tokens, then behind `«1»` tokens. The third recovered most of them — Russian went
from 28 unusable to 4. Two locales resist it because the model corrupts the string itself rather
than the token: Arabic rewrites `«1»` as `"١"` and drops the surrounding words, and Vietnamese
turns `%1$d` into `% 1$`, losing the `d`. No token scheme fixes that, so Arabic (32) and
Vietnamese (43) keep the most English. These are machine translations throughout and a native
speaker's corrections are welcome.

**`wallet_addEthereumChain` with a default RPC.** Deliberately never sent. Adding a chain means
handing a customer's wallet a third-party node chosen by this plugin. A shop that wants it can
supply its own through the `xdwp_wallet_add_chain` filter; otherwise the customer is told, in
words, to add the network themselves.

---

## 3. Release history

Full user-facing notes are in `readme.txt`. This is what each release put into the code.

| Release | Theme | Machinery introduced |
|---------|-------|----------------------|
| 1.0 – 1.4.5 | The gateway itself | `WC_Payment_Gateway` subclass, coin registry, address settings, QR, order-received payment box, cron verification |
| 1.5.0 – 1.5.28 | Coverage | Growth to 238 coins across 70+ chains; per-chain verifiers; rate sources; HPOS and Checkout Blocks support |
| 1.5.29 – 1.5.35 | Audits and assets | Repeated audits against a written checklist (TON fail-open fix among them); real logos for every coin |
| 1.5.36 | Live-store audit | Matching, dust and rate-limit fixes found by running a real shop |
| 1.5.37 | Supply chain | Ed25519-signed releases, signature enforced by the updater (`Xdwp_Updater`) |
| 1.6.0 – 1.6.1 | No lost payments | Partial payments, overpayment, late payments; backup rate sources; stablecoins priced 1:1 |
| 1.7.0 | Fit | Coins filtered to the order value; clearer waiting copy |
| 1.8.0 | Identity | Per-chain confirmation counts; memo/destination-tag matching (`Xdwp_Coins::make_memo()`) |
| 1.9.0 – 1.9.1 | One place to look | The Payments screen; explorer links, full transaction ids |
| 1.10.0 – 1.10.1 | Reassurance | Customer-facing assurance copy; Payments screen usable on a phone |
| 1.11.0 | A fresh address per order | BIP32/44/49/84 derivation from the merchant's own xpub, in `includes/class-xdwp-hd.php`, using Jacobian coordinates (8s → 80ms per address) |
| 1.12.0 – 1.12.1 | Explaining itself | The Help screen; sixteen bundled translations; Help moved last in the menu and redesigned |
| 1.13.0 | Independent audit | Two critical derivation bugs (xpub for LTC/DOGE producing Bitcoin addresses; master keys accepted); CSV formula injection; re-quote race; gap-limit recycling and warning |
| 1.14.0 | Prove the setup | `Xdwp_Selftest`; health checks in WooCommerce → Status; next derived addresses; network warning; wait estimates; lazy coin icons |
| 1.15.0 | Accessibility and mobile | Live regions, `role="timer"`, focus rings, `<bdi>` isolation, target sizes; QR behind a disclosure on mobile; address-only QR; polling paused in hidden tabs |
| 1.16.0 | Amounts that can be sent | Payable decimals per coin; unique dust spaced by one payable unit and capped at 1% of the order; vanished-transfer handling; the `flag` field; a day to pay for pegged coins |
| 1.17.0 | Pay from a wallet | EIP-6963 and TIP-6963 discovery, chain switch **and re-read**, balance pre-flight, plain-language errors, ERC-20 `transfer` only — never `approve` |
| 1.18.0 | Reconciliation | Order search by txid/address; per-order timeline; row actions (check now, extend); a period report read straight from order meta, bounded and cached. Countdown fixed for windows longer than an hour |
| 1.19.0 | Operations | Claim-link refunds: a hashed, expiring, rate-limited token, a front-end claim page, and an order panel that never sends money itself. Signed webhooks and Telegram alerts, queued out of band with backoff. Settings export/import with secrets held back. Per-coin discount or surcharge, applied once in pricing and mirrored as an order fee line. Confirmations tiered by order value. Bundled translations refreshed |

---

## 4. Where the machinery lives

| Path | Responsibility |
|------|----------------|
| `includes/class-xdwp-gateway.php` | The WooCommerce gateway: checkout, coin choice, order placement |
| `includes/class-xdwp-order.php` | Quoting an order, the payment box, order meta, the event timeline |
| `includes/class-xdwp-coins.php` | The coin registry, and everything coin-specific: decimals, confirmations, memos, explorers, payment URIs, wallet parameters |
| `includes/class-xdwp-prices.php` | Turning an order total into a coin amount, including the unique-dust allocation |
| `includes/class-xdwp-rates.php` | Exchange rates, backup sources, pegged-coin handling |
| `includes/class-xdwp-verifier.php` | Reading each chain, matching a transfer to an order, confirmations, vanished transfers |
| `includes/class-xdwp-hd.php` | xpub/ypub/zpub/Ltub/dgub derivation — a new address per order |
| `includes/class-xdwp-selftest.php` | The checks behind "Test this coin" and the health panel |
| `includes/class-xdwp-cron.php` | Scheduled verification, expiry, reminders |
| `includes/class-xdwp-notify.php` | Webhooks, Telegram, the daily summary |
| `includes/class-xdwp-refunds.php` | Claim links, and recording a refund the shop sent by hand |
| `includes/class-xdwp-backup.php` | Exporting and restoring the settings |
| `includes/class-xdwp-updater.php` | Update checks, and Ed25519 signature enforcement |
| `includes/admin/` | Settings, Wallets, Coins, Prices & APIs, Payments and Help screens |
| `templates/payment.php` | What the customer sees after placing the order |
| `assets/js/frontend.js` | Countdown, status polling, QR, copy buttons |
| `assets/js/wallet.js` | Paying from a wallet in the browser |
| `tests/` | `smoke-test.php`, `matching-tests.php`, `hd-tests.php`, fixtures |

---

## 5. Invariants

These hold across every release above. Breaking one is a defect, however good the reason looks.

1. **Non-custodial.** Money moves from the customer to the merchant's own address. No private key,
   seed or mnemonic is ever accepted, stored or transmitted — only extended *public* keys.
2. **Amounts are integer strings.** Matching uses fixed-point integer arithmetic at 1e18 through
   bcmath. A float never touches an amount comparison.
3. **A wallet is never asked to `approve`.** Token payments use `transfer` (`0xa9059cbb`).
   `approve` (`0x095ea7b3`) is what a drainer asks for, and this plugin must never look like one.
   `tests/smoke-test.php` asserts this.
4. **The chain decides.** A wallet's own report that it sent something is recorded, never trusted.
   An order is confirmed only by reading the blockchain.
5. **The manual route always stays.** Every convenience — wallet buttons, deep links, QR codes — is
   in addition to a visible address and amount that can be copied.
6. **A refund is sent by a person.** The plugin collects the address and records the
   transaction. It has no code path that moves money, and `tests/smoke-test.php` asserts that the
   refund code contains none.
7. **Address allocation is atomic.** Slots are claimed with `INSERT IGNORE` / `LAST_INSERT_ID`, so
   two simultaneous orders cannot be handed the same address and amount.

---

## 6. Verifying a change

Run with a plain PHP binary, not `wp eval-file`:

```bash
php tests/smoke-test.php && php tests/matching-tests.php && php tests/hd-tests.php
```

CI (`.github/workflows/ci.yml`) runs the same three on PHP 7.4, 8.0, 8.2 and 8.3, plus a JavaScript
parse check and WordPress coding standards (failing on errors, not warnings).
