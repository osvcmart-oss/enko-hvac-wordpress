<?php
/**
 * Базовий передзапусковий харденінг: заголовки, XML-RPC, антиспам форм, версії.
 *
 * №4 XML-RPC вимкнено повністю (методи порожні, X-Pingback знято, pings off) —
 *     класичний ампліфікатор brute-force через system.multicall.
 * №2 Security-заголовки на PHP-відповідях (send_headers). CSP свідомо НЕ додаємо —
 *     спершу інвентаризація інлайн-скриптів прототипу, окремий прохід.
 * №6 Антиспам публічних лід-ендпоїнтів: rate-limit за IP (хелпер тут, guard-и в
 *     request-flow.php і cart.php) + honeypot enko_hp (інжектиться JS-ом у форми,
 *     enko-forms.js / enko-cart.js; заповнене ботом → тихий "ok" без сповіщень).
 * №8 Не світити версію WP (meta generator).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---- №4: XML-RPC геть ---- */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter( 'pings_open', '__return_false' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
// Фільтри вище лишають живими вбудовані system.* (multicall!) — глушимо
// сам запит: xmlrpc.php вантажить WP (init) ДО диспетчеризації методів.
add_action( 'init', function () {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		nocache_headers();
		exit;
	}
} );

/* ---- №2: security-заголовки (лише PHP-відповіді; статику віддає edge) ---- */
add_action( 'send_headers', function () {
	if ( headers_sent() ) { return; }
	header( 'Strict-Transport-Security: max-age=31536000' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
} );

/* ---- №8: не світити версію WP ---- */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* ---- №6: антиспам публічних лід-форм ---- */

/** true, якщо ліміт НЕ вичерпано ($max сабмітів за 15 хв з одного IP на $scope). */
function enko_form_rate_ok( $scope, $max = 10 ) {
	$ip  = function_exists( 'enko_client_ip' ) ? enko_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'enko_frl_' . $scope . '_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) { return false; }
	set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );
	return true;
}

/** Заповнений honeypot enko_hp? Людина прихованого поля не бачить. */
function enko_form_is_bot( WP_REST_Request $req ) {
	return '' !== trim( (string) $req->get_param( 'enko_hp' ) );
}
