<?php
/**
 * Резервні копії БД — кнопка в кабінеті менеджера (/manager/), у патерні
 * inc/catalog-sync.php: кнопка → REST enko/v1/mgr/backup → дамп → звіт.
 *
 * Дамп робиться чистим PHP через $wpdb (на сервері НЕМА WP-CLI/mysqldump):
 * усі таблиці з префіксом WP → SHOW CREATE TABLE + рядки пачками → потоково
 * у .sql.gz (gzopen/gzwrite, мала памʼять). Transients (кеш) пропускаються.
 *
 * БЕЗПЕКА: дамп містить хеші паролів, email-и, замовлення (персональні дані).
 *  - зберігається у wp-content/enko-backups/<випадковий-токен>/…sql.gz;
 *  - тека закрита .htaccess (Apache/LiteSpeed) + index.php;
 *  - завантаження ЛИШЕ через авторизований хендлер (manage_woocommerce + nonce),
 *    а не прямим публічним лінком (імʼя-тека нерозгадуване як другий рубіж).
 * Ретенція: зберігаються останні ENKO_BACKUP_KEEP бекапів, старіші — авто-видалення.
 *
 * Google Drive: enko_backup_push_gdrive() РЕАЛІЗОВАНА, але поки no-op — поле
 * URL (Settings → ENKO) порожнє. Коли впишете webhook-URL приймача (Apps Script
 * web-app) — кожен новий бекап автоматично відвантажиться туди.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'ENKO_BACKUP_KEEP' ) ) { define( 'ENKO_BACKUP_KEEP', 5 ); }

/** Вміст .htaccess, що блокує прямий доступ (Apache/LiteSpeed). */
function enko_backup_htaccess() {
	return "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
}

/** Закрити теку від веб-доступу (.htaccess + index.php). */
function enko_backup_secure_dir( $dir ) {
	if ( ! file_exists( $dir . '/.htaccess' ) ) { @file_put_contents( $dir . '/.htaccess', enko_backup_htaccess() ); }
	if ( ! file_exists( $dir . '/index.php' ) )  { @file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); }
}

/** Базова тека бекапів (створює й закриває за потреби). */
function enko_backup_dir() {
	$base = WP_CONTENT_DIR . '/enko-backups';
	if ( ! is_dir( $base ) ) { wp_mkdir_p( $base ); }
	enko_backup_secure_dir( $base );
	return $base;
}

/** Потоковий дамп усіх WP-таблиць у .sql.gz. Повертає лічильники або WP_Error. */
function enko_backup_dump_to_gz( $gzpath ) {
	global $wpdb;
	$gz = gzopen( $gzpath, 'wb9' );
	if ( ! $gz ) { return new WP_Error( 'gz', 'Не вдалося створити файл бекапу (gzopen).' ); }

	gzwrite( $gz, "-- ENKO DB backup\n-- Site: " . home_url( '/' ) . "\n-- Date: " . current_time( 'mysql' )
		. "\n-- WP: " . get_bloginfo( 'version' ) . "\n-- Prefix: " . $wpdb->prefix . "\n" );
	gzwrite( $gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" );

	$like   = $wpdb->esc_like( $wpdb->prefix ) . '%';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	$tcount = 0; $rcount = 0;

	foreach ( $tables as $table ) {
		$create = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
		if ( ! $create || ! isset( $create[1] ) ) { continue; }
		gzwrite( $gz, "\n-- Table: $table\nDROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n" );
		$tcount++;

		// wp_options: не дампимо тимчасовий кеш (transients) — чистіший і менший бекап.
		$where = '';
		if ( $table === $wpdb->options ) {
			$where = " WHERE option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%'";
		}

		$offset = 0; $batch = 500; $cols = null;
		while ( true ) {
			$sql  = "SELECT * FROM `$table`$where LIMIT $offset, $batch";
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! $rows ) { break; }
			foreach ( $rows as $row ) {
				if ( null === $cols ) { $cols = '`' . implode( '`,`', array_keys( $row ) ) . '`'; }
				$vals = array();
				foreach ( $row as $v ) {
					$vals[] = ( null === $v ) ? 'NULL' : "'" . $wpdb->_real_escape( $v ) . "'";
				}
				gzwrite( $gz, "INSERT INTO `$table` ($cols) VALUES (" . implode( ',', $vals ) . ");\n" );
				$rcount++;
			}
			$offset += $batch;
			if ( count( $rows ) < $batch ) { break; }
		}
	}

	gzwrite( $gz, "\nSET FOREIGN_KEY_CHECKS=1;\n" );
	gzclose( $gz );
	return array( 'tables' => $tcount, 'rows' => $rcount );
}

