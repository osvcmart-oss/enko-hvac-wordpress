<?php
/**
 * Анонімний чат (фаза 2) — гість ↔ менеджер через ОКРЕМУ супергрупу Topics.
 *
 * Ключ — guest-UUID у cookie `enko_guest` (стабільний на браузер). Сховище:
 *   wp_enko_guest       — мапа гостя: thread_id (тема в анон-групі), tg_dm_chat
 *                         (приватний чат, якщо гість зайшов через Telegram), ip/ua/page.
 *   wp_enko_guest_chat  — повідомлення (author = guest/support).
 *
 * Два входи (обидва → та сама анон-тема за guest-UUID):
 *   (А) віджет на сайті  → REST guest/send + guest/poll;
 *   (Б) Telegram гостя   → deep-link t.me/<bot>?start=g-<uuid> (guest/tglink) → /start
 *       прив'язує приватний чат до guest-UUID (обробка — inc/telegram.php).
 *
 * Релей у Telegram (enko_tg_push_anon / enko_tg_ensure_anon_topic) — у inc/telegram.php.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ENKO_GUEST_DB_VER', '1' );

function enko_guest_table()      { global $wpdb; return $wpdb->prefix . 'enko_guest'; }
function enko_guest_chat_table() { global $wpdb; return $wpdb->prefix . 'enko_guest_chat'; }

/** Створити/оновити таблиці (dbDelta) — активація + self-heal на init. */
function enko_guest_install() {
	global $wpdb;
	$cs = $wpdb->get_charset_collate();
	$g  = enko_guest_table();
	$c  = enko_guest_chat_table();
	$sql1 = "CREATE TABLE {$g} (
		uuid VARCHAR(40) NOT NULL,
		thread_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		tg_dm_chat BIGINT NOT NULL DEFAULT 0,
		ip VARCHAR(45) NOT NULL DEFAULT '',
		ua VARCHAR(255) NOT NULL DEFAULT '',
		page VARCHAR(255) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		last_seen DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (uuid),
		KEY thread_id (thread_id),
		KEY tg_dm_chat (tg_dm_chat)
	) {$cs};";
	$sql2 = "CREATE TABLE {$c} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		guest_uuid VARCHAR(40) NOT NULL,
		author VARCHAR(12) NOT NULL DEFAULT 'guest',
		text TEXT NOT NULL,
		created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		read_guest TINYINT(1) NOT NULL DEFAULT 0,
		read_mgr TINYINT(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY guest_uuid (guest_uuid),
		KEY created_at (created_at)
	) {$cs};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql1 );
	dbDelta( $sql2 );
	update_option( 'enko_guest_db_ver', ENKO_GUEST_DB_VER );
}
add_action( 'init', function () {
	if ( get_option( 'enko_guest_db_ver' ) !== ENKO_GUEST_DB_VER ) { enko_guest_install(); }
} );

/** Поточний guest-UUID із cookie; за потреби створює + ставить cookie (рік, HttpOnly). */
function enko_guest_uuid( $create = true ) {
	$c = isset( $_COOKIE['enko_guest'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $_COOKIE['enko_guest'] ) : '';
	if ( strlen( $c ) >= 16 ) { return $c; }
	if ( ! $create ) { return ''; }
	$uuid = wp_generate_password( 32, false );
	setcookie( 'enko_guest', $uuid, array(
		'expires'  => time() + YEAR_IN_SECONDS,
		'path'     => '/',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	) );
	$_COOKIE['enko_guest'] = $uuid;
	return $uuid;
}

/** Рядок гостя (масив) або null. */
function enko_guest_get( $uuid ) {
	global $wpdb;
	$t = enko_guest_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE uuid = %s", $uuid ), ARRAY_A );
}

/** Запис/оновлення інфо гостя (ip/ua/page/last_seen). */
function enko_guest_touch( $uuid, $page = '' ) {
	global $wpdb;
	$t   = enko_guest_table();
	$now = current_time( 'mysql' );
	$ip  = function_exists( 'enko_client_ip' ) ? enko_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$ua  = mb_substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 );
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT uuid FROM {$t} WHERE uuid = %s", $uuid ) ) ) {
		$data = array( 'last_seen' => $now, 'ip' => $ip, 'ua' => $ua );
		if ( '' !== $page ) { $data['page'] = mb_substr( $page, 0, 255 ); }
		$wpdb->update( $t, $data, array( 'uuid' => $uuid ) );
	} else {
		$wpdb->insert( $t, array(
			'uuid' => $uuid, 'ip' => $ip, 'ua' => $ua, 'page' => mb_substr( $page, 0, 255 ),
			'created_at' => $now, 'last_seen' => $now,
		) );
	}
}

