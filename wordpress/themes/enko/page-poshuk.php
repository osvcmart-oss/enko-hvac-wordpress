<?php
/**
 * Сторінка результатів пошуку (/poshuk/). Серверний рендер через
 * enko_search_query() (inc/search.php плагіна) — працює і без JS. Шапковий
 * пошук дублює сюди ?q=. Шаблон використовується для сторінки зі слагом «poshuk».
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$res = ( '' !== $q && function_exists( 'enko_search_query' ) )
	? enko_search_query( $q, 48 )
	: array( 'products' => array(), 'categories' => array(), 'total' => 0 );
$tt = function_exists( 'et' ) ? 'et' : 'strval';

get_header();
?>
<main class="enko-sr">
	<div class="enko-sr__head">
		<h1><?php echo esc_html( call_user_func( $tt, 'Пошук по каталогу' ) ); ?></h1>
		<?php if ( '' !== $q ) : ?>
			<p><?php
				/* translators: %1$s = к-сть, %2$s = запит */
				printf(
					esc_html( call_user_func( $tt, 'Знайдено %1$s за запитом «%2$s»' ) ),
					'<b>' . (int) $res['total'] . '</b>',
					esc_html( $q )
				);
			?></p>
		<?php endif; ?>
	</div>

	<form class="enko-sr__form" role="search" method="get" action="<?php echo esc_url( home_url( '/poshuk/' ) ); ?>">
		<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>"
			placeholder="<?php echo esc_attr( call_user_func( $tt, 'Назва, категорія або характеристика…' ) ); ?>"
			aria-label="<?php echo esc_attr( call_user_func( $tt, 'Пошук' ) ); ?>" autofocus>
		<button type="submit"><?php echo esc_html( call_user_func( $tt, 'Знайти' ) ); ?></button>
	</form>

	<?php if ( ! empty( $res['categories'] ) ) : ?>
		<div class="enko-sr__cats">
			<?php foreach ( $res['categories'] as $c ) : ?>
				<a href="<?php echo esc_url( $c['url'] ); ?>">
					<svg viewBox="0 0 24 24" width="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
					<?php echo esc_html( $c['name'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $res['products'] ) ) : ?>
		<div class="enko-sr__rows">
			<?php foreach ( $res['products'] as $p ) :
				$meta = array();
				if ( $p['cat'] ) { $meta[] = $p['cat']; }
				if ( ! empty( $p['specs'] ) ) { $meta[] = $p['specs']; }
			?>
				<a class="enko-sr__row" href="<?php echo esc_url( $p['url'] ); ?>">
					<span class="enko-sr__rimg"><?php if ( $p['img'] ) : ?><img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>" loading="lazy"><?php endif; ?></span>
					<span class="enko-sr__rmain">
						<span class="enko-sr__rname"><?php echo esc_html( $p['name'] ); ?></span>
						<?php if ( $meta ) : ?><span class="enko-sr__rmeta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
					</span>
					<?php if ( $p['uah'] ) : ?>
						<span class="enko-sr__rprice"><?php echo esc_html( number_format( (float) $p['uah'], 0, '.', ' ' ) ); ?> ₴<?php
							if ( $p['eur'] ) { echo ' <small>/ ' . esc_html( number_format( (float) $p['eur'], 0, '.', ' ' ) ) . ' €</small>'; }
						?></span>
					<?php endif; ?>
					<span class="enko-sr__rbtn"><?php echo esc_html( call_user_func( $tt, 'Детальніше' ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php elseif ( '' !== $q ) : ?>
		<div class="enko-sr__empty">
			<p><?php echo esc_html( call_user_func( $tt, 'За вашим запитом нічого не знайдено. Спробуйте іншу назву чи категорію.' ) ); ?></p>
			<p><a href="<?php echo esc_url( function_exists( 'enko_shop_url' ) ? enko_shop_url() : home_url( '/shop/' ) ); ?>"><?php echo esc_html( call_user_func( $tt, 'Переглянути весь каталог →' ) ); ?></a></p>
		</div>
	<?php else : ?>
		<div class="enko-sr__empty">
			<p><?php echo esc_html( call_user_func( $tt, 'Введіть назву товару, категорію або характеристику.' ) ); ?></p>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
