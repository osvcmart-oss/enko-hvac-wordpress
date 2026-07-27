<?php
/**
 * ENKO catalog (shop / product archive) — 1:1 port of prototype catalog.html.
 * The product grid (#catalog-grid) + filters are driven by the prototype
 * enko.js engine, fed real WooCommerce data via window.ENKO_PRODUCTS
 * (see enko-core/inc/catalog-data.php).
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
if ( ! function_exists( 'enko_is_ru' ) ) { function enko_is_ru() { return false; } }
$td = get_template_directory_uri();
get_header();
?>
<div class="container">

  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>">
    <ol>
      <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li>
      <li class="sep">/</li>
      <li aria-current="page"><?php echo esc_html( et( 'Кондиціонери' ) ); ?></li>
    </ol>
  </nav>

  <div class="catalog-head">
    <h1><?php echo esc_html( et( 'Кондиціонери' ) ); ?></h1>
    <p><?php echo esc_html( et( 'Спліт- та мульти-системи від офіційного дилера. Не знаєте, що обрати — увімкніть фільтри або залиште заявку, підберемо під ваше приміщення.' ) ); ?></p>
  </div>

  <div class="type-quick" id="type-quick" role="group" aria-label="<?php echo esc_attr( et( 'Тип кондиціонера' ) ); ?>">
    <button type="button" class="type-quick__btn active" data-type="all"><span class="tq-ic tq-ic--all"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="tq-label"><?php echo esc_html( et( 'Усі типи' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="wall"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-wall.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Настінні' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="console"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-console.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Консольні' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="duct"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-duct.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Канальні' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="cassette"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-cassette.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Касетні' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="floorceil"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-floorceil.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Підлогово-стельові' ) ); ?></span></button>
    <button type="button" class="type-quick__btn" data-type="multi"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-multi-all-sq.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Мульти-спліт' ) ); ?></span></button>
  </div>
  <div class="type-sub" id="type-sub" hidden role="group" aria-label="<?php echo esc_attr( et( 'Блоки мульти-спліт' ) ); ?>">
    <span class="type-sub__lead"><?php echo esc_html( et( 'Мульти-спліт:' ) ); ?></span>
    <button type="button" class="type-sub__btn active" data-block="all"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-multi-all.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Усі блоки' ) ); ?></span></button>
    <button type="button" class="type-sub__btn" data-block="outdoor"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-multi-outdoor.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Зовнішні блоки' ) ); ?></span></button>
    <button type="button" class="type-sub__btn" data-block="indoor"><span class="tq-ic"><img src="<?php echo esc_url( content_url( 'uploads/enko/types-multi-indoor.webp' ) ); ?>" alt=""></span><span class="tq-label"><?php echo esc_html( et( 'Внутрішні блоки' ) ); ?></span></button>
  </div>
  <div hidden aria-hidden="true"><input type="radio" name="f-block" value="all" checked><input type="radio" name="f-block" value="outdoor"><input type="radio" name="f-block" value="indoor"></div>

  <div class="filters-overlay" id="filters-overlay"></div>

  <div class="catalog-layout">
    <aside class="filters" aria-label="<?php echo esc_attr( et( 'Фільтри' ) ); ?>">
      <div class="filters__head">
        <b><?php echo esc_html( et( 'Фільтри' ) ); ?></b>
        <button id="filters-close" aria-label="<?php echo esc_attr( et( 'Закрити фільтри' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Тип' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body">
          <label class="f-check"><input type="radio" name="f-sub" value="all" checked> <?php echo esc_html( et( 'Усі типи' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="wall"> <?php echo esc_html( et( 'Настінні' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="console"> <?php echo esc_html( et( 'Консольні' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="duct"> <?php echo esc_html( et( 'Канальні' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="cassette"> <?php echo esc_html( et( 'Касетні' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="floorceil"> <?php echo esc_html( et( 'Підлогово-стельові' ) ); ?></label>
          <label class="f-check"><input type="radio" name="f-sub" value="multi"> <?php echo esc_html( et( 'Мульти-спліт' ) ); ?></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Бренд' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-brands">
          <label class="f-brandchip"><input type="checkbox" name="f-brand" value="Kaysun"><span class="f-brandchip__box"><img src="<?php echo esc_url( $td ); ?>/assets/brands/kaysun.svg" alt="Kaysun"></span></label>
          <label class="f-brandchip"><input type="checkbox" name="f-brand" value="LG"><span class="f-brandchip__box"><img src="<?php echo esc_url( $td ); ?>/assets/brands/lg.svg" alt="LG"></span></label>
          <label class="f-brandchip"><input type="checkbox" name="f-brand" value="Panasonic"><span class="f-brandchip__box"><img src="<?php echo esc_url( $td ); ?>/assets/brands/panasonic.svg" alt="Panasonic"></span></label>
          <label class="f-brandchip"><input type="checkbox" name="f-brand" value="Juwent"><span class="f-brandchip__box"><img src="<?php echo esc_url( $td ); ?>/assets/brands/juwent.svg" alt="Juwent"></span></label>
          <label class="f-brandchip"><input type="checkbox" name="f-brand" value="Klimor"><span class="f-brandchip__box"><img src="<?php echo esc_url( $td ); ?>/assets/brands/klimor.svg" alt="Klimor"></span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Серія' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body">
          <label class="f-check"><input type="checkbox" name="f-series" value="casual"> Casual</label>
          <label class="f-check"><input type="checkbox" name="f-series" value="prodigy"> Prodigy PRO</label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Потужність' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-pills">
          <label class="f-pill"><input type="radio" name="f-power" value="all" checked><span><?php echo esc_html( et( 'Усі' ) ); ?></span></label>
          <label class="f-pill"><input type="radio" name="f-power" value="lt35"><span>до 3.5 кВт</span></label>
          <label class="f-pill"><input type="radio" name="f-power" value="mid"><span>3.5–5.5</span></label>
          <label class="f-pill"><input type="radio" name="f-power" value="gt55"><span>5.5+</span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Площа приміщення' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-pills">
          <label class="f-pill"><input type="radio" name="f-area" value="all" checked><span><?php echo esc_html( et( 'Будь-яка' ) ); ?></span></label>
          <label class="f-pill"><input type="radio" name="f-area" value="lt25"><span>до 25 м²</span></label>
          <label class="f-pill"><input type="radio" name="f-area" value="a2540"><span>25–40 м²</span></label>
          <label class="f-pill"><input type="radio" name="f-area" value="a4060"><span>40–60 м²</span></label>
          <label class="f-pill"><input type="radio" name="f-area" value="gt60"><span>60+ м²</span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Типорозмір, BTU' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-pills">
          <label class="f-pill"><input type="checkbox" name="f-btu" value="9"><span>9K</span></label>
          <label class="f-pill"><input type="checkbox" name="f-btu" value="12"><span>12K</span></label>
          <label class="f-pill"><input type="checkbox" name="f-btu" value="18"><span>18K</span></label>
          <label class="f-pill"><input type="checkbox" name="f-btu" value="24"><span>24K</span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Енергоклас' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-pills">
          <label class="f-pill"><input type="checkbox" name="f-energy" value="A+"><span>A+</span></label>
          <label class="f-pill"><input type="checkbox" name="f-energy" value="A++"><span>A++</span></label>
          <label class="f-pill"><input type="checkbox" name="f-energy" value="A+++"><span>A+++</span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Ціна' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body f-pills">
          <label class="f-pill"><input type="radio" name="f-price" value="all" checked><span><?php echo esc_html( et( 'Будь-яка' ) ); ?></span></label>
          <label class="f-pill"><input type="radio" name="f-price" value="lt25"><span><?php echo esc_html( et( 'до 25 тис.' ) ); ?></span></label>
          <label class="f-pill"><input type="radio" name="f-price" value="mid"><span><?php echo esc_html( et( '25–40 тис.' ) ); ?></span></label>
          <label class="f-pill"><input type="radio" name="f-price" value="gt40"><span><?php echo esc_html( et( '40 тис.+' ) ); ?></span></label>
        </div>
      </details>

      <details class="filter-group" open>
        <summary><?php echo esc_html( et( 'Функції' ) ); ?> <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></summary>
        <div class="filter-group__body">
          <label class="f-toggle"><?php echo esc_html( et( 'WiFi-керування' ) ); ?> <input type="checkbox" id="f-wifi"></label>
        </div>
      </details>
    </aside>

    <div class="catalog-main">
      <h2 class="sr-only"><?php echo esc_html( et( 'Каталог товарів' ) ); ?></h2>

      <div class="catalog-toolbar">
        <div class="catalog-toolbar__left">
          <button class="filters-open" id="filters-open"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg><?php echo esc_html( et( 'Фільтри' ) ); ?></button>
          <span id="catalog-count">—</span>
        </div>
        <div class="catalog-toolbar__right">
          <select class="sort-select" id="sort" aria-label="<?php echo esc_attr( et( 'Сортування' ) ); ?>">
            <option value="pop"><?php echo esc_html( et( 'Спочатку популярні' ) ); ?></option>
            <option value="price-asc"><?php echo esc_html( et( 'Ціна: спершу дешевші' ) ); ?></option>
            <option value="price-desc"><?php echo esc_html( et( 'Ціна: спершу дорожчі' ) ); ?></option>
            <option value="new"><?php echo esc_html( et( 'Спочатку новинки' ) ); ?></option>
          </select>
        </div>
      </div>

      <div class="catalog-chips-wrap" id="catalog-chips-wrap" style="display:none">
        <div class="catalog-chips" id="catalog-chips"></div>
        <button class="filters-clear" id="filters-clear"><?php echo esc_html( et( 'Очистити все' ) ); ?></button>
      </div>

      <div class="prod-grid" id="catalog-grid"><!-- cards rendered by enko.js from window.ENKO_PRODUCTS --></div>

      <div class="catalog-empty" id="catalog-empty">
        <b><?php echo esc_html( et( 'Нічого не знайдено' ) ); ?></b>
        <?php echo esc_html( et( 'За обраними фільтрами товарів немає. Спробуйте зняти частину фільтрів або очистити все.' ) ); ?>
      </div>
    </div>
  </div>

</div><!-- /.container -->
<?php
get_footer();
