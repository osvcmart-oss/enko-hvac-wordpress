<?php
/**
 * 301-редіректи зі старих адрес прототипу (*.html) на нові WordPress-адреси.
 * Підстраховка для закладок, зовнішніх посилань і будь-яких залишкових .html.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $path ) { return; }
	$file = basename( $path ); // напр. kaysun-casual.html (навіть з /shop/...)
	if ( substr( $file, -5 ) !== '.html' ) { return; }

	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

	// Товари — за SKU.
	$skus = array(
		'kaysun-casual.html'  => 'EN-AC-KAYSUN-CASUAL',
		'kaysun-prodigy.html' => 'EN-AC-KAYSUN-PRODIGY',
	);
	if ( isset( $skus[ $file ] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
		$pid = wc_get_product_id_by_sku( $skus[ $file ] );
		if ( $pid ) { wp_safe_redirect( get_permalink( $pid ), 301 ); exit; }
	}

	$map = array(
		'index.html'          => home_url( '/' ),
		'catalog.html'        => $shop,
		'cat-vrf.html'        => home_url( '/vrf/' ),
		'cat-heat-pumps.html' => home_url( '/heat-pumps/' ),
		'cat-ventilation.html'=> home_url( '/ventilation/' ),
		'cat-microclimate.html'=> home_url( '/microclimate/' ),
		'cat-fancoils.html'   => home_url( '/fancoils/' ),
		'about.html'          => home_url( '/about/' ),
		'contacts.html'       => home_url( '/contacts/' ),
		'delivery.html'       => home_url( '/delivery/' ),
		'cart.html'           => $cart,
		'account.html'        => home_url( '/account/' ),
		'admin.html'          => home_url( '/' ), // старий SSM-прототип — ховаємо (адмінка лише на /manager/)
		'privacy-policy.html' => home_url( '/privacy-policy/' ),
		'cookies.html'        => home_url( '/cookies/' ),
		'terms.html'          => home_url( '/terms/' ),
	);
	if ( isset( $map[ $file ] ) ) { wp_safe_redirect( $map[ $file ], 301 ); exit; }
}, 1 );
