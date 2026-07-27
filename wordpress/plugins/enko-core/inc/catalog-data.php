<?php
/**
 * Catalog data bridge — feeds the prototype enko.js engine real Woo data.
 * Builds window.ENKO_PRODUCTS (catalog cards + filters) from WooCommerce
 * products. Variable products emit one card per version (matching the
 * prototype's per-size cards); simple products emit one card.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Spec table per «Версія» term (kW / area m² / BTU) — Kaysun line. */
function enko_version_specs() {
	return array(
		'26' => array( 'power' => 2.6,  'area' => 25, 'btu' => 9 ),
		'35' => array( 'power' => 3.5,  'area' => 35, 'btu' => 12 ),
		'52' => array( 'power' => 5.28, 'area' => 50, 'btu' => 18 ),
		'71' => array( 'power' => 7.03, 'area' => 70, 'btu' => 24 ),
	);
}

/** Map a Woo product (+ its variations) into prototype card objects. */
function enko_build_products_data() {
	$td    = get_template_directory_uri();
	$rate  = enko_eur_rate();
	$vspec = enko_version_specs();
	$type_map = array( 'настінний' => 'wall', 'касетний' => 'cassette', 'канальний' => 'duct', 'консольний' => 'console', 'підлогово-стельовий' => 'floorceil' );

	$items = array();
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );

	foreach ( $products as $p ) {
		// «Прихований» товар (catalog_visibility hidden/search) — не показуємо в каталозі,
		// хоча сторінка й історія заявок лишаються доступними.
		$vis = $p->get_catalog_visibility();
		if ( 'hidden' === $vis || 'search' === $vis ) { continue; }
		$brand  = $p->get_meta( '_enko_brand' ) ?: ( $p->get_attribute( 'pa_brend' ) ?: 'Kaysun' );
		$series = $p->get_meta( '_enko_series' ) ?: '';
		$energy = $p->get_meta( '_enko_energy' ) ?: ( $p->get_attribute( 'pa_energoklas' ) ?: 'A++' );
		$wifi   = (int) ( $p->get_meta( '_enko_wifi' ) ?: 0 );
		$badge  = $p->get_meta( '_enko_badge' ) ?: '';
		$pop    = (int) ( $p->get_meta( '_enko_pop' ) ?: 50 );
		$type   = $p->get_meta( '_enko_type' );
		if ( ! $type ) {
			$t = strtolower( $p->get_attribute( 'pa_typ' ) );
			$type = $type_map[ $t ] ?? 'wall';
		}
		$photo = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
		if ( ! $photo ) {
			$photo = ( stripos( $p->get_name(), 'kaysun' ) !== false )
				? content_url( 'uploads/enko/products-kaysun-casual-indoor.webp' )
				: content_url( 'uploads/enko/types-' . $type . '.webp' );
		}
		$base = array(
			'brand' => $brand, 'series' => $series, 'type' => $type, 'block' => $p->get_meta( '_enko_block' ) ?: '',
			'energy' => $energy, 'wifi' => $wifi, 'badge' => ( function_exists( 'et' ) ? et( $badge ) : $badge ), 'pop' => $pop, 'rating' => 0,
			'warranty' => 3, 'stock' => $p->is_in_stock() ? 'in' : 'order', 'eta' => 0,
			'href' => get_permalink( $p->get_id() ), 'img' => $type, 'photo' => $photo,
		);

		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) { continue; }
				$ver = '';
				foreach ( $v->get_attributes() as $k => $val ) { if ( strpos( $k, 'versiya' ) !== false ) { $ver = $val; } }
				$sp  = $vspec[ $ver ] ?? array( 'power' => 0, 'area' => 0, 'btu' => 0 );
				$eur = (float) ( $v->get_meta( '_enko_eur' ) ?: enko_product_eur( $p ) );
				$items[] = array_merge( $base, array(
					'id'    => ( $p->get_sku() ?: ( 'P' . $p->get_id() ) ) . '-' . $ver,
					'name'  => trim( ( ( enko_is_ru() && $p->get_meta( '_enko_name_ru' ) ) ? $p->get_meta( '_enko_name_ru' ) : $p->get_name() ) . ' ' . $ver ),
					'href'  => add_query_arg( 'ver', $ver, get_permalink( $p->get_id() ) ),
					'btu'   => (int) $sp['btu'], 'area' => (int) $sp['area'], 'power' => (float) $sp['power'],
					'range' => (string) $sp['power'], 'eur' => $eur, 'uah' => $eur ? round( $eur * $rate ) : (float) $v->get_price(),
				) );
			}
		} else {
			$eur = (float) enko_product_eur( $p );
			$items[] = array_merge( $base, array(
				'id'    => ( $p->get_sku() ?: 'P' . $p->get_id() ),
				'name'  => ( ( enko_is_ru() && $p->get_meta( '_enko_name_ru' ) ) ? $p->get_meta( '_enko_name_ru' ) : $p->get_name() ),
				'btu'   => (int) ( $p->get_meta( '_enko_btu' ) ?: 0 ),
				'area'  => (int) ( $p->get_meta( '_enko_area' ) ?: 0 ),
				'power' => (float) ( $p->get_meta( '_enko_power' ) ?: 0 ),
				'range' => (string) ( $p->get_meta( '_enko_range' ) ?: ( $p->get_meta( '_enko_power' ) ?: '' ) ),
				'eur'   => $eur, 'uah' => $eur ? round( $eur * $rate ) : (float) $p->get_price(),
			) );
		}
	}
	return $items;
}

