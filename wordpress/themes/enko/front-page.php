<?php
/**
 * ENKO front page — 1:1 port of the prototype index.html <main>.
 * (header.php opens <main>, footer.php closes it.) UA/RU: рядки через et()/enko_t().
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$td   = get_template_directory_uri();
$shop = enko_shop_url();
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
$_to = et( 'до розділу' );
get_header();
?>

<!-- =================== HERO =================== -->
<section class="hero">
  <div class="hero__bg">
    <div class="ph" aria-hidden="true"></div>
  </div>
  <div class="hero__overlay" aria-hidden="true"></div>
  <div class="container hero__in">
    <div class="hero__content">
      <p class="eyebrow"><?php echo esc_html( et( 'Офіційний дилер HVAC в Україні' ) ); ?></p>
      <h1><?php echo esc_html( et( 'Кліматична техніка для дому та бізнесу' ) ); ?></h1>
      <p><?php echo enko_t( 'Кондиціонери, мультизональні VRF, теплові насоси, вентиляція, мікроклімат і фанкойли.<br>Допоможемо з підбором техніки під ваш індивідуальний запит з гарантією від виробника.', 'Кондиционеры, мультизональные VRF, тепловые насосы, вентиляция, микроклимат и фанкойлы.<br>Поможем с подбором техники под ваш индивидуальный запрос с гарантией от производителя.' ); ?></p>
      <div class="hero__cta">
        <a href="#products" class="btn btn--primary"><?php echo esc_html( et( 'Підібрати техніку' ) ); ?></a>
        <button class="btn btn--on-hero" data-modal-open data-product=""><?php echo esc_html( et( 'Залишити заявку' ) ); ?></button>
      </div>
    </div>
  </div>
</section>

<!-- =================== ПРОДУКЦІЯ (showcase, hover) =================== -->
<section class="showcase" id="products">
  <div class="container">
    <div class="showcase__grid">
      <div class="sc-tile sc-tile--tn">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Теплові насоси' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Теплові насоси' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: тепловий насос (будинок)' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'Повітря-вода' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'Повітря-повітря' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'Для опалення та ГВП' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
      <div class="sc-tile sc-tile--vrf">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Мультизональні VRF' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Мультизональні VRF' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: VRF-система' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'Зовнішні блоки' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'Внутрішні блоки' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'Комплектуючі' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
      <div class="sc-tile sc-tile--cond">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Кондиціонери' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Кондиціонери' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: кондиціонер' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( add_query_arg( 'type', 'wall', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Настінні' ) ); ?></a></li><li><a href="<?php echo esc_url( add_query_arg( 'type', 'console', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Консольні' ) ); ?></a></li><li><a href="<?php echo esc_url( add_query_arg( 'type', 'duct', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Канальні' ) ); ?></a></li><li><a href="<?php echo esc_url( add_query_arg( 'type', 'cassette', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Касетні' ) ); ?></a></li><li><a href="<?php echo esc_url( add_query_arg( 'type', 'floorceil', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Підлогово-стельові' ) ); ?></a></li><li><a href="<?php echo esc_url( add_query_arg( 'type', 'multi', enko_cat_url( 'conditioners' ) ) ); ?>"><?php echo esc_html( et( 'Мульти-спліт' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
      <div class="sc-tile sc-tile--vent">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Вентиляція' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Вентиляція' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: вентиляція' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><?php echo esc_html( et( 'Припливно-витяжна' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><?php echo esc_html( et( 'Рекуперація' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
      <div class="sc-tile sc-tile--micro">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Мікроклімат' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Мікроклімат' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: зволожувач' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'Зволожувачі' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'Осушувачі' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'Очисники повітря' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
      <div class="sc-tile sc-tile--fan">
        <a class="sc-tile__main" href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Фанкойли' ) . ' — ' . $_to ); ?>">
          <span class="sc-tile__label"><?php echo esc_html( et( 'Фанкойли' ) ); ?></span>
          <div class="ph"><span><?php echo esc_html( et( 'Фото: фанкойл' ) ); ?></span></div>
        </a>
        <div class="sc-tile__reveal"><ul><li><a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'Настінні та підлогові' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'Касетні та канальні' ) ); ?></a></li><li><a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'Для чилер-фанкойл' ) ); ?></a></li></ul><a class="sc-go" href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'До розділу' ) ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
      </div>
    </div>
  </div>
</section>

<!-- =================== НАШІ ПЕРЕВАГИ =================== -->
<section class="value-band">
  <div class="container section--tight">
    <div class="section-head section-head--center">
      <h2><?php echo esc_html( et( 'Переваги ENKO' ) ); ?></h2>
    </div>
    <div class="adv-grid">
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/></svg></span><div><h3><?php echo esc_html( et( 'Офіційний дилер' ) ); ?></h3><p><?php echo esc_html( et( 'Прямі поставки та авторизація від виробників.' ) ); ?></p></div></div>
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><div><h3><?php echo esc_html( et( 'Сертифіковане обладнання' ) ); ?></h3><p><?php echo esc_html( et( 'Продаж оригінальної техніки з документами.' ) ); ?></p></div></div>
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg></span><div><h3><?php echo esc_html( et( 'Гарантія від виробника' ) ); ?></h3><p><?php echo esc_html( et( 'Офіційна гарантія на все обладнання.' ) ); ?></p></div></div>
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><div><h3><?php echo esc_html( et( '20+ років на ринку Європи' ) ); ?></h3><p><?php echo esc_html( et( 'Досвід інженерної групи зі Словаччини та Чехії.' ) ); ?></p></div></div>
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 14l2 2 4-4"/></svg></span><div><h3><?php echo esc_html( et( 'Працюємо за договором' ) ); ?></h3><p><?php echo esc_html( et( 'Прозорі умови та офіційні документи.' ) ); ?></p></div></div>
      <div class="adv"><span class="adv__ic"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.66 5.66l1.41 1.41M9.3 17.7a4 4 0 0 0 5.66-5.66"/><circle cx="12" cy="12" r="9"/></svg></span><div><h3><?php echo esc_html( et( 'Комплексні рішення та сервісне обслуговування' ) ); ?></h3><p><?php echo esc_html( et( 'Проєкт → монтаж → пуск → обслуговування.' ) ); ?></p></div></div>
    </div>
  </div>
</section>

<!-- =================== СХЕМА СПІВПРАЦІ =================== -->
<section class="container" style="padding-block:24px 88px">
  <div class="scheme">
    <div class="scheme__head"><h2><?php echo esc_html( et( 'Ми пропонуємо надійну схему співпраці' ) ); ?></h2></div>
    <div class="scheme__steps">
      <div class="scheme-step"><div class="scheme-step__n">1</div><p><?php echo esc_html( et( 'Заявка та консультація' ) ); ?></p></div>
      <div class="scheme-step"><div class="scheme-step__n">2</div><p><?php echo esc_html( et( 'Розрахунок та пропозиція' ) ); ?></p></div>
      <div class="scheme-step"><div class="scheme-step__n">3</div><p><?php echo esc_html( et( 'Оформлення договору' ) ); ?></p></div>
      <div class="scheme-step"><div class="scheme-step__n">4</div><p><?php echo esc_html( et( 'Доставка та монтаж' ) ); ?></p></div>
      <div class="scheme-step"><div class="scheme-step__n">5</div><p><?php echo esc_html( et( 'Пусконалагодження та інструктаж' ) ); ?></p></div>
      <div class="scheme-step"><div class="scheme-step__n">6</div><p><?php echo esc_html( et( 'Супровід та обслуговування' ) ); ?></p></div>
    </div>
  </div>
</section>

<!-- =================== BRANDS =================== -->
<section class="brands">
  <div class="container section--tight">
    <div class="section-head section-head--center">
      <p class="eyebrow"><?php echo esc_html( et( 'Наші виробники' ) ); ?></p>
    </div>
    <div class="brand-row">
      <div class="brand-chip"><img src="<?php echo esc_url( $td ); ?>/assets/brands/kaysun.svg" alt="Kaysun" loading="lazy"></div>
      <div class="brand-chip"><img src="<?php echo esc_url( $td ); ?>/assets/brands/lg.svg" alt="LG" loading="lazy"></div>
      <div class="brand-chip"><img src="<?php echo esc_url( $td ); ?>/assets/brands/panasonic.svg" alt="Panasonic" loading="lazy"></div>
      <div class="brand-chip"><img src="<?php echo esc_url( $td ); ?>/assets/brands/juwent.svg" alt="Juwent" loading="lazy"></div>
      <div class="brand-chip"><img src="<?php echo esc_url( $td ); ?>/assets/brands/klimor.svg" alt="Klimor" loading="lazy"></div>
    </div>
  </div>
</section>

<!-- =================== ЗАМОВИТИ КОНСУЛЬТАЦІЮ =================== -->
<section class="container" style="padding-bottom:88px">
  <div class="consult">
    <h2><?php echo esc_html( et( 'Замовити консультацію' ) ); ?></h2>
    <p><?php echo esc_html( et( 'Залиште контакти — підберемо техніку під ваше приміщення та порахуємо вартість.' ) ); ?></p>
    <form class="consult__form" id="consult-form" onsubmit="return false">
      <input type="text" placeholder="<?php echo esc_attr( et( "Прізвище та ім'я" ) ); ?>" aria-label="<?php echo esc_attr( et( "Ім'я" ) ); ?>" required>
      <input type="tel" placeholder="<?php echo esc_attr( et( 'Номер телефону' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Телефон' ) ); ?>" required>
      <input type="text" placeholder="<?php echo esc_attr( et( 'Вкажіть ваше місто/область' ) ); ?>" aria-label="<?php echo esc_attr( et( 'Місто' ) ); ?>">
      <button class="btn" type="submit"><?php echo esc_html( et( 'Надіслати' ) ); ?></button>
    </form>
    <p class="consult__ok" id="consult-ok"><?php echo esc_html( et( "Дякуємо! Заявку надіслано — ми зв'яжемося з вами." ) ); ?></p>
  </div>
</section>

<?php
get_footer();
