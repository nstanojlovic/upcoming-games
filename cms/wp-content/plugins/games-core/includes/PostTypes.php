<?php

namespace GamesCore;

/**
 * The `game` post type, its taxonomies and the meta fields
 * the Astro frontend reads over the REST API.
 */
final class PostTypes {

	/** Meta fields exposed in REST under `meta`. */
	public const META = [
		'release_date' => [ 'type' => 'string', 'description' => 'Release date, Y-m-d.' ],
		'release_tba'  => [ 'type' => 'boolean', 'description' => 'Date is a placeholder (TBA).' ],
		'cover_url'    => [ 'type' => 'string', 'description' => 'Cover image URL (IGDB CDN).' ],
		'hero_url'     => [ 'type' => 'string', 'description' => 'Wide artwork or screenshot URL (IGDB CDN), for the homepage slider.' ],
		'featured'     => [ 'type' => 'boolean', 'description' => 'Show in the homepage slider. Set by an editor.' ],
		'developer'    => [ 'type' => 'string' ],
		'publisher'    => [ 'type' => 'string' ],
		'website'      => [ 'type' => 'string' ],
		'trailer_url'  => [ 'type' => 'string', 'description' => 'YouTube URL, set by an editor.' ],
		'anticipation' => [ 'type' => 'integer', 'description' => 'IGDB "hypes": follows before release. Used for sorting.' ],
		'source_id'    => [ 'type' => 'integer', 'description' => 'IGDB game ID.' ],
		'source_url'   => [ 'type' => 'string', 'description' => 'IGDB page, for attribution.' ],
	];

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_types' ] );
		add_filter( 'rest_game_collection_params', [ self::class, 'collection_params' ] );
		add_filter( 'rest_game_query', [ self::class, 'rest_query' ], 10, 2 );
	}

	public static function register_types(): void {
		register_post_type( 'game', [
			'labels'        => [
				'name'          => __( 'Games', 'games-core' ),
				'singular_name' => __( 'Game', 'games-core' ),
				'add_new_item'  => __( 'Add game', 'games-core' ),
				'edit_item'     => __( 'Edit game', 'games-core' ),
			],
			'public'        => true,
			'show_in_rest'  => true,
			'rest_base'     => 'games',
			'menu_icon'     => 'dashicons-games',
			'menu_position' => 5,
			'has_archive'   => false,
			'rewrite'       => [ 'slug' => 'games' ],
			'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
			// New games open with a sensible starting layout.
			'template'      => [
				[ 'games-core/countdown' ],
				[ 'games-core/trailer' ],
				[ 'core/paragraph', [ 'placeholder' => 'What is this game, and why is it worth waiting for?' ] ],
			],
		] );

		foreach ( [ 'platform' => 'Platforms', 'genre' => 'Genres' ] as $taxonomy => $label ) {
			register_taxonomy( $taxonomy, 'game', [
				'label'             => __( $label, 'games-core' ),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => $taxonomy ],
			] );
		}

		register_post_meta( 'game', 'stores', [
			'type'          => 'array',
			'description'   => 'Store pages, e.g. [{ store: "steam", url: "https://..." }]. Synced from IGDB.',
			'single'        => true,
			'default'       => [],
			'auth_callback' => fn () => current_user_can( 'edit_posts' ),
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'store' => [ 'type' => 'string' ],
							'url'   => [ 'type' => 'string', 'format' => 'uri' ],
						],
					],
				],
			],
		] );

		foreach ( self::META as $key => $args ) {
			register_post_meta( 'game', $key, [
				'type'          => $args['type'],
				'description'   => $args['description'] ?? '',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => fn () => current_user_can( 'edit_posts' ),
			] );
		}
	}

	/** Allow ?orderby=release_date and ?after_date=Y-m-d on /wp/v2/games. */
	public static function collection_params( array $params ): array {
		$params['orderby']['enum'][] = 'release_date';
		$params['orderby']['enum'][] = 'anticipation';

		$params['after_date'] = [
			'description' => 'Only games releasing on or after this date (Y-m-d).',
			'type'        => 'string',
			'format'      => 'date',
		];

		return $params;
	}

	public static function rest_query( array $args, \WP_REST_Request $request ): array {
		$orderby = $request->get_param( 'orderby' );

		if ( in_array( $orderby, [ 'release_date', 'anticipation' ], true ) ) {
			$args['meta_key'] = $orderby;
			$args['orderby']  = $orderby === 'anticipation' ? 'meta_value_num' : 'meta_value';
		}

		if ( $after = $request->get_param( 'after_date' ) ) {
			$args['meta_query'][] = [
				'key'     => 'release_date',
				'value'   => $after,
				'compare' => '>=',
				'type'    => 'DATE',
			];
		}

		return $args;
	}
}
