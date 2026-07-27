<?php
/**
 * Синхронізація каталогу з Google Sheet (CSV).
 *
 * Замовник веде каталог у Google-таблиці (аркуш «Кондиціонери»), публікує/відкриває
 * її, і натискає «Синхронізувати каталог» у кабінеті менеджера. WordPress читає CSV
 * і за артикулом (SKU) створює/оновлює прості товари WooCommerce + поля фільтрів
 * `_enko_*` (їх читає inc/catalog-data.php для каталогу/фільтрів/PDP).
 *
 * Стовпці аркуша → поля товару (мапінг у enko_sync_upsert).
 * URL CSV — опція `enko_catalog_csv_url` (Settings → ENKO).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Тип українською → ключ типу (як inc/catalog-data.php). */
function enko_sync_type_key( $t ) {
	$map = array(
		'настінний'           => 'wall',
		'касетний'            => 'cassette',
		'канальний'           => 'duct',
		'консольний'          => 'console',
		'підлогово-стельовий' => 'floorceil',
		'мультиспліт'         => 'wall',
		'мульти-спліт'        => 'wall',
	);
	$k = mb_strtolower( trim( (string) $t ) );
	return isset( $map[ $k ] ) ? $map[ $k ] : 'wall';
}

/** «так»/«1»/«yes» → 1, інакше 0. */
function enko_sync_bool( $v ) {
	$v = mb_strtolower( trim( (string) $v ) );
	return ( 'так' === $v || '1' === $v || 'yes' === $v || 'true' === $v || '+' === $v ) ? 1 : 0;
}

/** Завантажити рядки CSV із URL → масив асоціативних рядків (ключі — заголовки). */
function enko_sync_fetch_rows( $url ) {
	$resp = wp_remote_get( $url, array( 'timeout' => 45, 'redirection' => 5 ) );
	if ( is_wp_error( $resp ) ) { return $resp; }
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return new WP_Error( 'http', 'CSV недоступний (HTTP ' . $code . '). Перевірте доступ до таблиці.' );
	}
	$body = wp_remote_retrieve_body( $resp );
	if ( '' === trim( $body ) || false !== stripos( substr( $body, 0, 200 ), '<html' ) ) {
		return new WP_Error( 'noaccess', 'Замість CSV отримано HTML — таблиця закрита. Відкрийте доступ «Усі, хто має посилання → Глядач».' );
	}
	$fh = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $body );
	rewind( $fh );
	$rows   = array();
	$header = null;
	while ( ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
		if ( null === $header ) {
			$header = array_map( function ( $h ) { return trim( (string) $h ); }, $r );
			continue;
		}
		$assoc = array();
		foreach ( $header as $i => $h ) {
			if ( '' === $h ) { continue; }
			$assoc[ $h ] = isset( $r[ $i ] ) ? trim( (string) $r[ $i ] ) : '';
		}
		if ( '' !== ( isset( $assoc['sku'] ) ? $assoc['sku'] : '' ) ) { $rows[] = $assoc; }
	}
	fclose( $fh );
	return $rows;
}

/** Прикріпити фото за іменами файлів (зіставлення з медіабібліотекою). */
function enko_sync_attach_images( $pid, $filenames ) {
	$names = array_filter( array_map( 'trim', preg_split( '/[;,]+/', (string) $filenames ) ) );
	if ( ! $names ) { return 0; }
	global $wpdb;
	$ids = array();
	foreach ( $names as $fn ) {
		$aid = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
			'%' . $wpdb->esc_like( $fn )
		) );
		if ( $aid ) { $ids[] = (int) $aid; }
	}
	if ( ! $ids ) { return 0; }
	set_post_thumbnail( $pid, $ids[0] );
	if ( count( $ids ) > 1 ) {
		update_post_meta( $pid, '_product_image_gallery', implode( ',', array_slice( $ids, 1 ) ) );
	}
	return count( $ids );
}

