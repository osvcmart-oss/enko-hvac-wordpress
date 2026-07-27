<?php
/**
 * ENKO settings page — replaces the prototype admin.html controls.
 * Settings → ENKO. Stores site options consumed by the front-end JS and pricing.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'ENKO', 'enko-core' ),
		__( 'ENKO', 'enko-core' ),
		'manage_options',
		'enko-settings',
		'enko_render_settings_page'
	);
} );

/**
 * Курс €→₴ з адмінки: "45", "45.5", "45,50" — кома чи крапка, максимум 2 знаки
 * після розділювача, діапазон 1–1000. Невалідне значення НЕ зберігаємо —
 * повертаємо чинний курс (вимога замовника 2026-07-13).
 */
function enko_sanitize_eur_rate( $v ) {
	$v = str_replace( ',', '.', trim( (string) $v ) );
	if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $v ) ) { return enko_opt( 'eur_rate', 45 ); }
	$f = round( (float) $v, 2 );
	if ( $f < 1 || $f > 1000 ) { return enko_opt( 'eur_rate', 45 ); }
	return $f;
}

add_action( 'admin_init', function () {
	$fields = array(
		'enko_eur_rate'      => 'enko_sanitize_eur_rate',
		'enko_delay_lead'    => 'absint',
		'enko_delay_callbar' => 'absint',
		'enko_delay_cookie'  => 'absint',
		'enko_work_start'    => 'sanitize_text_field',
		'enko_work_end'      => 'sanitize_text_field',
		'enko_manager_email' => 'sanitize_email',
		'enko_default_manager' => 'absint',
		'enko_catalog_csv_url' => 'esc_url_raw',
		'enko_gdrive_webhook_url' => 'esc_url_raw',
		'enko_phone'         => 'sanitize_text_field',
		'enko_email'         => 'sanitize_email',
		'enko_viber'         => 'sanitize_text_field',
		'enko_whatsapp'      => 'sanitize_text_field',
		'enko_tg_token'      => 'sanitize_text_field',
		'enko_tg_chat'       => 'sanitize_text_field',
		'enko_tg_link'       => 'esc_url_raw',
		'enko_smtp_host'     => 'sanitize_text_field',
		'enko_smtp_port'     => 'absint',
		'enko_smtp_user'     => 'sanitize_text_field',
		'enko_smtp_pass'     => 'sanitize_text_field',
		'enko_smtp_secure'   => 'sanitize_text_field',
		'enko_smtp_from'     => 'sanitize_email',
		'enko_smtp_from_name'=> 'sanitize_text_field',
	);
	foreach ( $fields as $name => $cb ) {
		register_setting( 'enko_settings', $name, array( 'sanitize_callback' => $cb ) );
	}
} );

