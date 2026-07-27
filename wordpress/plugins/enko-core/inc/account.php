<?php
/**
 * Кабінет користувача (клієнт) — реальний WordPress замість localStorage-прототипу.
 * Реєстрація/вхід/вихід/скидання пароля, профіль, історія заявок (Woo orders),
 * закріплений менеджер, чат підтримки client↔manager (власна таблиця + polling).
 *
 * Порт логіки прототипу scripts/enko.js (auth-модалка, renderAccount, чат) на
 * REST enko/v1/*. Знижка вже в inc/discount.php (user-meta enko_discount).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ENKO_CHAT_DB_VER', '1' );

/** Імʼя таблиці чату. */
function enko_chat_table() {
	global $wpdb;
	return $wpdb->prefix . 'enko_chat';
}

/** Створити/оновити таблицю чату (dbDelta). Викликається на активації + self-heal на init. */
function enko_chat_install() {
	global $wpdb;
	$table   = enko_chat_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		author VARCHAR(12) NOT NULL DEFAULT 'user',
		text TEXT NOT NULL,
		created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		read_mgr TINYINT(1) NOT NULL DEFAULT 0,
		read_user TINYINT(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY created_at (created_at)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'enko_chat_db_ver', ENKO_CHAT_DB_VER );
}
add_action( 'init', function () {
	if ( get_option( 'enko_chat_db_ver' ) !== ENKO_CHAT_DB_VER ) {
		enko_chat_install();
	}
} );

/* =========================================================================
   ХЕЛПЕРИ ДАНИХ
   ========================================================================= */

/** Профіль користувача для JSON. */
function enko_account_profile( $uid ) {
	$u = get_userdata( $uid );
	if ( ! $u ) { return null; }
	return array(
		'id'         => (int) $uid,
		'firstName'  => $u->first_name,
		'lastName'   => $u->last_name,
		'email'      => $u->user_email,
		'phone'      => get_user_meta( $uid, 'billing_phone', true ),
		'entity'     => get_user_meta( $uid, 'enko_entity', true ) ?: 'fiz',
		'coop'       => get_user_meta( $uid, 'enko_coop', true ),
		'company'    => get_user_meta( $uid, 'billing_company', true ),
		'edrpou'     => get_user_meta( $uid, 'enko_edrpou', true ),
		'city'       => get_user_meta( $uid, 'enko_city', true ),
		'note'       => get_user_meta( $uid, 'enko_note', true ),
		'discount'   => max( 0, min( 99, (int) get_user_meta( $uid, 'enko_discount', true ) ) ),
		'managerId'  => (int) get_user_meta( $uid, 'enko_manager', true ),
		'registered' => mysql2date( 'd.m.Y', $u->user_registered ),
		'isManager'  => user_can( $uid, 'manage_woocommerce' ),
	);
}

/** Менеджери для випадайок — без службових акаунтів (інтеграційні / порожній email). */
function enko_manager_users() {
	$out = array();
	foreach ( get_users( array( 'capability' => 'manage_woocommerce' ) ) as $m ) {
		if ( ! $m->user_email ) { continue; }                          // службовий без пошти
		if ( 0 === strpos( (string) $m->user_login, 'manage-' ) ) { continue; } // service-логіни
		$out[] = $m;
	}
	return $out;
}

/** Дані закріпленого менеджера (із fallback на дефолтного з налаштувань). */
function enko_account_manager( $uid ) {
	$mid = (int) get_user_meta( $uid, 'enko_manager', true );
	if ( ! $mid ) { $mid = (int) enko_opt( 'default_manager', 0 ); }
	if ( ! $mid ) { return null; }
	$m = get_userdata( $mid );
	if ( ! $m ) { return null; }
	$name = trim( $m->first_name . ' ' . $m->last_name );
	if ( ! $name ) { $name = $m->display_name; }
	return array(
		'id'    => (int) $mid,
		'name'  => $name,
		'role'  => get_user_meta( $mid, 'enko_mgr_role', true ) ?: 'Персональний менеджер ENKO',
		'phone' => get_user_meta( $mid, 'enko_mgr_phone', true ) ?: '+380 777 147 777',
		'email' => $m->user_email,
		'tg'    => get_user_meta( $mid, 'enko_mgr_tg', true ),
	);
}

