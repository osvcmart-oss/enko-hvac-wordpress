<?php
/**
 * Кошик «заявка» на базі WooCommerce.
 * «Додати в заявку» (data-add-request) додає товар у кошик Woo через REST,
 * резолвлячи штучний id каталогу (PARENT-SKU-ВЕРСІЯ) у реальну варіацію.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Резолв id каталогу → {product_id, variation_id, variation[]}. */
function enko_resolve_cart_item( $id ) {
	$id = trim( (string) $id );
	if ( '' === $id ) { return null; }

	// Простий товар — id збігається зі SKU.
	$pid = wc_get_product_id_by_sku( $id );
	if ( $pid ) {
		return array( 'product_id' => $pid, 'variation_id' => 0, 'variation' => array() );
	}

	// Варіація — id = "<PARENT-SKU>-<ВЕРСІЯ>".
	if ( preg_match( '/^(.*)-([0-9]+)$/', $id, $m ) ) {
		$parent_sku = $m[1];
		$ver        = $m[2];
		$ppid       = wc_get_product_id_by_sku( $parent_sku );
		if ( $ppid ) {
			$parent = wc_get_product( $ppid );
			if ( $parent && $parent->is_type( 'variable' ) ) {
				foreach ( $parent->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( ! $v ) { continue; }
					foreach ( $v->get_attributes() as $k => $val ) {
						if ( strpos( $k, 'versiya' ) !== false && (string) $val === (string) $ver ) {
							return array(
								'product_id'   => $ppid,
								'variation_id' => $vid,
								'variation'    => array( 'attribute_' . $k => $val ),
							);
						}
					}
				}
			}
		}
	}
	return null;
}

/** Зберегти кошик у сесію Woo + форсувати session-cookie (потрібно в REST-контексті). */
function enko_persist_cart_session() {
	if ( ! function_exists( 'WC' ) || is_null( WC()->session ) ) { return 'no-session'; }
	WC()->session->set_customer_session_cookie( true );
	if ( WC()->cart ) {
		// Явно кладемо позиції в сесію (у Woo 10.x set_session() не завжди це робить у REST).
		WC()->session->set( 'cart', WC()->cart->get_cart_for_session() );
		WC()->cart->set_session();
	}
	WC()->session->save_data();
}

/** Поточна кількість позицій у кошику Woo (безпечно). */
function enko_cart_count() {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		return (int) WC()->cart->get_cart_contents_count();
	}
	return 0;
}

/**
 * Створити замовлення WooCommerce зі списку позицій (гібрид: кошик у браузері).
 *
 * @param array $items  позиції з localStorage: {id,name,ver,qty,uah,eur}.
 * @param array $c      контакти: name,phone,email,note.
 * @return int|WP_Error order id.
 */
function enko_create_order_from_items( $items, $c ) {
	if ( empty( $items ) || ! is_array( $items ) ) { return new WP_Error( 'empty', 'Порожній кошик' ); }
	$order = wc_create_order();
	foreach ( $items as $it ) {
		$id  = sanitize_text_field( (string) ( $it['id'] ?? '' ) );
		$qty = max( 1, (int) ( $it['qty'] ?? 1 ) );
		$r   = enko_resolve_cart_item( $id );
		if ( $r ) {
			$product = wc_get_product( $r['variation_id'] ? $r['variation_id'] : $r['product_id'] );
			if ( $product ) { $order->add_product( $product, $qty ); continue; }
		}
		// Фолбек: не знайдено за SKU. НІКОЛИ не довіряємо ціні з браузера (аудит L4).
		// 1) пробуємо знайти товар у БД за точною назвою → ціна з БД (з фільтром знижки);
		// 2) якщо взагалі нема — фіксуємо запит без ціни (менеджер прорахує вручну).
		$nm    = sanitize_text_field( (string) ( $it['name'] ?? 'Товар' ) );
		$found = $nm ? get_posts( array( 'post_type' => 'product', 'title' => $nm, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) ) : array();
		$byname = ! empty( $found ) ? wc_get_product( $found[0] ) : null;
		if ( $byname && $byname->is_type( array( 'simple', 'variation' ) ) ) {
			$order->add_product( $byname, $qty );
			continue;
		}
		$line = new WC_Order_Item_Product();
		$line->set_name( $nm );
		$line->set_quantity( $qty );
		$line->set_subtotal( 0 );
		$line->set_total( 0 );
		$order->add_item( $line );
	}
	$parts = preg_split( '/\s+/', (string) ( $c['name'] ?? '' ), 2 );
	$order->set_billing_first_name( $parts[0] ?? '' );
	$order->set_billing_last_name( $parts[1] ?? '' );
	$order->set_billing_phone( (string) ( $c['phone'] ?? '' ) );
	if ( ! empty( $c['email'] ) ) { $order->set_billing_email( $c['email'] ); }
	if ( ! empty( $c['note'] ) ) { $order->set_customer_note( $c['note'] ); }
	$__uid = function_exists( 'enko_current_uid' ) ? enko_current_uid() : get_current_user_id(); if ( $__uid ) { $order->set_customer_id( $__uid ); }
	$order->calculate_totals();
	$order->update_status( 'on-hold', 'Заявка з сайту. ' );
	$order->save();
	do_action( 'woocommerce_checkout_order_processed', $order->get_id(), array(), $order );
	return $order->get_id();
}

