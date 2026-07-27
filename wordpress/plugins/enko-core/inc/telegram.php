<?php
/**
 * Telegram — двосторонній міст чату підтримки.
 *
 * Спільний журнал wp_enko_chat (ключ user_id, author=user/support) синхронізується
 * з кількома «вікнами» в Telegram:
 *
 *  ФАЗА 1   — менеджер у супергрупі `tg_chat` з Topics: клієнт = тема (user_id ↔ thread).
 *  ФАЗА 1.5 — клієнт у приватному чаті з ботом: deep-link ?start=<token> прив'язує
 *             Telegram до акаунта (user_id ↔ private chat_id).
 *  ФАЗА 2   — анонімний гість (НЕзалогінений): окрема супергрупа `tg_anon_chat`, гість =
 *             тема за guest-UUID. Вхід (А) віджет на сайті, (Б) Telegram гостя
 *             (?start=g-<uuid>). Сховище/REST — inc/guest-chat.php.
 *
 * Маршрутизація без петель — за $source події enko_chat_added: web / tg_group / tg_private.
 * Загальні сповіщення (замовлення/реєстрації/ліди) — enko_tg_send() (General `tg_chat`).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Базовий виклик Telegram Bot API. Повертає decoded-масив або false. */
function enko_tg_api( $method, $params = array() ) {
	$token = enko_opt( 'tg_token', '' );
	if ( ! $token ) { return false; }
	$resp = wp_remote_post( "https://api.telegram.org/bot{$token}/{$method}", array(
		'timeout' => 10,
		'body'    => $params,
	) );
	if ( is_wp_error( $resp ) ) { return false; }
	$j = json_decode( wp_remote_retrieve_body( $resp ), true );
	return is_array( $j ) ? $j : false;
}

/** Юзернейм бота (для deep-link). */
function enko_tg_bot_username() {
	return enko_opt( 'tg_bot_username', 'EnkoSupportBot' );
}

/** Секрет для перевірки webhook (генерується раз і зберігається). */
function enko_tg_secret() {
	$s = get_option( 'enko_tg_secret', '' );
	if ( ! $s ) {
		$s = wp_generate_password( 32, false );
		update_option( 'enko_tg_secret', $s );
	}
	return $s;
}

/* ---- Мапи зв'язків ---- */

/** thread_id (тема в супергрупі менеджерів) → user_id. */
function enko_tg_topic_map() {
	$m = get_option( 'enko_tg_topic_map', array() );
	return is_array( $m ) ? $m : array();
}
function enko_tg_topic_user( $thread_id ) {
	$m = enko_tg_topic_map();
	return isset( $m[ (string) $thread_id ] ) ? (int) $m[ (string) $thread_id ] : 0;
}

/** private chat_id (особистий чат КЛІЄНТА з ботом) → user_id. */
function enko_tg_dm_map() {
	$m = get_option( 'enko_tg_dm_map', array() );
	return is_array( $m ) ? $m : array();
}
function enko_tg_dm_user( $chat_id ) {
	$m = enko_tg_dm_map();
	return isset( $m[ (string) $chat_id ] ) ? (int) $m[ (string) $chat_id ] : 0;
}

/** thread_id (тема в анон-супергрупі) → guest-UUID. */
function enko_tg_anon_map() {
	$m = get_option( 'enko_tg_anon_map', array() );
	return is_array( $m ) ? $m : array();
}
function enko_tg_anon_user( $thread_id ) {
	$m = enko_tg_anon_map();
	return isset( $m[ (string) $thread_id ] ) ? (string) $m[ (string) $thread_id ] : '';
}

/** private chat_id (особистий чат ГОСТЯ з ботом) → guest-UUID. */
function enko_tg_guest_dm_map() {
	$m = get_option( 'enko_tg_guest_dm_map', array() );
	return is_array( $m ) ? $m : array();
}
function enko_tg_guest_dm_user( $chat_id ) {
	$m = enko_tg_guest_dm_map();
	return isset( $m[ (string) $chat_id ] ) ? (string) $m[ (string) $chat_id ] : '';
}