function enko_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	?>
	<div class="wrap">
		<h1>ENKO — налаштування сайту</h1>
		<?php if ( function_exists( 'enko_backup_admin_block' ) ) { enko_backup_admin_block(); } ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'enko_settings' ); ?>
			<h2>Курс і ціни</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="enko_eur_rate">1 € = N грн</label></th>
					<td><input name="enko_eur_rate" id="enko_eur_rate" type="text" inputmode="decimal" maxlength="7"
						pattern="\d+([.,]\d{1,2})?" title="Число, максимум 2 знаки після крапки/коми, напр. 45.50"
						value="<?php echo esc_attr( enko_opt( 'eur_rate', 45 ) ); ?>" class="small-text"> грн
						<p class="description">Гривневі ціни на сайті показуються як EUR × курс. Кома чи крапка, до 2 знаків після розділювача: 45 · 45.5 · 45,50.</p></td>
				</tr>
			</table>

			<h2>Робочі години (Пн–Пт)</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Початок / Кінець</th>
					<td>
						<input name="enko_work_start" type="time" value="<?php echo esc_attr( enko_opt( 'work_start', '09:00' ) ); ?>">
						—
						<input name="enko_work_end" type="time" value="<?php echo esc_attr( enko_opt( 'work_end', '18:00' ) ); ?>">
						<p class="description">У діапазоні Пн–Пт — робочий час (онлайн-чат, автодзвінок). Поза ним — «неробочі» версії попапів.</p>
					</td>
				</tr>
			</table>

			<h2>Затримки попапів / барів (секунди)</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Лід-форма</th><td><input name="enko_delay_lead" type="number" min="0" value="<?php echo esc_attr( enko_opt( 'delay_lead', 30 ) ); ?>" class="small-text"></td></tr>
				<tr><th scope="row">Колбек-бар</th><td><input name="enko_delay_callbar" type="number" min="0" value="<?php echo esc_attr( enko_opt( 'delay_callbar', 60 ) ); ?>" class="small-text"></td></tr>
				<tr><th scope="row">Cookie-банер</th><td><input name="enko_delay_cookie" type="number" min="0" value="<?php echo esc_attr( enko_opt( 'delay_cookie', 0 ) ); ?>" class="small-text"> <span class="description">0 = одразу</span></td></tr>
			</table>

			<h2>Каталог (Google Sheet)</h2>
			<p class="description">CSV-посилання на аркуш каталогу. Менеджер натискає «Синхронізувати каталог» у кабінеті <code>/manager/</code> — товари створюються/оновлюються за артикулом (SKU). Як отримати посилання: у таблиці <b>Файл → Поділитися → Опублікувати в Інтернеті → аркуш «Кондиціонери» → CSV</b>, або відкрити доступ «Усі, хто має посилання → Глядач».</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">URL CSV-каталогу</th><td><input name="enko_catalog_csv_url" type="url" class="large-text" value="<?php echo esc_attr( enko_opt( 'catalog_csv_url', '' ) ); ?>" placeholder="https://docs.google.com/spreadsheets/d/.../gviz/tq?tqx=out:csv&sheet=Кондиціонери"></td></tr>
			</table>

			<h2>Бекапи (резервні копії БД)</h2>
			<p class="description">Кнопка <b>«Створити бекап БД»</b>, список і завантаження — <b>угорі цієї сторінки</b> (блок «Резервні копії бази даних»). Зберігаються останні <?php echo (int) ( defined( 'ENKO_BACKUP_KEEP' ) ? ENKO_BACKUP_KEEP : 5 ); ?>; бекап містить персональні дані — пряме завантаження закрите. <b>Google Drive</b> (необовʼязково): впишіть URL приймача (Google Apps Script web-app) у поле нижче — і кожен новий бекап автоматично відвантажуватиметься туди. Поки порожньо — бекапи лише на сервері.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">URL Google Drive (webhook)</th><td><input name="enko_gdrive_webhook_url" type="url" class="large-text" value="<?php echo esc_attr( enko_opt( 'gdrive_webhook_url', '' ) ); ?>" placeholder="(поки порожньо) https://script.google.com/macros/s/.../exec"></td></tr>
			</table>

			<h2>Контакти (шапка та підвал сайту)</h2>
			<p class="description">Ці значення показуються у верхній панелі, підвалі та на кнопках месенджерів по всьому сайту. Telegram — поле «Telegram-канал» у блоці «Сповіщення» нижче.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Телефон</th><td>
					<input name="enko_phone" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'phone', '+380 777 147 777' ) ); ?>" placeholder="+380 XX XXX XX XX">
					<p class="description">Показується у шапці й підвалі. Кнопка «подзвонити» формується автоматично з цифр номера.</p></td></tr>
				<tr><th scope="row">Email</th><td><input name="enko_email" type="email" class="regular-text" value="<?php echo esc_attr( enko_opt( 'email', 'info@enkogroup.com.ua' ) ); ?>"></td></tr>
				<tr><th scope="row">Viber (номер)</th><td><input name="enko_viber" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'viber', '' ) ); ?>" placeholder="+380XXXXXXXXX — порожньо = телефон вище"></td></tr>
				<tr><th scope="row">WhatsApp (номер)</th><td><input name="enko_whatsapp" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'whatsapp', '' ) ); ?>" placeholder="+380XXXXXXXXX — порожньо = телефон вище"></td></tr>
			</table>

			<h2>Telegram-чат та сповіщення</h2>
				<p class="description">Telegram тут виконує дві ролі: (1) <b>чат-міст</b> — повідомлення клієнтів з кабінету потрапляють у супергрупу з Темами, де менеджер відповідає прямо з Telegram, а відповідь повертається в кабінет клієнта; (2) <b>сповіщення</b> — нові заявки/реєстрації/ліди приходять у головну тему «General». Поля нижче.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Email менеджера</th><td><input name="enko_manager_email" type="email" class="regular-text" value="<?php echo esc_attr( enko_opt( 'manager_email', get_option( 'admin_email' ) ) ); ?>"></td></tr>
				<tr><th scope="row">Менеджер за замовчуванням</th><td>
					<select name="enko_default_manager">
						<option value="0">— не призначати —</option>
						<?php
						$cur_mgr = (int) enko_opt( 'default_manager', 0 );
						foreach ( enko_manager_users() as $m ) {
							printf( '<option value="%d" %s>%s</option>', (int) $m->ID, selected( $cur_mgr, $m->ID, false ), esc_html( $m->display_name ) );
						}
						?>
					</select>
					<p class="description">Закріплюється за новими реєстраціями. Контакти менеджера (посада/телефон/Telegram) — у профілі цього користувача.</p>
				</td></tr>
				<tr><th scope="row">Telegram бот-токен</th><td><input name="enko_tg_token" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'tg_token', '' ) ); ?>" autocomplete="off">
						<p class="description">Токен бота-моста від <b>@BotFather</b> (зараз <code>EnkoSupportBot</code>). Через нього сайт надсилає повідомлення в Telegram і приймає відповіді менеджера. Тримайте в таємниці — це ключ доступу до бота.</p></td></tr>
				<tr><th scope="row">Telegram chat ID</th><td><input name="enko_tg_chat" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'tg_chat', '' ) ); ?>">
						<p class="description">ID супергрупи з увімкненими <b>Темами (Topics)</b>, де для кожного клієнта створюється окрема тема-переписка. Це від'ємне число виду <code>-100…</code>. Заповнюється автоматично при підключенні моста — вручну змінюйте лише за потреби.</p></td></tr>
				<tr><th scope="row">Telegram-канал (CTA)</th><td><input name="enko_tg_link" type="url" class="regular-text" value="<?php echo esc_attr( enko_opt( 'tg_link', 'https://t.me/EnkoGroup' ) ); ?>">
						<p class="description">Публічне <code>t.me</code>-посилання для кнопок «Написати в Telegram» на сайті (для <b>неавторизованих</b> відвідувачів). Це звичайне посилання на акаунт/канал, <b>не</b> бот-токен і не chat ID.</p></td></tr>
			</table>

			<h2>Пошта (SMTP)</h2>
			<p class="description">Відправка листів сайту через власну пошту. З'єднання перевірене для Websupport.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">SMTP-хост</th><td><input name="enko_smtp_host" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'smtp_host', '' ) ); ?>" placeholder="smtp.example.com"></td></tr>
				<tr><th scope="row">Порт</th><td><input name="enko_smtp_port" type="number" class="small-text" value="<?php echo esc_attr( enko_opt( 'smtp_port', 465 ) ); ?>"></td></tr>
				<tr><th scope="row">Шифрування</th><td>
					<select name="enko_smtp_secure">
						<option value="ssl" <?php selected( enko_opt( 'smtp_secure', 'ssl' ), 'ssl' ); ?>>SSL/TLS (465)</option>
						<option value="tls" <?php selected( enko_opt( 'smtp_secure', 'ssl' ), 'tls' ); ?>>STARTTLS (587)</option>
					</select></td></tr>
				<tr><th scope="row">Логін</th><td><input name="enko_smtp_user" type="text" class="regular-text" value="<?php echo esc_attr( enko_opt( 'smtp_user', '' ) ); ?>" autocomplete="off"></td></tr>
				<tr><th scope="row">Пароль</th><td><input name="enko_smtp_pass" type="password" class="regular-text" value="<?php echo esc_attr( enko_opt( 'smtp_pass', '' ) ); ?>" autocomplete="new-password"></td></tr>
				<tr><th scope="row">Відправник (From)</th><td><input name="enko_smtp_from" type="email" class="regular-text" value="<?php echo esc_attr( enko_opt( 'smtp_from', '' ) ); ?>"> <input name="enko_smtp_from_name" type="text" value="<?php echo esc_attr( enko_opt( 'smtp_from_name', 'ENKO' ) ); ?>" placeholder="Ім'я відправника"></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
