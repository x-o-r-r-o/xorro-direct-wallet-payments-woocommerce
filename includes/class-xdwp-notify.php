<?php
/**
 * Telling somewhere else that something happened.
 *
 * A shop owner should not have to keep a browser tab open to find out that a payment arrived
 * short, or that one turned up two days late. This sends the events that matter to a webhook, to
 * Telegram, or both — and once a day, a summary of what the crypto gateway did.
 *
 * Nothing here decides anything about an order. It reports, and it never blocks the request that
 * caused it: every message goes out on a scheduled event of its own, so a slow endpoint cannot
 * hold up a customer's checkout.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Webhooks, Telegram and the daily digest.
 */
class Xdwp_Notify {

	/**
	 * The scheduled event that actually sends one message.
	 */
	const SEND_HOOK = 'xdwp_send_notification';

	/**
	 * The daily summary.
	 */
	const DIGEST_HOOK = 'xdwp_daily_digest';

	/**
	 * How many times a message is retried before it is given up on, and how long between tries.
	 */
	const MAX_TRIES = 4;

	/**
	 * Everything that can be reported, and how it reads in a message.
	 *
	 * @return array<string, string>
	 */
	public static function events() {
		return array(
			'paid'      => __( 'Payment confirmed', 'xorro-direct-wallet-payments-woocommerce' ),
			'underpaid' => __( 'Part payment received', 'xorro-direct-wallet-payments-woocommerce' ),
			'overpaid'  => __( 'Overpayment received', 'xorro-direct-wallet-payments-woocommerce' ),
			'late'      => __( 'Payment arrived after the window closed', 'xorro-direct-wallet-payments-woocommerce' ),
			'dropped'   => __( 'A payment seen on chain disappeared', 'xorro-direct-wallet-payments-woocommerce' ),
			'ambiguous' => __( 'A payment could not be matched to one order', 'xorro-direct-wallet-payments-woocommerce' ),
			'expired'   => __( 'Payment window closed with nothing received', 'xorro-direct-wallet-payments-woocommerce' ),
			'refund_address' => __( 'A customer gave an address for their refund', 'xorro-direct-wallet-payments-woocommerce' ),
			'refund_sent'    => __( 'A refund was sent', 'xorro-direct-wallet-payments-woocommerce' ),
		);
	}

	/**
	 * Events reported unless the shop says otherwise: the ones that need a person.
	 *
	 * @return array<int, string>
	 */
	public static function default_events() {
		return array( 'paid', 'underpaid', 'overpaid', 'late', 'dropped', 'ambiguous', 'refund_address' );
	}

	/**
	 * Listen for the things worth reporting.
	 */
	public static function init() {
		add_action( 'xdwp_order_paid', array( __CLASS__, 'on_paid' ), 10, 2 );
		add_action( 'xdwp_order_underpaid', array( __CLASS__, 'on_underpaid' ), 10, 4 );
		add_action( 'xdwp_order_overpaid', array( __CLASS__, 'on_overpaid' ), 10, 2 );
		add_action( 'xdwp_late_payment_detected', array( __CLASS__, 'on_late' ), 10, 3 );
		add_action( 'xdwp_payment_dropped', array( __CLASS__, 'on_dropped' ), 10, 2 );
		add_action( 'xdwp_ambiguous_payment', array( __CLASS__, 'on_ambiguous' ), 10, 3 );
		add_action( 'xdwp_order_expired', array( __CLASS__, 'on_expired' ), 10, 2 );

		add_action( self::SEND_HOOK, array( __CLASS__, 'deliver' ), 10, 2 );
		add_action( self::DIGEST_HOOK, array( __CLASS__, 'send_digest' ) );
	}

	/**
	 * Whether anything is configured to receive messages at all.
	 *
	 * @return bool
	 */
	public static function configured() {
		return '' !== trim( (string) Xdwp_Settings::get( 'webhook_url', '' ) )
			|| ( '' !== trim( (string) Xdwp_Settings::get( 'telegram_token', '' ) ) && '' !== trim( (string) Xdwp_Settings::get( 'telegram_chat', '' ) ) );
	}

