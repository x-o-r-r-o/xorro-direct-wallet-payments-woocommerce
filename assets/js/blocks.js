/**
 * Checkout Blocks payment method for Chain Checkout.
 */
(function () {
	'use strict';

	var settings =
		typeof wc === 'object' &&
		wc.wcSettings &&
		typeof wc.wcSettings.getSetting === 'function'
			? wc.wcSettings.getSetting('chain_checkout_data', {})
			: {};

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

	if (!registerPaymentMethod || !createElement || !useState || !useEffect) {
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

	var Label = function () {
		var children = [];
		if ((display === 'both' || display === 'icon') && iconUrl) {
			children.push(
				createElement('img', {
					key: 'icon',
					src: iconUrl,
					alt: display === 'icon' ? titleText : '',
					className: 'chain-checkout-gateway-icon',
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
					{ key: 'title', className: 'chain-checkout-blocks-label__text' },
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
		return createElement('span', { className: 'chain-checkout-blocks-label' }, children);
	};

	var Content = function (props) {
		var eventRegistration = props.eventRegistration || {};
		var emitResponse = props.emitResponse || {};
		var initial = coins.length ? coins[0].id : '';
		var stateTuple = useState(initial);
		var coin = stateTuple[0];
		var setCoin = stateTuple[1];

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
								chain_checkout_coin: coin
							}
						}
					};
				});
				return unsubscribe;
			},
			[coin, eventRegistration, emitResponse]
		);

		if (!coins.length) {
			return createElement('p', null, __('No cryptocurrencies are configured.', 'xorro-direct-wallet-payments-woocommerce'));
		}

		return createElement(
			'div',
			{ className: 'chain-checkout-blocks' },
			settings.description
				? createElement('p', { className: 'chain-checkout-desc' }, decodeEntities(settings.description))
				: null,
			createElement(
				'div',
				{ className: 'chain-checkout-coin-grid' },
				coins.map(function (c) {
					var classes = 'chain-checkout-coin-option';
					if (coin === c.id) {
						classes += ' is-selected';
					}
					if (!c.icon) {
						classes += ' chain-checkout-coin-option--text';
					} else if (c.badge) {
						classes += ' chain-checkout-coin-option--stable';
					}

					var children = [
						createElement('input', {
							type: 'radio',
							name: 'chain_checkout_coin_blocks',
							value: c.id,
							checked: coin === c.id,
							onChange: function () {
								setCoin(c.id);
							}
						}),
						createElement(
							'span',
							{ className: 'chain-checkout-coin-option__sr' },
							c.name
						)
					];

					if (c.icon) {
						children.push(
							createElement(
								'span',
								{ className: 'chain-checkout-coin-option__icon', 'aria-hidden': 'true' },
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
									{ className: 'chain-checkout-coin-option__badge', 'aria-hidden': 'true' },
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
								{ className: 'chain-checkout-coin-option__text', 'aria-hidden': 'true' },
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
			)
		);
	};

	registerPaymentMethod({
		name: 'chain_checkout',
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
