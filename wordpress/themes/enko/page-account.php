<?php
/**
 * Сторінка «Кабінет» (/account/). Каркас + gate; застосунок рендерить
 * enko-account.js із REST enko/v1/account/me. Окремий id #enko-account-root
 * (НЕ #account-page), щоб прототипний enko.js не чіпав цю сторінку.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'et' ) ) { function et( $s ) { return $s; } }
get_header();
?>
<div class="container" id="enko-account-root">
  <nav class="breadcrumbs" aria-label="<?php echo esc_attr( et( 'Хлібні крихти' ) ); ?>"><ol>
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( et( 'Головна' ) ); ?></a></li><li class="sep">/</li><li aria-current="page"><?php echo esc_html( et( 'Кабінет' ) ); ?></li>
  </ol></nav>

  <div class="account-gate" style="display:none">
    <div class="account-gate__ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg></div>
    <h2><?php echo esc_html( et( 'Ви ще не увійшли' ) ); ?></h2>
    <p><?php echo esc_html( et( 'Увійдіть у свій акаунт або зареєструйтесь як партнер ENKO, щоб отримати особистий кабінет: чат із підтримкою, закріпленого менеджера та історію ваших заявок.' ) ); ?></p>
    <div class="account-gate__actions">
      <button class="btn btn--primary" data-auth-open type="button"><?php echo esc_html( et( 'Увійти' ) ); ?></button>
      <button class="btn btn--ghost" data-partner-open type="button"><?php echo esc_html( et( 'Стати партнером' ) ); ?></button>
    </div>
  </div>

  <div id="enko-account-app" style="display:none"><!-- rendered by enko-account.js --></div>
</div>
<?php
get_footer();
