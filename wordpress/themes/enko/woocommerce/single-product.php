<?php
/**
 * ENKO картка товару (PDP) — 1:1 порт прототипу kaysun-casual.html.
 * Вибір версії, таблиця характеристик і ціни беруться з варіацій Woo через
 * window.ENKO_PDP (enko-core/inc/catalog-data.php → enko_build_pdp_data()).
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$td = get_template_directory_uri();
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
if ( ! function_exists( 'enko_is_ru' ) ) { function enko_is_ru() { return false; } }
get_header();

global $product;
if ( ! $product instanceof WC_Product ) { $product = wc_get_product( get_the_ID() ); }

$pdp      = function_exists( 'enko_build_pdp_data' ) ? enko_build_pdp_data( $product ) : array( 'name' => get_the_title(), 'versions' => array(), 'energy' => 'A++/A+', 'skuBase' => '' );
$versions = $pdp['versions'];
$vkeys    = array_keys( $versions );
// Дефолтна версія = поточний товар-версія (а не перша в списку) — щоб ціна/спеки збігались із товаром.
$vk0      = ( isset( $pdp['current'] ) && isset( $versions[ $pdp['current'] ] ) ) ? $pdp['current'] : ( $vkeys[0] ?? '' );
$v0       = $versions[ $vk0 ] ?? array( 'model' => '', 'cool' => '', 'heat' => '', 'area' => '', 'noise' => '', 'breaker' => '', 'uah' => '', 'eur' => '' );
$title    = ( isset( $pdp['name'] ) && '' !== $pdp['name'] ) ? $pdp['name'] : $product->get_name();
$pname    = ( function_exists( 'enko_is_ru' ) && enko_is_ru() && $product->get_meta( '_enko_name_ru' ) ) ? $product->get_meta( '_enko_name_ru' ) : $product->get_name();

$is_kaysun = stripos( $product->get_name(), 'kaysun' ) !== false;
$series_map = array( 'casual' => 'Casual (AKAY-C)', 'prodigy' => 'Prodigy PRO (AKAY-P)' );
$series_lbl = $series_map[ $product->get_meta( '_enko_series' ) ] ?? '';

/* Галерея: зображення товару, або фолбек на прототипні фото Kaysun / тип. */
$gallery = array();
$main_id = $product->get_image_id();
$ids = array();
if ( $main_id ) { $ids[] = $main_id; }
$ids = array_merge( $ids, $product->get_gallery_image_ids() );
foreach ( $ids as $iid ) {
	$src = wp_get_attachment_image_url( $iid, 'large' );
	if ( $src ) { $gallery[] = array( $src, esc_html( $product->get_name() ) ); }
}
if ( ! $gallery ) {
	if ( $is_kaysun ) {
		$gallery = array(
			array( content_url( 'uploads/enko/products-kaysun-casual-indoor.webp' ), enko_t( 'Фото: внутрішній блок', 'Фото: внутренний блок' ) ),
			array( content_url( 'uploads/enko/products-kaysun-casual-outdoor.webp' ), enko_t( 'Фото: зовнішній блок', 'Фото: внешний блок' ) ),
			array( content_url( 'uploads/enko/products-kaysun-casual-remote.webp' ), enko_t( 'Фото: пульт / WiFi-керування', 'Фото: пульт / WiFi-управление' ) ),
			array( content_url( 'uploads/enko/products-kaysun-casual-room.webp' ), enko_t( 'Фото: приклад монтажу', 'Фото: пример монтажа' ) ),
		);
	} else {
		$type = $product->get_meta( '_enko_type' ) ?: 'wall';
		$gallery = array( array( content_url( 'uploads/enko/types-' . $type . '.webp' ), esc_html( $product->get_name() ) ) );
	}
}
$g0 = $gallery[0];

/* Кнопка «Додати в заявку»: багатоверсійний товар (варіативний АБО група простих
   товарів-версій) → __current__ (enko.js бере дані обраної версії з ENKO_PDP);
   одиночний простий товар → прямі дані (id/ціна/спеки). */
