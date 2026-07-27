<?php
/**
 * SEO Фаза 1 — мета-опис, Open Graph / Twitter-картки (прев'ю посилань у
 * месенджерах і соцмережах), JSON-LD «Організація». Невидимо для відвідувача;
 * впливає на вигляд сайту в Google і на картку при поділі посилання в
 * Telegram / Viber / WhatsApp / соцмережах.
 *
 * Що НЕ дублюємо (вже роблять WordPress / WooCommerce):
 *  - <title> (theme title-tag + i18n RU-фільтр), rel=canonical (core rel_canonical),
 *    /wp-sitemap.xml, robots.txt, Product JSON-LD (WC_Structured_Data).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Бренд для прев'ю (og:site_name). Системну назву сайту WP не чіпаємо. */
function enko_seo_brand() { return 'ENKO'; }

/** Дефолтна картинка прев'ю (лого ENKO). */
function enko_seo_default_image() {
	return get_template_directory_uri() . '/assets/logo-enko.png';
}

/** Скоротити й почистити довільний текст для meta-опису. */
function enko_seo_clean( $text, $limit = 200 ) {
	$text = wp_strip_all_tags( (string) $text, true );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( $text );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $limit ) {
		$text = rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '…';
	}
	return $text;
}

/** Дефолтний опис сайту (UA/RU). */
function enko_seo_default_desc() {
	if ( function_exists( 'enko_t' ) ) {
		return enko_t(
			'ENKO — кондиціонери, теплові насоси, вентиляція та мікроклімат: продаж, монтаж і сервіс. Консультація, підбір обладнання та прорахунок вартості з монтажем.',
			'ENKO — кондиционеры, тепловые насосы, вентиляция и микроклимат: продажа, монтаж и сервис. Консультация, подбор оборудования и расчёт стоимости с монтажом.'
		);
	}
	return 'ENKO — кондиціонери, теплові насоси, вентиляція та мікроклімат: продаж, монтаж і сервіс.';
}

/** Зібрати SEO-контекст поточної сторінки. */
function enko_seo_context() {
	$ctx = array(
		'type'  => 'website',
		'title' => wp_get_document_title(),
		'desc'  => '',
		'url'   => home_url( '/' ),
		'image' => enko_seo_default_image(),
	);

	if ( is_front_page() || is_home() ) {
		$ctx['desc'] = enko_seo_default_desc();
		$ctx['url']  = home_url( '/' );
	} elseif ( function_exists( 'is_product' ) && is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$ctx['type'] = 'product';
			$short = ( function_exists( 'enko_is_ru' ) && enko_is_ru() && $p->get_meta( '_enko_short_ru' ) )
				? $p->get_meta( '_enko_short_ru' )
				: $p->get_short_description();
			$ctx['desc'] = enko_seo_clean( $short ? $short : $p->get_description() );
			$ctx['url']  = get_permalink( $p->get_id() );
			$imgid = $p->get_image_id();
			if ( $imgid ) {
				$src = wp_get_attachment_image_url( $imgid, 'large' );
				if ( $src ) { $ctx['image'] = $src; }
			}
		}
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$ctx['desc'] = function_exists( 'enko_t' )
			? enko_t(
				'Каталог кліматичного обладнання ENKO: кондиціонери та супутня техніка. Ціни у ₴ і €, фільтри за потужністю й площею, заявка з прорахунком монтажу.',
				'Каталог климатического оборудования ENKO: кондиционеры и сопутствующая техника. Цены в ₴ и €, фильтры по мощности и площади, заявка с расчётом монтажа.'
			)
			: enko_seo_default_desc();
		if ( function_exists( 'wc_get_page_permalink' ) ) { $ctx['url'] = wc_get_page_permalink( 'shop' ); }
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$ctx['desc'] = $term->description ? enko_seo_clean( $term->description ) : enko_seo_default_desc();
			$tl = get_term_link( $term );
			if ( ! is_wp_error( $tl ) ) { $ctx['url'] = $tl; }
		}
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post ) {
			$src = has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content;
			$d   = enko_seo_clean( $src );
			$ctx['desc'] = $d ? $d : enko_seo_default_desc();
			$ctx['url']  = get_permalink( $post );
		}
	} else {
		$ctx['desc'] = enko_seo_default_desc();
	}

	if ( '' === $ctx['desc'] ) { $ctx['desc'] = enko_seo_default_desc(); }
	return $ctx;
}

/** Вивід meta-опису + OG/Twitter у <head>. */
function enko_seo_head() {
	if ( is_admin() || is_feed() || is_404() ) { return; }
	$c      = enko_seo_context();
	$locale = ( function_exists( 'enko_is_ru' ) && enko_is_ru() ) ? 'ru_RU' : 'uk_UA';
	$brand  = enko_seo_brand();

	$tags   = array();
	$tags[] = '<meta name="description" content="' . esc_attr( $c['desc'] ) . '">';
	$tags[] = '<meta property="og:type" content="' . esc_attr( $c['type'] ) . '">';
	$tags[] = '<meta property="og:site_name" content="' . esc_attr( $brand ) . '">';
	$tags[] = '<meta property="og:locale" content="' . esc_attr( $locale ) . '">';
	$tags[] = '<meta property="og:title" content="' . esc_attr( $c['title'] ) . '">';
	$tags[] = '<meta property="og:description" content="' . esc_attr( $c['desc'] ) . '">';
	$tags[] = '<meta property="og:url" content="' . esc_url( $c['url'] ) . '">';
	$tags[] = '<meta property="og:image" content="' . esc_url( $c['image'] ) . '">';
	$tags[] = '<meta property="og:image:alt" content="' . esc_attr( $brand ) . '">';
	$tags[] = '<meta name="twitter:card" content="summary_large_image">';
	$tags[] = '<meta name="twitter:title" content="' . esc_attr( $c['title'] ) . '">';
	$tags[] = '<meta name="twitter:description" content="' . esc_attr( $c['desc'] ) . '">';
	$tags[] = '<meta name="twitter:image" content="' . esc_url( $c['image'] ) . '">';

	echo "\n<!-- ENKO SEO -->\n" . implode( "\n", $tags ) . "\n";

	// JSON-LD «Організація» — лише на головній.
	if ( is_front_page() || is_home() ) {
		$ct  = function_exists( 'enko_contacts' ) ? enko_contacts() : array();
		$org = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Organization',
			'name'          => 'ТОВ «ЕНКО ЮА»',
			'alternateName' => $brand,
			'url'           => home_url( '/' ),
			'logo'          => get_template_directory_uri() . '/assets/logo-enko-trans.png',
		);
		if ( ! empty( $ct['email'] ) ) { $org['email'] = $ct['email']; }
		if ( ! empty( $ct['phone'] ) ) { $org['telephone'] = $ct['phone']; }
		$same = array();
		foreach ( array( 'tg', 'viber', 'whatsapp' ) as $k ) {
			if ( empty( $ct[ $k ] ) ) { continue; }
			$u = $ct[ $k ];
			// sameAs мусить бути http(s)-URL — viber://chat?… та інші схеми пропускаємо.
			if ( 0 !== strpos( $u, 'http' ) ) { continue; }
			$same[] = preg_replace( '#^http://#', 'https://', $u );
		}
		if ( $same ) { $org['sameAs'] = array_values( array_unique( $same ) ); }
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'enko_seo_head', 5 );
