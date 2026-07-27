<?php
/**
 * Кошик «заявка» — 1:1 порт прототипу cart.html на дані WooCommerce.
 * Список позицій + підсумок (₴+€ зі знижкою) + форма-заявка на одній сторінці.
 * ± кількість / видалення → REST enko/v1/cart-update; форма → enko/v1/checkout.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$cart = WC()->cart;
$rate = function_exists( 'enko_eur_rate' ) ? enko_eur_rate() : 45;
$td   = get_template_directory_uri();
$fmt  = function ( $n ) { return number_format( (float) $n, 0, ',', ' ' ); };

$user = wp_get_current_user();
$is_logged = is_user_logged_in();
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="Хлібні крихти">
    <ol>
      <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Головна</a></li>
      <li class="sep">/</li>
      <li aria-current="page">Заявка</li>
    </ol>
  </nav>

  <div class="cart-head">
    <h1>Ваша заявка</h1>
    <p>Зберіть техніку, що вас цікавить, і залиште заявку — менеджер зв'яжеться, уточнить деталі та підготує точну пропозицію з доставкою й монтажем.</p>
  </div>

  <?php if ( $cart->is_empty() ) : ?>
  <div class="cart-empty">
    <div class="cart-empty__ic"><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
    <h2>У заявці поки порожньо</h2>
    <p>Додайте техніку кнопкою «Додати в заявку» в каталозі чи на сторінці товару — і вона з'явиться тут.</p>
    <a href="<?php echo esc_url( enko_shop_url() ); ?>" class="btn btn--primary">Перейти до каталогу</a>
  </div>
  <?php else : ?>
  <div class="cart-grid" id="cart-grid">
    <div class="cart-list" id="cart-items">
      <?php
      foreach ( $cart->get_cart() as $key => $item ) :
        $p     = $item['data'];
        $qty   = (int) $item['quantity'];
        $unitU = (float) $p->get_price();           // зі знижкою (фільтр ціни)
        $lineU = $unitU * $qty;
        $lineE = $rate ? round( $lineU / $rate ) : 0;
        $parent_id = $p->is_type( 'variation' ) ? $p->get_parent_id() : $p->get_id();
        $parent    = wc_get_product( $parent_id );
        $name      = $parent ? $parent->get_name() : $p->get_name();
        $ver       = $p->is_type( 'variation' ) ? implode( ' ', $p->get_variation_attributes() ) : '';
        $img_id    = $p->get_image_id() ?: ( $parent ? $parent->get_image_id() : 0 );
        $photo     = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '';
        if ( ! $photo ) { $photo = ( stripos( $name, 'kaysun' ) !== false ) ? content_url( 'uploads/enko/products-kaysun-casual-indoor.webp' ) : content_url( 'uploads/enko/types-wall.webp' ); }
        ?>
      <div class="cart-row" data-key="<?php echo esc_attr( $key ); ?>">
        <div class="cart-row__media"><div class="ph"><img class="prod-photo" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>"></div></div>
        <div class="cart-row__info"><b><?php echo esc_html( $name ); ?></b>
          <?php if ( $ver ) : ?><span class="cart-row__ver">Версія: <?php echo esc_html( $ver ); ?></span><?php endif; ?>
          <button class="cart-row__rm-text" data-act="remove">Видалити</button>
        </div>
        <div class="qty" role="group" aria-label="Кількість">
          <button data-act="dec" aria-label="Зменшити">−</button>
          <span class="qty__n"><?php echo (int) $qty; ?></span>
          <button data-act="inc" aria-label="Збільшити">+</button>
        </div>
        <div class="cart-row__price"><span class="price__main price__uah"><?php echo esc_html( $fmt( $lineU ) ); ?> грн</span><span class="price__eur"><?php echo esc_html( $fmt( $lineE ) ); ?> €</span></div>
        <button class="cart-row__rm" data-act="remove" aria-label="Видалити товар"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <?php endforeach; ?>
    </div>

    <aside class="cart-summary">
      <div class="cart-summary__head">
        <h2>Підсумок заявки</h2>
        <span id="cart-count-label"><?php echo (int) $cart->get_cart_contents_count(); ?> товар(ів)</span>
      </div>
      <div class="cart-summary__body">
        <div class="cart-total">
          <span class="lbl">Орієнтовна сума</span>
          <span class="val"><span class="u"><?php echo esc_html( $fmt( $cart->get_cart_contents_total() ) ); ?> грн</span><span class="e"><?php echo esc_html( $fmt( $rate ? round( $cart->get_cart_contents_total() / $rate ) : 0 ) ); ?> €</span></span>
        </div>
        <p class="cart-note">Ціни орієнтовні (грн за поточним курсом EUR). Фінальну вартість із урахуванням комплектації, доставки та монтажу зафіксуємо в комерційній пропозиції.</p>
        <ul class="cart-summary__minitrust">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>Офіційний дилер</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>Гарантія</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>Доставка</li>
        </ul>
      </div>
      <form class="cart-summary__form" id="cart-form">
        <h3>Залишити заявку</h3>
        <div class="field"><label for="c-name">Ім'я</label><input id="c-name" type="text" name="name" required placeholder="Ваше ім'я" value="<?php echo $is_logged ? esc_attr( trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name ) : ''; ?>"></div>
        <div class="field"><label for="c-phone">Телефон</label><input id="c-phone" type="tel" name="phone" required placeholder="+380 __ ___ __ __" value="<?php echo $is_logged ? esc_attr( get_user_meta( $user->ID, 'billing_phone', true ) ) : ''; ?>"></div>
        <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" placeholder="you@email.com" value="<?php echo $is_logged ? esc_attr( $user->user_email ) : ''; ?>"></div>
        <div class="field"><label for="c-q">Коментар</label><textarea id="c-q" name="question" placeholder="Місто доставки, потреба в монтажі, питання…"></textarea></div>
        <button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Надіслати заявку</button>
      </form>
    </aside>
  </div>
  <?php endif; ?>

  <div class="cart-ok" id="cart-ok" style="display:none">
    <svg viewBox="0 0 24 24" width="64" fill="none" stroke="#2FA36B" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
    <h2>Дякуємо! Заявку надіслано.</h2>
    <p>Менеджер ENKO зв'яжеться з вами найближчим часом, щоб уточнити деталі та підготувати пропозицію.</p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary" style="margin-top:22px">Повернутися на головну</a>
  </div>
</div>