	/**
	 * Whether this event is one the shop asked to hear about.
	 *
	 * @param string $event Event key.
	 * @return bool
	 */
	public static function wanted( $event ) {
		$chosen = Xdwp_Settings::get( 'notify_events', null );
		if ( ! is_array( $chosen ) ) {
			$chosen = self::default_events();
		}
		return in_array( $event, $chosen, true );
	}

	/**
	 * Queue one event.
	 *
	 * @param string   $event Event key.
	 * @param WC_Order $order Order it happened to.
	 * @param array    $extra Anything the event adds (transaction id, amounts).
	 */
	public static function report( $event, $order, array $extra = array() ) {
		if ( ! $order instanceof WC_Order || ! self::configured() || ! self::wanted( $event ) ) {
			return;
		}

		$payload = array_merge(
			array(
				'event'     => $event,
				'order_id'  => $order->get_id(),
				'order'     => $order->get_order_number(),
				'status'    => (string) Xdwp_Order::meta( $order, 'status' ),
				'flag'      => (string) Xdwp_Order::meta( $order, 'flag' ),
				'coin'      => (string) Xdwp_Order::meta( $order, 'coin' ),
				'amount'    => (string) Xdwp_Order::meta( $order, 'amount' ),
				'address'   => (string) Xdwp_Order::meta( $order, 'address' ),
				'total'     => (string) $order->get_total(),
				'currency'  => $order->get_currency(),
				'admin_url' => $order->get_edit_order_url(),
				'site'      => home_url(),
				'time'      => time(),
			),
			$extra
		);

		/**
		 * The message about to be sent.
		 *
		 * @param array    $payload Everything the receiver is told.
		 * @param string   $event   Event key.
		 * @param WC_Order $order   Order.
		 */
		$payload = (array) apply_filters( 'xdwp_notification_payload', $payload, $event, $order );

		// Out of band: a webhook that takes ten seconds must not be ten seconds a customer waits.
		wp_schedule_single_event( time() + 5, self::SEND_HOOK, array( $payload, 1 ) );
	}

	/**
	 * Send one queued message, and put it back in the queue if it did not arrive.
	 *
	 * @param array $payload Message.
	 * @param int   $try     Which attempt this is.
	 */
	public static function deliver( $payload, $try = 1 ) {
		if ( ! is_array( $payload ) || empty( $payload['event'] ) ) {
			return;
		}
		$try    = max( 1, (int) $try );
		$failed = false;

		$url = trim( (string) Xdwp_Settings::get( 'webhook_url', '' ) );
		if ( '' !== $url && ! self::post_webhook( $url, $payload ) ) {
			$failed = true;
		}

		$token = trim( (string) Xdwp_Settings::get( 'telegram_token', '' ) );
		$chat  = trim( (string) Xdwp_Settings::get( 'telegram_chat', '' ) );
		if ( '' !== $token && '' !== $chat && ! self::post_telegram( $token, $chat, self::sentence( $payload ) ) ) {
			$failed = true;
		}

		if ( $failed && $try < self::MAX_TRIES ) {
			// 1, 5, 25 minutes: long enough for a short outage, short enough to still matter.
			$delay = (int) pow( 5, $try - 1 ) * MINUTE_IN_SECONDS;
			wp_schedule_single_event( time() + $delay, self::SEND_HOOK, array( $payload, $try + 1 ) );
		}
	}

