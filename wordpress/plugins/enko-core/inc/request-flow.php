<?php
/**
 * «Заявка» flow + notifications.
 * Catalog mode: checkout needs no payment, so the Woo checkout form acts as
 * the request form. On order placement, notify the manager (email + Telegram).
 * Customer confirmation is handled by Woo's standard new-order email.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** No online payment — this is a «заявка», not a sale. */
add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
add_filter( 'woocommerce_order_needs_payment', '__return_false' );

/** Send a Telegram message via Bot API (no-op if not configured). */
function enko_tg_send( $text ) {
	$token = enko_opt( 'tg_token', '' );
	// Авторизація — пріоритетний критерій: залогінений → група [Auth]; гість → [Anon]
	// (з фолбеком на [Auth], якщо анон-групу не налаштовано). Так лід-сповіщення
	// потрапляють у ту саму групу, що й чат відповідного типу користувача.
	$chat = ( function_exists( 'enko_current_uid' ) ? enko_current_uid() : is_user_logged_in() ) ? enko_opt( 'tg_chat', '' ) : ( enko_opt( 'tg_anon_chat', '' ) ?: enko_opt( 'tg_chat', '' ) );
	if ( ! $token || ! $chat ) { return false; }
	$resp = wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", array(
		'timeout' => 8,
		'body'    => array( 'chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML' ),
	) );
	return ! is_wp_error( $resp );
}

/** Notify manager when a request (order) is placed. */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	$lines     = array();
	$eur_total = 0;
	foreach ( $order->get_items() as $item ) {
		$lines[] = sprintf( '• %s × %d', $item->get_name(), $item->get_quantity() );
		$prod = $item->get_product();
		$eur_total += ( $prod ? (float) $prod->get_meta( '_enko_eur' ) : 0 ) * $item->get_quantity();
	}
	$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
	$phone = $order->get_billing_phone();
	$email = $order->get_billing_email();
	$note  = $order->get_customer_note();

	// Сума БЕЗ HTML-ентіті (&nbsp; тощо) — рахуємо самі, грн + €.
	$uah_total = (float) $order->get_total();
	$rate      = function_exists( 'enko_eur_rate' ) ? enko_eur_rate() : 0;
	if ( ! $eur_total && $rate ) { $eur_total = $uah_total / $rate; }
	$sum = number_format( round( $uah_total ), 0, '.', ' ' ) . ' ₴'
		. ( $eur_total ? ' / ' . number_format( round( $eur_total ), 0, '.', ' ' ) . ' €' : '' );

	// Заголовок окремо від тіла — щоб у Telegram не дублювався (bold + перший рядок).
	$title   = "Нова заявка #{$order_id}";
	$details = "Імʼя: {$name}\nТелефон: {$phone}\nEmail: {$email}\n"
		. ( $note ? "Коментар: {$note}\n" : '' )
		. "Позиції:\n" . implode( "\n", $lines ) . "\n"
		. 'Сума (орієнтовно): ' . $sum . "\n"
		. admin_url( 'post.php?post=' . $order_id . '&action=edit' );

	$to = enko_opt( 'manager_email', get_option( 'admin_email' ) );
	wp_mail( $to, "ENKO — {$title}", $title . "\n" . $details );
	if ( function_exists( 'enko_tg_notify_lead' ) ) { enko_tg_notify_lead( $title, $details ); }
	else { enko_tg_send( '<b>' . esc_html( $title ) . "</b>\n" . esc_html( $details ) ); }
}, 10, 1 );

/** REST: lead form from popups (name + phone). */
add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/request', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( function_exists( 'enko_form_is_bot' ) && enko_form_is_bot( $req ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 ); // honeypot — тихо, без сповіщень
			}
			if ( function_exists( 'enko_form_rate_ok' ) && ! enko_form_rate_ok( 'request', 10 ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
			}
			$name    = sanitize_text_field( (string) $req->get_param( 'name' ) );
			$phone   = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			$email   = sanitize_email( (string) $req->get_param( 'email' ) );
			$msg     = sanitize_textarea_field( (string) $req->get_param( 'message' ) );
			$product = sanitize_text_field( (string) $req->get_param( 'product' ) );
			$source  = sanitize_text_field( (string) $req->get_param( 'source' ) );
			if ( ! $phone && ! $name ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'empty' ), 400 );
			}
			$labels  = array( 'modal' => 'Заявка з форми', 'lead' => 'Лід з попапа', 'consult' => 'Заявка на консультацію', 'partner' => 'Запит на партнерство', 'callbar' => 'Замовлення дзвінка' );
			$title   = $labels[ $source ] ?? 'Заявка з сайту';
			// Тіло БЕЗ заголовка (заголовок іде окремо bold) — щоб не дублювався у Telegram.
			$details = "Імʼя: {$name}\nТелефон: {$phone}\n"
				. ( $email ? "Email: {$email}\n" : '' )
				. ( $product ? "Товар: {$product}\n" : '' )
				. ( $msg ? "Повідомлення: {$msg}\n" : '' )
				. 'Джерело: ' . ( $req->get_header( 'referer' ) ?: '—' );

			wp_mail( enko_opt( 'manager_email', get_option( 'admin_email' ) ), 'ENKO — ' . $title, $title . "\n" . $details );
			if ( function_exists( 'enko_tg_notify_lead' ) ) { enko_tg_notify_lead( $title, $details ); }
		else { enko_tg_send( '<b>' . esc_html( $title ) . "</b>\n" . esc_html( $details ) ); }
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );
} );
