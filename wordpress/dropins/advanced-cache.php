<?php
/**
 * ENKO page cache — drop-in (деплоїться у wp-content/advanced-cache.php;
 * вмикається define('WP_CACHE', true) у wp-config.php).
 *
 * Повний HTML анонімних GET-сторінок зберігається у файли
 * wp-content/cache/enko-pages/ і віддається ДО завантаження WordPress
 * (TTFB ~0.05с замість 0.45–0.85с). Пара до enko-core/inc/page-cache.php
 * (негайна інвалідація при змінах контенту/налаштувань).
 *
 * Ключ = host + URI + мова (кукі enko_lang: uk/ru — різний HTML тієї ж адреси!).
 * НЕ кешується: не-GET; залогінені/Woo-сесії (за куками); службові шляхи
 * (адмінка, REST, кабінети, кошик/чекаут); довільні query (дозволено лише
 * ?type= — фільтр каталогу; ?lang= — це редірект із Set-Cookie, повз кеш);
 * відповіді не-200, із Set-Cookie, не-HTML або без </html> (обірвані).
 * TTL 15 хв. Діагностика: заголовок X-Enko-Cache: HIT|MISS|BYPASS.
 *
 * @package enko-core
 */

if ( ! defined( 'ENKO_PC_DIR' ) ) {
	define( 'ENKO_PC_DIR', WP_CONTENT_DIR . '/cache/enko-pages' );
	define( 'ENKO_PC_TTL', 900 );
}

/** Чи підлягає ЗАПИТ кешуванню (до завантаження WP — лише $_SERVER/$_COOKIE). */
function enko_pc_request_cacheable() {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return false; }
	$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
	$path = (string) ( parse_url( $uri, PHP_URL_PATH ) ?: '/' );
	foreach ( array( '/wp-admin', '/wp-json', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/cart', '/checkout', '/my-account', '/account', '/manager', '/feed', '/wp-sitemap' ) as $ex ) {
		if ( 0 === strpos( $path, $ex ) ) { return false; }
	}
	if ( pathinfo( $path, PATHINFO_EXTENSION ) ) { return false; } // файли — не наша парафія
	$q = parse_url( $uri, PHP_URL_QUERY );
	if ( $q ) {
		parse_str( (string) $q, $params );
		foreach ( array_keys( $params ) as $k ) {
			if ( 'type' !== $k ) { return false; }
		}
	}
	foreach ( array_keys( $_COOKIE ) as $k ) {
		if ( preg_match( '/^(wordpress_logged_in_|wordpress_sec_|wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|comment_author_)/', (string) $k ) ) { return false; }
	}
	return true;
}

/** Файл кешу для поточного запиту (мова — частина ключа). */
function enko_pc_file() {
	$lang = ( isset( $_COOKIE['enko_lang'] ) && 'ru' === $_COOKIE['enko_lang'] ) ? 'ru' : 'uk';
	return ENKO_PC_DIR . '/' . md5( ( $_SERVER['HTTP_HOST'] ?? '' ) . '|' . ( $_SERVER['REQUEST_URI'] ?? '/' ) ) . '-' . $lang . '.html';
}

/** Заголовки віддачі з кешу (security-набір дублюємо — WP тут не вантажиться). */
function enko_pc_headers() {
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'Strict-Transport-Security: max-age=31536000' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
}

/** ob_start-колбек: зберегти згенерований HTML, якщо відповідь безпечно кешувати. */
function enko_pc_capture( $html ) {
	if ( strlen( $html ) < 255 ) { return $html; }
	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) { return $html; }
	if ( 200 !== http_response_code() ) { return $html; }
	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) { return $html; }
	foreach ( headers_list() as $h ) {
		if ( 0 === stripos( $h, 'Set-Cookie:' ) ) { return $html; }
		if ( 0 === stripos( $h, 'Content-Type:' ) && false === stripos( $h, 'text/html' ) ) { return $html; }
	}
	if ( false === stripos( $html, '</html>' ) ) { return $html; }
	if ( ! is_dir( ENKO_PC_DIR ) ) { @mkdir( ENKO_PC_DIR, 0755, true ); }
	$tmp = ENKO_PC_DIR . '/.tmp-' . getmypid();
	if ( false !== @file_put_contents( $tmp, $html . '<!-- enko-cache ' . gmdate( 'Y-m-d\TH:i:s\Z' ) . ' -->', LOCK_EX ) ) {
		@rename( $tmp, enko_pc_file() ); // атомарна підміна — читачі не бачать недописаного
	}
	return $html;
}

if ( enko_pc_request_cacheable() ) {
	$enko_pc_f = enko_pc_file();
	if ( is_file( $enko_pc_f ) && ( time() - (int) filemtime( $enko_pc_f ) ) < ENKO_PC_TTL ) {
		enko_pc_headers();
		header( 'X-Enko-Cache: HIT' );
		readfile( $enko_pc_f );
		exit;
	}
	header( 'X-Enko-Cache: MISS' );
	ob_start( 'enko_pc_capture' );
} else {
	header( 'X-Enko-Cache: BYPASS' );
}
