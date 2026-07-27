<?php
/**
 * Пошук по каталогу — серверний бекенд (REST + ядро для сторінки результатів).
 *
 * Шукає товари за: назвою (UA + RU), SKU/моделлю/брендом/серією/типом,
 * характеристиками (потужність/площа/BTU/енергоклас/wifi), описом і КАТЕГОРІЯМИ.
 * Двомовно: індекс містить обидві мови, тож запит будь-якою мовою знаходить товар.
 * Невеликий каталог → індекс будується раз і кешується в transient (скидається
 * при зміні товарів / синхронізації каталогу / зміні курсу).
 *
 * UX-принципи (Baymard): автодоповнення з фото+ціною, підказки категорій для
 * запитів-типів, частковий збіг, синоніми UA↔RU, дружній стан «нічого не знайдено».
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ENKO_SEARCH_INDEX_KEY', 'enko_search_index_v1' );

/** Навігаційні категорії сайту (назви + ключові слова обома мовами + URL). */
function enko_search_categories() {
	return array(
		array( 'key' => 'conditioners', 'ua' => 'Кондиціонери',  'ru' => 'Кондиционеры',  'kw' => 'кондиціонер кондиционер спліт сплит split інвертор инвертор настінний настенный' ),
		array( 'key' => 'vrf',          'ua' => 'VRF / VRV системи', 'ru' => 'VRF / VRV системы', 'kw' => 'vrf vrv мультизональн мультизональн центральн центральн' ),
		array( 'key' => 'heat-pumps',   'ua' => 'Теплові насоси', 'ru' => 'Тепловые насосы', 'kw' => 'тепловий тепловой насос heat pump опаленн отоплен гаряча вода' ),
		array( 'key' => 'ventilation',  'ua' => 'Вентиляція',     'ru' => 'Вентиляция',     'kw' => 'вентиляц рекуператор припливн вытяжн приточн' ),
		array( 'key' => 'microclimate', 'ua' => 'Мікроклімат',    'ru' => 'Микроклимат',    'kw' => 'зволожувач увлажнитель осушувач осушитель очисник очиститель мікроклімат микроклимат' ),
		array( 'key' => 'fancoils',     'ua' => 'Фанкойли',       'ru' => 'Фанкойлы',       'kw' => 'фанкойл чилер чиллер' ),
	);
}

/** URL категорії (з теми, з фолбеком). */
function enko_search_cat_url( $key ) {
	return function_exists( 'enko_cat_url' ) ? enko_cat_url( $key ) : home_url( '/' . $key . '/' );
}

/** Нормалізація рядка: lower, лише літери/цифри, уніфікація синонімів UA↔RU. */
function enko_search_normalize( $s ) {
	$s = ' ' . mb_strtolower( wp_strip_all_tags( (string) $s ), 'UTF-8' ) . ' ';
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );          // лишаємо літери+цифри
	$s = preg_replace( '/\s+/', ' ', $s );
	// Уніфікація варіантів (RU→UA-стем + абревіатури). Замінюємо стем зі стартом слова.
	$map = array(
		' кондиционер' => ' кондиціонер', ' кондей' => ' кондиціонер', ' сплит' => ' спліт',
		' инвертор' => ' інвертор', ' тепловой' => ' тепловий', ' вентиляция' => ' вентиляція',
		' увлажнитель' => ' зволожувач', ' осушитель' => ' осушувач', ' очиститель' => ' очисник',
		' микроклимат' => ' мікроклімат', ' бто' => ' btu', ' фанкойлы' => ' фанкойл',
	);
	foreach ( $map as $from => $to ) { $s = str_replace( $from, $to, $s ); }
	return trim( $s );
}

/** Побудувати індекс пошуку з опублікованих товарів (важка операція — кешується). */
function enko_search_build_index() {
	if ( ! function_exists( 'wc_get_products' ) ) { return array(); }
	$rate     = function_exists( 'enko_eur_rate' ) ? enko_eur_rate() : 45;
	$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) );
	$out      = array();

	foreach ( $products as $p ) {
		if ( 'hidden' === $p->get_catalog_visibility() ) { continue; } // приховані — не в пошуку
		$id  = $p->get_id();
		$ua  = $p->get_name();
		$ru  = (string) $p->get_meta( '_enko_name_ru' );
		$cats = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
		$cat_label = ( $cats && ! is_wp_error( $cats ) ) ? implode( ', ', $cats ) : '';

		$specs = trim(
			(string) $p->get_meta( '_enko_power' ) . ' кВт '
			. (string) $p->get_meta( '_enko_area' ) . ' м² '
			. (string) $p->get_meta( '_enko_btu' ) . ' btu '
			. (string) $p->get_meta( '_enko_energy' ) . ' '
			. ( $p->get_meta( '_enko_wifi' ) ? 'wifi' : '' )
		);
		$bag = array(
			$ua, $ru, (string) $p->get_sku(),
			(string) $p->get_meta( '_enko_brand' ), (string) $p->get_meta( '_enko_series' ),
			(string) $p->get_meta( '_enko_model' ), (string) $p->get_meta( '_enko_type' ),
			$specs, $cat_label, 'кондиціонер кондиционер',
			wp_strip_all_tags( (string) $p->get_short_description() ),
			(string) $p->get_meta( '_enko_short_ru' ),
		);

		$eur = function_exists( 'enko_product_eur' ) ? (float) enko_product_eur( $p ) : (float) $p->get_meta( '_enko_eur' );
		$uah = $eur ? round( $eur * $rate ) : (float) $p->get_price();
		$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';

		// Короткі характеристики для рядка результату (потужність · площа · клас).
		$sbits = array();
		$pw = (string) $p->get_meta( '_enko_power' );
		$ar = (int) $p->get_meta( '_enko_area' );
		$en = (string) $p->get_meta( '_enko_energy' );
		if ( '' !== $pw ) { $sbits[] = $pw . ' кВт'; }
		if ( $ar )        { $sbits[] = 'до ' . $ar . ' м²'; }
		if ( '' !== $en ) { $sbits[] = $en; }

		$out[] = array(
			'id'        => $id,
			'specs'     => implode( ' · ', $sbits ),
			'ua'        => $ua,
			'ru'        => $ru,
			'cat'       => $cat_label,
			'url'       => get_permalink( $id ),
			'img'       => $img ?: '',
			'eur'       => $eur,
			'uah'       => $uah,
			'pop'       => (int) ( $p->get_meta( '_enko_pop' ) ?: 50 ),
			'badge'     => (string) $p->get_meta( '_enko_badge' ),
			'name_norm' => enko_search_normalize( $ua . ' ' . $ru ),
			'haystack'  => enko_search_normalize( implode( ' ', $bag ) ),
		);
	}
	return $out;
}