/**
 * Персональне deep-link-посилання для прив'язки Telegram КЛІЄНТА до акаунта.
 * Токен одноразовий і короткоживучий (15 хв) — захист від прив'язки чужого акаунта.
 */
function enko_tg_make_deeplink( $uid ) {
	$uid = (int) $uid;
	if ( ! $uid ) { return ''; }
	$token = wp_generate_password( 20, false );
	set_transient( 'enko_tgbind_' . $token, $uid, 15 * MINUTE_IN_SECONDS );
	return 'https://t.me/' . enko_tg_bot_username() . '?start=' . $token;
}

/* =========================================================================
   ВИХІД: журнал/гість → Telegram
   ========================================================================= */

/** Отримати або створити тему КЛІЄНТА в супергрупі менеджерів. Повертає thread_id або 0. */
function enko_tg_ensure_topic( $uid ) {
	$uid  = (int) $uid;
	$chat = enko_opt( 'tg_chat', '' );
	if ( ! $chat || ! $uid ) { return 0; }

	$tid = (int) get_user_meta( $uid, 'enko_tg_topic', true );
	if ( $tid ) { return $tid; }

	$u = get_userdata( $uid );
	if ( ! $u ) { return 0; }
	$name = trim( $u->first_name . ' ' . $u->last_name );
	if ( ! $name ) { $name = $u->display_name ?: $u->user_email; }
	$phone = get_user_meta( $uid, 'billing_phone', true );
	$topic_name = mb_substr( $name . ( $phone ? ' · ' . $phone : '' ), 0, 120 );

	$r = enko_tg_api( 'createForumTopic', array( 'chat_id' => $chat, 'name' => $topic_name ) );
	if ( ! $r || empty( $r['ok'] ) || empty( $r['result']['message_thread_id'] ) ) { return 0; }

	$tid = (int) $r['result']['message_thread_id'];
	update_user_meta( $uid, 'enko_tg_topic', $tid );
	$map = enko_tg_topic_map();
	$map[ (string) $tid ] = $uid;
	update_option( 'enko_tg_topic_map', $map );

	$disc = (int) get_user_meta( $uid, 'enko_discount', true );
	$card = '<b>' . esc_html( $name ) . "</b>\n"
		. ( $phone ? 'Телефон: ' . esc_html( $phone ) . "\n" : '' )
		. 'Email: ' . esc_html( $u->user_email ) . "\n"
		. ( $disc ? 'Знижка: ' . $disc . "%\n" : '' )
		. 'Кабінет менеджера: ' . home_url( '/manager/' ) . "\n"
		. '↳ відповідайте просто в цій темі — клієнт побачить у кабінеті й у своєму Telegram.';
	enko_tg_api( 'sendMessage', array(
		'chat_id'           => $chat,
		'message_thread_id' => $tid,
		'text'              => $card,
		'parse_mode'        => 'HTML',
	) );
	return $tid;
}

/** Забути тему (її видалили в Telegram) — скинути прив'язку, щоб створилась нова. */
function enko_tg_forget_topic( $kind, $key, $tid ) {
	if ( 'user' === $kind ) {
		delete_user_meta( (int) $key, 'enko_tg_topic' );
		$m = enko_tg_topic_map(); unset( $m[ (string) $tid ] ); update_option( 'enko_tg_topic_map', $m );
	} elseif ( function_exists( 'enko_guest_table' ) ) {
		global $wpdb; $wpdb->update( enko_guest_table(), array( 'thread_id' => 0 ), array( 'uuid' => $key ) );
		$m = enko_tg_anon_map(); unset( $m[ (string) $tid ] ); update_option( 'enko_tg_anon_map', $m );
	}
}

/**
 * Надіслати текст у тему людини з АВТО-ВІДНОВЛЕННЯМ: якщо тему видалили в Telegram
 * («message thread not found») — скидаємо прив'язку, створюємо нову й повторюємо.
 * $kind: 'user'|'guest'; $key: user_id|guest_uuid. Повертає bool.
 */
