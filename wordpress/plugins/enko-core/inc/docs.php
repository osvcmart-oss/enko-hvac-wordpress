<?php
/**
 * ENKO — документи товару (PDF): зберігання, рендер-хелпери, адмін-поле.
 *
 * Документи живуть у product-meta `_enko_docs` = масив ['id'=>attach_id,'label'=>''].
 * PDP (themes/enko/woocommerce/single-product.php) рендерить їх як .doc-item[data-doc];
 * прототипний серверний home-r2.js initDocPreview() дає прев'ю-лайтбокс + завантаження
 * (JS не патчимо — клієнтська частина вже на сервері).
 *
 * Джерела документів:
 *  - адмін-поле «📄 Документи (PDF)» у картці товару (медіа-пікер);
 *  - стовпець `doc_filenames` у Google Sheet (inc/catalog-sync.php → enko_doc_resolve_filenames).
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Ім'я файлу → людська укр. назва документа (авто-підпис, якщо явну не задано). */
function enko_doc_label_from_filename( $fn ) {
	$base = preg_replace( '/\.[a-z0-9]+$/i', '', (string) $fn );
	$low  = mb_strtolower( $base );
	if ( preg_match( '/declaration|conformity|деклар|відповідн/u', $low ) ) { return 'Декларація відповідності'; }
	if ( preg_match( '/product[\s_-]?sheet|datasheet|паспорт|технічн/u', $low ) ) { return 'Технічний лист (Product Sheet)'; }
	if ( preg_match( '/remote|пульт/u', $low ) ) { return 'Інструкція пульта'; }
	if ( preg_match( '/manual|інструкц|user[\s_-]?guide|exploat|експлуат/u', $low ) ) { return 'Інструкція з експлуатації'; }
	if ( preg_match( '/warranty|гарант/u', $low ) ) { return 'Гарантійний талон'; }
	$pretty = trim( preg_replace( '/[\s_-]+/u', ' ', $base ) );
	return '' !== $pretty ? $pretty : 'Документ';
}

/** Розмір у байтах → «957 КБ» / «1.4 МБ» (укр. одиниці, як у прототипі). */
function enko_doc_size_h( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) { return ''; }
	if ( $bytes < 1024 * 1024 ) { return round( $bytes / 1024 ) . ' КБ'; }
	return round( $bytes / ( 1024 * 1024 ), 1 ) . ' МБ';
}

/** ID вкладення за іменем файлу (зіставлення з медіабібліотекою). */
function enko_doc_attachment_id_by_filename( $fn ) {
	global $wpdb;
	$fn = trim( (string) $fn );
	if ( '' === $fn ) { return 0; }
	$aid = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
		'%' . $wpdb->esc_like( $fn )
	) );
	return (int) $aid;
}

/**
 * Парс рядка `Назва|файл.pdf; файл2.pdf` (роздільник `;` або новий рядок) →
 * масив ['id'=>int,'label'=>string] (імена зіставляються з медіабібліотекою).
 * Невідомі імена пропускаються; дублікати ID — теж.
 */
function enko_doc_resolve_filenames( $spec ) {
	$out  = array();
	$seen = array();
	$rows = preg_split( '/[;\r\n]+/', (string) $spec );
	foreach ( $rows as $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) { continue; }
		$label = '';
		if ( false !== strpos( $raw, '|' ) ) {
			$parts = array_map( 'trim', explode( '|', $raw, 2 ) );
			$label = $parts[0];
			$raw   = $parts[1];
		}
		$aid = enko_doc_attachment_id_by_filename( $raw );
		if ( $aid && ! isset( $seen[ $aid ] ) ) {
			$seen[ $aid ] = 1;
			$out[]        = array( 'id' => $aid, 'label' => $label );
		}
	}
	return $out;
}

/**
 * База SKU без числового суфікса версії: `EN-AC-KAYSUN-CASUAL-26` → `EN-AC-KAYSUN-CASUAL`.
 * Це ключ «групи» (лінійки) — той самий, що групує версії на PDP. Документи логічно
 * належать ГРУПІ, тож у таблиці doc_filenames має бути однаковий для всіх рядків групи.
 */