/**
 * Відвантажити бекап у Google Drive через webhook-URL (Apps Script web-app).
 * РЕАЛІЗОВАНА, але якщо URL не задано — пропускає (no-op). Нічого не шле зараз.
 */
function enko_backup_push_gdrive( $filepath ) {
	$url = enko_opt( 'gdrive_webhook_url', '' );
	if ( ! $url ) {
		return array( 'status' => 'skipped', 'msg' => 'Google Drive не налаштовано (порожній URL).' );
	}
	$bytes = @file_get_contents( $filepath );
	if ( false === $bytes ) {
		return array( 'status' => 'error', 'msg' => 'Не вдалося прочитати файл для відвантаження.' );
	}
	$resp = wp_remote_post( $url, array(
		'timeout' => 60,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'filename' => basename( $filepath ),
			'mime'     => 'application/gzip',
			'data_b64' => base64_encode( $bytes ),
		) ),
	) );
	if ( is_wp_error( $resp ) ) {
		return array( 'status' => 'error', 'msg' => $resp->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$okc  = ( $code >= 200 && $code < 300 );
	return array(
		'status' => $okc ? 'ok' : 'error',
		'http'   => $code,
		'msg'    => $okc ? 'Відвантажено в Google Drive.' : ( 'Google Drive відповів HTTP ' . $code . '.' ),
	);
}

/** Видалити старі бекапи понад $keep (за датою файлу). */
function enko_backup_prune( $keep = ENKO_BACKUP_KEEP ) {
	$dirs = glob( enko_backup_dir() . '/*', GLOB_ONLYDIR );
	if ( ! $dirs ) { return; }
	usort( $dirs, function ( $a, $b ) { return filemtime( $b ) - filemtime( $a ); } );
	foreach ( array_slice( $dirs, max( 0, (int) $keep ) ) as $d ) {
		foreach ( glob( $d . '/*' ) as $f ) { @unlink( $f ); }
		@unlink( $d . '/.htaccess' );
		@rmdir( $d );
	}
}

/** Створити бекап: дамп → push GDrive → ретенція. Повертає звіт. */
function enko_backup_create() {
	$base  = enko_backup_dir();
	$token = bin2hex( random_bytes( 16 ) );
	$dir   = $base . '/' . $token;
	wp_mkdir_p( $dir );
	enko_backup_secure_dir( $dir );

	$filename = 'enko-db-' . gmdate( 'Ymd-His' ) . '.sql.gz';
	$path     = $dir . '/' . $filename;

	$t0  = microtime( true );
	$res = enko_backup_dump_to_gz( $path );
	if ( is_wp_error( $res ) ) {
		@unlink( $path ); @unlink( $dir . '/.htaccess' ); @unlink( $dir . '/index.php' ); @rmdir( $dir );
		return array( 'ok' => false, 'msg' => $res->get_error_message() );
	}
	$size   = file_exists( $path ) ? filesize( $path ) : 0;
	$gdrive = enko_backup_push_gdrive( $path );
	enko_backup_prune( ENKO_BACKUP_KEEP );

	return array(
		'ok'      => true,
		'token'   => $token,
		'filename'=> $filename,
		'size'    => $size,
		'size_h'  => size_format( $size, 1 ),
		'tables'  => $res['tables'],
		'rows'    => $res['rows'],
		'seconds' => round( microtime( true ) - $t0, 2 ),
		'gdrive'  => $gdrive,
	);
}

/** Список наявних бекапів (найновіші перші) з URL завантаження. */
function enko_backup_list() {
	$dirs = glob( enko_backup_dir() . '/*', GLOB_ONLYDIR );
	$out  = array();
	if ( $dirs ) {
		foreach ( $dirs as $dir ) {
			$files = glob( $dir . '/*.sql.gz' );
			if ( ! $files ) { continue; }
			$f     = $files[0];
			$token = basename( $dir );
			$out[] = array(
				'token'    => $token,
				'filename' => basename( $f ),
				'size'     => filesize( $f ),
				'size_h'   => size_format( filesize( $f ), 1 ),
				'date'     => date_i18n( 'd.m.Y H:i', filemtime( $f ) ),
				'mtime'    => filemtime( $f ),
				'url'      => add_query_arg(
					array( 'enko_backup_dl' => $token, '_wpnonce' => wp_create_nonce( 'enko_backup_dl' ) ),
					home_url( '/' )
				),
			);
		}
		usort( $out, function ( $a, $b ) { return $b['mtime'] - $a['mtime']; } );
	}
	return $out;
}

/** Захищене завантаження бекапу (manage_woocommerce + nonce), стрім файлу. */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['enko_backup_dl'] ) ) { return; }
	if ( ! current_user_can( 'manage_woocommerce' ) ) { status_header( 403 ); exit( 'Forbidden' ); }
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'enko_backup_dl' ) ) { status_header( 403 ); exit( 'Bad nonce' ); }
	$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['enko_backup_dl'] ) );
	if ( 32 !== strlen( $token ) ) { status_header( 400 ); exit( 'Bad token' ); }
	$files = glob( enko_backup_dir() . '/' . $token . '/*.sql.gz' );
	if ( ! $files ) { status_header( 404 ); exit( 'Not found' ); }
	$f = $files[0];
	nocache_headers();
	header( 'Content-Type: application/gzip' );
	header( 'Content-Disposition: attachment; filename="' . basename( $f ) . '"' );
	header( 'Content-Length: ' . filesize( $f ) );
	readfile( $f );
	exit;
} );