function enko_tg_send_topic( $chat, $kind, $key, $text ) {
	if ( ! $chat ) { return false; }
	$ensure = ( 'user' === $kind ) ? 'enko_tg_ensure_topic' : 'enko_tg_ensure_anon_topic';
	$tid = $ensure( $key );
	if ( ! $tid ) { return false; }
	$r = enko_tg_api( 'sendMessage', array( 'chat_id' => $chat, 'message_thread_id' => $tid, 'text' => $text, 'parse_mode' => 'HTML' ) );
	if ( is_array( $r ) && ! empty( $r['ok'] ) ) { return true; }
	if ( is_array( $r ) && false !== stripos( (string) ( $r['description'] ?? '' ), 'thread not found' ) ) {
		enko_tg_forget_topic( $kind, $key, $tid );                 // тему видалено → нова
		$tid = $ensure( $key );
		if ( $tid ) {
			$r = enko_tg_api( 'sendMessage', array( 'chat_id' => $chat, 'message_thread_id' => $tid, 'text' => $text, 'parse_mode' => 'HTML' ) );
			return is_array( $r ) && ! empty( $r['ok'] );
		}
	}
	return false;
}

/** Надіслати рядок журналу в тему КЛІЄНТА (супергрупа менеджерів). */
function enko_tg_push_to_topic( $uid, $author, $text, $source = 'web' ) {
	$chat = enko_opt( 'tg_chat', '' );
	if ( ! $chat ) { return; }
	$prefix = ( 'support' === $author ) ? '↩️ <i>менеджер (сайт)</i>:' : ( '💬 <b>клієнт</b> [' . ( 'tg_private' === $source ? 'через телеграм-бот' : 'через чат на сайті' ) . ']:' );
	enko_tg_send_topic( $chat, 'user', (int) $uid, $prefix . "\n" . esc_html( $text ) );
}

/** Надіслати відповідь менеджера у приватний чат КЛІЄНТА (якщо під'єднаний). */
function enko_tg_push_to_dm( $uid, $author, $text ) {
	$cid = (int) get_user_meta( (int) $uid, 'enko_tg_dm_chat', true );
	if ( ! $cid ) { return; }
	enko_tg_api( 'sendMessage', array(
		'chat_id'    => $cid,
		'text'       => '👨‍💼 <b>менеджер</b>:' . "\n" . esc_html( $text ),
		'parse_mode' => 'HTML',
	) );
}

/** Отримати або створити тему ГОСТЯ в анон-супергрупі. Повертає thread_id або 0. */
function enko_tg_ensure_anon_topic( $uuid ) {
	$chat = enko_opt( 'tg_anon_chat', '' );
	if ( ! $chat || ! $uuid ) { return 0; }
	$g   = function_exists( 'enko_guest_get' ) ? enko_guest_get( $uuid ) : null;
	$tid = $g ? (int) $g['thread_id'] : 0;
	if ( $tid ) { return $tid; }

	$short = strtoupper( substr( $uuid, 0, 6 ) );
	$r = enko_tg_api( 'createForumTopic', array( 'chat_id' => $chat, 'name' => 'Гість ' . $short ) );
	if ( ! $r || empty( $r['ok'] ) || empty( $r['result']['message_thread_id'] ) ) { return 0; }
	$tid = (int) $r['result']['message_thread_id'];

	if ( $g ) {
		global $wpdb;
		$wpdb->update( enko_guest_table(), array( 'thread_id' => $tid ), array( 'uuid' => $uuid ) );
	}
	$map = enko_tg_anon_map();
	$map[ (string) $tid ] = $uuid;
	update_option( 'enko_tg_anon_map', $map );

	$card = '<b>Анонімний гість</b> (' . esc_html( $short ) . ")\n"
		. ( $g && $g['ip']   ? 'IP: ' . esc_html( $g['ip'] ) . "\n" : '' )
		. ( $g && $g['page'] ? 'Сторінка: ' . esc_html( $g['page'] ) . "\n" : '' )
		. ( $g && $g['ua']   ? 'Браузер: ' . esc_html( mb_substr( $g['ua'], 0, 90 ) ) . "\n" : '' )
		. '↳ відповідайте в цій темі — гість побачить у чаті на сайті.';
	enko_tg_api( 'sendMessage', array(
		'chat_id'           => $chat,
		'message_thread_id' => $tid,
		'text'              => $card,
		'parse_mode'        => 'HTML',
	) );
	return $tid;
}