$is_var = $product->is_type( 'variable' );
$multi  = count( $versions ) > 1;
$add_class = $multi ? '' : ' pdp-add-simple'; // pdp-add-simple → inline-лічильник одиночного товару
if ( $multi ) {
	$add_attrs = 'data-product="__current__"';
} else {
	$_sku  = $product->get_sku() ?: ( 'P' . $product->get_id() );
	$_spec = $v0['cool'] . ' кВт · R-32 · ' . $pdp['energy'];
	$add_attrs = 'data-id="' . esc_attr( $_sku ) . '" data-name="' . esc_attr( $pname ) . '" data-spec="' . esc_attr( $_spec ) . '" data-uah="' . esc_attr( $v0['uah'] ) . '" data-eur="' . esc_attr( $v0['eur'] ) . '" data-img="товар" data-photo="' . esc_url( $g0[0] ) . '"';
}
?>

<script>window.ENKO_PDP = <?php echo wp_json_encode( $pdp ); ?>;</script>
<?php if ( $multi && '' !== $vk0 ) : ?>
<script>
/* Багатоверсійний PDP: якщо в URL нема ?ver — підставити версію цього товару, щоб enko.js
   одразу показав ціну/спеки саме цієї версії (а не першої в списку). Виконується ДО enko.js. */
(function () {
  try {
    var u = new URL(window.location.href);
    if (!u.searchParams.get('ver')) { u.searchParams.set('ver', <?php echo wp_json_encode( $vk0 ); ?>); history.replaceState(null, '', u); }
  } catch (e) {}
})();
</script>
<?php endif; ?>