/** Per-version full spec defaults (Kaysun Casual line) — fallback for the PDP. */
function enko_pdp_version_defaults() {
	return array(
		'26' => array( 'model' => 'AKAY-C 26 DR13', 'heat' => '3.0',  'noise' => '25', 'breaker' => '10 A', 'area' => '20–25' ),
		'35' => array( 'model' => 'AKAY-C 35 DR13', 'heat' => '3.8',  'noise' => '25', 'breaker' => '10 A', 'area' => '25–35' ),
		'52' => array( 'model' => 'AKAY-C 52 DR12', 'heat' => '5.57', 'noise' => '26', 'breaker' => '12 A', 'area' => '35–50' ),
		'71' => array( 'model' => 'AKAY-C 71 DR12', 'heat' => '7.33', 'noise' => '36', 'breaker' => '16 A', 'area' => '50–70' ),
	);
}

/** Build window.ENKO_PDP from a Woo variable product (drives the version selector). */
function enko_build_pdp_data( $product ) {
	$rate  = enko_eur_rate();
	$vspec = enko_version_specs();
	$def    = enko_pdp_version_defaults();
	$series = $product->get_meta( '_enko_series' );
	$versions = array();
	$current  = '';
	$name     = ( enko_is_ru() && $product->get_meta( '_enko_name_ru' ) ) ? $product->get_meta( '_enko_name_ru' ) : $product->get_name();
	$skuBase  = ( $product->get_sku() ?: ( 'P' . $product->get_id() ) ) . '-';

	// SIMPLE товари (із синхронізації Google Sheet): кожна версія потужності — окремий
	// товар із SKU виду `<БАЗА>-<версія>` (напр. EN-AC-KAYSUN-CASUAL-26). Щоб повернути
	// перемикач версій (як у прототипі), збираємо «братів» лінійки за префіксом SKU в один
	// список versions. cart-id у enko.js = skuBase+версія = SKU «брата» → заявка коректна.
	if ( ! $product->is_type( 'variable' ) ) {
		$sku = (string) $product->get_sku();
		if ( $sku && preg_match( '/^(.*)-(\d+)$/', $sku, $m ) ) {
			$base    = $m[1];
			$current = $m[2];
			$skuBase = $base . '-';
			$sibs    = wc_get_products( array( 'limit' => -1, 'type' => 'simple', 'status' => array( 'publish', 'draft', 'private' ) ) );
			foreach ( $sibs as $s ) {
				if ( ! preg_match( '/^' . preg_quote( $base, '/' ) . '-(\d+)$/', (string) $s->get_sku(), $mm ) ) { continue; }
				$ver    = $mm[1];
				$is_cur = ( $s->get_id() === $product->get_id() );
				$vis    = $s->get_catalog_visibility();
				$shown  = ( 'publish' === $s->get_status() && 'hidden' !== $vis && 'search' !== $vis );
				if ( ! $is_cur && ! $shown ) { continue; } // приховані/чорнові версії — лише поточну
				$eur = (float) ( $s->get_meta( '_enko_eur' ) ?: 0 );
				$uah = $eur ? round( $eur * $rate ) : (float) $s->get_price();
				$versions[ $ver ] = array(
					'model'   => $s->get_meta( '_enko_model' ) ?: $s->get_name(),
					'cool'    => $s->get_meta( '_enko_cool' ) ?: $s->get_meta( '_enko_power' ),
					'heat'    => $s->get_meta( '_enko_heat' ),
					'noise'   => $s->get_meta( '_enko_noise' ),
					'breaker' => $s->get_meta( '_enko_breaker' ),
					'area'    => $s->get_meta( '_enko_area_label' ) ?: $s->get_meta( '_enko_area' ),
					'uah'     => number_format( $uah, 0, ',', ' ' ),
					'eur'     => (string) round( $eur ),
				);
			}
			uksort( $versions, function ( $a, $b ) { return (int) $a - (int) $b; } );
			// H1/sticky/заявка: назва ЛІНІЙКИ без конкретної моделі (модель показує перемикач).
			$model = (string) $product->get_meta( '_enko_model' );
			if ( $model && count( $versions ) > 1 ) {
				$line = trim( preg_replace( '/\s*' . preg_quote( $model, '/' ) . '\s*$/u', '', $name ) );
				if ( '' !== $line ) { $name = $line; }
			}
		}
		// Фолбек: SKU без числового суфікса → одна «версія» (звичайний простий товар).
		if ( ! $versions ) {
			$eur = (float) ( $product->get_meta( '_enko_eur' ) ?: 0 );
			$uah = $eur ? round( $eur * $rate ) : (float) $product->get_price();
			$current = 'std';
			$versions['std'] = array(
				'model'   => $product->get_meta( '_enko_model' ) ?: $product->get_name(),
				'cool'    => $product->get_meta( '_enko_cool' ) ?: $product->get_meta( '_enko_power' ),
				'heat'    => $product->get_meta( '_enko_heat' ),
				'noise'   => $product->get_meta( '_enko_noise' ),
				'breaker' => $product->get_meta( '_enko_breaker' ),
				'area'    => $product->get_meta( '_enko_area_label' ) ?: $product->get_meta( '_enko_area' ),
				'uah'     => number_format( $uah, 0, ',', ' ' ),
				'eur'     => (string) round( $eur ),
			);
		}
	}

	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) { continue; }
			$ver = '';
			foreach ( $v->get_attributes() as $k => $val ) { if ( strpos( $k, 'versiya' ) !== false ) { $ver = $val; } }
			if ( '' === $ver ) { continue; }
			$sp  = $vspec[ $ver ] ?? array( 'power' => 0 );
			$d   = $def[ $ver ] ?? array( 'model' => $product->get_name() . ' ' . $ver, 'heat' => '', 'noise' => '', 'breaker' => '', 'area' => '' );
			$eur = (float) ( $v->get_meta( '_enko_eur' ) ?: 0 );
			$uah = $eur ? round( $eur * $rate ) : (float) $v->get_price();
			$versions[ $ver ] = array(
				'model'   => $v->get_meta( '_enko_model' ) ?: ( 'prodigy' === $series ? 'AKAY-P ' . $ver : $d['model'] ),
				'cool'    => $v->get_meta( '_enko_cool' ) ?: (string) $sp['power'],
				'heat'    => $v->get_meta( '_enko_heat' ) ?: $d['heat'],
				'noise'   => $v->get_meta( '_enko_noise' ) ?: $d['noise'],
				'breaker' => $v->get_meta( '_enko_breaker' ) ?: $d['breaker'],
				'area'    => $v->get_meta( '_enko_area_label' ) ?: $d['area'],
				'uah'     => number_format( $uah, 0, ',', ' ' ),
				'eur'     => (string) round( $eur ),
			);
		}
	}
	return array(
		'name'    => $name,
		'skuBase' => $skuBase,
		'energy'  => $product->get_meta( '_enko_energy' ) ?: 'A++/A+',
		'current' => $current,
		'versions'=> $versions,
	);
}

/** Inject window.ENKO_PRODUCTS on catalog/archive/product pages (before enko.js). */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_shop' ) ) { return; }
	if ( ! ( is_shop() || is_product_taxonomy() || is_product() ) ) { return; }
	$data = enko_build_products_data();
	wp_add_inline_script( 'enko-js', 'window.ENKO_PRODUCTS = ' . wp_json_encode( $data ) . ';', 'before' );
}, 25 );

/**
 * Мапа SKU → permalink товару для сторінки кошика/заявки — щоб рядки кошика
 * (enko.js rowHTML) могли вести на конкретний товар (із потрібною версією). Для
 * SIMPLE-товарів-версій SKU = окремий товар, тож permalink веде на потрібну версію.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) { return; }
	$map = array();
	foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) ) as $p ) {
		$sku = $p->get_sku();
		if ( $sku ) { $map[ $sku ] = get_permalink( $p->get_id() ); }
	}
	wp_add_inline_script( 'enko-js', 'window.ENKO_CART_LINKS = ' . wp_json_encode( $map ) . ';', 'before' );
}, 25 );
