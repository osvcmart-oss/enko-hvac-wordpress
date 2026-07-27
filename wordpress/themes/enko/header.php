<?php
/**
 * ENKO header — 1:1 port of the prototype topbar + site-header + mobile-nav.
 * UA/RU: рядки через et() (inc/i18n.php); перемикач мови — серверні ?lang= кнопки.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$td   = get_template_directory_uri();
$shop = enko_shop_url();
$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$cart_n = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
$c = function_exists( 'enko_contacts' ) ? enko_contacts() : array( 'phone' => '+380 777 147 777', 'phone_tel' => 'tel:+380777147777' );
$is_ru = function_exists( 'enko_is_ru' ) && enko_is_ru();
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- =================== TOP BAR =================== -->
<div class="topbar">
  <div class="container topbar__in">
    <a class="topbar__phone" href="<?php echo esc_attr( $c['phone_tel'] ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg><?php echo esc_html( $c['phone'] ); ?></a>
    <div class="topbar__right">
      <div class="topbar-msgr" aria-label="<?php echo esc_attr( et( 'Месенджери' ) ); ?>">
        <a href="#" aria-label="Viber" title="Viber"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg></a>
        <a href="#" aria-label="Telegram" title="Telegram"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></a>
        <a href="#" aria-label="WhatsApp" title="WhatsApp"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 8.5c0 3 2 5 5 5"/></svg></a>
      </div>
      <span class="sep"></span>
      <?php $la = ' style="background:#fff;color:#22325E;border-color:#fff" aria-current="true"'; ?>
      <div class="lang-pills" role="group" aria-label="<?php echo esc_attr( et( 'Мова' ) ); ?>">
        <button type="button" class="<?php echo $is_ru ? '' : 'active'; ?>"<?php echo $is_ru ? '' : $la; ?> data-lang="UA" onclick="location.href='<?php echo esc_url( add_query_arg( 'lang', 'uk' ) ); ?>'">UA</button>
        <button type="button" class="<?php echo $is_ru ? 'active' : ''; ?>"<?php echo $is_ru ? $la : ''; ?> data-lang="RU" onclick="location.href='<?php echo esc_url( add_query_arg( 'lang', 'ru' ) ); ?>'">RU</button>
      </div>
    </div>
  </div>
</div>

<!-- =================== HEADER =================== -->
<header class="site-header">
  <div class="container header__in">
    <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( et( 'ENKO — на головну' ) ); ?>">
      <img src="<?php echo esc_url( $td ); ?>/assets/logo-enko-trans.png" alt="ENKO">
    </a>
    <nav aria-label="<?php echo esc_attr( et( 'Головне меню' ) ); ?>">
      <ul class="main-nav">
        <li class="has-dropdown">
          <button class="nav-trigger" aria-expanded="false"><?php echo esc_html( et( 'Каталог' ) ); ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="dropdown">
            <a href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="9" rx="2"/><path d="M6 19h2M16 19h2M6 10h.01M10 10h6"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Кондиціонери' ) ); ?></b><span><?php echo esc_html( et( 'Спліт- та мульти-системи' ) ); ?></span></span></a>
            <a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="16" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><path d="M14 15h7"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Мультизональні VRF' ) ); ?></b><span><?php echo esc_html( et( 'Системи для багатьох зон' ) ); ?></span></span></a>
            <a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0z"/><path d="M12 3v18M3 12h18"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Теплові насоси' ) ); ?></b><span><?php echo esc_html( et( 'Повітря-вода / повітря-повітря' ) ); ?></span></span></a>
            <a href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><path d="M12 12a3 3 0 1 0 0-.01M12 12c0-4 2-7 6-7-1 4-2 7-6 7zM12 12c0 4-2 7-6 7 1-4 2-7 6-7z"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Вентиляція' ) ); ?></b><span><?php echo esc_html( et( 'Припливно-витяжні установки' ) ); ?></span></span></a>
            <a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><path d="M12 22a7 7 0 0 0 7-7c0-5-7-13-7-13S5 10 5 15a7 7 0 0 0 7 7z"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Мікроклімат' ) ); ?></b><span><?php echo esc_html( et( 'Зволожувачі, осушувачі, очисники' ) ); ?></span></span></a>
            <a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><span class="dd-ic"><svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 11h10M7 14h6"/></svg></span><span class="dd-txt"><b><?php echo esc_html( et( 'Фанкойли' ) ); ?></b><span><?php echo esc_html( et( 'Для систем чилер-фанкойл' ) ); ?></span></span></a>
          </div>
        </li>
        <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( et( 'Про нас' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( home_url( '/delivery/' ) ); ?>"><?php echo esc_html( et( 'Доставка і гарантії' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>"><?php echo esc_html( et( 'Контакти' ) ); ?></a></li>
      </ul>
    </nav>
      <form class="header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/poshuk/' ) ); ?>">
        <input type="search" name="q" placeholder="<?php echo esc_attr( et( 'Пошук по каталогу…' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Пошук' ) ); ?>">
        <button type="submit" aria-label="<?php echo esc_attr( et( 'Знайти' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
      </form>
    <div class="header__right">
      <a class="icon-btn cart-link" href="<?php echo esc_url( $cart ); ?>" aria-label="<?php echo esc_attr( et( 'Заявка / корзина' ) ); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <span class="cart-badge<?php echo $cart_n > 0 ? ' show' : ''; ?>"><?php echo (int) $cart_n; ?></span>
      </a>
      <div class="auth-area" id="auth-area"><?php
        if ( is_user_logged_in() ) {
          $cu  = wp_get_current_user();
          $nm  = $cu->first_name ?: $cu->display_name;
          $ini = mb_strtoupper( mb_substr( $cu->first_name ?: $nm, 0, 1 ) . mb_substr( $cu->last_name, 0, 1 ) );
          if ( '' === trim( $ini ) ) { $ini = 'EN'; }
          echo '<a class="account-chip" href="' . esc_url( home_url( '/account/' ) ) . '" aria-label="' . esc_attr( et( 'Мій кабінет' ) ) . '"><span class="ava">' . esc_html( $ini ) . '</span><span>' . esc_html( $nm ) . '</span></a>';
        } else {
          echo '<button class="auth-trigger" data-auth-open type="button"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>' . esc_html( et( 'Увійти' ) ) . '</button>';
        }
      ?></div>
      <button class="btn btn--primary btn--m" data-modal-open data-product=""><?php echo esc_html( et( 'Залишити заявку' ) ); ?></button>
      <button class="icon-btn burger" aria-label="<?php echo esc_attr( et( 'Меню' ) ); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- =================== MOBILE NAV =================== -->
<div class="mobile-nav" id="mobile-nav">
  <div class="mobile-nav__panel">
    <div class="mobile-nav__top">
      <img src="<?php echo esc_url( $td ); ?>/assets/logo-enko-trans.png" alt="ENKO">
      <button class="icon-btn mobile-nav__close" data-mnav-close aria-label="<?php echo esc_attr( et( 'Закрити' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form class="mnav-search" role="search" method="get" action="<?php echo esc_url( home_url( '/poshuk/' ) ); ?>">
      <input type="search" name="q" placeholder="<?php echo esc_attr( et( 'Пошук по каталогу…' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Пошук' ) ); ?>">
      <button type="submit" aria-label="<?php echo esc_attr( et( 'Знайти' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
    </form>
    <div class="mnav-group">
      <span class="mnav-title"><?php echo esc_html( et( 'Каталог' ) ); ?></span>
      <a href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>"><?php echo esc_html( et( 'Кондиціонери' ) ); ?></a>
      <a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'Мультизональні VRF' ) ); ?></a>
      <a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'Теплові насоси' ) ); ?></a>
      <a href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><?php echo esc_html( et( 'Вентиляція' ) ); ?></a>
      <a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'Мікроклімат' ) ); ?></a>
      <a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'Фанкойли' ) ); ?></a>
    </div>
    <a class="mnav-link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( et( 'Про нас' ) ); ?></a>
    <a class="mnav-link" href="<?php echo esc_url( home_url( '/delivery/' ) ); ?>"><?php echo esc_html( et( 'Доставка і гарантії' ) ); ?></a>
    <a class="mnav-link" href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>"><?php echo esc_html( et( 'Контакти' ) ); ?></a>
    <a class="mnav-link" href="<?php echo esc_url( $cart ); ?>"><?php echo esc_html( et( 'Заявка / Корзина' ) ); ?></a>
    <div id="auth-area-m"></div>
    <button class="btn btn--primary btn--block" data-modal-open data-product=""><?php echo esc_html( et( 'Залишити заявку' ) ); ?></button>
  </div>
  <div class="mobile-nav__backdrop" data-mnav-close></div>
</div>

<main>