/** Додати повідомлення гостя. Повертає id. */
function enko_guest_add( $uuid, $author, $text ) {
	global $wpdb;
	$author = 'support' === $author ? 'support' : 'guest';
	$wpdb->insert( enko_guest_chat_table(), array(
		'guest_uuid' => $uuid,
		'author'     => $author,
		'text'       => (string) $text,
		'created_at' => current_time( 'mysql' ),
		'read_guest' => 'guest' === $author ? 1 : 0,
		'read_mgr'   => 'support' === $author ? 1 : 0,
	), array( '%s', '%s', '%s', '%s', '%d', '%d' ) );
	return (int) $wpdb->insert_id;
}

/** Повідомлення гостя (за id > since) у форматі для віджета. */
function enko_guest_messages( $uuid, $since = 0 ) {
	global $wpdb;
	$t    = enko_guest_chat_table();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, author, text, created_at FROM {$t} WHERE guest_uuid = %s AND id > %d ORDER BY id ASC",
		$uuid, (int) $since
	), ARRAY_A );
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'   => (int) $r['id'],
			'from' => 'support' === $r['author'] ? 'support' : 'user',
			'text' => $r['text'],
			'time' => mysql2date( 'd.m H:i', $r['created_at'] ),
		);
	}
	return $out;
}

/** Антиспам: не більше 30 повідомлень за 15 хв з одного IP. */
function enko_guest_rate_ok() {
	$ip  = function_exists( 'enko_client_ip' ) ? enko_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'enko_grl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 30 ) { return false; }
	set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );
	return true;
}

/* =========================================================================
   REST (без авторизації; гість = НЕзалогінений)
   ========================================================================= */

add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/guest/send',   array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'enko_guest_rest_send' ) );
	register_rest_route( 'enko/v1', '/guest/poll',   array( 'methods' => 'GET',  'permission_callback' => '__return_true', 'callback' => 'enko_guest_rest_poll' ) );
	register_rest_route( 'enko/v1', '/guest/tglink', array( 'methods' => 'GET',  'permission_callback' => '__return_true', 'callback' => 'enko_guest_rest_tglink' ) );
} );

function enko_guest_rest_send( WP_REST_Request $req ) {
	if ( trim( (string) $req->get_param( 'hp' ) ) !== '' ) {            // honeypot — мовчазний ok
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	if ( is_user_logged_in() ) {                                        // залогінені — кабінет-чат, не сюди
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'use_account' ), 400 );
	}
	if ( ! enko_guest_rate_ok() ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
	}
	$text = trim( sanitize_textarea_field( (string) $req->get_param( 'text' ) ) );
	if ( '' === $text ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'empty' ), 400 );
	}
	$text = mb_substr( $text, 0, 1000 );
	$uuid = enko_guest_uuid( true );
	$page = esc_url_raw( (string) ( $req->get_param( 'page' ) ?: $req->get_header( 'referer' ) ) );
	enko_guest_touch( $uuid, $page );
	$id = enko_guest_add( $uuid, 'guest', $text );
	if ( function_exists( 'enko_tg_push_anon' ) ) { enko_tg_push_anon( $uuid, 'guest', $text ); }
	return new WP_REST_Response( array( 'ok' => true, 'id' => $id, 'uuid' => $uuid ), 200 );
}

function enko_guest_rest_poll( WP_REST_Request $req ) {
	$uuid = enko_guest_uuid( false );
	if ( ! $uuid ) { return new WP_REST_Response( array( 'ok' => true, 'messages' => array() ), 200 ); }
	$since = (int) $req->get_param( 'since' );
	$msgs  = enko_guest_messages( $uuid, $since );
	if ( $msgs ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . enko_guest_chat_table() . ' SET read_guest = 1 WHERE guest_uuid = %s AND author = %s', $uuid, 'support' ) );
	}
	return new WP_REST_Response( array( 'ok' => true, 'messages' => $msgs ), 200 );
}

function enko_guest_rest_tglink( WP_REST_Request $req ) {
	if ( is_user_logged_in() ) { return new WP_REST_Response( array( 'ok' => false ), 400 ); }
	if ( ! enko_opt( 'tg_token', '' ) ) { return new WP_REST_Response( array( 'ok' => false ), 400 ); }
	$uuid = enko_guest_uuid( true );
	enko_guest_touch( $uuid, esc_url_raw( (string) $req->get_header( 'referer' ) ) );
	$bot  = function_exists( 'enko_tg_bot_username' ) ? enko_tg_bot_username() : 'EnkoSupportBot';
	return new WP_REST_Response( array( 'ok' => true, 'deeplink' => 'https://t.me/' . $bot . '?start=g-' . $uuid ), 200 );
}
