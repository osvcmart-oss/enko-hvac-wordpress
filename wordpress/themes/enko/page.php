<?php
/**
 * Page template — Elementor-friendly (renders the_content, which Elementor
 * replaces with its own canvas on marketing pages).
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<div class="container enko-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="enko-content"><?php $ru = ( function_exists( 'enko_is_ru' ) && enko_is_ru() ) ? get_post_meta( get_the_ID(), '_enko_content_ru', true ) : ''; if ( $ru ) { echo apply_filters( 'the_content', $ru ); } else { the_content(); } ?></div>
	<?php endwhile; ?>
</div>
<?php
get_footer();