/**
 * Адмін-блок керування бекапами — рендериться на сторінці Налаштування → ENKO
 * (викликається з inc/settings.php). Кнопка «Створити», список, завантаження,
 * видалення. WP-нативно: дії через admin-post.php + admin-notice (без JS/REST).
 */
function enko_backup_admin_block() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }

	// Повідомлення про результат останньої дії (з transient після redirect).
	$key    = 'enko_backup_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( false !== $notice ) {
		delete_transient( $key );
		if ( is_array( $notice ) && ! empty( $notice['restored'] ) ) {
			$cls = empty( $notice['stmt_fail'] ) ? 'notice-success' : 'notice-warning';
			echo '<div class="notice ' . $cls . ' is-dismissible"><p>' . esc_html( $notice['msg'] );
			if ( ! empty( $notice['safety'] ) ) { echo ' <em>(запобіжна копія попереднього стану: ' . esc_html( $notice['safety'] ) . ')</em>'; }
			if ( ! empty( $notice['errors'] ) ) { echo '<br><small>' . esc_html( implode( ' | ', $notice['errors'] ) ) . '</small>'; }
			echo '</p></div>';
		} elseif ( is_array( $notice ) && ! empty( $notice['ok'] ) && isset( $notice['size_h'] ) ) {
			$g    = isset( $notice['gdrive'] ) ? $notice['gdrive'] : array();
			$gmsg = ( isset( $g['status'] ) && 'ok' === $g['status'] ) ? ' · Google Drive: відвантажено ✓'
				: ( ( isset( $g['status'] ) && 'skipped' === $g['status'] ) ? ' · Google Drive: не налаштовано'
				: ( isset( $g['msg'] ) ? ' · Google Drive: ' . $g['msg'] : '' ) );
			printf(
				'<div class="notice notice-success is-dismissible"><p>✓ Бекап створено: %s · таблиць %d · рядків %s · %s c%s</p></div>',
				esc_html( $notice['size_h'] ), (int) $notice['tables'],
				esc_html( number_format_i18n( (int) $notice['rows'] ) ), esc_html( $notice['seconds'] ), esc_html( $gmsg )
			);
		} elseif ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>Бекап видалено.</p></div>';
		} elseif ( is_array( $notice ) && isset( $notice['msg'] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $notice['msg'] ) );
		}
	}

	$list = enko_backup_list();
	?>
	<h2>Резервні копії бази даних</h2>
	<p class="description">Створює стиснений дамп бази (товари, замовлення, клієнти, налаштування, чати) і зберігає на сервері. Зберігаються останні <?php echo (int) ENKO_BACKUP_KEEP; ?>. Бекап містить персональні дані — пряме завантаження закрите, доступ лише звідси. Google Drive (опційно) — у блоці «Бекапи» нижче.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0 14px">
		<?php wp_nonce_field( 'enko_backup_create' ); ?>
		<input type="hidden" name="action" value="enko_backup_create">
		<?php submit_button( 'Створити бекап БД', 'primary', 'submit', false ); ?>
	</form>
	<table class="widefat striped" style="max-width:760px">
		<thead><tr><th>Дата</th><th>Розмір</th><th style="width:230px">Дії</th></tr></thead>
		<tbody>
		<?php if ( ! $list ) : ?>
			<tr><td colspan="3"><em>Бекапів ще немає.</em></td></tr>
		<?php else : foreach ( $list as $b ) : ?>
			<tr>
				<td><?php echo esc_html( $b['date'] ); ?></td>
				<td><?php echo esc_html( $b['size_h'] ); ?></td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( $b['url'] ); ?>">Завантажити</a>
					<?php if ( current_user_can( 'manage_options' ) ) :
						$rconfirm = 'var a=prompt(\'⚠ УВАГА: відновлення ПЕРЕЗАПИШЕ всю поточну базу даними з цього бекапу.\nПеред відновленням автоматично створиться запобіжна копія поточного стану.\nМожливо, доведеться знову увійти в адмінку.\n\nЩоб підтвердити — введіть слово ВІДНОВИТИ:\'); return a===\'ВІДНОВИТИ\';'; ?>
						<a class="button button-small" style="color:#8a6d00;border-color:#c8a04a" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=enko_backup_restore&token=' . $b['token'] ), 'enko_backup_restore_' . $b['token'] ) ); ?>" onclick="<?php echo esc_attr( $rconfirm ); ?>">Відновити</a>
					<?php endif; ?>
					<a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=enko_backup_delete&token=' . $b['token'] ), 'enko_backup_delete_' . $b['token'] ) ); ?>" onclick="return confirm('Видалити цей бекап?');">Видалити</a>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
	<?php
}