function enko_doc_sku_base( $sku ) {
	$sku = trim( (string) $sku );
	return preg_match( '/^(.*)-(\d+)$/', $sku, $m ) ? $m[1] : $sku;
}

/** Нормалізований (порядко-незалежний) набір імен файлів зі spec — для порівняння рядків групи. */
function enko_doc_norm_spec( $spec ) {
	$names = array();
	foreach ( preg_split( '/[;\r\n]+/', (string) $spec ) as $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) { continue; }
		if ( false !== strpos( $raw, '|' ) ) { $raw = trim( substr( $raw, strpos( $raw, '|' ) + 1 ) ); }
		$names[] = mb_strtolower( $raw );
	}
	sort( $names );
	return implode( ',', $names );
}

/**
 * Попередження про розбіжність документів у межах груп (рядки з одним префіксом SKU,
 * але різними doc_filenames). Повертає масив текстів для звіту синхронізації.
 */
function enko_doc_group_warnings( $rows ) {
	$groups = array();
	foreach ( $rows as $row ) {
		$sku = isset( $row['sku'] ) ? trim( $row['sku'] ) : '';
		if ( '' === $sku ) { continue; }
		$base = enko_doc_sku_base( $sku );
		$groups[ $base ][ $sku ] = enko_doc_norm_spec( isset( $row['doc_filenames'] ) ? $row['doc_filenames'] : '' );
	}
	$warnings = array();
	foreach ( $groups as $base => $bysku ) {
		if ( count( $bysku ) < 2 ) { continue; }
		if ( count( array_unique( array_values( $bysku ) ) ) > 1 ) {
			$warnings[] = 'Документи: у групі «' . $base . '» різні файли в рядках (' . implode( ', ', array_keys( $bysku ) ) . ') — впишіть ОДНАКОВИЙ doc_filenames у кожен рядок групи.';
		}
	}
	return $warnings;
}

/** Готовий до рендера список документів товару: [['title','url','size_h'], …]. */
function enko_product_docs( $product ) {
	if ( ! $product instanceof WC_Product ) { return array(); }
	$raw = $product->get_meta( '_enko_docs' );
	if ( ! is_array( $raw ) || ! $raw ) { return array(); }
	$docs = array();
	foreach ( $raw as $d ) {
		$id = isset( $d['id'] ) ? (int) $d['id'] : 0;
		if ( ! $id ) { continue; }
		$url = wp_get_attachment_url( $id );
		if ( ! $url ) { continue; }
		$path  = get_attached_file( $id );
		$fname = $path ? basename( $path ) : basename( (string) $url );
		$label = isset( $d['label'] ) ? trim( (string) $d['label'] ) : '';
		$docs[] = array(
			'title'  => ( function_exists( 'et' ) ? et( '' !== $label ? $label : enko_doc_label_from_filename( $fname ) ) : ( '' !== $label ? $label : enko_doc_label_from_filename( $fname ) ) ),
			'url'    => $url,
			'size_h' => ( $path && file_exists( $path ) ) ? enko_doc_size_h( filesize( $path ) ) : '',
		);
	}
	return $docs;
}

/* ============ Адмін-поле: медіа-пікер PDF у картці товару ============ */

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) { return; }
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'product' === $screen->post_type ) {
		wp_enqueue_media();
	}
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'enko-docs', '📄 Документи (PDF)', 'enko_docs_metabox', 'product', 'side', 'default' );
} );

