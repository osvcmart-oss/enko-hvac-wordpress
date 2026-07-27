<?php
/**
 * Кабінет менеджера (SSM) — фронт-сторінка /manager/.
 * Бекенд REST enko/v1/mgr/* (доступ лише manage_woocommerce): список клієнтів,
 * деталі, редагування профілю/знижки/менеджера, чат-відповіді, курс, вхід.
 *
 * Порт прототипу admin.html + scripts/home-r2.js (розділ F) на реальний WP.
 * Затримки попапів / робочі години / тест-кнопки (?poptest=) — у фронт-скрипті
 * (localStorage, як прототип), бекенд тут не потрібен.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Дозвіл для SSM-ендпойнтів. */
function enko_mgr_can() {
	return current_user_can( 'manage_woocommerce' );
}

/** Короткий запис клієнта для списку. */
function enko_mgr_user_brief( $u ) {
	$uid    = $u->ID;
	$name   = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
	$mid    = (int) get_user_meta( $uid, 'enko_manager', true );
	$mname  = '';
	if ( $mid ) { $md = get_userdata( $mid ); $mname = $md ? $md->display_name : ''; }
	$orders = function_exists( 'wc_get_orders' )
		? count( wc_get_orders( array( 'customer_id' => $uid, 'limit' => -1, 'return' => 'ids' ) ) )
		: 0;
	return array(
		'id'         => (int) $uid,
		'name'       => $name,
		'email'      => $u->user_email,
		'phone'      => get_user_meta( $uid, 'billing_phone', true ),
		'discount'   => max( 0, min( 99, (int) get_user_meta( $uid, 'enko_discount', true ) ) ),
		'managerId'  => $mid,
		'manager'    => $mname,
		'registered' => mysql2date( 'd.m.Y', $u->user_registered ),
		'orders'     => (int) $orders,
	);
}