<div class="container">
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>"><ol>
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li><li class="sep">/</li>
    <li><a href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>"><?php echo esc_html( et( 'Кондиціонери' ) ); ?></a></li><li class="sep">/</li>
    <li aria-current="page"><?php echo esc_html( $title ); ?></li>
  </ol></nav>

  <section class="pdp-main">
    <div class="gallery">
      <div class="gallery__main" role="button" tabindex="0" aria-label="<?php echo esc_attr( et( 'Збільшити фото' ) ); ?>">
        <div class="ph"><img id="gallery-main-img" src="<?php echo esc_url( $g0[0] ); ?>" alt="<?php echo esc_attr( $pname ); ?>"></div>
        <div class="gallery__zoom-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg><?php echo esc_html( et( 'Натисніть для збільшення' ) ); ?></div>
      </div>
      <div class="gallery__thumbs">
        <?php foreach ( $gallery as $i => $g ) : ?>
        <div class="thumb<?php echo 0 === $i ? ' active' : ''; ?>" data-img="<?php echo esc_url( $g[0] ); ?>" data-label="<?php echo esc_attr( $g[1] ); ?>"><div class="ph"><img src="<?php echo esc_url( $g[0] ); ?>" alt="<?php echo esc_attr( $g[1] ); ?>"></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="buybox">
      <h1><?php echo esc_html( $title ); ?></h1>
      <?php if ( $series_lbl ) : ?>
      <div class="pdp-title-meta"><span class="pdp-series"><?php echo esc_html( et( 'Серія:' ) ); ?> <b><?php echo esc_html( $series_lbl ); ?></b></span></div>
      <?php endif; ?>

      <div class="buybox__badges">
        <span class="spec-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h4l3-9 6 18 3-9h4"/></svg>Inverter</span>
        <span class="spec-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14 0M8.5 16a6 6 0 0 1 7 0M12 20h.01"/></svg>WiFi</span>
        <span class="spec-badge">−15&nbsp;°C</span>
        <span class="spec-badge"><?php echo esc_html( $pdp['energy'] ); ?></span>
        <span class="spec-badge">R-32</span>
        <span class="spec-badge">Golden Fin™</span>
      </div>

      <?php if ( count( $versions ) > 1 ) : ?>
      <div class="versions">
        <div class="versions__label"><span><?php echo esc_html( et( 'Версія / потужність' ) ); ?></span><b id="meta-model"><?php echo esc_html( $v0['model'] ); ?></b></div>
        <div class="ver-grid" role="group" aria-label="<?php echo esc_attr( et( 'Оберіть версію' ) ); ?>">
          <?php foreach ( $versions as $vk => $vd ) : ?>
          <button class="ver-btn<?php echo (string) $vk === (string) $vk0 ? ' active' : ''; ?>" data-ver="<?php echo esc_attr( $vk ); ?>" type="button"><b><?php echo esc_html( $vk ); ?></b><span><?php echo esc_html( $vd['cool'] ); ?> кВт</span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="buybox__price">
        <div class="buybox__stock">
          <span class="stock-ind stock--in"><i></i><?php echo esc_html( et( 'В наявності' ) ); ?></span>
          <span class="warranty-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg><?php echo esc_html( et( 'Гарантія 3 роки' ) ); ?></span>
        </div>
        <div class="price price--lg">
          <span class="price__label"><?php echo esc_html( et( 'Орієнтовна ціна' ) ); ?></span>
          <span class="price__row"><span class="price__main price__uah" id="price-uah"><?php echo esc_html( $v0['uah'] ); ?> грн</span><span class="price__eur" id="price-eur"><?php echo esc_html( $v0['eur'] ); ?> €</span></span>
        </div>
      </div>

      <p class="buybox__desc"><?php $short = ( enko_is_ru() && $product->get_meta( '_enko_short_ru' ) ) ? $product->get_meta( '_enko_short_ru' ) : $product->get_short_description(); echo esc_html( $short ? wp_strip_all_tags( $short ) : enko_t( 'Інверторний спліт-кондиціонер: тиха й економна робота, WiFi-керування та робота зовнішнього блока до −15 °C.', 'Инверторный сплит-кондиционер: тихая и экономная работа, WiFi-управление и работа наружного блока до −15 °C.' ) ); ?></p>

      <div class="buybox__actions" id="buybox-actions">
        <button class="btn btn--primary<?php echo $add_class; ?>" data-add-request <?php echo $add_attrs; ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg><span class="btn-label"><?php echo esc_html( et( 'Додати в заявку' ) ); ?></span></button>
        <button class="btn btn--ghost" data-modal-open data-product="<?php echo esc_attr( $pname ); ?>"><?php echo esc_html( et( 'Запитати спеціаліста' ) ); ?></button>
      </div>

      <ul class="minitrust">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Офіційний дилер' ) ); ?></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Гарантія' ) ); ?></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Доставка по Україні' ) ); ?></li>
      </ul>

      <div class="buybox__meta">
        <span>SKU: <b><?php echo esc_html( $product->get_sku() ?: ( 'P' . $product->get_id() ) ); ?></b></span>
        <span><?php echo esc_html( et( 'Категорія:' ) ); ?> <b><?php echo esc_html( et( 'Кондиціонери' ) ); ?></b></span>
      </div>
    </div>
  </section>

  <!-- SPEC TILES -->
  <section class="section--tight">
    <div class="spec-tiles">
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M9.5 2 7 11h4l-2 11 9-13h-5l3-7z"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Потужність охолодження' ) ); ?></div><div class="spec-tile__v"><span id="sp-cool"><?php echo esc_html( $v0['cool'] ); ?></span> <small>кВт</small></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M12 2s4 4 4 8a4 4 0 0 1-8 0c0-4 4-8 4-8z"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Потужність обігріву' ) ); ?></div><div class="spec-tile__v"><span id="sp-heat"><?php echo esc_html( $v0['heat'] ); ?></span> <small>кВт</small></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Площа приміщення' ) ); ?></div><div class="spec-tile__v"><span id="sp-area"><?php echo esc_html( $v0['area'] ); ?></span> <small>м²</small></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Рівень шуму' ) ); ?></div><div class="spec-tile__v"><span id="sp-noise"><?php echo esc_html( $v0['noise'] ); ?></span> <small>dB(A)</small></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M12 22a7 7 0 0 0 7-7c0-5-7-13-7-13S5 10 5 15a7 7 0 0 0 7 7z"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Холодоагент' ) ); ?></div><div class="spec-tile__v">R-32</div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Енергоклас' ) ); ?></div><div class="spec-tile__v" style="font-size:20px"><?php echo esc_html( $pdp['energy'] ); ?></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M2 14h20M12 14V6"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Живлення / автомат' ) ); ?></div><div class="spec-tile__v" style="font-size:18px">1ф/230В · <span id="sp-breaker"><?php echo esc_html( $v0['breaker'] ); ?></span></div></div>
      <div class="spec-tile"><span class="spec-tile__ic"><svg viewBox="0 0 24 24"><path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0z"/></svg></span><div class="spec-tile__k"><?php echo esc_html( et( 'Мін. температура' ) ); ?></div><div class="spec-tile__v">−15 <small>°C</small></div></div>
    </div>
  </section>

  <!-- TABS -->
  <section class="section--tight tabs">
    <div class="tabs__nav" role="tablist">
      <button class="tab-btn active" data-tab="desc" role="tab" aria-selected="true"><?php echo esc_html( et( 'Опис' ) ); ?></button>
      <button class="tab-btn" data-tab="specs" role="tab" aria-selected="false"><?php echo esc_html( et( 'Характеристики' ) ); ?></button>
      <button class="tab-btn" data-tab="docs" role="tab" aria-selected="false"><?php echo esc_html( et( 'Документація' ) ); ?></button>
    </div>

    <div class="tab-panel active" data-panel="desc" role="tabpanel">
      <?php $desc = ( enko_is_ru() && $product->get_meta( '_enko_long_ru' ) ) ? $product->get_meta( '_enko_long_ru' ) : $product->get_description(); ?>
      <?php if ( $desc ) : ?>
        <?php echo wp_kses_post( wpautop( $desc ) ); ?>
      <?php else : ?>
        <h3>Елегантний клімат-комфорт без компромісів</h3>
        <p>Серія створена для тих, хто цінує тишу, енергоефективність і сучасний дизайн. Інверторний компресор плавно підтримує задану температуру, а WiFi-модуль дозволяє керувати кондиціонером зі смартфона. Зовнішній блок упевнено працює на обігрів навіть за −15 °C — придатно для українського клімату цілий рік.</p>
        <h3 style="margin-top:32px">Ключові переваги</h3>
        <ul class="feature-list">
          <li><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span><span><b>WiFi-керування</b> зі смартфона; сумісність з Alexa та Google Home.</span></li>
          <li><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span><span><b>Golden Fin™</b> — антикорозійне покриття теплообмінника.</span></li>
          <li><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span><span><b>Робота до −15 °C</b> та інверторний компресор — тихо й економно.</span></li>
          <li><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span><span><b>Холодоагент R-32</b> — екологічніший та ефективніший за R-410A.</span></li>
        </ul>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="specs" role="tabpanel">
      <h3><?php echo esc_html( et( 'Технічні характеристики за версіями' ) ); ?></h3>
      <?php if ( $versions ) : ?>
      <div class="table-wrap">
        <table class="spec-table">
          <thead><tr><th><?php echo esc_html( et( 'Параметр' ) ); ?></th>
            <?php foreach ( $vkeys as $i => $vk ) : ?><th data-col="<?php echo esc_attr( $vk ); ?>"<?php echo 0 === $i ? ' class="col-active"' : ''; ?>><?php echo esc_html( $vk ); ?></th><?php endforeach; ?>
          </tr></thead>
          <tbody>
            <?php
            $rows = array(
              'Модель'                        => 'model',
              'Потужність охолодження, кВт'    => 'cool',
              'Потужність обігріву, кВт'       => 'heat',
              'Рівень шуму, dB(A)'             => 'noise',
              'Площа приміщення, м²'           => 'area',
              'Автомат. вимикач'               => 'breaker',
              'Ціна (орієнт.)'                 => 'price',
            );
            foreach ( $rows as $label => $key ) :
              $is_price = ( 'price' === $key );
              ?>
              <tr<?php echo $is_price ? ' class="price-row"' : ''; ?>><th><?php echo esc_html( et( $label ) ); ?></th>
                <?php foreach ( $vkeys as $i => $vk ) :
                  $cell = $is_price ? ( '≈' . $versions[ $vk ]['uah'] . ' грн · ' . $versions[ $vk ]['eur'] . ' €' ) : $versions[ $vk ][ $key ];
                  ?><td data-col="<?php echo esc_attr( $vk ); ?>"<?php echo 0 === $i ? ' class="col-active"' : ''; ?>><?php echo esc_html( $cell ); ?></td><?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            <tr><th><?php echo esc_html( et( 'Холодоагент' ) ); ?></th><?php foreach ( $vkeys as $i => $vk ) : ?><td data-col="<?php echo esc_attr( $vk ); ?>"<?php echo 0 === $i ? ' class="col-active"' : ''; ?>>R-32</td><?php endforeach; ?></tr>
            <tr><th><?php echo esc_html( et( 'Гарантія' ) ); ?></th><?php foreach ( $vkeys as $i => $vk ) : ?><td data-col="<?php echo esc_attr( $vk ); ?>"<?php echo 0 === $i ? ' class="col-active"' : ''; ?>><?php echo esc_html( et( '3 роки' ) ); ?></td><?php endforeach; ?></tr>
          </tbody>
        </table>
      </div>
      <?php else : ?>
      <p><?php echo esc_html( et( 'Характеристики уточнюйте в менеджера — залиште заявку.' ) ); ?></p>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="docs" role="tabpanel">
      <h3><?php echo esc_html( et( 'Документація та інструкції' ) ); ?></h3>
      <?php $docs = function_exists( 'enko_product_docs' ) ? enko_product_docs( $product ) : array(); ?>
      <div class="doc-list">
        <?php if ( $docs ) : foreach ( $docs as $d ) : ?>
        <div class="doc-item" role="button" tabindex="0" data-doc="<?php echo esc_url( $d['url'] ); ?>" data-title="<?php echo esc_attr( $d['title'] ); ?>">
          <span class="doc-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
          <span class="doc-item__txt"><b><?php echo esc_html( $d['title'] ); ?></b><span>PDF<?php echo $d['size_h'] ? ' · ' . esc_html( $d['size_h'] ) : ''; ?> · <i class="doc-ok"><?php echo esc_html( et( 'в наявності' ) ); ?></i></span></span>
          <button type="button" class="doc-dl" data-dl aria-label="<?php echo esc_attr( et( 'Завантажити' ) ); ?> <?php echo esc_attr( $d['title'] ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg></button>
        </div>
        <?php endforeach; else : ?>
        <div class="doc-item doc-item--off" aria-disabled="true" title="<?php echo esc_attr( et( 'Документ скоро буде додано' ) ); ?>">
          <span class="doc-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
          <span class="doc-item__txt"><b><?php echo esc_html( et( 'Документи готуються' ) ); ?></b><span><?php echo esc_html( et( 'Очікується' ) ); ?></span></span>
          <span class="doc-dl doc-dl--off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- TRUST -->
  <section class="section--tight">
    <div class="trust-band">
      <div class="section-head section-head--center" style="margin-bottom:32px"><h2 style="font-size:26px"><?php echo esc_html( et( 'При замовленні ви отримуєте' ) ); ?></h2></div>
      <div class="trust-grid">
        <div class="trust-it"><span class="ti-ic"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/></svg></span><h3><?php echo esc_html( et( 'Офіційна гарантія' ) ); ?></h3><p><?php echo esc_html( et( 'Гарантія виробника на все обладнання' ) ); ?></p></div>
        <div class="trust-it"><span class="ti-ic"><svg viewBox="0 0 24 24"><rect x="1" y="6" width="14" height="11" rx="1"/><path d="M15 9h4l3 3v5h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span><h3><?php echo esc_html( et( 'Безкоштовна доставка' ) ); ?></h3><p><?php echo esc_html( et( 'Доставка по всій Україні у ваше місто' ) ); ?></p></div>
        <div class="trust-it"><span class="ti-ic"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><h3><?php echo esc_html( et( 'Консультація і підбір' ) ); ?></h3><p><?php echo esc_html( et( 'Безкоштовно підберемо під ваше приміщення' ) ); ?></p></div>
        <div class="trust-it"><span class="ti-ic"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.66 5.66l1.41 1.41M9.3 17.7a4 4 0 0 0 5.66-5.66"/><circle cx="12" cy="12" r="9"/></svg></span><h3><?php echo esc_html( et( 'Сервісна підтримка' ) ); ?></h3><p><?php echo esc_html( et( 'Обслуговування протягом терміну служби' ) ); ?></p></div>
      </div>
    </div>
  </section>

  <!-- SIMILAR -->
  <section class="section">
    <div class="section-head section-head--row">
      <div><p class="eyebrow"><?php echo esc_html( et( 'Схожі товари' ) ); ?></p><h2><?php echo esc_html( et( 'Вам також може підійти' ) ); ?></h2></div>
      <a href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>" class="btn btn--ghost btn--m"><?php echo esc_html( et( 'Всі кондиціонери' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>
    <div class="prod-grid" id="similar-grid"></div>
  </section>

