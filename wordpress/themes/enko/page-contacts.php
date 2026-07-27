<?php
/**
 * Сторінка «Контакти» — 1:1 порт прототипу contacts.html.
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
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li><li class="sep">/</li><li aria-current="page"><?php echo esc_html( et( 'Контакти' ) ); ?></li>
  </ol></nav>
  <div class="catalog-head">
    <h1><?php echo esc_html( et( 'Контакти' ) ); ?></h1>
    <p><?php echo esc_html( et( "Зв'яжіться з нами зручним способом — проконсультуємо, підберемо рішення й обговоримо співпрацю." ) ); ?></p>
  </div>

  <section class="section" style="padding-top:8px">
    <div class="about-story">
      <div class="about-story__text">
        <p class="eyebrow eyebrow--nodash"><?php echo esc_html( et( 'Керівництво' ) ); ?></p>
        <h2 style="font-size:clamp(24px,2.6vw,30px);font-weight:700;letter-spacing:-.02em"><?php echo esc_html( et( 'Особистий контакт для великих проєктів' ) ); ?></h2>
        <p><?php echo esc_html( et( 'З питань великих систем, проєктування та партнерства ви можете звертатися напряму до керівництва компанії.' ) ); ?></p>
        <div class="about-facts about-facts--director" style="position:static;margin-top:16px">
          <div class="fact"><span style="flex:none;width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,rgba(31,58,110,.12),rgba(110,74,166,.16));display:grid;place-items:center;font-family:var(--f-head);font-weight:800;color:var(--violet)">EN</span>
          <div><dt><?php echo esc_html( et( 'Директор компанії · ТОВ «ЕНКО ЮА» · Україна' ) ); ?></dt><dd>Ім'я Прізвище</dd>
          <dd><a href="tel:+380777147777">+380 777 147 777</a></dd>
          <dd><a href="mailto:director@example.com">director@example.com</a></dd></div></div>
        </div>

        <div class="about-facts about-facts--partner" style="position:static;margin-top:16px">
          <div class="fact"><span style="flex:none;width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,rgba(221,0,11,.12),rgba(221,0,11,.2));display:grid;place-items:center;font-family:var(--f-head);font-weight:800;color:#DD000B">EN</span>
          <div><dt><?php echo esc_html( et( 'Партнер у Словаччині · ' ) ); ?><a href="https://enko.sk/" target="_blank" rel="noopener">ENKO group s.r.o.</a> · Slovensko</dt>
          <dd>Ім'я Прізвище</dd>
          <dd><a href="tel:+421000000000">+421 XXX XXX XXX</a></dd>
          <dd><a href="mailto:partner@example.com">partner@example.com</a></dd></div></div>
        </div>

        <p class="eyebrow eyebrow--nodash" style="margin-top:40px"><?php echo esc_html( et( "Кар'єра в ENKO Group" ) ); ?></p>
        <h2 style="font-size:clamp(24px,2.6vw,30px);font-weight:700;letter-spacing:-.02em"><?php echo esc_html( et( 'Відкриті вакансії' ) ); ?></h2>
        <p><?php echo esc_html( et( "У зв'язку з розширенням діяльності компанії ми запрошуємо до співпраці кваліфікованих фахівців у сфері кліматичних та інженерних систем." ) ); ?></p>
        <p style="font-weight:600;color:var(--ink);margin-top:18px"><?php echo esc_html( et( 'Актуальні вакансії:' ) ); ?></p>
        <ul class="seo-points" style="margin-top:12px">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Монтажник систем кондиціонування та вентиляції' ) ); ?></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Інженер-проєктувальник HVAC-систем' ) ); ?></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Менеджер з продажу кліматичного обладнання' ) ); ?></li>
        </ul>
        <p style="color:var(--muted);font-size:14.5px;margin-top:16px"><?php echo esc_html( et( 'Надсилайте резюме на ' ) ); ?><a href="mailto:info@enkogroup.com.ua" style="color:var(--violet);font-weight:600">info@enkogroup.com.ua</a>.</p>
      </div>

      <aside class="about-facts">
        <h3><?php echo esc_html( et( 'Наші контакти' ) ); ?></h3>
        <?php
        $cc = function_exists( 'enko_contacts' ) ? enko_contacts() : array( 'phone' => '', 'phone_tel' => '#', 'email' => '', 'email_url' => '#', 'tg' => 'https://t.me/EnkoGroup', 'viber' => '#', 'whatsapp' => '#' );
        $cc_tg = '@' . basename( (string) parse_url( $cc['tg'], PHP_URL_PATH ) );
        $cc_vb = ( function_exists( 'enko_opt' ) && enko_opt( 'viber', '' ) ) ? enko_opt( 'viber', '' ) : $cc['phone'];
        $cc_wa = ( function_exists( 'enko_opt' ) && enko_opt( 'whatsapp', '' ) ) ? enko_opt( 'whatsapp', '' ) : $cc['phone'];
        ?>
        <dl>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div><dt><?php echo esc_html( et( 'Графік' ) ); ?></dt><dd><?php echo esc_html( et( 'Пн–Пт, 9:00–18:00' ) ); ?></dd></div></div>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg><div><dt><?php echo esc_html( et( 'Телефон' ) ); ?></dt><dd><a href="<?php echo esc_url( $cc['phone_tel'] ); ?>"><?php echo esc_html( $cc['phone'] ); ?></a></dd></div></div>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg><div><dt>Email</dt><dd><a href="<?php echo esc_url( $cc['email_url'] ); ?>"><?php echo esc_html( $cc['email'] ); ?></a></dd></div></div>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg><div><dt>Telegram</dt><dd><a href="<?php echo esc_url( $cc['tg'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $cc_tg ); ?></a></dd></div></div>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg><div><dt>Viber</dt><dd><a href="<?php echo esc_url( $cc['viber'] ); ?>"><?php echo esc_html( $cc_vb ); ?></a></dd></div></div>
          <div class="fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 8.5c0 3 2 5 5 5"/></svg><div><dt>WhatsApp</dt><dd><a href="<?php echo esc_url( $cc['whatsapp'] ); ?>"><?php echo esc_html( $cc_wa ); ?></a></dd></div></div>
        </dl>
        <button type="button" class="btn btn--primary btn--block contacts-consult-btn" data-modal-open data-product=""><?php echo esc_html( et( 'Замовити консультацію' ) ); ?></button>
      </aside>
    </div>
  </section>
</div>

<section class="container" style="padding-bottom:88px">
  <div class="partner-band">
    <span class="lines-motif" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
    <h2><?php echo esc_html( et( 'Партнерство та співпраця' ) ); ?></h2>
    <p class="partner-lead"><?php echo esc_html( et( 'ENKO Group відкрита до співпраці з монтажними організаціями, приватними монтажниками, проєктними бюро, будівельними компаніями та торговими партнерами.' ) ); ?></p>
    <p class="partner-sub"><?php echo esc_html( et( 'Ми пропонуємо:' ) ); ?></p>
    <ul class="partner-list">
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Постачання систем кондиціонування, вентиляції та супутнього обладнання' ) ); ?></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Технічну підтримку підрядників і монтажних організацій' ) ); ?></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( "Розробку проєктної документації для об'єктів будь-якої складності" ) ); ?></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Інженерний супровід проєктів та тендерних процедур' ) ); ?></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( et( 'Партнерські умови для дилерів і торговельних компаній' ) ); ?></li>
    </ul>
    <p class="partner-foot"><?php echo esc_html( et( "Якщо вас цікавить співпраця, заповніть форму зворотного зв'язку, надішліть запит на електронну пошту або зв'яжіться з нами за телефоном." ) ); ?></p>
    <div class="partner-actions">
      <button class="btn btn--white" data-partner-open><?php echo esc_html( et( 'Стати партнером' ) ); ?></button>
    </div>
  </div>
</section>
<?php
get_footer();
