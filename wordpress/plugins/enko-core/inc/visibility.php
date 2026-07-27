<?php
/**
 * Видимість для пошуковиків + анти-енумерація користувачів.
 *
 * 1) wp-sitemap: прибирає провайдер users (світив логіни/слаги) і службові
 *    сторінки (кошик/чекаут/кабінети/пошук) зі списку pages.
 * 2) noindex,nofollow для службових сторінок (WC дублює для своїх — не шкодить).
 * 3) Енумерація користувачів: `?author=N` і архіви авторів → 301 на головну;
 *    REST `/wp/v2/users` — лише для залогінених.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Слаги службових сторінок: не для індексу й не для sitemap. */
function enko_service_page_slugs() {
	return array( 'cart', 'checkout', 'my-account', 'account', 'manager', 'poshuk' );
}

/** ID службових сторінок (за шляхом; кеш на час запиту). */
function enko_service_page_ids() {
	static $ids = null;
	if ( null !== $ids ) { return $ids; }
	$ids = array();
	foreach ( enko_service_page_slugs() as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p ) { $ids[] = (int) $p->ID; }
	}
	return $ids;
}

// 1а) Sitemap: без провайдера users — він розкривав логіни (енумерація).
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return ( 'users' === $name ) ? false : $provider;
}, 10, 2 );

// 1б) Sitemap: без службових сторінок.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$skip = enko_service_page_ids();
		if ( $skip ) {
			$not = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
			$args['post__not_in'] = array_merge( $not, $skip );
		}
	}
	return $args;
}, 10, 2 );

// 2) noindex,nofollow для службових сторінок.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_page( enko_service_page_slugs() ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

// 3а) Енумерація: `?author=N` і архіви авторів → 301 на головну.
// Пріоритет 1 — РАНІШЕ за redirect_canonical (пріоритет 10), інакше ядро
// встигає віддати 301 `?author=1` → `/author/<логін>/` і логін світиться
// в Location (перевірено на живому).
add_action( 'template_redirect', function () {
	if ( is_admin() ) { return; }
	$author_qs = isset( $_GET['author'] ) && '' !== $_GET['author']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $author_qs || is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );

// 3б) REST: список користувачів — лише залогіненим (кабінет менеджера має
// власні /mgr/* руті й цього не потребує).
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
