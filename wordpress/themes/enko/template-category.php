<?php
/**
 * Template Name: ENKO — Категорія каталогу
 *
 * Індивідуальна сторінка розділу каталогу (VRF / теплові насоси / вентиляція /
 * мікроклімат / фанкойли) — 1:1 порт прототипних cat-*.html.
 * Контент обирається за slug сторінки.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
if ( ! function_exists( 'enko_is_ru' ) ) { function enko_is_ru() { return false; } }
$td = get_template_directory_uri();

$cats = array(
	'vrf' => array(
		'title' => 'Мультизональні VRF',
		'lead'  => 'Системи для багатьох зон: одна зовнішня станція керує десятками внутрішніх блоків з індивідуальним налаштуванням — для офісів, готелів і торгових центрів.',
		'h2'    => 'Мультизональні VRF-системи',
		'img'   => 'catalog-vrf-desktop.webp', 'objpos' => 'center 30%',
		'points'=> array( 'Зовнішні блоки', 'Внутрішні блоки', 'Комплектуючі та керування' ),
	),
	'heat-pumps' => array(
		'title' => 'Теплові насоси',
		'lead'  => 'Опалення, охолодження та гаряча вода з одного джерела. Економія до 70% порівняно з електричним чи газовим котлом — для приватних будинків і комерції.',
		'h2'    => 'Теплові насоси для дому та бізнесу',
		'img'   => 'catalog-heat-pumps-desktop.webp', 'objpos' => 'center 50%',
		'points'=> array( 'Повітря-вода', 'Повітря-повітря', 'Для опалення та ГВП' ),
	),
	'ventilation' => array(
		'title' => 'Вентиляція',
		'lead'  => 'Свіже, очищене й підігріте повітря без втрат тепла. Припливно-витяжні установки та рекуперація для квартир, офісів і виробництва.',
		'h2'    => 'Вентиляція та рекуперація',
		'img'   => 'catalog-ventilation-desktop.webp', 'objpos' => 'center center',
		'points'=> array( 'Припливно-витяжна', 'Рекуперація', 'Канальне обладнання' ),
	),
	'microclimate' => array(
		'title' => 'Мікроклімат',
		'lead'  => 'Точний контроль вологості та чистоти повітря: зволожувачі, осушувачі й очисники для здорового мікроклімату вдома та в офісі.',
		'h2'    => 'Прилади мікроклімату',
		'img'   => 'catalog-microclimate-desktop.webp', 'objpos' => 'center center',
		'points'=> array( 'Зволожувачі', 'Осушувачі', 'Очисники повітря' ),
	),
	'fancoils' => array(
		'title' => 'Фанкойли',
		'lead'  => 'Внутрішні блоки для систем чилер-фанкойл: настінні, підлогові, касетні та канальні. Гнучке рішення для опалення й охолодження великих площ.',
		'h2'    => 'Фанкойли для систем чилер-фанкойл',
		'img'   => 'catalog-fancoils-desktop.webp', 'objpos' => 'center 45%',
		'points'=> array( 'Настінні та підлогові', 'Касетні та канальні', 'Для чилер-фанкойл' ),
	),
);

$slug = get_post_field( 'post_name', get_queried_object_id() );
$c    = $cats[ $slug ] ?? array(
	'title' => get_the_title(), 'lead' => '', 'h2' => get_the_title(),
	'img' => 'catalog-conditioners-desktop.webp', 'objpos' => 'center center', 'points' => array(),
);

get_header();
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>"><ol>
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li><li class="sep">/</li><li aria-current="page"><?php echo esc_html( et( $c['title'] ) ); ?></li>
  </ol></nav>

  <section class="about-hero" style="margin-top:10px">
    <div class="about-hero__in">
      <p class="eyebrow"><?php echo esc_html( et( 'Каталог' ) ); ?></p>
      <h1><?php echo esc_html( et( $c['title'] ) ); ?></h1>
      <p><?php echo esc_html( et( $c['lead'] ) ); ?></p>
    </div>
  </section>

  <section class="section" style="padding-top:48px">
    <div class="section-head"><p class="eyebrow"><?php echo esc_html( et( 'Категорія' ) ); ?></p><h2 style="font-size:clamp(24px,3vw,32px);font-weight:700"><?php echo esc_html( et( $c['h2'] ) ); ?></h2></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px" class="cat-photos">
      <div class="ph" style="aspect-ratio:4/3;border-radius:16px;position:relative;overflow:hidden"><img class="cat-photo-real" src="<?php echo esc_url( content_url( 'uploads/enko/' . $c['img'] ) ); ?>" alt="<?php echo esc_attr( et( $c['title'] ) ); ?>" style="object-position:<?php echo esc_attr( $c['objpos'] ); ?>" loading="lazy"></div>
    </div>
    <?php if ( $c['points'] ) : ?>
    <ul class="seo-points" style="margin-top:28px">
      <?php foreach ( $c['points'] as $pt ) : ?>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( $pt ) ); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="container" style="padding:0 0 88px">
    <div class="cta-strip">
      <div class="cta-strip__txt">
        <span class="lines-motif" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <h2><?php echo esc_html( et( 'Замовити консультацію' ) ); ?></h2>
        <p><?php echo esc_html( et( 'Залиште заявку — інженер ENKO підбере обладнання та підготує пропозицію саме під ваше приміщення.' ) ); ?></p>
      </div>
      <button class="btn btn--white" data-modal-open data-product="<?php echo esc_attr( et( $c['title'] ) ); ?>"><?php echo esc_html( et( 'Залишити заявку' ) ); ?></button>
    </div>
  </section>
</div>
<?php
get_footer();
