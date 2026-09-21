/**
 * Pay from a wallet already installed in the browser.
 *
 * This is a shortcut, never the only route: the address, the amount and the QR code stay on
 * the page throughout, because a wallet can be missing, on the wrong network, or simply
 * refuse. Nothing here decides that an order is paid — the wallet's transaction hash is
 * recorded for the customer and the shop owner, and the payment is then confirmed against the
 * chain exactly as one typed in by hand would be.
 */
(function () {
	'use strict';

	var data = window.xdwpData || {};
	var pay = data.wallet || null;
	if (!pay) {
		return;
	}

	var host = document.getElementById('xdwp-wallet-pay');
	if (!host) {
		return;
	}

	var i18n = data.i18n || {};
	var providers = [];
	var busy = false;
	// Created on first use and kept, so a second attempt reuses the same session.
	var wcProvider = null;

	/**
	 * Collect the wallets that announce themselves.
	 *
	 * EIP-6963 exists because several wallets fighting over window.ethereum meant "the last
	 * one to load wins" — so ask, and let each answer for itself. Tron mirrors the same
	 * handshake as TIP-6963.
	 */
	function discover() {
		var seen = {};

		function add(detail, kind) {
			if (!detail || !detail.provider || !detail.info) {
				return;
			}
			var id = detail.info.rdns || detail.info.name;
			if (!id || seen[id]) {
				return;
			}
			seen[id] = true;
			providers.push({ name: detail.info.name, icon: detail.info.icon, provider: detail.provider, kind: kind });
		}

		if ('evm' === pay.kind) {
			window.addEventListener('eip6963:announceProvider', function (event) {
				add(event.detail, 'evm');
				render();
			});
			window.dispatchEvent(new Event('eip6963:requestProvider'));

			// A wallet that has not adopted the handshake yet is still worth offering.
			window.setTimeout(function () {
				if (!providers.length && window.ethereum) {
					providers.push({
						name: window.ethereum.isMetaMask ? 'MetaMask' : (i18n.walletGeneric || 'Browser wallet'),
						icon: '',
						provider: window.ethereum,
						kind: 'evm'
					});
					render();
				}
			}, 400);
		}

		if ('tron' === pay.kind) {
			window.addEventListener('TIP6963:announceProvider', function (event) {
				add(event.detail, 'tron');
				render();
			});
			window.dispatchEvent(new Event('TIP6963:requestProvider'));

			window.setTimeout(function () {
				if (!providers.length && window.tronLink) {
					providers.push({ name: 'TronLink', icon: '', provider: window.tronLink, kind: 'tron' });
					render();
				}
			}, 400);
		}
	}

	/**
	 * @param {string} text Message for the customer.
	 * @param {boolean} isError Whether it is a problem rather than progress.
	 */
	function say(text, isError) {
		var note = document.getElementById('xdwp-wallet-status');
		if (!note) {
			return;
		}
		note.textContent = text || '';
		note.className = 'xdwp-wallet-status' + (isError ? ' is-error' : '');
	}

	/**
	 * Turn a wallet's rejection into something a person can act on.
	 *
	 * @param {Object} err Error from the provider.
	 * @return {string} What to tell the customer.
	 */
	function explain(err) {
		var code = err && (err.code || (err.data && err.data.code));
		var message = (err && err.message) || '';

		if (4001 === code || /user rejected|user denied/i.test(message)) {
			return i18n.walletCancelled || 'You cancelled in your wallet. Nothing was sent.';
		}
		if (-32002 === code || /already pending/i.test(message)) {
			return i18n.walletPending || 'Your wallet is already asking you to approve something — open it and finish there.';
		}
		if (4100 === code) {
			return i18n.walletUnauthorised || 'Your wallet has not allowed this site to ask for a payment.';
		}
		if (4900 === code || 4901 === code) {
			return i18n.walletDisconnected || 'Your wallet is not connected to a network.';
		}
		if (/insufficient funds/i.test(message)) {
			return i18n.walletFunds || 'That wallet does not have enough to cover the amount plus the network fee.';
		}
		return message || (i18n.walletFailed || 'The payment could not be started in your wallet.');
	}

	/**
	 * Put the wallet on the right chain, adding it when the wallet has never heard of it.
	 *
	 * @param {Object} provider EIP-1193 provider.
	 * @return {Promise}
	 */
	function ensureChain(provider) {
		return provider.request({ method: 'eth_chainId' }).then(function (current) {
			if (String(current).toLowerCase() === String(pay.chainId).toLowerCase()) {
				return true;
			}
			say((i18n.walletSwitching || 'Switch to %s in your wallet…').replace('%s', pay.network || ''), false);
			return provider
				.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: pay.chainId }] })
				.catch(function (err) {
					// 4902 means the wallet has never heard of this chain. It can only be added
					// with an RPC endpoint, and this plugin will not put a third-party node in
					// front of a customer's wallet uninvited — a shop that wants that can supply
					// one through the xdwp_wallet_add_chain filter. Otherwise, say plainly what
					// the customer needs to do.
					if (err && 4902 === err.code) {
						if (pay.addChain) {
							return provider.request({ method: 'wallet_addEthereumChain', params: [pay.addChain] });
						}
						throw new Error((i18n.walletAddChain || 'Your wallet does not have %s set up yet. Add that network in your wallet, then try again.').replace('%s', pay.network || ''));
					}
					throw err;
				})
				.then(function () {
					// Do not trust the promise resolving — ask again.
					return provider.request({ method: 'eth_chainId' });
				})
				.then(function (after) {
					if (String(after).toLowerCase() !== String(pay.chainId).toLowerCase()) {
						throw new Error((i18n.walletWrongChain || 'Your wallet is still on another network. Switch it to %s and try again.').replace('%s', pay.network || ''));
					}
					return true;
				});
		});
	}

	/**
	 * Check the customer can actually cover this before opening the wallet, so a rejection is
	 * not the first they hear of it.
	 *
	 * @param {Object} provider EIP-1193 provider.
	 * @param {string} account  Paying address.
	 * @return {Promise}
	 */
	function preflight(provider, account) {
		if (pay.token) {
			// Token balances need a contract call; the wallet will say if it is short.
			return Promise.resolve(true);
		}
		return provider
			.request({ method: 'eth_getBalance', params: [account, 'latest'] })
			.then(function (balance) {
				var have = BigInt(balance);
				var need = BigInt(pay.value);
				if (have <= need) {
					throw new Error(i18n.walletFunds || 'That wallet does not have enough to cover the amount plus the network fee.');
				}
				return true;
			})
			.catch(function (err) {
				// A provider that will not answer eth_getBalance is not a reason to block the
				// payment — the wallet itself will refuse if the funds are short.
				if (err && err.message && /enough/.test(err.message)) {
					throw err;
				}
				return true;
			});
	}

	/**
	 * Tell the site which transaction the wallet produced, so it can be shown and traced.
	 *
	 * @param {string} txid Transaction hash.
	 */
	function record(txid) {
		if (!data.walletUrl) {
			return;
		}
		var body = new FormData();
		body.append('action', 'xdwp_wallet_sent');
		body.append('nonce', data.nonce);
		body.append('order_id', String(data.orderId));
		body.append('order_key', String(data.orderKey || ''));
		body.append('txid', txid);
		fetch(data.walletUrl, { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
	}

	/**
	 * @param {Object} entry Chosen wallet.
	 */
	function payWith(entry) {
		if (busy) {
			return;
		}
		busy = true;
		say(i18n.walletOpening || 'Opening your wallet…', false);

		var provider = entry.provider;

		if ('tron' === entry.kind) {
			payWithTron(entry).catch(function (err) {
				say(explain(err), true);
			}).then(function () {
				busy = false;
			});
			return;
		}

		provider
			.request({ method: 'eth_requestAccounts' })
			.then(function (accounts) {
				var account = accounts && accounts[0];
				if (!account) {
					throw new Error(i18n.walletNoAccount || 'No account was shared by your wallet.');
				}
				return ensureChain(provider).then(function () {
					return preflight(provider, account).then(function () {
						var tx = { from: account, to: pay.token || pay.to, value: pay.value };
						if (pay.data) {
							tx.data = pay.data;
						}
						say(i18n.walletConfirm || 'Confirm the payment in your wallet…', false);
						return provider.request({ method: 'eth_sendTransaction', params: [tx] });
					});
				});
			})
			.then(function (txid) {
				say(i18n.walletSent || 'Sent. This page will confirm the payment once the network has.', false);
				record(String(txid));
			})
			.catch(function (err) {
				say(explain(err), true);
			})
			.then(function () {
				busy = false;
			});
	}

	/**
	 * @param {Object} entry TronLink entry.
	 * @return {Promise}
	 */
	function payWithTron(entry) {
		var link = entry.provider;
		var request = link.request ? link.request({ method: 'tron_requestAccounts' }) : Promise.resolve();

		return request.then(function () {
			var tronWeb = link.tronWeb || window.tronWeb;
			if (!tronWeb || !tronWeb.defaultAddress || !tronWeb.defaultAddress.base58) {
				throw new Error(i18n.walletNoAccount || 'No account was shared by your wallet.');
			}
			var from = tronWeb.defaultAddress.base58;
			say(i18n.walletConfirm || 'Confirm the payment in your wallet…', false);

			if (pay.token) {
				return tronWeb
					.contract()
					.at(pay.token)
					.then(function (contract) {
						return contract.transfer(pay.to, pay.amount).send({ from: from });
					});
			}
			return tronWeb.trx.sendTransaction(pay.to, Number(pay.amount), { from: from }).then(function (result) {
				return (result && (result.txid || (result.transaction && result.transaction.txID))) || '';
			});
		}).then(function (txid) {
			if (txid) {
				say(i18n.walletSent || 'Sent. This page will confirm the payment once the network has.', false);
				record(String(txid));
			}
		});
	}

	/**
	 * Draw a button per wallet that answered. Nothing is drawn when none did, so the customer
	 * is never offered a button that cannot work.
	 */
	function render() {
		host.innerHTML = '';
		if (!providers.length) {
			return;
		}

		var label = document.createElement('p');
		label.className = 'xdwp-wallet-lead';
		label.textContent = i18n.walletLead || 'Or pay straight from a wallet in this browser:';
		host.appendChild(label);

		providers.forEach(function (entry) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'button xdwp-wallet-button';
			if (entry.icon) {
				var img = document.createElement('img');
				img.src = entry.icon;
				img.alt = '';
				img.width = 20;
				img.height = 20;
				button.appendChild(img);
			}
			button.appendChild(document.createTextNode((i18n.walletPayWith || 'Pay with %s').replace('%s', entry.name)));
			button.addEventListener('click', function () {
				payWith(entry);
			});
			host.appendChild(button);
		});

		if (pay.walletConnect && pay.walletConnect.projectId && pay.chainId) {
			var wc = document.createElement('button');
			wc.type = 'button';
			wc.className = 'button xdwp-wallet-button xdwp-wallet-button--wc';
			wc.appendChild(document.createTextNode(i18n.walletConnect || 'Pay from a wallet on your phone'));
			wc.addEventListener('click', function () {
				connectAndPay(wc);
			});
			host.appendChild(wc);
		}

		var note = document.createElement('p');
		note.className = 'xdwp-wallet-status';
		note.id = 'xdwp-wallet-status';
		note.setAttribute('role', 'status');
		note.setAttribute('aria-live', 'polite');
		host.appendChild(note);
	}

	/**
	 * Pair with a wallet on another device, then pay from it.
	 *
	 * The library this needs is fetched only when somebody presses the button — never on page
	 * load — so a payment page nobody uses this on carries no third-party code at all. What
	 * comes back is an ordinary EIP-1193 provider, which is why the paying below is the same
	 * code every other wallet goes through rather than a second implementation of it.
	 *
	 * Whatever happens here, the wallet still shows the customer the destination and the amount
	 * before they approve, the address stays printed on this page, and the order is confirmed
	 * only by reading the chain.
	 *
	 * @param {HTMLElement} button The button that was pressed.
	 */
	function connectAndPay(button) {
		if (busy) {
			return;
		}
		busy = true;
		button.disabled = true;
		say(i18n.walletConnecting || 'Opening the wallet connector…', false);

		loadWalletConnect()
			.then(function (provider) {
				busy = false;
				button.disabled = false;
				payWith({ provider: provider, kind: 'evm', name: 'WalletConnect' });
			})
			.catch(function (err) {
				busy = false;
				button.disabled = false;
				say(explain(err), true);
			});
	}

	/**
	 * Fetch the WalletConnect provider and open its pairing dialog.
	 *
	 * @return {Promise<Object>} An EIP-1193 provider.
	 */
	function loadWalletConnect() {
		if (wcProvider) {
			return Promise.resolve(wcProvider);
		}
		var cfg = pay.walletConnect;
		// A pinned version, not a range: the page must not silently start running different
		// code because an upstream tag moved.
		return import(/* webpackIgnore: true */ cfg.src)
			.then(function (mod) {
				var EthereumProvider = mod.EthereumProvider || (mod.default && mod.default.EthereumProvider) || mod.default;
				if (!EthereumProvider || typeof EthereumProvider.init !== 'function') {
					throw new Error(i18n.walletConnectFailed || 'The wallet connector could not be loaded.');
				}
				return EthereumProvider.init({
					projectId: cfg.projectId,
					chains: [parseInt(pay.chainId, 16)],
					showQrModal: true,
					metadata: cfg.metadata
				});
			})
			.then(function (provider) {
				wcProvider = provider;
				return provider.connect().then(function () {
					return provider;
				});
			});
	}

	discover();
})();
