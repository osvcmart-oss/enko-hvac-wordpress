<?php
/**
 * ENKO theme — bootstrap.
 *
 * 1:1 port of the static ENKO prototype. Enqueues the prototype's real
 * stylesheets and scripts verbatim (from /styles and /scripts), so the
 * WordPress front-end matches the design pixel-for-pixel. Business logic
 * lives in the `enko-core` plugin.
 *
 * @package enko
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ENKO_THEME_VER', '0.6.10' );

/**
 * Продуктивність першого рендеру (2026-07-13, покадровий аналіз): preconnect до
 * Google Fonts і preload критичних фото головної (герой слайд 1 + фони 6 плиток).
 * Без preload CSS-фони стартують лише ПІСЛЯ парсингу CSS — фаза «білих плиток» ~1с.
 * Пріоритет 2 — щоб підказки стояли в <head> раніше за стилі (wp_print_styles на 8).
 */
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	if ( ! is_front_page() ) { return; }
	$up = content_url( 'uploads/enko' );
	echo '<link rel="preload" as="image" href="' . esc_url( $up . '/catalog-conditioners-mobile.webp' ) . '" media="(max-width:600px)">' . "\n";
	foreach ( array( 'conditioners', 'vrf', 'heat-pumps', 'ventilation', 'microclimate', 'fancoils' ) as $c ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $up . '/catalog-' . $c . '-desktop.webp' ) . '" media="(min-width:601px)">' . "\n";
	}
}, 2 );

/**
 * Prototype assets — exact CSS/JS load order from index.html.
 */
add_action( 'wp_enqueue_scripts', function () {
	$u = get_template_directory_uri();
	$v = ENKO_THEME_VER;

	// Google Fonts (same families/weights as the prototype).
	wp_enqueue_style( 'enko-fonts', 'https://fonts.googleapis.com/css2?family=Exo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap', array(), null );

	// Stylesheets — order matters: enko → components → home-r1 → home-r2.
	wp_enqueue_style( 'enko-base',       "$u/styles/enko.css",       array( 'enko-fonts' ),      $v );
	wp_enqueue_style( 'enko-components', "$u/styles/components.css",  array( 'enko-base' ),       $v );
	wp_enqueue_style( 'enko-r1',         "$u/styles/home-r1.css",     array( 'enko-components' ), $v );
	wp_enqueue_style( 'enko-r2',         "$u/styles/home-r2.css",     array( 'enko-r1' ),         $v );

	// Scripts — order matters: enko → home-r1 → home-r2 (footer).
	wp_enqueue_script( 'enko-js',    "$u/scripts/enko.js",     array(),                       $v, true );
	wp_enqueue_script( 'enko-r1-js', "$u/scripts/home-r1.js",  array( 'enko-js' ),            $v, true );
	wp_enqueue_script( 'enko-r2-js', "$u/scripts/home-r2.js",  array( 'enko-js', 'enko-r1-js' ), $v, true );

	// Мобільна поправка: притиснути праву групу іконок шапки (пошук/кошик) до
	// правого краю екрана на будь-якій вузькій ширині. Лого лишається зліва.
	wp_add_inline_style( 'enko-r2', '@media (max-width:1024px){.site-header .header__in>.brand{margin-right:auto}}' );
}, 20 );

/**
 * Theme supports.
 */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	register_nav_menus( array(
		'primary' => __( 'Головне меню', 'enko' ),
		'footer'  => __( 'Меню підвалу', 'enko' ),
	) );
} );

add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 12; } );

/** Shop (Кондиціонери full catalog) URL. */
function enko_shop_url() { return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ); }

/** Catalog section URL by key. «Кондиціонери» = full catalog (shop); the rest = individual pages. */
function enko_cat_url( $key ) {
	return ( 'conditioners' === $key ) ? enko_shop_url() : home_url( '/' . $key . '/' );
}
