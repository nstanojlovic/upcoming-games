<?php

namespace GamesCore;

use RuntimeException;

/**
 * IGDB API client (https://api-docs.igdb.com).
 *
 * Auth is a Twitch app token (client credentials), cached until it expires.
 * Free for non-commercial use under the Twitch Developer Service Agreement;
 * commercial use needs a partnership with IGDB.
 *
 * Set in wp-config.php:
 *   define( 'GAMES_IGDB_CLIENT_ID', '...' );
 *   define( 'GAMES_IGDB_CLIENT_SECRET', '...' );
 */
final class Igdb {

	private const API   = 'https://api.igdb.com/v4';
	private const TOKEN = 'https://id.twitch.tv/oauth2/token';

	public function __construct(
		private readonly string $client_id,
		private readonly string $client_secret,
	) {}

	public static function from_config(): self {
		if ( ! defined( 'GAMES_IGDB_CLIENT_ID' ) || ! defined( 'GAMES_IGDB_CLIENT_SECRET' ) ) {
			throw new RuntimeException( 'Define GAMES_IGDB_CLIENT_ID and GAMES_IGDB_CLIENT_SECRET in wp-config.php.' );
		}

		return new self( GAMES_IGDB_CLIENT_ID, GAMES_IGDB_CLIENT_SECRET );
	}

	/**
	 * Main games (no DLC, bundles or ports) releasing between two dates,
	 * most followed first, normalized for the importer.
	 *
	 * @return list<array>
	 */
	public function upcoming( string $from, string $to, int $max = 200 ): array {
		$from_ts = strtotime( "{$from} 00:00:00 UTC" );
		$to_ts   = strtotime( "{$to} 23:59:59 UTC" );
		$limit   = min( $max, 500 );

		$query = <<<APICALYPSE
			fields name, slug, url, summary, first_release_date, hypes,
				cover.image_id, platforms.name, genres.name,
				involved_companies.developer, involved_companies.publisher, involved_companies.company.name,
				websites.url, websites.type, videos.video_id,
				external_games.url, external_games.uid, external_games.category, external_games.external_game_source,
				artworks.image_id, screenshots.image_id;
			where first_release_date >= {$from_ts} & first_release_date <= {$to_ts}
				& parent_game = null & version_parent = null;
			sort hypes desc;
			limit {$limit};
			APICALYPSE;

		return array_map( [ $this, 'normalize' ], $this->post( '/games', $query ) );
	}

	/** Store links, in display order. IGDB website types and external game sources mapped to one key. */
	private const WEBSITE_STORES = [ 13 => 'steam', 16 => 'epic', 17 => 'gog', 15 => 'itch', 10 => 'apple', 11 => 'apple', 12 => 'google-play' ];
	private const EXTERNAL_STORES = [ 1 => 'steam', 26 => 'epic', 5 => 'gog', 36 => 'playstation', 31 => 'xbox', 11 => 'microsoft', 30 => 'itch', 13 => 'apple', 15 => 'google-play' ];
	private const STORE_ORDER = [ 'steam', 'epic', 'gog', 'playstation', 'xbox', 'microsoft', 'itch', 'apple', 'google-play' ];

	/** @return list<array{store:string, url:string}> */
	private function stores( array $game ): array {
		$found = [];

		foreach ( $game['websites'] ?? [] as $site ) {
			$store = self::WEBSITE_STORES[ (int) ( $site['type'] ?? 0 ) ] ?? null;
			if ( $store && ! empty( $site['url'] ) ) {
				$found[ $store ] ??= $site['url'];
			}
		}

		foreach ( $game['external_games'] ?? [] as $ext ) {
			$source = (int) ( $ext['external_game_source'] ?? $ext['category'] ?? 0 );
			$store  = self::EXTERNAL_STORES[ $source ] ?? null;
			$url    = $ext['url'] ?? '';

			// Steam records often carry only the app ID.
			if ( ! $url && $store === 'steam' && ! empty( $ext['uid'] ) && ctype_digit( (string) $ext['uid'] ) ) {
				$url = "https://store.steampowered.com/app/{$ext['uid']}/";
			}

			if ( $store && $url ) {
				$found[ $store ] ??= $url;
			}
		}

		$stores = [];
		foreach ( self::STORE_ORDER as $store ) {
			if ( isset( $found[ $store ] ) && str_starts_with( $found[ $store ], 'http' ) ) {
				$stores[] = [ 'store' => $store, 'url' => $found[ $store ] ];
			}
		}

		return $stores;
	}

	private function normalize( array $game ): array {
		$companies = $game['involved_companies'] ?? [];
		$company   = fn ( string $role ) => implode( ', ', array_map(
			fn ( $c ) => $c['company']['name'] ?? '',
			array_filter( $companies, fn ( $c ) => ! empty( $c[ $role ] ) )
		) );

		// Website type 1 is the official site.
		$official = array_values( array_filter( $game['websites'] ?? [], fn ( $w ) => (int) ( $w['type'] ?? 0 ) === 1 ) );
		$video    = $game['videos'][0]['video_id'] ?? null;
		$cover    = $game['cover']['image_id'] ?? null;
		$wide     = $game['artworks'][0]['image_id'] ?? $game['screenshots'][0]['image_id'] ?? null;

		return [
			'id'           => (int) $game['id'],
			'slug'         => $game['slug'] ?? sanitize_title( $game['name'] ),
			'name'         => $game['name'],
			'url'          => $game['url'] ?? '',
			'summary'      => $game['summary'] ?? '',
			'release_date' => gmdate( 'Y-m-d', (int) $game['first_release_date'] ),
			'anticipation' => (int) ( $game['hypes'] ?? 0 ),
			'cover_url'    => $cover ? "https://images.igdb.com/igdb/image/upload/t_cover_big_2x/{$cover}.jpg" : '',
			'hero_url'     => $wide ? "https://images.igdb.com/igdb/image/upload/t_1080p/{$wide}.jpg" : '',
			'platforms'    => array_column( $game['platforms'] ?? [], 'name' ),
			'genres'       => array_column( $game['genres'] ?? [], 'name' ),
			'developer'    => $company( 'developer' ),
			'publisher'    => $company( 'publisher' ),
			'website'      => $official[0]['url'] ?? '',
			'trailer_url'  => $video ? "https://www.youtube.com/watch?v={$video}" : '',
			'stores'       => $this->stores( $game ),
		];
	}

	private function post( string $endpoint, string $body ): array {
		$response = wp_remote_post( self::API . $endpoint, [
			'timeout' => 30,
			'headers' => [
				'Client-ID'     => $this->client_id,
				'Authorization' => 'Bearer ' . $this->token(),
				'Accept'        => 'application/json',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'IGDB request failed: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 401 ) {
			delete_transient( 'games_core_igdb_token' ); // Token revoked or expired early; next run gets a new one.
		}

		if ( $code !== 200 ) {
			throw new RuntimeException( "IGDB returned HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: [];
	}

	private function token(): string {
		if ( $token = get_transient( 'games_core_igdb_token' ) ) {
			return $token;
		}

		$response = wp_remote_post( add_query_arg( [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type'    => 'client_credentials',
		], self::TOKEN ), [ 'timeout' => 15 ] );

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_wp_error( $response ) || empty( $data['access_token'] ) ) {
			throw new RuntimeException( 'Could not get a Twitch token. Check the IGDB client ID and secret.' );
		}

		set_transient( 'games_core_igdb_token', $data['access_token'], max( 60, (int) $data['expires_in'] - 300 ) );

		return $data['access_token'];
	}
}