/** Історія заявок = Woo-замовлення клієнта. */
function enko_account_orders( $uid ) {
	if ( ! function_exists( 'wc_get_orders' ) ) { return array(); }
	$orders = wc_get_orders( array( 'customer_id' => (int) $uid, 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$out = array();
	foreach ( $orders as $o ) {
		$items = array();
		foreach ( $o->get_items() as $it ) {
			$prod = $it->get_product();
			$items[] = array(
				'name' => $it->get_name(),
				'qty'  => (int) $it->get_quantity(),
				'sku'  => $prod ? $prod->get_sku() : '',
				'uah'  => (float) $it->get_total(),
			);
		}
		$out[] = array(
			'id'     => (int) $o->get_id(),
			'number' => (string) $o->get_order_number(),
			'date'   => wc_format_datetime( $o->get_date_created(), 'd.m.Y' ),
			'status' => wc_get_order_status_name( $o->get_status() ),
			'uah'    => (float) $o->get_total(),
			'items'  => $items,
		);
	}
	return $out;
}

/** Повідомлення чату користувача (за id > since). */
function enko_chat_messages( $uid, $since = 0 ) {
	global $wpdb;
	$table = enko_chat_table();
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, author, text, created_at FROM {$table} WHERE user_id = %d AND id > %d ORDER BY id ASC",
		(int) $uid, (int) $since
	), ARRAY_A );
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'     => (int) $r['id'],
			'from'   => $r['author'] === 'support' ? 'support' : 'user',
			'text'   => $r['text'],
			'time'   => mysql2date( 'd.m H:i', $r['created_at'] ),
		);
	}
	return $out;
}

/**
 * Додати повідомлення в чат. Повертає id.
 *
 * @param string $source Звідки прийшло: 'web' (кабінет/сайт) або 'telegram'
 *                       (відповідь менеджера через бот-міст). Передається в подію
 *                       enko_chat_added, щоб модуль Telegram не дублював повідомлення
 *                       назад у ту саму тему.
 */
function enko_chat_add( $uid, $author, $text, $source = 'web' ) {
	global $wpdb;
	$author = $author === 'support' ? 'support' : 'user';
	$wpdb->insert( enko_chat_table(), array(
		'user_id'    => (int) $uid,
		'author'     => $author,
		'text'       => (string) $text,
		'created_at' => current_time( 'mysql' ),
		'read_mgr'   => $author === 'support' ? 1 : 0,
		'read_user'  => $author === 'user' ? 1 : 0,
	), array( '%d', '%s', '%s', '%s', '%d', '%d' ) );
	$id = (int) $wpdb->insert_id;
	/** Нове повідомлення журналу — слухає inc/telegram.php (міст у тему Telegram). */
	do_action( 'enko_chat_added', (int) $uid, $author, (string) $text, $id, (string) $source );
	return $id;
}

/** Унікальний username з email. */
function enko_unique_username( $email ) {
	$base = sanitize_user( current( explode( '@', $email ) ), true );
	if ( ! $base ) { $base = 'enko'; }
	$login = $base;
	$i = 1;
	while ( username_exists( $login ) ) {
		$login = $base . $i;
		$i++;
	}
	return $login;
}

/* =========================================================================
   REST API
   ========================================================================= */

function enko_account_logged_in() {
	return is_user_logged_in();
}

/* ---- Анти-брутфорс для форм входу (захист від підбору пароля) ---- */
function enko_client_ip() {
	// Свідомо лише REMOTE_ADDR: на цьому оточенні він містить реальний IP
	// клієнта (перевірено). X-Forwarded-For НЕ читаємо — цей заголовок
	// підставляє сам клієнт, тож довіра до нього дозволяє обійти rate-limit.
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	return $ip ?: 'unknown';
}
/** Ключ лічильника невдалих спроб за IP+призначення. */
function enko_login_key( $scope ) {
	return 'enko_lb_' . $scope . '_' . md5( enko_client_ip() );
}
/** true, якщо вичерпано ліміт невдалих спроб (8 за 15 хв). */
function enko_login_blocked( $scope ) {
	return (int) get_transient( enko_login_key( $scope ) ) >= 8;
}
/** Зафіксувати невдалу спробу. */
function enko_login_fail( $scope ) {
	$key = enko_login_key( $scope );
	set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
}
/** Скинути лічильник після успішного входу. */
function enko_login_reset( $scope ) {
	delete_transient( enko_login_key( $scope ) );
}

/*
 * Анти-брутфорс: блокуємо підбір пароля на ВСІХ формах входу (кабінет, /manager/,
 * /wp-login.php) одним хуком. 8 невдалих спроб з одного IP → блок на 15 хв.
 * Порожні username+password (службовий app-password інтеграції, cookie-auth) не чіпаємо.
 */