/** Сторінка кошика → прототипний шаблон (кошик у браузері через enko.js). */
add_filter( 'template_include', function ( $template ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$tpl = get_theme_root() . '/enko/enko-cart-page.php';
		if ( file_exists( $tpl ) ) { return $tpl; }
	}
	return $template;
}, 99 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'enko/v1', '/cart-add', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$id  = sanitize_text_field( (string) $req->get_param( 'id' ) );
			$qty = max( 1, (int) $req->get_param( 'qty' ) );

			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) { wc_load_cart(); }
			if ( is_null( WC()->cart ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'no-cart' ), 500 );
			}
			// Розбудити кошик: у REST WC_Cart lazy-load — форсуємо відновлення з сесії перед додаванням.
			WC()->cart->get_cart();

			$r = enko_resolve_cart_item( $id );
			if ( ! $r ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'not-found', 'id' => $id ), 404 );
			}

			$added = WC()->cart->add_to_cart( $r['product_id'], $qty, $r['variation_id'], $r['variation'] );
			WC()->cart->calculate_totals();
			enko_persist_cart_session();

			// Кількість саме цього товару в кошику (для каунтера на кнопці).
			$item_qty = 0;
			foreach ( WC()->cart->get_cart() as $ci ) {
				if ( (int) $ci['product_id'] === (int) $r['product_id'] && (int) $ci['variation_id'] === (int) $r['variation_id'] ) {
					$item_qty += (int) $ci['quantity'];
				}
			}

			return new WP_REST_Response( array(
				'ok'       => (bool) $added,
				'count'    => (int) WC()->cart->get_cart_contents_count(),
				'total'    => wp_strip_all_tags( WC()->cart->get_cart_total() ),
				'item_qty' => $item_qty,
			), 200 );
		},
	) );

	// Зміна кількості / видалення позиції.
	register_rest_route( 'enko/v1', '/cart-update', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$key = sanitize_text_field( (string) $req->get_param( 'key' ) );
			$qty = (int) $req->get_param( 'qty' );
			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) { wc_load_cart(); }
			if ( is_null( WC()->cart ) ) { return new WP_REST_Response( array( 'ok' => false ), 500 ); }
			WC()->cart->get_cart();
			if ( $qty <= 0 ) { WC()->cart->remove_cart_item( $key ); }
			else { WC()->cart->set_quantity( $key, $qty, true ); }
			WC()->cart->calculate_totals();
			enko_persist_cart_session();
			return new WP_REST_Response( array( 'ok' => true, 'count' => (int) WC()->cart->get_cart_contents_count() ), 200 );
		},
	) );

	// Оформлення заявки → замовлення WooCommerce (без оплати) + сповіщення.
	register_rest_route( 'enko/v1', '/checkout', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( function_exists( 'enko_form_is_bot' ) && enko_form_is_bot( $req ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 ); // honeypot — тихо, без замовлення
			}
			if ( function_exists( 'enko_form_rate_ok' ) && ! enko_form_rate_ok( 'checkout', 5 ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
			}
			$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
			$phone = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$note  = sanitize_textarea_field( (string) $req->get_param( 'question' ) );

			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) { wc_load_cart(); }
			if ( is_null( WC()->cart ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'no-cart' ), 500 ); }
			WC()->cart->get_cart();
			if ( WC()->cart->is_empty() ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'empty' ), 400 ); }
			if ( ! $phone && ! $name ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'no-contact' ), 400 ); }

			$order = wc_create_order();
			foreach ( WC()->cart->get_cart() as $item ) { $order->add_product( $item['data'], $item['quantity'] ); }
			$parts = preg_split( '/\s+/', $name, 2 );
			$order->set_billing_first_name( $parts[0] ?? '' );
			$order->set_billing_last_name( $parts[1] ?? '' );
			$order->set_billing_phone( $phone );
			if ( $email ) { $order->set_billing_email( $email ); }
			if ( $note ) { $order->set_customer_note( $note ); }
			$__uid = function_exists( 'enko_current_uid' ) ? enko_current_uid() : get_current_user_id(); if ( $__uid ) { $order->set_customer_id( $__uid ); }
			$order->calculate_totals();
			$order->update_status( 'on-hold', 'Заявка з сайту. ' );
			$order->save();

			// Переюзаємо сповіщення менеджеру (email + Telegram) з request-flow.
			do_action( 'woocommerce_checkout_order_processed', $order->get_id(), array(), $order );

			WC()->cart->empty_cart();
			enko_persist_cart_session();
			return new WP_REST_Response( array( 'ok' => true, 'order' => $order->get_id() ), 200 );
		},
	) );

	// Заявка з браузерного кошика (localStorage) → замовлення Woo + сповіщення.
	register_rest_route( 'enko/v1', '/checkout-items', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( function_exists( 'enko_form_is_bot' ) && enko_form_is_bot( $req ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 ); // honeypot — тихо, без замовлення
			}
			if ( function_exists( 'enko_form_rate_ok' ) && ! enko_form_rate_ok( 'checkout', 5 ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
			}
			$items = $req->get_param( 'items' );
			$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );
			$phone = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			$note  = sanitize_textarea_field( (string) $req->get_param( 'question' ) );
			if ( ! $phone && ! $name ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'no-contact' ), 400 ); }
			$oid = enko_create_order_from_items( $items, array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'note' => $note ) );
			if ( is_wp_error( $oid ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => $oid->get_error_code() ), 400 ); }
			return new WP_REST_Response( array( 'ok' => true, 'order' => $oid ), 200 );
		},
	) );

	// Поточний стан кошика (для синхронізації лічильника).
	register_rest_route( 'enko/v1', '/cart-count', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) { wc_load_cart(); }
			return new WP_REST_Response( array( 'count' => enko_cart_count() ), 200 );
		},
	) );
} );
