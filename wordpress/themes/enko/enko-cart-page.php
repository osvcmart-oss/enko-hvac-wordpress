<?php
/**
 * Сторінка кошика «Заявка» — 1:1 порт прототипу cart.html.
 * Позиції рендерить прототипний enko.js (renderCart) з localStorage; ± кількість,
 * видалення й сабміт працюють нативно. Сабміт додатково створює замовлення Woo
 * (enko-core/assets/enko-cart.js → /enko/v1/checkout-items).
 * Підключається через template_include для is_cart() (enko-core/inc/cart.php).
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
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>">
    <ol>
      <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li>
      <li class="sep">/</li>
      <li aria-current="page"><?php echo esc_html( et( 'Заявка' ) ); ?></li>
    </ol>
  </nav>

  <div class="cart-head">
    <h1><?php echo esc_html( et( 'Ваша заявка' ) ); ?></h1>
    <p><?php echo esc_html( et( "Зберіть техніку, що вас цікавить, і залиште заявку — менеджер зв'яжеться, уточнить деталі та підготує точну пропозицію з доставкою й монтажем." ) ); ?></p>
  </div>

  <div class="cart-empty" id="cart-empty" style="display:none">
    <div class="cart-empty__ic"><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
    <h2><?php echo esc_html( et( 'У заявці поки порожньо' ) ); ?></h2>
    <p><?php echo esc_html( et( "Додайте техніку кнопкою «Додати в заявку» в каталозі чи на сторінці товару — і вона з'явиться тут. Заявка зберігається, навіть якщо ви повернетесь пізніше." ) ); ?></p>
    <a href="<?php echo esc_url( enko_shop_url() ); ?>" class="btn btn--primary"><?php echo esc_html( et( 'Перейти до каталогу' ) ); ?></a>
  </div>

  <div class="cart-grid" id="cart-grid" style="display:none">
    <div class="cart-list" id="cart-items"><!-- rows injected by enko.js from localStorage --></div>

    <aside class="cart-summary">
      <div class="cart-summary__head">
        <h2><?php echo esc_html( et( 'Підсумок заявки' ) ); ?></h2>
        <span id="cart-count-label">0 <?php echo esc_html( et( 'товарів' ) ); ?></span>
      </div>
      <div class="cart-summary__body">
        <div class="cart-total">
          <span class="lbl"><?php echo esc_html( et( 'Орієнтовна сума' ) ); ?></span>
          <span class="val"><span class="u" id="sum-uah">0 грн</span><span class="e" id="sum-eur">0 €</span></span>
        </div>
        <p class="cart-note"><?php echo esc_html( et( 'Ціни орієнтовні (грн за поточним курсом EUR). Фінальну вартість із урахуванням комплектації, доставки та монтажу зафіксуємо в комерційній пропозиції.' ) ); ?></p>
        <ul class="cart-summary__minitrust">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Офіційний дилер' ) ); ?></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Гарантія' ) ); ?></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Доставка' ) ); ?></li>
        </ul>
      </div>
      <form class="cart-summary__form" id="cart-form">
        <h3><?php echo esc_html( et( 'Залишити заявку' ) ); ?></h3>
        <div class="field"><label for="c-name"><?php echo esc_html( et( "Ім'я" ) ); ?></label><input id="c-name" type="text" name="name" required placeholder="<?php echo esc_attr( et( "Ваше ім'я" ) ); ?>"></div>
        <div class="field"><label for="c-phone"><?php echo esc_html( et( 'Телефон' ) ); ?></label><input id="c-phone" type="tel" name="phone" required placeholder="+380 __ ___ __ __"></div>
        <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" placeholder="you@email.com"></div>
        <div class="field"><label for="c-q"><?php echo esc_html( et( 'Коментар' ) ); ?></label><textarea id="c-q" name="question" placeholder="<?php echo esc_attr( et( 'Місто доставки, потреба в монтажі, питання…' ) ); ?>"></textarea></div>
        <button class="btn btn--primary btn--block" type="submit" style="margin-top:18px"><?php echo esc_html( et( 'Надіслати заявку' ) ); ?></button>
      </form>
    </aside>
  </div>

  <div class="cart-ok" id="cart-ok">
    <svg viewBox="0 0 24 24" width="64" fill="none" stroke="#2FA36B" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
    <h2><?php echo esc_html( et( 'Дякуємо! Заявку надіслано.' ) ); ?></h2>
    <p><?php echo esc_html( et( "Менеджер ENKO зв'яжеться з вами найближчим часом, щоб уточнити деталі та підготувати пропозицію." ) ); ?></p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary" style="margin-top:22px"><?php echo esc_html( et( 'Повернутися на головну' ) ); ?></a>
  </div>
</div>
<?php
get_footer();