/** Метабокс: список обраних PDF + кнопка медіа-пікера; стан у прихованому JSON-полі. */
function enko_docs_metabox( $post ) {
	$raw = get_post_meta( $post->ID, '_enko_docs', true );
	if ( ! is_array( $raw ) ) { $raw = array(); }
	$init = array();
	foreach ( $raw as $d ) {
		$id = isset( $d['id'] ) ? (int) $d['id'] : 0;
		if ( ! $id ) { continue; }
		$path   = get_attached_file( $id );
		$init[] = array(
			'id'    => $id,
			'label' => isset( $d['label'] ) ? (string) $d['label'] : '',
			'name'  => $path ? basename( $path ) : ( 'ID ' . $id ),
		);
	}
	wp_nonce_field( 'enko_docs_save', 'enko_docs_nonce' );
	?>
	<div id="enko-docs-box">
		<p class="description" style="margin:0 0 8px">PDF-документи товару (декларації, технічні листи, інструкції). Показуються в табі «Документація» на сторінці товару.</p>
		<ul id="enko-docs-list" style="margin:8px 0;padding:0;list-style:none"></ul>
		<button type="button" class="button" id="enko-docs-add">Обрати PDF…</button>
		<textarea name="enko_docs_json" id="enko-docs-json" style="display:none"><?php echo esc_textarea( wp_json_encode( $init ) ); ?></textarea>
	</div>
	<script>
	(function () {
		var data = <?php echo wp_json_encode( $init ); ?> || [];
		var list = document.getElementById('enko-docs-list');
		var json = document.getElementById('enko-docs-json');
		function sync() { json.value = JSON.stringify(data.map(function (d) { return { id: d.id, label: d.label || '' }; })); }
		function render() {
			list.innerHTML = '';
			data.forEach(function (d, i) {
				var li = document.createElement('li');
				li.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0 0 6px;padding:7px;border:1px solid #dcdcde;border-radius:6px;background:#fff';
				var nm = document.createElement('span'); nm.textContent = d.name || ('ID ' + d.id);
				nm.style.cssText = 'flex:0 0 100%;font-size:11px;color:#646970;word-break:break-all';
				var inp = document.createElement('input'); inp.type = 'text'; inp.value = d.label || '';
				inp.placeholder = 'Назва (необов’язково — авто)'; inp.style.cssText = 'flex:1;min-width:0';
				inp.addEventListener('input', function () { d.label = inp.value; sync(); });
				var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'button-link'; rm.textContent = '✕';
				rm.setAttribute('aria-label', 'Прибрати'); rm.style.cssText = 'color:#b32d2e;flex:none';
				rm.addEventListener('click', function () { data.splice(i, 1); render(); sync(); });
				li.appendChild(nm); li.appendChild(inp); li.appendChild(rm); list.appendChild(li);
			});
		}
		var frame;
		document.getElementById('enko-docs-add').addEventListener('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: 'Оберіть PDF-документи', button: { text: 'Додати' }, library: { type: 'application/pdf' }, multiple: true });
			frame.on('select', function () {
				frame.state().get('selection').each(function (att) {
					var a = att.toJSON();
					if (!data.some(function (d) { return d.id === a.id; })) {
						data.push({ id: a.id, label: '', name: a.filename || a.title || ('ID ' + a.id) });
					}
				});
				render(); sync();
			});
			frame.open();
		});
		render(); sync();
	})();
	</script>
	<?php
}

/** Збереження метабокса (хук save_post_product). */
add_action( 'save_post_product', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['enko_docs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['enko_docs_nonce'] ) ), 'enko_docs_save' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$json = isset( $_POST['enko_docs_json'] ) ? wp_unslash( $_POST['enko_docs_json'] ) : '[]';
	$arr  = json_decode( $json, true );
	$docs = array();
	if ( is_array( $arr ) ) {
		foreach ( $arr as $d ) {
			$id = isset( $d['id'] ) ? (int) $d['id'] : 0;
			if ( $id ) {
				$docs[] = array( 'id' => $id, 'label' => isset( $d['label'] ) ? sanitize_text_field( $d['label'] ) : '' );
			}
		}
	}
	if ( $docs ) {
		update_post_meta( $post_id, '_enko_docs', $docs );
	} else {
		delete_post_meta( $post_id, '_enko_docs' );
	}
} );
