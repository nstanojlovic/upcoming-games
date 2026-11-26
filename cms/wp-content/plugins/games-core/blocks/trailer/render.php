<?php
/**
 * Server render for games-core/trailer: privacy-friendly YouTube embed.
 *
 * @var WP_Block $block
 */

$post_id = $block->context['postId'] ?? get_the_ID();
$url     = (string) get_post_meta( $post_id, 'trailer_url', true );

if ( ! preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m ) ) {
	return;
}

printf(
	'<figure %1$s><iframe src="https://www.youtube-nocookie.com/embed/%2$s" title="%3$s" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe></figure>',
	get_block_wrapper_attributes( [ 'class' => 'games-trailer' ] ),
	esc_attr( $m[1] ),
	esc_attr( sprintf( __( '%s trailer', 'games-core' ), get_the_title( $post_id ) ) )
);
