<?php
/**
 * Сторінка «Кабінет менеджера» (/manager/) — SSM. Standalone-документ
 * (як прототип admin.html), без сайтової шапки. Доступ — лише manage_woocommerce:
 * інакше показуємо лоґін-екран. Рендер застосунку — enko-manager.js.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'wp_robots', 'wp_robots_no_robots' );
$is_mgr = current_user_can( 'manage_woocommerce' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="admin-page">
  <div class="admin-topbar">
    <div class="admin-topbar__in">
      <div>
        <b>ENKO · Кабінет менеджера (SSM)</b><br>
        <span>Керування клієнтами, знижками, чатом і курсом</span>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <a class="btn btn--ghost btn--s" href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#fff;border-color:rgba(255,255,255,.4)">На сайт</a>
        <?php if ( $is_mgr ) : ?>
          <button class="btn btn--ghost btn--s" data-admin-logout type="button" style="color:#fff;border-color:rgba(255,255,255,.4)">Вийти</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ( ! $is_mgr ) : ?>
  <!-- ====== ЛОГІН МЕНЕДЖЕРА ====== -->
  <div id="admin-login">
    <div class="admin-login">
      <h1>Вхід для менеджера</h1>
      <p>Доступ лише для співробітників ENKO. Увійдіть робочим email і паролем.</p>
      <form id="admin-login-form" novalidate>
        <div class="field"><label for="adm-login">Email або логін</label><input id="adm-login" type="text" autocomplete="username" placeholder="manager@enkogroup.com.ua"></div>
        <div class="field"><label for="adm-pass">Пароль</label><input id="adm-pass" type="password" autocomplete="current-password" placeholder="••••••••"></div>
        <div class="auth-err" id="adm-err">Невірний логін або пароль.</div>
        <button class="btn btn--primary btn--block" type="submit" style="margin-top:18px">Увійти</button>
      </form>
    </div>
  </div>

  <?php else : ?>
  <!-- ====== ЗАСТОСУНОК SSM ====== -->
  <div id="admin-app">
    <div class="admin-wrap">
      <div class="admin-rate" id="admin-rate">
        <div class="admin-rate__info">
          <h2>Курс валют (EUR → грн)</h2>
          <p class="sub">Встановіть поточний курс — гривневі ціни на всьому сайті перераховуються автоматично як EUR × курс.</p>
        </div>
        <form class="admin-rate__form" id="admin-rate-form" novalidate>
          <div class="admin-rate__row">
            <label for="rate-input">1 € =</label>
            <div class="rate-input-wrap"><input id="rate-input" type="text" inputmode="decimal" autocomplete="off" maxlength="7" pattern="\d+([.,]\d{1,2})?" title="Число, максимум 2 знаки після крапки/коми, напр. 45.50"><span>грн</span></div>
            <button class="btn btn--primary btn--m" type="submit">Застосувати курс</button>
          </div>
          <span class="admin-saved" id="rate-saved">Збережено ✓ — ціни оновлено на сайті</span>
        </form>
      </div>

      <div class="admin-rate" id="admin-catalog">
        <div class="admin-rate__info">
          <h2>Каталог з Google Sheet</h2>
          <p class="sub">Синхронізувати товари з таблиці каталогу — створює нові й оновлює наявні за артикулом (SKU). Посилання на таблицю — у Налаштування → ENKO.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-start;min-width:240px">
          <button class="btn btn--primary btn--m" id="sync-catalog" type="button">Синхронізувати каталог</button>
          <div id="sync-result" style="font-size:14px;line-height:1.5"></div>
        </div>
      </div>

      <div class="admin-rate admin-tests admin-collapse" id="admin-tests">
        <button type="button" class="admin-collapse__head" id="admin-tests-toggle" aria-expanded="false">
          <h2>Попапи та бари сайту</h2>
          <svg class="admin-collapse__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="admin-collapse__body">
          <p class="sub">Перевірте будь-який попап чи бар: відкриється головна сторінка в новій вкладці з цим станом. Встановіть, через скільки секунд кожен зʼявляється автоматично (зберігається у вашому браузері).</p>
          <div class="admin-tests__hours" id="admin-tests-hours"></div>
          <div class="admin-tests__rows" id="admin-tests-rows"></div>
        </div>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
          <h1 style="font-size:clamp(24px,3vw,30px);font-weight:700">Користувачі</h1>
          <p style="color:var(--muted);font-size:14.5px;margin-top:4px">Оберіть користувача, відредагуйте дані та задайте індивідуальну знижку. Зміни одразу впливають на ціни цього користувача в каталозі й на сторінках товарів.</p>
        </div>
        <button class="btn btn--ghost btn--m" id="admin-add-demo" type="button">
          <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h2M19 16v6M16 19h6"/></svg>
          Додати користувача
        </button>
      </div>

      <div class="admin-grid">
        <div class="admin-card">
          <h2>Зареєстровані</h2>
          <p class="sub">Список акаунтів, створених через реєстрацію на сайті.</p>
          <div class="admin-userlist" id="admin-userlist"></div>
        </div>
        <div class="admin-card" id="admin-detail"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
<?php
// Standalone-документ: подальший вивід теми не потрібен.
exit;