add_filter( 'authenticate', function ( $user, $username, $password ) {
	if ( '' === (string) $username && '' === (string) $password ) { return $user; }
	if ( enko_login_blocked( 'login' ) ) {
		return new WP_Error( 'enko_throttled', 'Забагато спроб входу. Спробуйте за 15 хвилин.' );
	}
	return $user;
}, 1, 3 );
add_action( 'wp_login_failed', function () { enko_login_fail( 'login' ); } );
add_action( 'wp_login', function () { enko_login_reset( 'login' ); } );

add_action( 'rest_api_init', function () {

	/* ---- Реєстрація партнера ---- */
	register_rest_route( 'enko/v1', '/auth/register', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			// Honeypot: ботам — мовчазний «успіх» без створення.
			if ( trim( (string) $req->get_param( 'hp' ) ) !== '' ) {
				return new WP_REST_Response( array( 'ok' => true, 'redirect' => home_url( '/account/' ) ), 200 );
			}
			// IP-ліміт проти масової реєстрації (аудит L3): 5 акаунтів / 15 хв.
			if ( function_exists( 'enko_form_rate_ok' ) && ! enko_form_rate_ok( 'register', 5 ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate', 'msg' => 'Забагато спроб. Спробуйте за 15 хвилин.' ), 429 );
			}
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$phone = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			$pass  = (string) $req->get_param( 'password' );
			if ( ! is_email( $email ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'email', 'msg' => 'Вкажіть коректний email.' ), 400 );
			}
			if ( ! $phone ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'phone', 'msg' => 'Вкажіть телефон.' ), 400 );
			}
			if ( email_exists( $email ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'exists', 'msg' => 'Акаунт з таким email вже існує. Увійдіть або скиньте пароль.' ), 409 );
			}
			$send_set_pass = false;
			if ( strlen( $pass ) < 6 ) {
				$pass          = wp_generate_password( 12, true );
				$send_set_pass = true;
			}
			$uid = wp_insert_user( array(
				'user_login'   => enko_unique_username( $email ),
				'user_email'   => $email,
				'user_pass'    => $pass,
				'first_name'   => sanitize_text_field( (string) $req->get_param( 'firstName' ) ),
				'last_name'    => sanitize_text_field( (string) $req->get_param( 'lastName' ) ),
				'display_name' => sanitize_text_field( trim( $req->get_param( 'firstName' ) . ' ' . $req->get_param( 'lastName' ) ) ) ?: $email,
				'role'         => 'customer',
			) );
			if ( is_wp_error( $uid ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'create', 'msg' => $uid->get_error_message() ), 400 );
			}
			// Профільні поля.
			update_user_meta( $uid, 'billing_phone', $phone );
			$entity = $req->get_param( 'entity' ) === 'ur' ? 'ur' : 'fiz';
			update_user_meta( $uid, 'enko_entity', $entity );
			update_user_meta( $uid, 'enko_coop', sanitize_text_field( (string) $req->get_param( 'coop' ) ) );
			update_user_meta( $uid, 'billing_company', sanitize_text_field( (string) $req->get_param( 'company' ) ) );
			update_user_meta( $uid, 'enko_edrpou', sanitize_text_field( (string) $req->get_param( 'edrpou' ) ) );
			update_user_meta( $uid, 'enko_city', sanitize_text_field( (string) $req->get_param( 'city' ) ) );
			update_user_meta( $uid, 'enko_note', sanitize_textarea_field( (string) $req->get_param( 'note' ) ) );
			// Закріпити дефолтного менеджера.
			$dm = (int) enko_opt( 'default_manager', 0 );
			if ( $dm ) { update_user_meta( $uid, 'enko_manager', $dm ); }

			// Авторизувати одразу.
			wp_set_current_user( $uid );
			wp_set_auth_cookie( $uid, true );

			// Якщо пароль не задано — лист для встановлення.
			if ( $send_set_pass ) {
				$u = get_userdata( $uid );
				retrieve_password( $u->user_login );
			}

			// Сповістити менеджера про нову реєстрацію.
			$name = trim( $req->get_param( 'firstName' ) . ' ' . $req->get_param( 'lastName' ) );
			$body = "Нова реєстрація партнера\nІмʼя: {$name}\nEmail: {$email}\nТелефон: {$phone}\n"
				. 'Тип: ' . ( 'ur' === $entity ? 'Юридична особа' : 'Фізична особа' ) . "\n"
				. admin_url( 'user-edit.php?user_id=' . $uid );
			wp_mail( enko_opt( 'manager_email', get_option( 'admin_email' ) ), 'ENKO — нова реєстрація', $body );
			if ( function_exists( 'enko_tg_notify_lead' ) ) { enko_tg_notify_lead( 'Нова реєстрація партнера', preg_replace( '/^[^\n]*\n/', '', $body ) ); } elseif ( function_exists( 'enko_tg_send' ) ) { enko_tg_send( esc_html( $body ) ); }

			return new WP_REST_Response( array(
				'ok'         => true,
				'redirect'   => home_url( '/account/' ),
				'setPass'    => $send_set_pass,
				'profile'    => enko_account_profile( $uid ),
			), 200 );
		},
	) );

	/* ---- Вхід ---- */
	register_rest_route( 'enko/v1', '/auth/login', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$pass  = (string) $req->get_param( 'password' );
			$user  = $email ? get_user_by( 'email', $email ) : null;
			// Єдина відповідь для «нема акаунта» і «невірний пароль» — щоб не
			// давати оракул наявності акаунта (аудит L1). wp_signon викликаємо
			// завжди (навіть без user), щоб не було й таймінг-різниці.
			$signon = wp_signon( array(
				'user_login'    => $user ? $user->user_login : $email,
				'user_password' => $pass,
				'remember'      => true,
			), is_ssl() );
			if ( ! $user || is_wp_error( $signon ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth', 'msg' => 'Невірний email або пароль.' ), 401 );
			}
			wp_set_current_user( $signon->ID );
			wp_set_auth_cookie( $signon->ID, true );
			return new WP_REST_Response( array(
				'ok'       => true,
				'redirect' => home_url( '/account/' ),
				'profile'  => enko_account_profile( $signon->ID ),
			), 200 );
		},
	) );

	/* ---- Вихід ---- */
	register_rest_route( 'enko/v1', '/auth/logout', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			wp_logout();
			return new WP_REST_Response( array( 'ok' => true, 'redirect' => home_url( '/' ) ), 200 );
		},
	) );

	/* ---- Скидання пароля (завжди ok — не розкриваємо наявність акаунту) ---- */
	register_rest_route( 'enko/v1', '/auth/forgot', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			// Антиспам: honeypot + ліміт 5/15хв на IP — проти email-бомбардування
			// листами скидання (аудит L2). Завжди повертаємо ok, не розкриваючи.
			if ( function_exists( 'enko_form_is_bot' ) && enko_form_is_bot( $req ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}
			if ( function_exists( 'enko_form_rate_ok' ) && ! enko_form_rate_ok( 'forgot', 5 ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$user  = $email ? get_user_by( 'email', $email ) : null;
			if ( $user ) { retrieve_password( $user->user_login ); }
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );

	/* ---- Поточний профіль + знижка + менеджер + замовлення ---- */
	register_rest_route( 'enko/v1', '/account/me', array(
		'methods'             => 'GET',
		'permission_callback' => 'enko_account_logged_in',
		'callback'            => function () {
			$uid = get_current_user_id();
			return new WP_REST_Response( array(
				'ok'      => true,
				'profile' => enko_account_profile( $uid ),
				'manager' => enko_account_manager( $uid ),
				'orders'  => enko_account_orders( $uid ),
				'rate'    => enko_eur_rate(),
				'tgBind'  => array(
					'linked'   => (bool) get_user_meta( $uid, 'enko_tg_dm_chat', true ),
					'deeplink' => ( enko_opt( 'tg_token', '' ) && enko_opt( 'tg_chat', '' ) && function_exists( 'enko_tg_make_deeplink' ) ) ? enko_tg_make_deeplink( $uid ) : '',
				),
			), 200 );
		},
	) );

	/* ---- Оновлення профілю (+ опц. зміна пароля) ---- */
	register_rest_route( 'enko/v1', '/account/update', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_account_logged_in',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid = get_current_user_id();
			$fn  = sanitize_text_field( (string) $req->get_param( 'firstName' ) );
			$ln  = sanitize_text_field( (string) $req->get_param( 'lastName' ) );
			wp_update_user( array(
				'ID'           => $uid,
				'first_name'   => $fn,
				'last_name'    => $ln,
				'display_name' => trim( $fn . ' ' . $ln ) ?: get_userdata( $uid )->user_email,
			) );
			update_user_meta( $uid, 'billing_phone', sanitize_text_field( (string) $req->get_param( 'phone' ) ) );
			update_user_meta( $uid, 'enko_coop', sanitize_text_field( (string) $req->get_param( 'coop' ) ) );
			update_user_meta( $uid, 'billing_company', sanitize_text_field( (string) $req->get_param( 'company' ) ) );
			update_user_meta( $uid, 'enko_edrpou', sanitize_text_field( (string) $req->get_param( 'edrpou' ) ) );
			update_user_meta( $uid, 'enko_city', sanitize_text_field( (string) $req->get_param( 'city' ) ) );

			$reauth = false;
			$np = (string) $req->get_param( 'password' );
			if ( strlen( $np ) >= 6 ) {
				wp_set_password( $np, $uid );          // знищує сесії
				wp_set_current_user( $uid );
				wp_set_auth_cookie( $uid, true );      // відновлюємо вхід
				$reauth = true;
			}
			return new WP_REST_Response( array(
				'ok'      => true,
				'reauth'  => $reauth,
				'profile' => enko_account_profile( $uid ),
			), 200 );
		},
	) );

	/* ---- Чат: надіслати повідомлення клієнта ---- */
	register_rest_route( 'enko/v1', '/chat/send', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_account_logged_in',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid  = get_current_user_id();
			$text = trim( sanitize_textarea_field( (string) $req->get_param( 'text' ) ) );
			if ( '' === $text ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'empty' ), 400 );
			}
			$text = mb_substr( $text, 0, 1000 );
			$id   = enko_chat_add( $uid, 'user', $text );

			// Сповістити менеджера.
			$u    = get_userdata( $uid );
			$name = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->user_email;
			$body = "Нове повідомлення в чаті від {$name} ({$u->user_email}):\n{$text}\n" . home_url( '/manager/' );
			wp_mail( enko_opt( 'manager_email', get_option( 'admin_email' ) ), 'ENKO — повідомлення в чаті', $body );
			// Доставка в Telegram — через подію enko_chat_added (пуш у тему клієнта, inc/telegram.php).

			return new WP_REST_Response( array( 'ok' => true, 'id' => $id ), 200 );
		},
	) );

	/* ---- Чат: отримати нові повідомлення (polling) ---- */
	register_rest_route( 'enko/v1', '/chat/poll', array(
		'methods'             => 'GET',
		'permission_callback' => 'enko_account_logged_in',
		'callback'            => function ( WP_REST_Request $req ) {
			$uid   = get_current_user_id();
			$since = (int) $req->get_param( 'since' );
			$msgs  = enko_chat_messages( $uid, $since );
			// Позначити відповіді менеджера прочитаними клієнтом.
			if ( $msgs ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . enko_chat_table() . ' SET read_user = 1 WHERE user_id = %d AND author = %s', $uid, 'support' ) );
			}
			return new WP_REST_Response( array( 'ok' => true, 'messages' => $msgs ), 200 );
		},
	) );
} );

