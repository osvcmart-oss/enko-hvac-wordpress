<?php
/**
 * Медіа-інфраструктура сайту (див. memory: site-images-architecture).
 *
 * Файли в uploads/enko/ — WebP-фони/іконки зі стабільними іменами, на які
 * посилаються CSS/JS/шаблони НАПРЯМУ (без srcset). Проміжні розміри для них
 * нікому не потрібні, а оптимізатор картинок рахує КОЖНУ мініатюру у квоту
 * плану (2026-07-13: 35 webp породили 199 мініатюр і спалили Free Trial).
 * Тому для enko/ мініатюри не генеруємо зовсім. Звичайні завантаження
 * (фото товарів у uploads/РРРР/ММ/) не зачіпаємо — їм розміри потрібні.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'intermediate_image_sizes_advanced', function ( $sizes, $metadata, $attachment_id ) {
	$file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( '' === $file && ! empty( $metadata['file'] ) ) { $file = (string) $metadata['file']; }
	if ( 0 === strpos( $file, 'enko/' ) ) { return array(); }
	return $sizes;
}, 10, 3 );
