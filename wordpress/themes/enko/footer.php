<?php
/**
 * ENKO footer — 1:1 port of the prototype footer + modals/popups/toast/quick-launch.
 * UA/RU: рядки через et() (inc/i18n.php).
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$td   = get_template_directory_uri();
$shop = enko_shop_url();
$c    = function_exists( 'enko_contacts' ) ? enko_contacts() : array( 'phone' => '+380 777 147 777', 'phone_tel' => 'tel:+380777147777', 'email' => 'info@enkogroup.com.ua', 'email_url' => 'mailto:info@enkogroup.com.ua' );
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
?>
</main>

<!-- =================== FOOTER =================== -->
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-col">
      <img class="footer-logo" src="<?php echo esc_url( $td ); ?>/assets/logo-enko-white-crop.png" alt="ENKO">
      <p class="footer-about"><?php echo esc_html( et( 'Офіційний дилер HVAC в Україні. Кліматична техніка для дому та бізнесу.' ) ); ?></p>
    </div>
    <div class="footer-col">
      <h4><?php echo esc_html( et( 'Каталог' ) ); ?></h4>
      <ul>
        <li><a href="<?php echo esc_url( enko_cat_url( 'conditioners' ) ); ?>"><?php echo esc_html( et( 'Кондиціонери' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( enko_cat_url( 'vrf' ) ); ?>"><?php echo esc_html( et( 'Мультизональні VRF' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( enko_cat_url( 'heat-pumps' ) ); ?>"><?php echo esc_html( et( 'Теплові насоси' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( enko_cat_url( 'ventilation' ) ); ?>"><?php echo esc_html( et( 'Вентиляція' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( enko_cat_url( 'microclimate' ) ); ?>"><?php echo esc_html( et( 'Мікроклімат' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( enko_cat_url( 'fancoils' ) ); ?>"><?php echo esc_html( et( 'Фанкойли' ) ); ?></a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4><?php echo esc_html( et( 'Компанія' ) ); ?></h4>
      <ul>
        <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( et( 'Про нас' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( home_url( '/delivery/' ) ); ?>"><?php echo esc_html( et( 'Доставка і гарантії' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( home_url( '/about/#refs' ) ); ?>"><?php echo esc_html( et( "Реалізовані об'єкти" ) ); ?></a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4><?php echo esc_html( et( 'Контакти' ) ); ?></h4>
      <ul class="footer-contacts">
        <li><a href="<?php echo esc_attr( $c['phone_tel'] ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg><?php echo esc_html( $c['phone'] ); ?></a></li>
        <li><a href="<?php echo esc_attr( $c['email_url'] ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg><?php echo esc_html( $c['email'] ); ?></a></li>
        <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>Telegram</a></li>
        <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>Viber</a></li>
        <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 8.5c0 3 2 5 5 5"/></svg>WhatsApp</a></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span><?php echo esc_html( et( 'Пн–Пт, 9:00–18:00' ) ); ?></span></li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <span>© ENKO <?php echo esc_html( gmdate( 'Y' ) ); ?> · <?php echo esc_html( enko_t( 'ТОВ «ЕНКО ЮА» · ЄДРПОУ 46207393', 'ООО «ЭНКО ЮА» · ЕГРПОУ 46207393' ) ); ?></span>
    </div>
    <div class="legal">
      <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php echo esc_html( et( 'Політика конфіденційності' ) ); ?></a>
      <a href="<?php echo esc_url( home_url( '/cookies/' ) ); ?>">Cookies</a>
      <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php echo esc_html( et( 'Угода користувача' ) ); ?></a>
    </div>
  </div>
</footer>

<!-- =================== REQUEST MODAL =================== -->
<div class="modal" id="request-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="modal__overlay" data-modal-close></div>
  <div class="modal__panel">
    <button class="modal__close" data-modal-close aria-label="<?php echo esc_attr( et( 'Закрити' ) ); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h3 id="modal-title"><?php echo esc_html( et( 'Запитати спеціаліста' ) ); ?></h3>
    <p class="modal__sub"><?php echo esc_html( et( "Залиште контакти — менеджер зв'яжеться, допоможе з підбором і порахує вартість із доставкою та монтажем." ) ); ?></p>
    <div class="form-product-tag" id="modal-product-tag" style="display:none">
      <svg viewBox="0 0 24 24" width="18" fill="none" stroke="#6E54A6" stroke-width="2"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0l-7.59-7.59a2 2 0 0 1-.59-1.41V4a2 2 0 0 1 2-2h7.59a2 2 0 0 1 1.41.59l7.59 7.59a2 2 0 0 1 0 2.83z"/></svg>
      <span><?php echo esc_html( et( 'Товар:' ) ); ?> <b id="modal-product-name"></b></span>
    </div>
    <form id="modal-form">
      <div class="field"><label for="f-name"><?php echo esc_html( et( "Ім'я" ) ); ?></label><input id="f-name" type="text" name="name" required placeholder="<?php echo esc_attr( et( "Ваше ім'я" ) ); ?>"></div>
      <div class="field"><label for="f-phone"><?php echo esc_html( et( 'Телефон' ) ); ?></label><input id="f-phone" type="tel" name="phone" required placeholder="+380 __ ___ __ __"></div>
      <div class="field"><label for="f-email">Email</label><input id="f-email" type="email" name="email" placeholder="you@email.com"></div>
      <div class="field"><label for="f-q"><?php echo esc_html( et( 'Питання' ) ); ?></label><textarea id="f-q" name="question" placeholder="<?php echo esc_attr( et( 'Опишіть приміщення або задачу — підкажемо рішення' ) ); ?>"></textarea></div>
      <button class="btn btn--primary btn--block" type="submit" style="margin-top:20px"><?php echo esc_html( et( 'Надіслати' ) ); ?></button>
    </form>
    <div class="form-ok" id="modal-ok">
      <svg viewBox="0 0 24 24" width="46" fill="none" stroke="#2FA36B" stroke-width="2" style="margin:0 auto 10px"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
      <p style="font-size:18px;color:#1A1F2D"><?php echo esc_html( et( 'Дякуємо! Заявку надіслано.' ) ); ?></p>
      <p style="color:#5B6472;font-weight:400;margin-top:6px"><?php echo esc_html( et( "Ми зв'яжемося з вами найближчим часом." ) ); ?></p>
    </div>
  </div>
</div>

<!-- =================== TOAST =================== -->
<div class="toast" id="toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
  <span class="toast__msg"><?php echo esc_html( et( 'Додано в заявку' ) ); ?></span>
</div>

<!-- =================== POPUPS (lead / quick contact) =================== -->
<div class="pop lead-pop" id="lead-pop" role="dialog" aria-label="<?php echo esc_attr( et( 'Підбір техніки' ) ); ?>">
  <button class="pop__close" data-pop-close="lead-pop" aria-label="<?php echo esc_attr( et( 'Закрити' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  <h3><?php echo esc_html( et( 'Для індивідуального підбору вже сьогодні' ) ); ?></h3>
  <p><?php echo esc_html( et( "Залиште свій контакт — підкажемо оптимальне рішення під ваш об'єкт." ) ); ?></p>
  <form id="lead-form" onsubmit="return false">
    <input type="tel" placeholder="+380 __ ___ __ __" aria-label="<?php echo esc_attr( et( 'Телефон' ) ); ?>" required>
    <button class="btn btn--primary" type="submit"><?php echo esc_html( et( 'Залишити контакт' ) ); ?></button>
    <p class="ok" id="lead-ok"><?php echo esc_html( et( "Дякуємо! Ми зв'яжемося з вами найближчим часом." ) ); ?></p>
  </form>
</div>

<div class="pop quick-pop" id="quick-pop" role="dialog" aria-label="<?php echo esc_attr( et( "Швидкий зв'язок" ) ); ?>">
  <button class="pop__close" data-pop-close="quick-pop" aria-label="<?php echo esc_attr( et( 'Закрити' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  <div class="quick-pop__inner" id="quick-pop-inner"></div>
</div>

<div class="quick-launch" id="quick-launch">
  <button class="ql-call" id="ql-call" aria-label="<?php echo esc_attr( et( 'Передзвоніть мені' ) ); ?>"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></button>
  <button class="ql-chat" id="ql-chat" aria-label="<?php echo esc_attr( et( "Швидкий зв'язок" ) ); ?>"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></button>
</div>

<?php wp_footer(); ?>
</body>
</html>