/** Надіслати повідомлення ГОСТЯ в анон-тему. */
function enko_tg_push_anon( $uuid, $author, $text, $source = 'web' ) {
	$chat = enko_opt( 'tg_anon_chat', '' );
	if ( ! $chat ) { return; }
	$prefix = ( 'support' === $author ) ? '↩️ <i>менеджер</i>:' : ( '💬 <b>гість</b> [' . ( 'tg_private' === $source ? 'через телеграм-бот' : 'через чат на сайті' ) . ']:' );
	enko_tg_send_topic( $chat, 'guest', $uuid, $prefix . "\n" . esc_html( $text ) );
}

/**
 * Лід-сповіщення (заявка/форма/колбек/реєстрація) — у ПЕРСОНАЛЬНУ тему людини:
 * залогінений → тема клієнта в [Auth]; гість → тема гостя в [Anon] (за cookie guest-UUID,
 * перевіряє існування / мінтить за потреби). Так усі дії однієї людини (чат + заявки)
 * консолідуються в ОДНУ тему. Фолбек — General відповідної групи (enko_tg_send).
 */
function enko_tg_notify_lead( $title, $details ) {
	if ( ! enko_opt( 'tg_token', '' ) ) { return; }
	$text = '📋 <b>' . esc_html( $title ) . "</b>\n" . esc_html( $details );
	$uid  = function_exists( 'enko_current_uid' ) ? enko_current_uid() : get_current_user_id();
	if ( $uid ) {
		if ( enko_tg_send_topic( enko_opt( 'tg_chat', '' ), 'user', $uid, $text ) ) { return; }
	} elseif ( enko_opt( 'tg_anon_chat', '' ) && function_exists( 'enko_guest_uuid' ) ) {
		$uuid = enko_guest_uuid( true );                          // існуючий cookie або новий
		if ( function_exists( 'enko_guest_touch' ) ) { enko_guest_touch( $uuid ); }
		if ( enko_tg_send_topic( enko_opt( 'tg_anon_chat', '' ), 'guest', $uuid, $text ) ) { return; }
	}
	enko_tg_send( $text ); // фолбек: General відповідної групи
}

/**
 * Кожне нове повідомлення журналу (зареєстровані) → у Telegram-канали, КРІМ свого джерела.
 */
add_action( 'enko_chat_added', function ( $uid, $author, $text, $id, $source ) {
	if ( ! enko_opt( 'tg_chat', '' ) ) { return; }
	if ( 'tg_group' !== $source ) {
		enko_tg_push_to_topic( (int) $uid, (string) $author, (string) $text, (string) $source );
	}
	if ( 'support' === $author && 'tg_private' !== $source ) {
		enko_tg_push_to_dm( (int) $uid, (string) $author, (string) $text );
	}
}, 10, 5 );

/* =========================================================================
   ВХІД: webhook — повідомлення з Telegram
   ========================================================================= */

add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/tg/webhook', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'enko_tg_webhook',
	) );
	register_rest_route( 'enko/v1', '/tg/deeplink', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'enko_tg_deeplink_rest',
	) );
} );

/**
 * Персональний deep-link на бота для кнопок у шапці/підвалі (і будь-де на сайті).
 * Залогінений → одноразовий токен-прив'язка до акаунта; гість → guest-UUID (ставить cookie
 * + створює запис). Викликається з КЛІКУ (JS), щоб НЕ плодити cookie/записи гостей на кожному
 * завантаженні сторінки. Кабінет-кнопка має власний baked-токен і цей ендпоінт не використовує.
 */