</div><!-- /.container -->

<!-- LIGHTBOX -->
<div class="modal" id="lightbox" role="dialog" aria-modal="true" aria-label="Перегляд фото">
  <div class="modal__overlay" data-lightbox-close></div>
  <div class="modal__panel" style="width:min(800px,100%);padding:0;overflow:hidden;background:#fff">
    <button class="modal__close" data-lightbox-close aria-label="Закрити" style="background:rgba(255,255,255,.9)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <div class="ph" style="aspect-ratio:1/1"><img data-lightbox-img src="<?php echo esc_url( $g0[0] ); ?>" alt="<?php echo esc_attr( $pname ); ?>" style="width:100%;height:100%;object-fit:contain;display:block"></div>
  </div>
</div>
<script>
/* Оновлювати ?ver у адресі при перемиканні версії (узгоджено з deep-link із каталогу). */
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.ver-btn');
    if (!b) return;
    var ver = b.getAttribute('data-ver');
    if (!ver) return;
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('ver', ver);
      history.replaceState(null, '', u);
    } catch (err) {}
  });
})();
</script>

<?php if ( ! $multi ) : ?>
<script>
/* PDP одиночного простого товару: лічильник «Додано в заявку N» на кнопці (без версійного механізму). */
(function () {
  var SKU = <?php echo wp_json_encode( $_sku ); ?>;
  var BAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
  function qty() { try { var c = JSON.parse(localStorage.getItem('enko_cart_v1') || '[]'); var it = c.filter(function (x) { return x.id === SKU; })[0]; return it ? it.qty : 0; } catch (e) { return 0; } }
  function upd() {
    var q = qty();
    Array.prototype.forEach.call(document.querySelectorAll('.pdp-add-simple'), function (b) {
      var lab = b.querySelector('.btn-label'); if (lab) lab.textContent = q > 0 ? 'Додано в заявку' : 'Додати в заявку';
      var c = b.querySelector('.btn-incart');
      if (q > 0) {
        if (!c) { c = document.createElement('span'); c.className = 'btn-incart'; c.setAttribute('aria-hidden', 'true'); c.innerHTML = BAG + '<span class="btn-incart__n"></span>'; b.appendChild(c); }
        c.querySelector('.btn-incart__n').textContent = q; c.hidden = false;
      } else if (c) { c.hidden = true; }
    });
  }
  upd();
  document.addEventListener('click', function (e) { if (e.target.closest('.pdp-add-simple')) setTimeout(upd, 60); });
  window.addEventListener('storage', function (e) { if (e.key === 'enko_cart_v1') upd(); });
  window.addEventListener('enko:cart', upd);
})();
</script>
<?php endif; ?>

<?php
get_footer();
