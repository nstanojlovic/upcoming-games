<?php
/**
 * Server render for games-core/countdown.
 * Outputs a <time> element; the frontend turns it into a live count.
 *
 * @var array    $attributes
 * @var WP_Block $block
 */

$post_id = $block->context['postId'] ?? get_the_ID();
$date    = get_post_meta( $post_id, 'release_date', true );
$tba     = (bool) get_post_meta( $post_id, 'release_tba', true );
$label   = $attributes['label'] ?? 'Out in';

$wrapper = get_block_wrapper_attributes( [ 'class' => 'games-countdown' ] );

if ( ! $date || $tba ) {
	printf( '<div %s><span class="games-countdown__tba">%s</span></div>', $wrapper, esc_html__( 'Release date to be announced', 'games-core' ) );
	return;
}

printf(
	'<div %1$s data-label="%2$s"><time datetime="%3$s">%4$s</time></div>',
	$wrapper,
	esc_attr( $label ),
	esc_attr( $date ),
	esc_html( wp_date( get_option( 'date_format' ), strtotime( $date ) ) )
);