function enko_tg_deeplink_rest( WP_REST_Request $req ) {
	if ( is_user_logged_in() ) {
		return new WP_REST_Response( array( 'ok' => true, 'deeplink' => enko_tg_make_deeplink( get_current_user_id() ) ), 200 );
	}
	if ( ! function_exists( 'enko_guest_uuid' ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 200 );
	}
	$uuid = enko_guest_uuid( true );
	enko_guest_touch( $uuid, esc_url_raw( (string) $req->get_header( 'referer' ) ) );
	return new WP_REST_Response( array( 'ok' => true, 'deeplink' => 'https://t.me/' . enko_tg_bot_username() . '?start=g-' . $uuid ), 200 );
}

function enko_tg_webhook( WP_REST_Request $req ) {
	$secret = (string) $req->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
	if ( ! hash_equals( enko_tg_secret(), $secret ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}

	$upd = (array) $req->get_json_params();
	$msg = $upd['message'] ?? null;                        // редагування ігноруємо
	if ( ! $msg || empty( $msg['chat'] ) ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }
	if ( ! empty( $msg['from']['is_bot'] ) ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }

	$chat_id   = (string) ( $msg['chat']['id'] ?? '' );
	$chat_type = (string) ( $msg['chat']['type'] ?? '' );
	$text      = trim( (string) ( $msg['text'] ?? '' ) );

	/* ===== Приватний чат з ботом ===== */
	if ( 'private' === $chat_type ) {
		if ( 0 === strpos( $text, '/start' ) ) {
			$parts   = preg_split( '/\s+/', $text, 2 );
			$payload = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			// (Б) Гість: payload «g-<uuid>».
			if ( 0 === strpos( $payload, 'g-' ) ) {
				$uuid = preg_replace( '/[^a-zA-Z0-9]/', '', substr( $payload, 2 ) );
				if ( strlen( $uuid ) >= 16 && function_exists( 'enko_guest_touch' ) ) {
					enko_guest_touch( $uuid );
					global $wpdb;
					$wpdb->update( enko_guest_table(), array( 'tg_dm_chat' => (int) $chat_id ), array( 'uuid' => $uuid ) );
					$map = enko_tg_guest_dm_map();
					$map[ (string) $chat_id ] = $uuid;
					update_option( 'enko_tg_guest_dm_map', $map );
					enko_tg_api( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => 'Вітаємо! 🟢 Пишіть тут — це чат із менеджером ENKO, відповімо найближчим часом.' ) );
				} else {
					enko_tg_api( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => 'Вітаємо! Напишіть ваше питання — менеджер ENKO відповість.' ) );
				}
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}

			// (1.5) Зареєстрований клієнт: одноразовий токен.
			$uid = $payload ? (int) get_transient( 'enko_tgbind_' . $payload ) : 0;
			if ( $uid && get_userdata( $uid ) ) {
				delete_transient( 'enko_tgbind_' . $payload );
				update_user_meta( $uid, 'enko_tg_dm_chat', (int) $chat_id );
				$map = enko_tg_dm_map();
				$map[ (string) $chat_id ] = $uid;
				update_option( 'enko_tg_dm_map', $map );
				$u  = get_userdata( $uid );
				$nm = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
				enko_tg_api( 'sendMessage', array(
					'chat_id'    => $chat_id,
					'text'       => 'Вітаємо, ' . esc_html( $nm ) . "! 🟢\nТепер пишіть тут — це той самий чат із менеджером, що й у вашому кабінеті на сайті. Історія синхронізується.",
					'parse_mode' => 'HTML',
				) );
			} else {
				enko_tg_api( 'sendMessage', array(
					'chat_id' => $chat_id,
					'text'    => 'Вітаємо! Напишіть ваше питання — менеджер ENKO відповість. (Для синхронізації з кабінетом відкрийте «Написати в Telegram» у своєму кабінеті на сайті.)',
				) );
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Звичайне приватне повідомлення: спершу зареєстрований клієнт, потім гість.
		$uid = enko_tg_dm_user( $chat_id );
		if ( $uid && '' !== $text ) {
			enko_chat_add( (int) $uid, 'user', mb_substr( $text, 0, 1000 ), 'tg_private' );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		$guuid = enko_tg_guest_dm_user( $chat_id );
		if ( $guuid && '' !== $text && function_exists( 'enko_guest_add' ) ) {
			$t = mb_substr( $text, 0, 1000 );
			enko_guest_add( $guuid, 'guest', $t );
			enko_tg_push_anon( $guuid, 'guest', $t, 'tg_private' );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		// Непривʼязаний приват, що написав прямо в бота (CTA без токена) → мінтимо
		// guest-UUID для цього чату й релеїмо в анон-групу (вхід Б без сайт-кукі).
		if ( ! $uid && ! $guuid && '' !== $text && function_exists( 'enko_guest_add' ) ) {
			$uuid = wp_generate_password( 32, false );
			enko_guest_touch( $uuid, 'прямий вхід у Telegram' );
			global $wpdb;
			$wpdb->update( enko_guest_table(), array( 'tg_dm_chat' => (int) $chat_id ), array( 'uuid' => $uuid ) );
			$map = enko_tg_guest_dm_map();
			$map[ (string) $chat_id ] = $uuid;
			update_option( 'enko_tg_guest_dm_map', $map );
			$t = mb_substr( $text, 0, 1000 );
			enko_guest_add( $uuid, 'guest', $t );
			enko_tg_push_anon( $uuid, 'guest', $t, 'tg_private' );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ===== Супергрупа менеджерів (зареєстровані) ===== */
	if ( $chat_id === (string) enko_opt( 'tg_chat', '' ) ) {
		$thread = (int) ( $msg['message_thread_id'] ?? 0 );
		if ( ! $thread || '' === $text ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }
		$uid = enko_tg_topic_user( $thread );
		if ( ! $uid ) { return new WP_REST_Response( array( 'ok' => true, 'note' => 'no-user' ), 200 ); }
		enko_chat_add( (int) $uid, 'support', mb_substr( $text, 0, 1000 ), 'tg_group' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ===== Анон-супергрупа (гості) ===== */
	if ( $chat_id === (string) enko_opt( 'tg_anon_chat', '' ) ) {
		$thread = (int) ( $msg['message_thread_id'] ?? 0 );
		if ( ! $thread || '' === $text ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }
		$uuid = enko_tg_anon_user( $thread );
		if ( ! $uuid || ! function_exists( 'enko_guest_add' ) ) { return new WP_REST_Response( array( 'ok' => true, 'note' => 'no-guest' ), 200 ); }
		$t = mb_substr( $text, 0, 1000 );
		enko_guest_add( $uuid, 'support', $t );
		// Якщо гість зайшов і через Telegram (Б) — продублювати йому в приватний чат.
		$g = function_exists( 'enko_guest_get' ) ? enko_guest_get( $uuid ) : null;
		if ( $g && (int) $g['tg_dm_chat'] ) {
			enko_tg_api( 'sendMessage', array(
				'chat_id'    => (int) $g['tg_dm_chat'],
				'text'       => '👨‍💼 <b>менеджер</b>:' . "\n" . esc_html( $t ),
				'parse_mode' => 'HTML',
			) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ===== Незнайома група → діагностика (для підключення нових супергруп) ===== */
	if ( in_array( $chat_type, array( 'supergroup', 'group' ), true ) ) {
		$seen = get_option( 'enko_tg_seen_groups', array() );
		if ( ! is_array( $seen ) ) { $seen = array(); }
		$seen[ $chat_id ] = (string) ( $msg['chat']['title'] ?? '' );
		update_option( 'enko_tg_seen_groups', $seen );
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Встановити webhook на наш REST-ендпоінт (викликається вручну після налаштування
 * токена + chat_id). Повертає відповідь Telegram setWebhook.
 */
function enko_tg_set_webhook() {
	return enko_tg_api( 'setWebhook', array(
		'url'             => home_url( '/wp-json/enko/v1/tg/webhook' ),
		'secret_token'    => enko_tg_secret(),
		'allowed_updates' => wp_json_encode( array( 'message' ) ),
	) );
}
