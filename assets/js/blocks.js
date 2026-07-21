/**
 * Checkout Blocks payment method for Xorro Wallet Payments.
 */
(function () {
	'use strict';

	var settings = {};
	if (typeof wc === 'object' && wc.wcSettings) {
		// WC 10+ exposes gateway data via getPaymentMethodData(name).
		// Older builds used getSetting(name + '_data').
		if (typeof wc.wcSettings.getPaymentMethodData === 'function') {
			settings = wc.wcSettings.getPaymentMethodData('xdwp', {}) || {};
		}
		if ((!settings || !settings.title) && typeof wc.wcSettings.getSetting === 'function') {
			settings = wc.wcSettings.getSetting('xdwp_data', {}) || {};
		}
	}

	if (!settings || !settings.title) {
		return;
	}

	var registerPaymentMethod =
		window.wc &&
		window.wc.wcBlocksRegistry &&
		window.wc.wcBlocksRegistry.registerPaymentMethod;

	var decodeEntities =
		window.wp &&
		window.wp.htmlEntities &&
		window.wp.htmlEntities.decodeEntities
			? window.wp.htmlEntities.decodeEntities
			: function (s) {
					return s;
			  };

	var createElement =
		window.wp && window.wp.element && window.wp.element.createElement
			? window.wp.element.createElement
			: null;

	var useState =
		window.wp && window.wp.element && window.wp.element.useState
			? window.wp.element.useState
			: null;

	var useEffect =
		window.wp && window.wp.element && window.wp.element.useEffect
			? window.wp.element.useEffect
			: null;

	var useRef =
		window.wp && window.wp.element && window.wp.element.useRef
			? window.wp.element.useRef
			: null;

	if (!registerPaymentMethod || !createElement || !useState || !useEffect || !useRef) {
		return;
	}

	var __ =
		window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function'
			? window.wp.i18n.__
			: function (s) {
					return s;
			  };

	var coins = Array.isArray(settings.coins) ? settings.coins : [];
	var display = settings.display || 'both';
	var iconUrl = settings.icon || '';
	var iconW = parseInt(settings.iconWidth, 10) || 32;
	var iconH = parseInt(settings.iconHeight, 10) || 32;
	var titleText = decodeEntities(settings.title);
	var ajaxUrl = settings.ajaxUrl || '';
	var nonce = settings.nonce || '';

	var Label = function () {
		var children = [];
		if ((display === 'both' || display === 'icon') && iconUrl) {
			children.push(
				createElement('img', {
					key: 'icon',
					src: iconUrl,
					alt: display === 'icon' ? titleText : '',
					className: 'xdwp-gateway-icon',
					width: iconW,
					height: iconH,
					style: {
						width: iconW + 'px',
						height: iconH + 'px',
						maxWidth: iconW + 'px',
						maxHeight: iconH + 'px',
						objectFit: 'contain',
						verticalAlign: 'middle',
						marginRight: display === 'both' ? '8px' : '0'
					}
				})
			);
		}
		if (display === 'both' || display === 'text') {
			children.push(
				createElement(
					'span',
					{ key: 'title', className: 'xdwp-blocks-label__text' },
					titleText
				)
			);
		} else if (display === 'icon') {
			children.push(
				createElement(
					'span',
					{ key: 'sr', className: 'screen-reader-text' },
					titleText
				)
			);
		}
		return createElement('span', { className: 'xdwp-blocks-label' }, children);
	};

	var Content = function (props) {
		var eventRegistration = props.eventRegistration || {};
		var emitResponse = props.emitResponse || {};
		var initial = coins.length ? coins[0].id : '';
		var coinState = useState(initial);
		var coin = coinState[0];
		var setCoin = coinState[1];
		var quoteState = useState('');
		var quote = quoteState[0];
		var setQuote = quoteState[1];
		var seqRef = useRef(0);

		useEffect(
			function () {
				if (!eventRegistration.onPaymentSetup) {
					return undefined;
				}
				var unsubscribe = eventRegistration.onPaymentSetup(function () {
					if (!coin) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: __('Please select a cryptocurrency.', 'xorro-direct-wallet-payments-woocommerce')
						};
					}
					return {
						type: emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								xdwp_coin: coin
							}
						}
					};
				});
				return unsubscribe;
			},
			[coin, eventRegistration, emitResponse]
		);

		useEffect(
			function () {
				if (!coin || !ajaxUrl || !nonce) {
					setQuote('');
					return undefined;
				}

				var seq = ++seqRef.current;
				var aborted = false;
				var controller =
					typeof AbortController === 'function' ? new AbortController() : null;

				setQuote('…');

				var attempt = 0;

				var run = function () {
					var body = new FormData();
					body.append('action', 'xdwp_quote');
					body.append('nonce', nonce);
					body.append('coin', coin);

					fetch(ajaxUrl, {
						method: 'POST',
						body: body,
						credentials: 'same-origin',
						signal: controller ? controller.signal : undefined
					})
						.then(function (res) {
							return res.json();
						})
						.then(function (res) {
							if (aborted || seq !== seqRef.current) {
								return;
							}
							if (res && res.success && res.data && res.data.amount) {
								if (res.data.coin && res.data.coin !== coin) {
									return;
								}
								var prefix = res.data.approx ? '≈ ' : '';
								var label = prefix + res.data.amount + ' ' + res.data.symbol;
								if (res.data.message) {
									label += ' — ' + res.data.message;
								}
								setQuote(label);
								return;
							}
							if (attempt < 1) {
								attempt += 1;
								window.setTimeout(run, 400);
								return;
							}
							setQuote('Unable to load rate. Try another coin or refresh.');
						})
						.catch(function (err) {
							if (aborted || seq !== seqRef.current) {
								return;
							}
							if (err && err.name === 'AbortError') {
								return;
							}
							if (attempt < 1) {
								attempt += 1;
								window.setTimeout(run, 400);
								return;
							}
							setQuote('Unable to load rate. Try another coin or refresh.');
						});
				};

				run();

				return function () {
					aborted = true;
					if (controller) {
						controller.abort();
					}
				};
			},
			[coin]
		);

		if (!coins.length) {
			return createElement('p', null, __('No cryptocurrencies are configured.', 'xorro-direct-wallet-payments-woocommerce'));
		}

		return createElement(
			'div',
			{ className: 'xdwp-blocks' },
			settings.description
				? createElement('p', { className: 'xdwp-desc' }, decodeEntities(settings.description))
				: null,
			createElement(
				'div',
				{ className: 'xdwp-coin-grid' },
				coins.map(function (c) {
					var classes = 'xdwp-coin-option';
					if (coin === c.id) {
						classes += ' is-selected';
					}
					if (!c.icon) {
						classes += ' xdwp-coin-option--text';
					} else if (c.badge) {
						classes += ' xdwp-coin-option--stable';
					}

					var children = [
						createElement('input', {
							type: 'radio',
							name: 'xdwp_coin_blocks',
							value: c.id,
							checked: coin === c.id,
							onChange: function () {
								setCoin(c.id);
							}
						}),
						createElement(
							'span',
							{ className: 'xdwp-coin-option__sr' },
							c.name
						)
					];

					if (c.icon) {
						children.push(
							createElement(
								'span',
								{ className: 'xdwp-coin-option__icon', 'aria-hidden': 'true' },
								createElement('img', {
									src: c.icon,
									alt: '',
									width: 28,
									height: 28,
									decoding: 'async',
									style: {
										width: '28px',
										height: '28px',
										maxWidth: '28px',
										maxHeight: '28px',
										objectFit: 'contain',
										display: 'block'
									}
								})
							)
						);
						if (c.badge) {
							children.push(
								createElement(
									'span',
									{ className: 'xdwp-coin-option__badge', 'aria-hidden': 'true' },
									createElement('img', {
										src: c.badge,
										alt: '',
										width: 14,
										height: 14,
										decoding: 'async',
										style: {
											width: '14px',
											height: '14px',
											maxWidth: '14px',
											maxHeight: '14px',
											objectFit: 'contain',
											display: 'block'
										}
									})
								)
							);
						}
					} else {
						children.push(
							createElement(
								'span',
								{ className: 'xdwp-coin-option__text', 'aria-hidden': 'true' },
								c.symbol || c.name
							)
						);
					}

					return createElement(
						'label',
						{
							key: c.id,
							className: classes,
							title: c.name
						},
						children
					);
				})
			),
			createElement(
				'p',
				{ className: 'xdwp-quote', 'aria-live': 'polite' },
				quote
			)
		);
	};

	registerPaymentMethod({
		name: 'xdwp',
		label: createElement(Label, null),
		ariaLabel: titleText,
		canMakePayment: function () {
			return Promise.resolve(coins.length > 0);
		},
		content: createElement(Content, null),
		edit: createElement(Content, null),
		supports: {
			features: settings.supports || ['products']
		}
	});
})();
