<?php
/**
 * Сторінка «Про нас» — 1:1 порт прототипу about.html (з портфоліо).
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
if ( ! function_exists( 'enko_t' ) ) { function enko_t( $uk, $ru = '' ) { return $uk; } }
if ( ! function_exists( 'enko_is_ru' ) ) { function enko_is_ru() { return false; } }
$td = get_template_directory_uri();
get_header();

/* Реалізовані об'єкти: [категорія, файл, назва, локація]. */
$refs = array(
	array( 'Адміністративні', 'rtvs', 'СТВР (колишнє РТВС)', 'Банська Бистриця, Словаччина' ),
	array( 'Адміністративні', 'eltek', 'ЕЛТЕК', 'Ліптовський Градок, Словаччина' ),
	array( "Чисті приміщення", 'nemocnica-zvolen', 'Лікарня Зволен', 'Зволен, Словаччина' ),
	array( "Чисті приміщення", 'easys', 'ІЗІС', 'Тренчин, Словаччина' ),
	array( "Чисті приміщення", 'zhongding-europe', 'Zhongding Europe', 'Середь, Словаччина' ),
	array( "Чисті приміщення", 'fakultna-nemocnica-nitra', 'Факультетська лікарня Нітра', 'Нітра, Словаччина' ),
	array( "Чисті приміщення", 'neways', 'НЬЮВЕЙС — виробництво електроніки', 'Нова Дубниця, Словаччина' ),
	array( "Охорона здоров'я", 'nemocnica-trebisov', 'Лікарня Требішов', 'Требішов, Словаччина' ),
	array( "Охорона здоров'я", 'lubovnianska-nemocnica', 'Любовнянська лікарня', 'Стара Любовня, Словаччина' ),
	array( 'Промислові', 'udenco-nitra', 'Юденко Нітра', 'Нітра, Словаччина' ),
	array( 'Промислові', 'mediderma', 'Медідерма', 'Класов, Словаччина' ),
	array( 'Промислові', 'matec', 'МАТЕК', 'Сабінов, Словаччина' ),
	array( 'Промислові', 'mubea', 'МУБЕА', 'Велька Іда, Словаччина' ),
	array( "Громадські об'єкти", 'zs-ms-velke-hamry', 'Початкова школа з дитячим садком', 'Велке Гамри, Чехія' ),
	array( 'Торгові / Розважальні', 'siko-kosice', 'СІКО Кошиці', 'Кошиці, Словаччина' ),
	array( 'Торгові / Розважальні', 'eglo-lemesany', 'ЕГЛО', 'Лемешани, Словаччина' ),
	array( 'Торгові / Розважальні', 'oc-slanica', 'ОЦ Сланіца', 'Наместово, Словаччина' ),
	array( 'Торгові / Розважальні', 'admiral-cafe', 'Адмірал Кафе', 'Спішська Нова Весь, Словаччина' ),
	array( 'Торгові / Розважальні', 'magic-planet', 'Казино «Меджик Пленет»', 'Вестец, Чехія' ),
	array( 'Спортивні комплекси', 'telocvicna-senica', 'Спортивний зал', 'Сениця, Словаччина' ),
	array( 'Спортивні комплекси', 'sportova-hala-pasienky', 'Спортивна зала «Пасєнки»', 'Братислава, Словаччина' ),
);
$pin = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>">
    <ol>
      <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li>
      <li class="sep">/</li>
      <li aria-current="page"><?php echo esc_html( et( 'Про нас' ) ); ?></li>
    </ol>
  </nav>

  <section class="about-hero">
    <div class="about-hero__in">
      <p class="eyebrow"><?php echo esc_html( et( 'Про компанію' ) ); ?></p>
      <h1><?php echo esc_html( et( 'Кліматична техніка для дому і бізнесу' ) ); ?></h1>
      <p><?php echo enko_t( "ENKO group — міжнародний постачальник професійних HVAC-рішень для промисловості та житлового сектору, що з 2005 року працює на ринках Словаччини та Чехії, а тепер забезпечує європейську якість інженерних систем в Україні.<br>Компанія пропонує комплексний підхід: постачання обладнання від світових виробників, монтаж, професійний технічний супровід та цілодобовий сервіс 24/7.", "ENKO group — международный поставщик профессиональных HVAC-решений для промышленности и жилого сектора, который с 2005 года работает на рынках Словакии и Чехии, а теперь обеспечивает европейское качество инженерных систем в Украине.<br>Компания предлагает комплексный подход: поставка оборудования от мировых производителей, монтаж, профессиональное техническое сопровождение и круглосуточный сервис 24/7." ); ?></p>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="section-head">
      <h2 id="refs" style="font-size:clamp(26px,3vw,36px);font-weight:700;letter-spacing:-.02em"><?php echo esc_html( et( 'Реалізовано нашими спеціалістами у Європі' ) ); ?></h2>
      <p><?php echo esc_html( et( "Кілька знакових об'єктів, збудованих за нашими технологіями та інженерними рішеннями — від заводів і чистих приміщень до лікарень." ) ); ?></p>
    </div>

    <div class="ref-grid">
      <?php foreach ( $refs as $r ) :
        list( $cat, $img, $title, $loc ) = $r;
        $src = content_url( 'uploads/enko/portfolio-' . $img . '.webp' );
        ?>
      <article class="ref-card">
        <div class="ref-card__media"><div class="ref-cat"><span><?php echo esc_html( et( $cat ) ); ?></span></div><div class="ph"><img class="ref-img" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( et( $title ) ); ?>" loading="lazy"><span><?php echo esc_html( et( 'Фото об’єкта:' ) ); ?><br><?php echo esc_html( et( $title ) ); ?></span></div></div>
        <div class="ref-card__body"><h3><?php echo esc_html( et( $title ) ); ?></h3><p class="ref-card__loc"><?php echo $pin; // phpcs:ignore ?><?php echo esc_html( et( $loc ) ); ?></p></div>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="ref-note"><?php echo esc_html( et( "…та десятки інших реалізованих об'єктів у Словаччині й Чехії: лікарні, заводи, торгові центри, спортивні та адміністративні будівлі." ) ); ?></p>
  </section>
</div>

<section class="container" style="padding-bottom:88px">
  <div class="consult">
    <h2><?php echo esc_html( et( 'Замовити консультацію' ) ); ?></h2>
    <p><?php echo esc_html( et( "Виробництво, чисте приміщення, медичний чи комерційний об'єкт — обговоримо задачу й прорахуємо рішення." ) ); ?></p>
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