/* =========================================================================
   ПРОФІЛЬ У WP-ADMIN — призначення менеджера + контакти менеджера
   ========================================================================= */

add_action( 'show_user_profile', 'enko_user_account_fields' );
add_action( 'edit_user_profile', 'enko_user_account_fields' );
function enko_user_account_fields( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	$managers = enko_manager_users();
	$assigned = (int) get_user_meta( $user->ID, 'enko_manager', true );
	?>
	<h2>ENKO — кабінет</h2>
	<table class="form-table">
		<tr>
			<th><label for="enko_manager">Закріплений менеджер</label></th>
			<td>
				<select name="enko_manager" id="enko_manager">
					<option value="0">— дефолтний —</option>
					<?php foreach ( $managers as $m ) : ?>
						<option value="<?php echo (int) $m->ID; ?>" <?php selected( $assigned, $m->ID ); ?>><?php echo esc_html( $m->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php if ( user_can( $user->ID, 'manage_woocommerce' ) ) : ?>
		<tr><th><label for="enko_mgr_role">Посада (для картки менеджера)</label></th>
			<td><input type="text" name="enko_mgr_role" id="enko_mgr_role" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'enko_mgr_role', true ) ); ?>" placeholder="Персональний менеджер ENKO"></td></tr>
		<tr><th><label for="enko_mgr_phone">Телефон менеджера</label></th>
			<td><input type="text" name="enko_mgr_phone" id="enko_mgr_phone" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'enko_mgr_phone', true ) ); ?>"></td></tr>
		<tr><th><label for="enko_mgr_tg">Telegram менеджера (@user)</label></th>
			<td><input type="text" name="enko_mgr_tg" id="enko_mgr_tg" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'enko_mgr_tg', true ) ); ?>"></td></tr>
		<?php endif; ?>
	</table>
	<?php
}
add_action( 'personal_options_update', 'enko_save_user_account_fields' );
add_action( 'edit_user_profile_update', 'enko_save_user_account_fields' );
function enko_save_user_account_fields( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	if ( isset( $_POST['enko_manager'] ) ) { update_user_meta( $user_id, 'enko_manager', (int) $_POST['enko_manager'] ); }
	foreach ( array( 'enko_mgr_role', 'enko_mgr_phone', 'enko_mgr_tg' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) { update_user_meta( $user_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ); }
	}
}
