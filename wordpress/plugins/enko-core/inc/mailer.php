<?php
/**
 * SMTP-відправка через власну пошту (Websupport) — заявки/сповіщення йдуть з
 * info@enkogroup.com.ua. Параметри зберігаються в опціях (Settings → ENKO).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Налаштувати PHPMailer на SMTP, якщо вказано хост. */
add_action( 'phpmailer_init', function ( $phpmailer ) {
	$host = enko_opt( 'smtp_host', '' );
	if ( ! $host ) { return; }
	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = enko_opt( 'smtp_user', '' );
	$phpmailer->Password   = enko_opt( 'smtp_pass', '' );
	$phpmailer->Port       = (int) enko_opt( 'smtp_port', 465 );
	$phpmailer->SMTPSecure = enko_opt( 'smtp_secure', 'ssl' ); // 'ssl' (465) | 'tls' (587)
} );

/** Відправник = info@enkogroup.com.ua, імʼя «ENKO». */
add_filter( 'wp_mail_from', function ( $email ) {
	$from = enko_opt( 'smtp_from', '' );
	return $from ? $from : $email;
}, 20 );
add_filter( 'wp_mail_from_name', function ( $name ) {
	$n = enko_opt( 'smtp_from_name', 'ENKO' );
	return $n ? $n : $name;
}, 20 );
