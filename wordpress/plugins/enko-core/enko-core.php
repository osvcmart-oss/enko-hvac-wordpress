<?php
/**
 * Plugin Name: ENKO Core
 * Description: Бізнес-логіка ENKO HVAC — каталог-режим («заявка» замість оплати), курс EUR→грн і подвійні ціни, персональні знижки, попапи/бари з налаштуваннями, робочі години, сповіщення (email + Telegram). Переїзд прототипу ENKO у WordPress.
 * Version: 0.1.0
 * Author: Oleh Martynenko
 * Text Domain: enko-core
 * Requires Plugins: woocommerce
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ENKO_CORE_VER', '0.2.44' );
define( 'ENKO_CORE_FILE', __FILE__ );
define( 'ENKO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ENKO_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Option helpers (mirror prototype localStorage keys / defaults).
 */
function enko_opt( $key, $default = '' ) {
	$v = get_option( 'enko_' . $key, null );
	return ( null === $v || '' === $v ) ? $default : $v;
}
function enko_eur_rate() {
	return (float) enko_opt( 'eur_rate', 45 );
}
/**
 * ID поточного користувача, надійний у REST БЕЗ nonce. WP REST для cookie-авторизації
 * вимагає X-WP-Nonce; БЕЗ нього `rest_cookie_check_errors` робить wp_set_current_user(0),
 * тож get_current_user_id() = 0 навіть із валідною auth-cookie. Наші публічні сабміти
 * (форма/кошик) nonce НЕ шлють (щоб гості не ловили 403), тому залогінений виглядав би
 * гостем. Фолбек — пряма валідація logged_in-cookie, що відновлює справжній ID.
 */
function enko_current_uid() {
	$uid = get_current_user_id();
	if ( $uid ) { return (int) $uid; }
	if ( function_exists( 'wp_validate_auth_cookie' ) ) {
		$u = wp_validate_auth_cookie( '', 'logged_in' );
		if ( $u ) { return (int) $u; }
	}
	return 0;
}
/** Working hours check — Mon–Fri within [start,end], visitor local time handled client-side too. */
function enko_is_working() {
	$start = enko_opt( 'work_start', '09:00' );
	$end   = enko_opt( 'work_end', '18:00' );
	$now   = current_time( 'H:i' );
	$dow   = (int) current_time( 'w' ); // 0 Sun .. 6 Sat
	if ( 0 === $dow || 6 === $dow ) { return false; }
	return ( $now >= $start && $now <= $end );
}

/**
 * Нормалізовані контакти сайту (шапка/підвал/месенджери) з опцій Settings → ENKO.
 * Телефон — основне джерело; Viber/WhatsApp беруть власний номер або телефон.
 */
function enko_contacts() {
	$phone   = enko_opt( 'phone', '+380 777 147 777' );
	$pdigits = preg_replace( '/\D/', '', $phone );
	$vb_num  = preg_replace( '/\D/', '', enko_opt( 'viber', '' ) );
	$wa_num  = preg_replace( '/\D/', '', enko_opt( 'whatsapp', '' ) );
	if ( '' === $vb_num ) { $vb_num = $pdigits; }
	if ( '' === $wa_num ) { $wa_num = $pdigits; }
	$email = enko_opt( 'email', 'info@enkogroup.com.ua' );
	return array(
		'phone'     => $phone,
		'phone_tel' => 'tel:+' . $pdigits,
		'email'     => $email,
		'email_url' => 'mailto:' . $email,
		'tg'        => enko_opt( 'tg_link', 'https://t.me/EnkoGroup' ),
		'viber'     => 'viber://chat?number=%2B' . $vb_num,
		'whatsapp'  => 'https://wa.me/' . $wa_num,
	);
}

require_once ENKO_CORE_DIR . 'inc/i18n.php';
require_once ENKO_CORE_DIR . 'inc/settings.php';
require_once ENKO_CORE_DIR . 'inc/pricing.php';
require_once ENKO_CORE_DIR . 'inc/catalog-mode.php';
require_once ENKO_CORE_DIR . 'inc/discount.php';
require_once ENKO_CORE_DIR . 'inc/frontend-popups.php';
require_once ENKO_CORE_DIR . 'inc/request-flow.php';
require_once ENKO_CORE_DIR . 'inc/catalog-data.php';
require_once ENKO_CORE_DIR . 'inc/docs.php';
require_once ENKO_CORE_DIR . 'inc/redirects.php';
require_once ENKO_CORE_DIR . 'inc/cart.php';
require_once ENKO_CORE_DIR . 'inc/mailer.php';
require_once ENKO_CORE_DIR . 'inc/account.php';
require_once ENKO_CORE_DIR . 'inc/manager.php';
require_once ENKO_CORE_DIR . 'inc/telegram.php';
require_once ENKO_CORE_DIR . 'inc/guest-chat.php';
require_once ENKO_CORE_DIR . 'inc/catalog-sync.php';
require_once ENKO_CORE_DIR . 'inc/seo.php';
require_once ENKO_CORE_DIR . 'inc/visibility.php';
require_once ENKO_CORE_DIR . 'inc/security.php';
require_once ENKO_CORE_DIR . 'inc/media.php';
require_once ENKO_CORE_DIR . 'inc/page-cache.php';
require_once ENKO_CORE_DIR . 'inc/backup.php';
require_once ENKO_CORE_DIR . 'inc/search.php';

/**
 * Seed default options on activation (idempotent).
 */
register_activation_hook( __FILE__, function () {
	$defaults = array(
		'eur_rate'      => 45,
		'delay_lead'    => 30,
		'delay_callbar' => 60,
		'delay_cookie'  => 0,
		'work_start'    => '09:00',
		'work_end'      => '18:00',
	);
	foreach ( $defaults as $k => $v ) {
		if ( null === get_option( 'enko_' . $k, null ) ) { add_option( 'enko_' . $k, $v ); }
	}
	if ( null === get_option( 'enko_default_manager', null ) ) { add_option( 'enko_default_manager', 0 ); }
	// Таблиця чату кабінету.
	if ( function_exists( 'enko_chat_install' ) ) { enko_chat_install(); }
	// Таблиці анонімного чату (фаза 2).
	if ( function_exists( 'enko_guest_install' ) ) { enko_guest_install(); }
} );
