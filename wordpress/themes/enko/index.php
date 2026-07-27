<?php
/**
 * Generic fallback template.
 *
 * @package enko
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<div class="container enko-page">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="enko-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p>Нічого не знайдено.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