/** Індекс пошуку (з кешу або побудувати). */
function enko_search_index() {
	$cached = get_transient( ENKO_SEARCH_INDEX_KEY );
	if ( is_array( $cached ) ) { return $cached; }
	$idx = enko_search_build_index();
	set_transient( ENKO_SEARCH_INDEX_KEY, $idx, 6 * HOUR_IN_SECONDS );
	return $idx;
}

/** Гарантувати сторінку результатів /poshuk/ (ідемпотентно, один раз). */
add_action( 'init', function () {
	if ( get_option( 'enko_search_page_done' ) ) { return; }
	if ( ! get_page_by_path( 'poshuk' ) ) {
		wp_insert_post( array(
			'post_title'   => 'Пошук',
			'post_name'    => 'poshuk',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		) );
	}
	update_option( 'enko_search_page_done', 1 );
} );

/** Скинути кеш індексу при змінах. */
function enko_search_flush() { delete_transient( ENKO_SEARCH_INDEX_KEY ); }
add_action( 'save_post_product', 'enko_search_flush' );
add_action( 'woocommerce_update_product', 'enko_search_flush' );
add_action( 'woocommerce_new_product', 'enko_search_flush' );
add_action( 'update_option_enko_eur_rate', 'enko_search_flush' );

/**
 * Виконати пошук. Повертає ['products'=>[...], 'categories'=>[...], 'total'=>N].
 * Кожен токен запиту має зустрітися у haystack (AND); ранжування — за збігом у назві.
 */
function enko_search_query( $q, $limit = 20 ) {
	$qn = enko_search_normalize( $q );
	if ( '' === $qn ) { return array( 'products' => array(), 'categories' => array(), 'total' => 0 ); }
	$tokens = array_values( array_filter( explode( ' ', $qn ) ) );
	$ru     = function_exists( 'enko_is_ru' ) && enko_is_ru();

	$hits = array();
	foreach ( enko_search_index() as $r ) {
		$all = true;
		foreach ( $tokens as $t ) {
			if ( false === mb_stripos( $r['haystack'], $t ) ) { $all = false; break; }
		}
		if ( ! $all ) { continue; }

		$score = 1 + ( (int) $r['pop'] ) / 100;                       // база + популярність (tiebreak)
		if ( false !== mb_stripos( $r['name_norm'], $qn ) ) { $score += 100; }      // повний запит у назві
		if ( 0 === mb_stripos( $r['name_norm'] . ' ', $tokens[0] ) ) { $score += 40; } // назва починається з токена
		foreach ( $tokens as $t ) { if ( false !== mb_stripos( $r['name_norm'], $t ) ) { $score += 10; } }

		$hits[] = array( 'r' => $r, 's' => $score );
	}
	usort( $hits, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );

	$products = array();
	foreach ( array_slice( $hits, 0, $limit ) as $h ) {
		$r      = $h['r'];
		$name   = ( $ru && $r['ru'] ) ? $r['ru'] : $r['ua'];
		$products[] = array(
			'id'    => $r['id'],
			'name'  => $name,
			'url'   => $r['url'],
			'img'   => $r['img'],
			'uah'   => $r['uah'],
			'eur'   => $r['eur'],
			'cat'   => $r['cat'],
			'specs' => $r['specs'],
		);
	}

	// Підказки категорій (Baymard: запити-типи → одразу на категорію).
	$categories = array();
	foreach ( enko_search_categories() as $c ) {
		$hay = enko_search_normalize( $c['ua'] . ' ' . $c['ru'] . ' ' . $c['kw'] );
		$ok  = true;
		foreach ( $tokens as $t ) { if ( false === mb_stripos( $hay, $t ) ) { $ok = false; break; } }
		if ( $ok ) {
			$categories[] = array( 'name' => ( $ru ? $c['ru'] : $c['ua'] ), 'url' => enko_search_cat_url( $c['key'] ) );
		}
	}

	return array( 'products' => $products, 'categories' => $categories, 'total' => count( $hits ) );
}

/** REST: автодоповнення / результати. Публічний, read-only. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/search', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$q     = (string) $req->get_param( 'q' );
			$limit = max( 1, min( 20, (int) ( $req->get_param( 'limit' ) ?: 8 ) ) );
			$res   = enko_search_query( $q, $limit );
			return new WP_REST_Response( array_merge( array( 'ok' => true, 'q' => $q ), $res ), 200 );
		},
	) );
} );