/** admin-post: створити бекап → redirect назад на сторінку налаштувань із повідомленням. */
add_action( 'admin_post_enko_backup_create', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Недостатньо прав.' ); }
	check_admin_referer( 'enko_backup_create' );
	try {
		$r = enko_backup_create();
	} catch ( Throwable $e ) {
		$r = array( 'ok' => false, 'msg' => $e->getMessage() );
	}
	set_transient( 'enko_backup_notice_' . get_current_user_id(), $r, 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=enko-settings' ) );
	exit;
} );

/** admin-post: видалити конкретний бекап. */
add_action( 'admin_post_enko_backup_delete', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Недостатньо прав.' ); }
	$token = isset( $_GET['token'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['token'] ) ) : '';
	check_admin_referer( 'enko_backup_delete_' . $token );
	if ( 32 === strlen( $token ) ) {
		$dir = enko_backup_dir() . '/' . $token;
		if ( is_dir( $dir ) ) {
			foreach ( glob( $dir . '/*' ) as $f ) { @unlink( $f ); }
			@unlink( $dir . '/.htaccess' );
			@rmdir( $dir );
		}
	}
	set_transient( 'enko_backup_notice_' . get_current_user_id(), 'deleted', 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=enko-settings' ) );
	exit;
} );

/**
 * Відновити базу з бекапу (ПЕРЕЗАПИСУЄ всю БД). Перед відновленням — запобіжний
 * бекап поточного стану (щоб дію можна було відкотити). Виконує дамп по одному
 * стейтменту (межа = ";\n"; переноси рядків у значеннях екрановані → надійно).
 */
