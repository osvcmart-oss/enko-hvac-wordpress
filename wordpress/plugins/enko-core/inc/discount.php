<?php
/**
 * Personal discount (0–99%) per registered user — mirrors prototype
 * enko_accounts_v1[user].discount + home-r2.js price decoration.
 * Stored in user-meta `enko_discount`; applied via Woo price filter.
 *
 * @package enko-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Current user's discount as a fraction (0..0.99). */
function enko_current_discount() {
	if ( ! is_user_logged_in() ) { return 0.0; }
	$d = (int) get_user_meta( get_current_user_id(), 'enko_discount', true );
	$d = max( 0, min( 99, $d ) );
	return $d / 100;
}

/** Apply the discount to the effective product price. */
add_filter( 'woocommerce_product_get_price', 'enko_apply_discount', 20, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'enko_apply_discount', 20, 2 );
function enko_apply_discount( $price, $product ) {
	$d = enko_current_discount();
	if ( $d <= 0 || '' === $price ) { return $price; }
	return round( (float) $price * ( 1 - $d ), 2 );
}

/** Show struck-through original + −N% badge when a discount applies. */
add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	$d = enko_current_discount();
	if ( $d <= 0 ) { return $html; }
	$pct = (int) round( $d * 100 );
	return $html . ' <span class="enko-badge sale">−' . $pct . '%</span>';
}, 30, 2 );

/* ---- Admin: discount field on the user profile ---- */
add_action( 'show_user_profile', 'enko_user_discount_field' );
add_action( 'edit_user_profile', 'enko_user_discount_field' );
function enko_user_discount_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	$d      = (int) get_user_meta( $user->ID, 'enko_discount', true );
	$entity = ( 'ur' === get_user_meta( $user->ID, 'enko_entity', true ) ) ? 'ur' : 'fiz';
	$city   = (string) get_user_meta( $user->ID, 'enko_city', true );
	$coop   = (string) get_user_meta( $user->ID, 'enko_coop', true );
	$edrpou = (string) get_user_meta( $user->ID, 'enko_edrpou', true );
	$mid    = (int) get_user_meta( $user->ID, 'enko_manager', true );
	?>
	<h2>ENKO — дані клієнта</h2>
	<p class="description" style="margin:-6px 0 10px">Ці поля синхронізовані з кабінетом менеджера (<code>/manager/</code>): зміни тут видно там і навпаки. Телефон і компанію редагуйте у блоці «Адреса для оплати» нижче.</p>
	<table class="form-table">
		<tr>
			<th><label for="enko_entity">Тип особи</label></th>
			<td><select name="enko_entity" id="enko_entity">
				<option value="fiz" <?php selected( $entity, 'fiz' ); ?>>Фізична особа</option>
				<option value="ur" <?php selected( $entity, 'ur' ); ?>>Юридична особа</option>
			</select></td>
		</tr>
		<tr>
			<th><label for="enko_city">Місто</label></th>
			<td><input type="text" name="enko_city" id="enko_city" value="<?php echo esc_attr( $city ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th><label for="enko_coop">Тип співпраці</label></th>
			<td><input type="text" name="enko_coop" id="enko_coop" value="<?php echo esc_attr( $coop ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th><label for="enko_edrpou">ЄДРПОУ / ІПН</label></th>
			<td><input type="text" name="enko_edrpou" id="enko_edrpou" value="<?php echo esc_attr( $edrpou ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th><label for="enko_manager">Закріплений менеджер</label></th>
			<td><select name="enko_manager" id="enko_manager">
				<option value="0">— не призначено —</option>
				<?php
				if ( function_exists( 'enko_manager_users' ) ) {
					foreach ( enko_manager_users() as $m ) {
						printf( '<option value="%d" %s>%s</option>', (int) $m->ID, selected( $mid, $m->ID, false ), esc_html( $m->display_name ) );
					}
				}
				?>
			</select></td>
		</tr>
		<tr>
			<th><label for="enko_discount">Індивідуальна знижка, %</label></th>
			<td><input type="number" min="0" max="99" name="enko_discount" id="enko_discount" value="<?php echo esc_attr( $d ); ?>" class="small-text">
				<p class="description">0–99%. Застосовується до всіх цін для цього користувача.</p></td>
		</tr>
	</table>
	<?php
}
add_action( 'personal_options_update', 'enko_save_user_discount' );
add_action( 'edit_user_profile_update', 'enko_save_user_discount' );
function enko_save_user_discount( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	if ( isset( $_POST['enko_discount'] ) ) {
		update_user_meta( $user_id, 'enko_discount', max( 0, min( 99, (int) $_POST['enko_discount'] ) ) );
	}
	if ( isset( $_POST['enko_entity'] ) ) {
		update_user_meta( $user_id, 'enko_entity', 'ur' === $_POST['enko_entity'] ? 'ur' : 'fiz' );
	}
	if ( isset( $_POST['enko_city'] ) )    { update_user_meta( $user_id, 'enko_city', sanitize_text_field( wp_unslash( $_POST['enko_city'] ) ) ); }
	if ( isset( $_POST['enko_coop'] ) )    { update_user_meta( $user_id, 'enko_coop', sanitize_text_field( wp_unslash( $_POST['enko_coop'] ) ) ); }
	if ( isset( $_POST['enko_edrpou'] ) )  { update_user_meta( $user_id, 'enko_edrpou', sanitize_text_field( wp_unslash( $_POST['enko_edrpou'] ) ) ); }
	if ( isset( $_POST['enko_manager'] ) ) { update_user_meta( $user_id, 'enko_manager', (int) $_POST['enko_manager'] ); }
}
