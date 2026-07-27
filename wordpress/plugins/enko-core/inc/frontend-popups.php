<?php
/**
 * Front-end popups / bars (lead form, callback bar, cookie banner, chat launcher).
 * Logic ported from prototype home-r1.js; timings/working-hours come from
 * ENKO options instead of localStorage.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * NOTE: The 1:1 prototype port loads the prototype's own home-r1.js, which
 * already renders the lead/quick/callback/cookie popups + launcher. To avoid
 * duplicate widgets we DO NOT enqueue enko-core's popups.js here. The settings
 * (delays/working hours) are still exposed for the front JS via the global
 * below, and consumed by the prototype logic where wired.
 */
add_action( 'wp_enqueue_scripts', function () {
	$cfg = array(
		'delays'  => array(
			'lead'    => (int) enko_opt( 'delay_lead', 30 ),
			'callbar' => (int) enko_opt( 'delay_callbar', 60 ),
			'cookie'  => (int) enko_opt( 'delay_cookie', 0 ),
		),
		'working' => (bool) enko_is_working(),
		'work'    => array( 'start' => enko_opt( 'work_start', '09:00' ), 'end' => enko_opt( 'work_end', '18:00' ) ),
		'tgLink'    => enko_opt( 'tg_link', 'https://t.me/EnkoGroup' ),
			'tgBot'     => function_exists( 'enko_tg_bot_username' ) ? enko_tg_bot_username() : 'EnkoSupportBot',
		'restUrl'   => esc_url_raw( rest_url( 'enko/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'cartCount' => function_exists( 'enko_cart_count' ) ? enko_cart_count() : 0,
		// Account / cabinet.
		'loggedIn'   => is_user_logged_in(),
		'userName'   => is_user_logged_in() ? ( wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ) : '',
		'userEmail'  => is_user_logged_in() ? wp_get_current_user()->user_email : '',
		'discount'   => is_user_logged_in() ? max( 0, min( 99, (int) get_user_meta( get_current_user_id(), 'enko_discount', true ) ) ) : 0,
		'isManager'  => current_user_can( 'manage_woocommerce' ),
		'accountUrl' => home_url( '/account/' ),
		'managerUrl' => home_url( '/manager/' ),
		'searchUrl'  => home_url( '/poshuk/' ),
	);
	// Attach to the prototype's first script so home-r1.js can read window.ENKO_CFG.
	wp_add_inline_script( 'enko-js', 'window.ENKO_CFG = ' . wp_json_encode( $cfg ) . ';', 'before' );

	// Контакти для месенджер-кнопок (home-r2.js LINKS читає window.ENKO_LINKS).
	if ( function_exists( 'enko_contacts' ) ) {
		$c = enko_contacts();
		$links = array(
			'phone' => $c['phone_tel'],
			'mail'  => $c['email_url'],
			'tg'    => $c['tg'],
			'vb'    => $c['viber'],
			'wa'    => $c['whatsapp'],
		);
		wp_add_inline_script( 'enko-js', 'window.ENKO_LINKS = ' . wp_json_encode( $links ) . ';', 'before' );
	}

	/*
	 * Discount bridge: the prototype's visual discount layer (home-r2.js — struck
	 * price, −N% badge, catalog note) reads the personal discount from localStorage
	 * (enko_user_v1 / enko_accounts_v1). The real discount now lives server-side
	 * (user-meta enko_discount, applied to Woo get_price for the actual order).
	 * Mirror the server value into localStorage BEFORE enko.js/home-r2.js run, so
	 * the native visual layer lights up site-wide (catalog, PDP selector, cart)
	 * with no edits to the patched prototype scripts. Prices fed to enko.js stay
	 * full (eur×rate); home-r2.js applies the discount once → no double discount.
	 */
	if ( is_user_logged_in() ) {
		$cu   = wp_get_current_user();
		$rec  = array(
			'email'     => $cu->user_email,
			'firstName' => $cu->first_name,
			'discount'  => $cfg['discount'],
		);
		$sync = 'try{var _r=' . wp_json_encode( $rec ) . ';localStorage.setItem("enko_user_v1",JSON.stringify(_r));localStorage.setItem("enko_accounts_v1",JSON.stringify([_r]));}catch(e){}';
	} else {
		$sync = 'try{localStorage.removeItem("enko_user_v1");localStorage.removeItem("enko_accounts_v1");}catch(e){}';
	}
	wp_add_inline_script( 'enko-js', $sync, 'before' );

	// Серверний курс €→₴ у localStorage — enko.js getRate() читає його для ВСІХ цін
	// (каталог/картка/кошик). Інакше фронт рахує зі старим дефолтом 45.
	wp_add_inline_script( 'enko-js', 'try{localStorage.setItem("enko_eur_rate",' . wp_json_encode( (string) enko_eur_rate() ) . ');}catch(e){}', 'before' );

	// Робочі години + затримки попапів → localStorage (СЕРВЕР = джерело правди). Прототип
	// home-r1.js (логіка попапів/робочих годин) і панель тестів у /manager/ читають саме
	// localStorage; без цього містка зміни в Налаштування → ENKO не впливали ні на сайт,
	// ні на кабінет (показувались дефолти прототипу 09:00–18:00).
	$wh = wp_json_encode( array(
		'enko_work_start'    => (string) enko_opt( 'work_start', '09:00' ),
		'enko_work_end'      => (string) enko_opt( 'work_end', '18:00' ),
		'enko_delay_lead'    => (string) (int) enko_opt( 'delay_lead', 30 ),
		'enko_delay_callbar' => (string) (int) enko_opt( 'delay_callbar', 60 ),
		'enko_delay_cookie'  => (string) (int) enko_opt( 'delay_cookie', 0 ),
	) );
	wp_add_inline_script( 'enko-js', 'try{var _wh=' . $wh . ';for(var k in _wh){localStorage.setItem(k,_wh[k]);}}catch(e){}', 'before' );

	// Real form submission (modal / lead / consult → REST → email + Telegram).
	wp_enqueue_script( 'enko-forms', ENKO_CORE_URL . 'assets/enko-forms.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	// GDPR: текст-згадка згоди під кнопками лід-форм (рендерить enko-forms.js;
	// текст серверний — обирає мову UA/RU і лінкує чинну сторінку політики).
	$consent_html = enko_t(
		'Надсилаючи форму, ви погоджуєтесь із <a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '" target="_blank" rel="noopener">політикою конфіденційності</a>.',
		'Отправляя форму, вы соглашаетесь с <a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '" target="_blank" rel="noopener">политикой конфиденциальности</a>.'
	);
	wp_add_inline_script( 'enko-forms', 'window.ENKO_CONSENT=' . wp_json_encode( array( 'html' => $consent_html ) ) . ';', 'before' );
	// Cart «заявка» → WooCommerce (add-to-cart + badge sync).
	wp_enqueue_script( 'enko-cart', ENKO_CORE_URL . 'assets/enko-cart.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	// Account cabinet: auth modal, header chip, profile, orders, manager, chat. Site-wide.
	wp_enqueue_script( 'enko-account', ENKO_CORE_URL . 'assets/enko-account.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	// Пошук по каталогу: автодоповнення у шапці (фото+ціна+категорії). Site-wide.
	wp_enqueue_script( 'enko-search', ENKO_CORE_URL . 'assets/enko-search.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	wp_enqueue_style( 'enko-search', ENKO_CORE_URL . 'assets/enko-search.css', array(), ENKO_CORE_VER );
	// Анонімний чат (фаза 2): перекриває плаваючий чат для НЕзалогінених → REST guest/*.
	if ( ! is_user_logged_in() ) {
		wp_enqueue_script( 'enko-guest-chat', ENKO_CORE_URL . 'assets/enko-guest-chat.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	}
	// Manager SSM — only on /manager/.
	if ( is_page( 'manager' ) ) {
		wp_enqueue_script( 'enko-manager', ENKO_CORE_URL . 'assets/enko-manager.js', array( 'enko-js' ), ENKO_CORE_VER, true );
	}
}, 30 );

/** Body classes reflecting working hours (popup theming hook). */
add_filter( 'body_class', function ( $classes ) {
	$classes[] = enko_is_working() ? 'enko-working' : 'enko-offhours';
	return $classes;
} );