function enko_backup_restore( $token ) {
	global $wpdb;
	$files = glob( enko_backup_dir() . '/' . $token . '/*.sql.gz' );
	if ( ! $files ) { return array( 'ok' => false, 'msg' => 'Файл бекапу не знайдено.' ); }

	// Читаємо дамп У ПАМʼЯТЬ ДО запобіжного бекапу (раптом ретенція видалить цей файл).
	$sql = gzdecode( (string) file_get_contents( $files[0] ) );
	if ( false === $sql || '' === trim( (string) $sql ) ) {
		return array( 'ok' => false, 'msg' => 'Не вдалося розпакувати файл бекапу.' );
	}

	// Запобіжний бекап поточного стану ПЕРЕД відновленням.
	try {
		$safety = enko_backup_create();
	} catch ( Throwable $e ) {
		return array( 'ok' => false, 'msg' => 'Запобіжний бекап не створився (' . $e->getMessage() . '). Відновлення скасовано.' );
	}
	if ( empty( $safety['ok'] ) ) {
		return array( 'ok' => false, 'msg' => 'Запобіжний бекап не створився. Відновлення скасовано.' );
	}

	$uid = get_current_user_id();
	@set_time_limit( 0 );
	ignore_user_abort( true );

	$statements = explode( ";\n", $sql );
	$ok = 0; $fail = 0; $errors = array();
	$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
	foreach ( $statements as $stmt ) {
		$clean = trim( preg_replace( '/^--.*$/m', '', $stmt ) );
		if ( '' === $clean ) { continue; }
		$res = $wpdb->query( $clean );
		if ( false === $res ) {
			$fail++;
			if ( count( $errors ) < 6 ) { $errors[] = mb_substr( (string) $wpdb->last_error, 0, 160 ); }
		} else {
			$ok++;
		}
	}
	$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
	wp_cache_flush();

	// Усермета session_tokens перезаписано бекапом → поновлюємо власну сесію, щоб не вилетіти.
	if ( $uid && get_userdata( $uid ) ) { wp_set_auth_cookie( $uid, true ); }

	return array(
		'ok'        => ( 0 === $fail ),
		'restored'  => true,
		'stmt_ok'   => $ok,
		'stmt_fail' => $fail,
		'errors'    => $errors,
		'safety'    => isset( $safety['filename'] ) ? $safety['filename'] : '',
		'msg'       => $fail
			? ( 'Відновлено з ' . $fail . ' помилками (виконано ' . $ok . ' операцій). Перевірте сайт; за потреби відновіть запобіжну копію.' )
			: ( 'Базу відновлено успішно (' . $ok . ' операцій). Створено запобіжну копію попереднього стану.' ),
	);
}

/** admin-post: відновити базу з бекапу (лише адміністратор). */
add_action( 'admin_post_enko_backup_restore', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Відновлення доступне лише адміністратору.' ); }
	$token = isset( $_GET['token'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['token'] ) ) : '';
	check_admin_referer( 'enko_backup_restore_' . $token );
	if ( 32 !== strlen( $token ) ) {
		$r = array( 'ok' => false, 'msg' => 'Невірний ідентифікатор бекапу.' );
	} else {
		try {
			$r = enko_backup_restore( $token );
		} catch ( Throwable $e ) {
			$r = array( 'ok' => false, 'msg' => 'Помилка відновлення: ' . $e->getMessage() );
		}
	}
	set_transient( 'enko_backup_notice_' . get_current_user_id(), $r, 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=enko-settings' ) );
	exit;
} );
