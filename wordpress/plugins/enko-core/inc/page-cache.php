<?php
/**
 * Інвалідація ENKO page cache (пара до wp-content/advanced-cache.php,
 * джерело дроп-іна в репо: wp-build/dropins/advanced-cache.php).
 *
 * Кеш — файли wp-content/cache/enko-pages/*.html з TTL 15 хв; тут — НЕГАЙНИЙ
 * скид при змінах, що впливають на анонімний HTML: будь-яка enko_* опція
 * (курс €→₴ вшитий у сторінку через localStorage-місток! години, попапи,
 * контакти), назва/опис сайту, збереження товару/сторінки/запису (CSV-синк
 * каталогу теж веде сюди через WC-хуки).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Скинути весь page cache. Повертає кількість видалених файлів. */
function enko_page_cache_flush() {
	$dir = WP_CONTENT_DIR . '/cache/enko-pages';
	if ( ! is_dir( $dir ) ) { return 0; }
	$n = 0;
	foreach ( glob( $dir . '/*.html' ) as $f ) {
		if ( @unlink( $f ) ) { $n++; }
	}
	return $n;
}

// updated_option НЕ спрацьовує для нових опцій (там added_option) і для
// видалення — слухаємо всі три, перевірка однакова.
$enko_pc_on_option = function ( $option ) {
	if ( 0 === strpos( (string) $option, 'enko_' ) || in_array( $option, array( 'blogname', 'blogdescription' ), true ) ) {
		enko_page_cache_flush();
	}
};
add_action( 'updated_option', $enko_pc_on_option );
add_action( 'added_option', $enko_pc_on_option );
add_action( 'deleted_option', $enko_pc_on_option );

add_action( 'save_post', function ( $post_id, $post ) {
	if ( $post && in_array( $post->post_type, array( 'product', 'product_variation', 'page', 'post' ), true ) ) {
		enko_page_cache_flush();
	}
}, 10, 2 );

add_action( 'woocommerce_update_product', 'enko_page_cache_flush' );
add_action( 'switch_theme', 'enko_page_cache_flush' );