add_action( 'rest_api_init', function () {

	/* ---- Вхід менеджера (для логін-екрана /manager/) ---- */
	register_rest_route( 'enko/v1', '/mgr/login', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$login = sanitize_text_field( (string) $req->get_param( 'login' ) );
			$pass  = (string) $req->get_param( 'password' );
			if ( is_email( $login ) ) {
				$u = get_user_by( 'email', $login );
				if ( $u ) { $login = $u->user_login; }
			}
			$signon = wp_signon( array( 'user_login' => $login, 'user_password' => $pass, 'remember' => true ), is_ssl() );
			if ( is_wp_error( $signon ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'badpass', 'msg' => 'Невірний логін або пароль.' ), 401 );
			}
			if ( ! user_can( $signon->ID, 'manage_woocommerce' ) ) {
				wp_logout();
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'noaccess', 'msg' => 'Доступ лише для менеджерів ENKO.' ), 403 );
			}
			wp_set_current_user( $signon->ID );
			wp_set_auth_cookie( $signon->ID, true );
			return new WP_REST_Response( array( 'ok' => true, 'redirect' => home_url( '/manager/' ) ), 200 );
		},
	) );

	/* ---- Список клієнтів ---- */
	register_rest_route( 'enko/v1', '/mgr/users', array(
		'methods'             => 'GET',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function () {
			$users = get_users( array( 'role__in' => array( 'customer', 'subscriber' ), 'orderby' => 'registered', 'order' => 'DESC' ) );
			$out   = array();
			foreach ( $users as $u ) { $out[] = enko_mgr_user_brief( $u ); }
			// Список менеджерів — для випадайки призначення (без службових акаунтів).
			$mgrs = array();
			foreach ( enko_manager_users() as $m ) {
				$mgrs[] = array( 'id' => (int) $m->ID, 'name' => $m->display_name );
			}
			return new WP_REST_Response( array( 'ok' => true, 'users' => $out, 'managers' => $mgrs, 'rate' => enko_eur_rate() ), 200 );
		},
	) );

	/* ---- Деталі клієнта + замовлення + чат ---- */
	register_rest_route( 'enko/v1', '/mgr/user', array(
		'methods'             => 'GET',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid = (int) $req->get_param( 'id' );
			$u   = get_userdata( $uid );
			if ( ! $u ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'notfound' ), 404 ); }
			return new WP_REST_Response( array(
				'ok'      => true,
				'profile' => enko_account_profile( $uid ),
				'orders'  => enko_account_orders( $uid ),
				'chat'    => enko_chat_messages( $uid, 0 ),
			), 200 );
		},
	) );

	/* ---- Оновлення клієнта (профіль / знижка / менеджер) ---- */
	register_rest_route( 'enko/v1', '/mgr/user-update', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid = (int) $req->get_param( 'id' );
			$u   = get_userdata( $uid );
			if ( ! $u ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'notfound' ), 404 ); }
			if ( user_can( $uid, 'manage_options' ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'protected', 'msg' => 'Не можна редагувати адміністратора звідси.' ), 403 );
			}
			$has = function ( $k ) use ( $req ) { return null !== $req->get_param( $k ); };
			if ( $has( 'firstName' ) || $has( 'lastName' ) ) {
				$fn = sanitize_text_field( (string) $req->get_param( 'firstName' ) );
				$ln = sanitize_text_field( (string) $req->get_param( 'lastName' ) );
				wp_update_user( array( 'ID' => $uid, 'first_name' => $fn, 'last_name' => $ln, 'display_name' => trim( $fn . ' ' . $ln ) ?: $u->user_email ) );
			}
			if ( $has( 'phone' ) )   { update_user_meta( $uid, 'billing_phone', sanitize_text_field( (string) $req->get_param( 'phone' ) ) ); }
			if ( $has( 'company' ) ) { update_user_meta( $uid, 'billing_company', sanitize_text_field( (string) $req->get_param( 'company' ) ) ); }
			if ( $has( 'city' ) )    { update_user_meta( $uid, 'enko_city', sanitize_text_field( (string) $req->get_param( 'city' ) ) ); }
			if ( $has( 'edrpou' ) )  { update_user_meta( $uid, 'enko_edrpou', sanitize_text_field( (string) $req->get_param( 'edrpou' ) ) ); }
			if ( $has( 'coop' ) )    { update_user_meta( $uid, 'enko_coop', sanitize_text_field( (string) $req->get_param( 'coop' ) ) ); }
			if ( $has( 'discount' ) ) {
				update_user_meta( $uid, 'enko_discount', max( 0, min( 99, (int) $req->get_param( 'discount' ) ) ) );
			}
			if ( $has( 'managerId' ) ) {
				update_user_meta( $uid, 'enko_manager', (int) $req->get_param( 'managerId' ) );
			}
			return new WP_REST_Response( array( 'ok' => true, 'profile' => enko_account_profile( $uid ) ), 200 );
		},
	) );

	/* ---- Створити клієнта вручну ---- */
	register_rest_route( 'enko/v1', '/mgr/user-create', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			if ( ! is_email( $email ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'email' ), 400 ); }
			if ( email_exists( $email ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'exists' ), 409 ); }
			$fn  = sanitize_text_field( (string) $req->get_param( 'firstName' ) );
			$ln  = sanitize_text_field( (string) $req->get_param( 'lastName' ) );
			$uid = wp_insert_user( array(
				'user_login'   => enko_unique_username( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 12, true ),
				'first_name'   => $fn,
				'last_name'    => $ln,
				'display_name' => trim( $fn . ' ' . $ln ) ?: $email,
				'role'         => 'customer',
			) );
			if ( is_wp_error( $uid ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'create', 'msg' => $uid->get_error_message() ), 400 ); }
			update_user_meta( $uid, 'billing_phone', sanitize_text_field( (string) $req->get_param( 'phone' ) ) );
			update_user_meta( $uid, 'enko_discount', max( 0, min( 99, (int) $req->get_param( 'discount' ) ) ) );
			update_user_meta( $uid, 'enko_city', sanitize_text_field( (string) $req->get_param( 'city' ) ) );
				update_user_meta( $uid, 'enko_coop', sanitize_text_field( (string) $req->get_param( 'coop' ) ) );
				update_user_meta( $uid, 'billing_company', sanitize_text_field( (string) $req->get_param( 'company' ) ) );
				update_user_meta( $uid, 'enko_edrpou', sanitize_text_field( (string) $req->get_param( 'edrpou' ) ) );
				$mid = (int) $req->get_param( 'managerId' ); if ( ! $mid ) { $mid = (int) enko_opt( 'default_manager', 0 ); }
				if ( $mid ) { update_user_meta( $uid, 'enko_manager', $mid ); }
				$u = get_userdata( $uid ); retrieve_password( $u->user_login );
				return new WP_REST_Response( array( 'ok' => true, 'user' => enko_mgr_user_brief( $u ) ), 200 );
		},
	) );

	/* ---- Чат: відповідь менеджера ---- */
	register_rest_route( 'enko/v1', '/mgr/chat-send', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid  = (int) $req->get_param( 'user' );
			$text = trim( sanitize_textarea_field( (string) $req->get_param( 'text' ) ) );
			if ( ! $uid || '' === $text ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'empty' ), 400 ); }
			$id = enko_chat_add( $uid, 'support', mb_substr( $text, 0, 1000 ) );
			return new WP_REST_Response( array( 'ok' => true, 'id' => $id ), 200 );
		},
	) );

	/* ---- Чат: polling конкретного клієнта (для менеджера) ---- */
	register_rest_route( 'enko/v1', '/mgr/chat', array(
		'methods'             => 'GET',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid   = (int) $req->get_param( 'user' );
			$since = (int) $req->get_param( 'since' );
			$msgs  = enko_chat_messages( $uid, $since );
			if ( $msgs ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . enko_chat_table() . ' SET read_mgr = 1 WHERE user_id = %d AND author = %s', $uid, 'user' ) );
			}
			return new WP_REST_Response( array( 'ok' => true, 'messages' => $msgs ), 200 );
		},
	) );

	/* ---- Курс EUR→грн ---- */
	register_rest_route( 'enko/v1', '/mgr/rate', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			// Приймаємо число АБО рядок із крапкою/комою ("45,50"); максимум 2 знаки
			// після розділювача, жодних інших символів (вимога замовника 2026-07-13).
			$raw = str_replace( ',', '.', trim( (string) $req->get_param( 'rate' ) ) );
			if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $raw ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'format' ), 400 ); }
			$rate = round( (float) $raw, 2 );
			if ( $rate < 1 || $rate > 1000 ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'range' ), 400 ); }
			update_option( 'enko_eur_rate', $rate );
			return new WP_REST_Response( array( 'ok' => true, 'rate' => $rate ), 200 );
		},
	) );

	/* Попапи / робочі години → СЕРВЕР (двостороння синхронізація з Settings → ENKO):
	   зміни з кабінету пишуть ті самі опції, що й адмінка, тож впливають на попапи/чат. */
	register_rest_route( 'enko/v1', '/mgr/site-settings', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$saved = array();
			foreach ( array( 'work_start', 'work_end' ) as $k ) {
				$v = $req->get_param( $k );
				if ( null !== $v && preg_match( '/^\d{1,2}:\d{2}$/', (string) $v ) ) {
					update_option( 'enko_' . $k, (string) $v );
					$saved[ $k ] = (string) $v;
				}
			}
			foreach ( array( 'delay_lead', 'delay_callbar', 'delay_cookie' ) as $k ) {
				if ( null !== $req->get_param( $k ) ) {
					$val = max( 0, min( 86400, (int) $req->get_param( $k ) ) );
					update_option( 'enko_' . $k, $val );
					$saved[ $k ] = $val;
				}
			}
			return new WP_REST_Response( array( 'ok' => true, 'saved' => $saved ), 200 );
		},
	) );
} );
