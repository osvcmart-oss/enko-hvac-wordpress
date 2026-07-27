<?php
/**
 * Сторінка «Доставка і гарантії» — 1:1 порт прототипу delivery.html.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
if ( ! function_exists( 'enko_is_ru' ) ) { function enko_is_ru() { return false; } }
get_header();
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>"><ol>
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li><li class="sep">/</li><li aria-current="page"><?php echo esc_html( et( 'Доставка і гарантії' ) ); ?></li>
  </ol></nav>
  <div class="catalog-head">
    <h1><?php echo esc_html( et( 'Доставка і гарантії' ) ); ?></h1>
    <p><?php echo enko_t( "Базова інформація — детальні умови уточнюються індивідуально під замовлення.<br>Зв'яжіться з нами, і ми розрахуємо доставку та гарантійні умови для вашого проєкту.", "Базовая информация — детальные условия уточняются индивидуально под заказ.<br>Свяжитесь с нами, и мы рассчитаем доставку и гарантийные условия для вашего проекта." ); ?></p>
  </div>

  <section class="section" style="padding-top:8px">
    <div class="exp-grid">
      <div class="exp-card">
        <span class="exp-ic"><svg viewBox="0 0 24 24"><rect x="1" y="6" width="14" height="11" rx="1"/><path d="M15 9h4l3 3v5h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
        <h3><?php echo esc_html( et( 'Доставка по всій Україні' ) ); ?></h3>
        <p><?php echo esc_html( et( 'Доставляємо обладнання у будь-яке місто України зручним для вас способом в погоджені терміни.' ) ); ?></p>
      </div>
      <div class="exp-card">
        <span class="exp-ic"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <h3><?php echo esc_html( et( 'Гарантія від виробника' ) ); ?></h3>
        <p><?php echo esc_html( et( 'На все обладнання діє офіційна гарантія виробника. Точні терміни залежать від моделі та умов експлуатації.' ) ); ?></p>
      </div>
      <div class="exp-card">
        <span class="exp-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5"/></svg></span>
        <h3><?php echo esc_html( et( 'Офіційний європейський партнер' ) ); ?></h3>
        <p><?php echo esc_html( et( 'Ми — офіційний партнер європейських виробників HVAC, тож ви отримуєте оригінальну техніку та повноцінну підтримку.' ) ); ?></p>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="section-head"><h2 style="font-size:clamp(26px,3vw,34px);font-weight:700;letter-spacing:-.02em"><?php echo esc_html( et( 'Часті запитання' ) ); ?></h2></div>
    <div class="faq">
      <details class="faq-item" open>
        <summary><?php echo esc_html( et( 'Чи робите монтаж техніки?' ) ); ?><span class="q-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span></summary>
        <p class="faq-body"><?php echo esc_html( et( 'Так. Ми організовуємо професійний монтаж кондиціонерів, теплових насосів та систем вентиляції силами сертифікованих монтажників. Вартість залежить від типу обладнання й складності робіт — розрахуємо під час консультації.' ) ); ?></p>
      </details>
      <details class="faq-item">
        <summary><?php echo esc_html( et( 'Яка гарантія на техніку?' ) ); ?><span class="q-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span></summary>
        <p class="faq-body"><?php echo esc_html( et( 'На все обладнання діє офіційна гарантія виробника. Точний термін залежить від моделі та умов експлуатації — уточнюйте в картці товару або в наших спеціалістів.' ) ); ?></p>
      </details>
      <details class="faq-item">
        <summary><?php echo esc_html( et( 'Як оформити заявку?' ) ); ?><span class="q-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span></summary>
        <p class="faq-body"><?php echo esc_html( et( "Додайте потрібну техніку в заявку кнопкою «Додати в заявку» або натисніть «Залишити заявку» чи «Запитати спеціаліста». Менеджер зв'яжеться з вами, уточнить деталі та підготує комерційну пропозицію. Онлайн-оплати немає — все погоджуємо особисто." ) ); ?></p>
      </details>
      <details class="faq-item">
        <summary><?php echo esc_html( et( 'Чи є доставка у моє місто?' ) ); ?><span class="q-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span></summary>
        <p class="faq-body"><?php echo esc_html( et( 'Доставляємо по всій Україні. Спосіб і строки залежать від габаритів обладнання та вашого населеного пункту — підкажемо оптимальний варіант при оформленні заявки.' ) ); ?></p>
      </details>
      <details class="faq-item">
        <summary><?php echo esc_html( et( 'Як дізнатися точну ціну?' ) ); ?><span class="q-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span></summary>
        <p class="faq-body"><?php echo esc_html( et( 'Ціни на сайті орієнтовні (UAH, поряд — EUR за поточним курсом). Фінальну вартість із урахуванням комплектації, монтажу та доставки ми фіксуємо в комерційній пропозиції після вашої заявки.' ) ); ?></p>
      </details>
    </div>
  </section>
</div>
<?php
get_footer();