	/**
	 * POST one signed message to the shop's endpoint.
	 *
	 * The signature is over the exact bytes sent, so the receiver can recompute it without
	 * having to agree with us about how JSON is spelled. A timestamp goes in the signed material
	 * too, which is what stops an old message being replayed at the endpoint later.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $payload Message.
	 * @return bool
	 */
	private static function post_webhook( $url, array $payload ) {
		$body      = (string) wp_json_encode( $payload );
		$timestamp = (string) time();
		$secret    = (string) Xdwp_Settings::get( 'webhook_secret', '' );

		$headers = array(
			'Content-Type'     => 'application/json; charset=utf-8',
			'X-Xdwp-Event'     => (string) $payload['event'],
			'X-Xdwp-Timestamp' => $timestamp,
		);
		if ( '' !== $secret ) {
			$headers['X-Xdwp-Signature'] = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 10,
				'headers'     => $headers,
				'body'        => $body,
				'user-agent'  => 'XorroDirectWalletPayments/' . XDWP_VERSION,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Send one message to a Telegram chat.
	 *
	 * @param string $token Bot token.
	 * @param string $chat  Chat ID.
	 * @param string $text  Message.
	 * @return bool
	 */
	private static function post_telegram( $token, $chat, $text ) {
		$response = wp_safe_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id'                  => $chat,
					'text'                     => $text,
					'disable_web_page_preview' => 'true',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}
		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * The event, in a sentence a person reads on a phone.
	 *
	 * @param array $payload Message.
	 * @return string
	 */
	public static function sentence( array $payload ) {
		$labels = self::events();
		$event  = (string) $payload['event'];
		$coin   = Xdwp_Coins::get( (string) ( $payload['coin'] ?? '' ) );
		$what   = isset( $labels[ $event ] ) ? $labels[ $event ] : $event;

		$lines = array(
			sprintf(
				/* translators: 1: what happened, 2: order number */
				__( '%1$s — order #%2$s', 'xorro-direct-wallet-payments-woocommerce' ),
				$what,
				(string) ( $payload['order'] ?? '' )
			),
		);

		if ( ! empty( $payload['amount'] ) && $coin ) {
			$lines[] = sprintf(
				/* translators: 1: crypto amount with symbol, 2: order total in shop currency */
				__( '%1$s (%2$s)', 'xorro-direct-wallet-payments-woocommerce' ),
				$payload['amount'] . ' ' . $coin['symbol'],
				html_entity_decode( wp_strip_all_tags( wc_price( (float) ( $payload['total'] ?? 0 ), array( 'currency' => (string) ( $payload['currency'] ?? '' ) ) ) ), ENT_QUOTES, 'UTF-8' )
			);
		}
		if ( ! empty( $payload['txid'] ) ) {
			$lines[] = (string) $payload['txid'];
		}
		if ( ! empty( $payload['admin_url'] ) ) {
			$lines[] = (string) $payload['admin_url'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Yesterday, in one message.
	 */
	public static function send_digest() {
		if ( ! self::configured() || 'yes' !== Xdwp_Settings::get( 'digest_daily', 'no' ) ) {
			return;
		}
		if ( ! class_exists( 'Xdwp_Payments_Admin' ) ) {
			require_once XDWP_PATH . 'includes/admin/class-xdwp-payments-admin.php';
		}

		$report = Xdwp_Payments_Admin::report( 1 );
		$text   = sprintf(
			/* translators: 1: site name, 2: number of paid orders, 3: money taken, 4: number needing attention */
			__( "%1\$s — crypto in the last day\n%2\$s paid, %3\$s taken\n%4\$s need you", 'xorro-direct-wallet-payments-woocommerce' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			number_format_i18n( (int) $report['paid'] ),
			html_entity_decode( wp_strip_all_tags( wc_price( (float) $report['value'] ) ), ENT_QUOTES, 'UTF-8' ),
			number_format_i18n( Xdwp_Payments_Admin::attention_count() )
		);

		$payload = array(
			'event'     => 'digest',
			'paid'      => (int) $report['paid'],
			'quoted'    => (int) $report['quoted'],
			'value'     => (string) $report['value'],
			'currency'  => get_woocommerce_currency(),
			'attention' => Xdwp_Payments_Admin::attention_count(),
			'site'      => home_url(),
			'time'      => time(),
		);

		$url = trim( (string) Xdwp_Settings::get( 'webhook_url', '' ) );
		if ( '' !== $url ) {
			self::post_webhook( $url, $payload );
		}
		$token = trim( (string) Xdwp_Settings::get( 'telegram_token', '' ) );
		$chat  = trim( (string) Xdwp_Settings::get( 'telegram_chat', '' ) );
		if ( '' !== $token && '' !== $chat ) {
			self::post_telegram( $token, $chat, $text );
		}
	}

	/**
	 * Something happened that the shop needs to know about straight away.
	 *
	 * Sent now rather than queued, and never through the ordinary event filter a merchant can
	 * switch off. The one case this exists for is a payout address changing: if somebody has
	 * taken over an admin account, they have the mailbox too, and an emailed warning that only
	 * reaches that mailbox warns nobody. A webhook or a Telegram message goes somewhere else.
	 *
	 * @param string $message Plain sentence, for Telegram and for a person.
	 * @param array  $extra   Machine-readable detail for the webhook.
	 */
	public static function security_alert( $message, array $extra = array() ) {
		$payload = array_merge(
			array(
				'event'   => 'security',
				'message' => (string) $message,
				'site'    => home_url(),
				'time'    => time(),
			),
			$extra
		);

		$url = trim( (string) Xdwp_Settings::get( 'webhook_url', '' ) );
		if ( '' !== $url ) {
			self::post_webhook( $url, $payload );
		}

		$token = trim( (string) Xdwp_Settings::get( 'telegram_token', '' ) );
		$chat  = trim( (string) Xdwp_Settings::get( 'telegram_chat', '' ) );
		if ( '' !== $token && '' !== $chat ) {
			self::post_telegram( $token, $chat, (string) $message );
		}
	}

	/**
	 * Send one message now, so a shop can see whether it arrives.
	 *
	 * @return array{webhook:?bool, telegram:?bool} Null where nothing is configured.
	 */
	public static function send_test() {
		$payload = array(
			'event'     => 'test',
			'order'     => '0',
			'site'      => home_url(),
			'time'      => time(),
			'message'   => __( 'This is a test from Xorro Wallet Payments. If you can read it, alerts are working.', 'xorro-direct-wallet-payments-woocommerce' ),
		);

		$result = array(
			'webhook'  => null,
			'telegram' => null,
		);

		$url = trim( (string) Xdwp_Settings::get( 'webhook_url', '' ) );
		if ( '' !== $url ) {
			$result['webhook'] = self::post_webhook( $url, $payload );
		}
		$token = trim( (string) Xdwp_Settings::get( 'telegram_token', '' ) );
		$chat  = trim( (string) Xdwp_Settings::get( 'telegram_chat', '' ) );
		if ( '' !== $token && '' !== $chat ) {
			$result['telegram'] = self::post_telegram( $token, $chat, (string) $payload['message'] );
		}

		return $result;
	}

	// --- What each hook reports -------------------------------------------------------------

	/**
	 * @param WC_Order $order Order.
	 * @param string   $txid  Transaction id.
	 */
	public static function on_paid( $order, $txid = '' ) {
		self::report( 'paid', $order, array( 'txid' => (string) $txid ) );
	}

	/**
	 * @param WC_Order $order     Order.
	 * @param string   $received  Amount received.
	 * @param string   $remainder Amount still due.
	 * @param string   $txid      Transaction id.
	 */
	public static function on_underpaid( $order, $received = '', $remainder = '', $txid = '' ) {
		self::report(
			'underpaid',
			$order,
			array(
				'received'  => (string) $received,
				'remainder' => (string) $remainder,
				'txid'      => (string) $txid,
			)
		);
	}

	/**
	 * @param WC_Order $order    Order.
	 * @param string   $overpaid Amount over.
	 */
	public static function on_overpaid( $order, $overpaid = '' ) {
		self::report( 'overpaid', $order, array( 'overpaid' => (string) $overpaid ) );
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $txid   Transaction id.
	 * @param string   $amount Amount.
	 */
	public static function on_late( $order, $txid = '', $amount = '' ) {
		self::report(
			'late',
			$order,
			array(
				'txid'     => (string) $txid,
				'received' => (string) $amount,
			)
		);
	}

	/**
	 * @param WC_Order $order Order.
	 * @param string   $seen  Transaction id that disappeared.
	 */
	public static function on_dropped( $order, $seen = '' ) {
		self::report( 'dropped', $order, array( 'txid' => (string) $seen ) );
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $txid   Transaction id.
	 * @param string   $amount Amount.
	 */
	public static function on_ambiguous( $order, $txid = '', $amount = '' ) {
		self::report(
			'ambiguous',
			$order,
			array(
				'txid'     => (string) $txid,
				'received' => (string) $amount,
			)
		);
	}

	/**
	 * @param WC_Order $order  Order.
	 * @param string   $status Status it expired from.
	 */
	public static function on_expired( $order, $status = '' ) {
		self::report( 'expired', $order, array( 'from' => (string) $status ) );
	}
}
