<?php
/**
 * Dual currency display (₴ + €), mirroring the prototype.
 * EUR amount is the source of truth in product meta `_enko_eur`;
 * the UAH (Woo) price is what carts/sorting use.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Get the EUR figure for a product (meta, else derived from UAH price / rate). */
function enko_product_eur( $product ) {
	if ( ! $product instanceof WC_Product ) { return 0; }
	$eur = $product->get_meta( '_enko_eur' );
	if ( $eur ) { return (float) $eur; }
	$uah = (float) $product->get_price();
	$rate = enko_eur_rate();
	return $rate ? round( $uah / $rate ) : 0;
}

/** Append "· €N" to the price HTML on loop + single. */
add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	if ( is_admin() && ! wp_doing_ajax() ) { return $html; }
	$eur = enko_product_eur( $product );
	if ( $eur > 0 ) {
		$html .= ' <span class="enko-eur">· €' . number_format( $eur, 0, ',', ' ' ) . '</span>';
	}
	return $html;
}, 20, 2 );

/** Admin: simple EUR field on the product "General" tab. */
add_action( 'woocommerce_product_options_pricing', function () {
	woocommerce_wp_text_input( array(
		'id'          => '_enko_eur',
		'label'       => 'Ціна, € (ENKO)',
		'desc_tip'    => true,
		'description' => 'Орієнтовна ціна в євро (джерело правди; гривня = € × курс).',
		'data_type'   => 'decimal',
	) );
} );
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	if ( isset( $_POST['_enko_eur'] ) ) {
		$product->update_meta_data( '_enko_eur', wc_clean( wp_unslash( $_POST['_enko_eur'] ) ) );
	}
} );