/** Створити/оновити один товар із рядка таблиці. */
function enko_sync_upsert( $row ) {
	$sku     = trim( $row['sku'] );
	$pid     = wc_get_product_id_by_sku( $sku );
	$product = $pid ? wc_get_product( $pid ) : null;
	if ( ! $product ) { $product = new WC_Product_Simple(); }
	$created = ! $pid;

	$product->set_sku( $sku );
	$product->set_name( ( isset( $row['name_uk'] ) && '' !== $row['name_uk'] ) ? $row['name_uk'] : $sku );
	if ( isset( $row['long_description_uk'] ) )  { $product->set_description( $row['long_description_uk'] ); }
	if ( isset( $row['short_description_uk'] ) ) { $product->set_short_description( $row['short_description_uk'] ); }
	// Ціна — у ЄВРО (джерело правди). Гривня рахується як eur × курс (Settings → ENKO).
	$eur = (float) preg_replace( '/[^\d.]/', '', str_replace( array( ',', ' ', "\xc2\xa0" ), array( '.', '', '' ), (string) ( isset( $row['price_eur'] ) ? $row['price_eur'] : '' ) ) );
	if ( $eur > 0 ) { $product->set_regular_price( (string) round( $eur * enko_eur_rate() ) ); }
	// Статус видимості з таблиці (стовпець `status`) — менеджер керує показом на сайті.
	$st = mb_strtolower( trim( (string) ( isset( $row['status'] ) ? $row['status'] : '' ) ) );
	if ( 'прихований' === $st ) {
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' ); // немає в каталозі/пошуку, але сторінка й замовлення живі
	} elseif ( 'чорновик' === $st || 'вимкнений' === $st || 'неактивний' === $st ) {
		$product->set_status( 'draft' );               // на сайті немає (наповнюється / вимкнено)
	} else {                                            // «опублікований» або порожньо
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
	}
	$instock = ( false !== mb_stripos( isset( $row['stock_status'] ) ? $row['stock_status'] : '', 'наявн' ) );
	$product->set_stock_status( $instock ? 'instock' : 'onbackorder' );
	$pid = $product->save();

	$g = function ( $k ) use ( $row ) { return isset( $row[ $k ] ) ? $row[ $k ] : ''; };
	$badge = '';
	if ( enko_sync_bool( $g( 'is_hit' ) ) )       { $badge = 'Хіт'; }
	elseif ( enko_sync_bool( $g( 'is_new' ) ) )   { $badge = 'Новинка'; }
	elseif ( enko_sync_bool( $g( 'is_sale' ) ) )  { $badge = 'Акція'; }

	$meta = array(
		'_enko_status'         => $g( 'status' ),
		'_enko_eur'            => ( $eur > 0 ? $eur : '' ),
		'_enko_brand'          => $g( 'brand' ),
		'_enko_series'         => $g( 'series' ),
		'_enko_type'           => enko_sync_type_key( $g( 'type_uk' ) ),
		'_enko_model'          => $g( 'model' ),
		'_enko_version'        => $g( 'version' ),
		'_enko_power'          => $g( 'power_kw' ),
		'_enko_cool'           => $g( 'power_kw' ),
		'_enko_heat'           => $g( 'heat_kw' ),
		'_enko_breaker'        => $g( 'breaker_a' ),
		'_enko_area_label'     => $g( 'area_range' ),
		'_enko_range'          => $g( 'power_kw' ),
		'_enko_btu'            => (int) $g( 'btu' ),
		'_enko_area'           => (int) $g( 'area_m2' ),
		'_enko_rooms'          => $g( 'rooms' ),
		'_enko_compressor'     => $g( 'compressor_type' ),
		'_enko_refrigerant'    => $g( 'refrigerant' ),
		'_enko_energy'         => $g( 'energy_class' ),
		'_enko_min_temp'       => $g( 'min_temp_c' ),
		'_enko_wifi'           => enko_sync_bool( $g( 'wifi' ) ),
		'_enko_noise'          => $g( 'noise_db' ),
		'_enko_color'          => $g( 'color' ),
		'_enko_functions'      => $g( 'functions' ),
		'_enko_air_filtration' => $g( 'air_filtration' ),
		'_enko_warranty'       => (int) $g( 'warranty_years' ),
		'_enko_eta'            => (int) $g( 'eta_days' ),
		'_enko_pop'            => (int) ( $g( 'popularity' ) ?: 50 ),
		'_enko_rating'         => $g( 'rating' ),
		'_enko_badge'          => $badge,
		'_enko_name_ru'        => $g( 'name_ru' ),
		'_enko_short_ru'       => $g( 'short_description_ru' ),
		'_enko_long_ru'        => $g( 'long_description_ru' ),
		'_enko_images'         => $g( 'image_filenames' ),
	);
	foreach ( $meta as $k => $v ) { update_post_meta( $pid, $k, $v ); }

	// Категорія «Кондиціонери».
	$term = get_term_by( 'name', 'Кондиціонери', 'product_cat' );
	if ( $term ) { wp_set_object_terms( $pid, array( (int) $term->term_id ), 'product_cat' ); }

	$imgs = enko_sync_attach_images( $pid, $g( 'image_filenames' ) );

	// PDF-документи за іменами файлів (стовпець `doc_filenames`, опційно `Назва|файл.pdf`).
	// ВАЖЛИВО: чіпаємо `_enko_docs` лише якщо стовпець є в таблиці. Якщо стовпця нема —
	// не зачищаємо документи, задані вручну в картці товару (медіа-пікер).
	$docs = array();
	if ( array_key_exists( 'doc_filenames', $row ) ) {
		$docs = enko_doc_resolve_filenames( $row['doc_filenames'] );
		update_post_meta( $pid, '_enko_docs', $docs );
	}

	$status_label = ( '' === $st || 'опублікований' === $st ) ? 'опублікований' : ( 'неактивний' === $st ? 'вимкнений' : $st );
	return array(
		'sku'    => $sku,
		'id'     => (int) $pid,
		'action' => $created ? 'created' : 'updated',
		'name'   => $product->get_name(),
		'images' => $imgs,
		'docs'   => count( $docs ),
		'status' => $status_label,
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/mgr/sync-catalog', array(
		'methods'             => 'POST',
		'permission_callback' => 'enko_mgr_can',
		'callback'            => function () {
			if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'msg' => 'WooCommerce неактивний.' ), 500 );
			}
			$url = enko_opt( 'catalog_csv_url', '' );
			if ( ! $url ) {
				return new WP_REST_Response( array( 'ok' => false, 'msg' => 'Не задано URL CSV-каталогу в Налаштування → ENKO.' ), 400 );
			}
			$rows = enko_sync_fetch_rows( $url );
			if ( is_wp_error( $rows ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'msg' => $rows->get_error_message() ), 502 );
			}
			$created = 0; $updated = 0; $items = array(); $errors = array();
			$by = array( 'опублікований' => 0, 'прихований' => 0, 'чорновик' => 0, 'вимкнений' => 0 );
			foreach ( $rows as $row ) {
				try {
					$r = enko_sync_upsert( $row );
					if ( 'created' === $r['action'] ) { $created++; } else { $updated++; }
					$s = isset( $r['status'] ) ? $r['status'] : 'опублікований';
					if ( ! isset( $by[ $s ] ) ) { $by[ $s ] = 0; }
					$by[ $s ]++;
					$items[] = $r;
				} catch ( Throwable $e ) {
					$errors[] = ( isset( $row['sku'] ) ? $row['sku'] : '?' ) . ': ' . $e->getMessage();
				}
			}
			if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients(); }
			return new WP_REST_Response( array(
				'ok'      => true,
				'total'   => count( $rows ),
				'created' => $created,
				'updated' => $updated,
				'errors'  => $errors,
				'warnings' => function_exists( 'enko_doc_group_warnings' ) ? enko_doc_group_warnings( $rows ) : array(),
				'by_status' => $by,
				'items'   => $items,
			), 200 );
		},
	) );
} );
