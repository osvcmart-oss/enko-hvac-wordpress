<?php
/**
 * Catalog mode — «заявка», not online payment.
 * The Woo cart is reused as the request basket; checkout is replaced by a
 * request form (see request-flow.php). Here we relabel the buttons and add
 * PDP spec tiles + the mobile sticky bar.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Relabel add-to-cart everywhere → "Додати в заявку". */
add_filter( 'woocommerce_product_single_add_to_cart_text', function () { return 'Додати в заявку'; } );
add_filter( 'woocommerce_product_add_to_cart_text', function () { return 'Додати в заявку'; } );

/** Cart/checkout copy nudged toward "заявка". */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) { return $translated; }
	$map = array(
		'Proceed to checkout' => 'Оформити заявку',
		'Checkout'            => 'Заявка',
		'Cart'                => 'Заявка',
		'Update cart'         => 'Оновити заявку',
		'Add to cart'         => 'Додати в заявку',
	);
	return $map[ $text ] ?? $translated;
}, 20, 3 );

/** PDP: spec tiles built from product attributes / meta (mirrors prototype spec-плитки). */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	$tiles = array();
	foreach ( array(
		'pa_potuzhnist' => 'кВт',
		'pa_ploshcha'   => 'м²',
		'pa_riven-shumu' => 'дБ',
	) as $tax => $unit ) {
		$val = $product->get_attribute( $tax );
		if ( $val ) { $tiles[] = array( $val, $unit ); }
	}
	if ( ! $tiles ) { return; }
	echo '<div class="enko-specs">';
	foreach ( $tiles as $t ) {
		echo '<div class="enko-spec"><b>' . esc_html( $t[0] ) . '</b><span>' . esc_html( $t[1] ) . '</span></div>';
	}
	echo '</div>';
}, 25 );

/** PDP: mobile sticky request bar markup. */
add_action( 'woocommerce_after_single_product', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	echo '<div class="enko-sticky-bar" aria-hidden="true">'
		. '<span class="enko-sb-price">' . wp_kses_post( $product->get_price_html() ) . '</span>'
		. '<a class="button" href="#" onclick="document.querySelector(\'.single_add_to_cart_button\')?.click();return false;">Додати в заявку</a>'
		. '</div>';
} );
