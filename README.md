# Upcoming

Upcoming video game releases for the next 90 days, week by week.

**Live:** [games.anonemus.dev](https://games.anonemus.dev)

A headless WordPress project: editors work in Gutenberg, visitors get a fast static site built with Astro. Release data is imported daily from [IGDB](https://www.igdb.com).

```
upcoming-games/
├── cms/wp-content/plugins/games-core/   WordPress plugin (the only tracked part of cms/)
└── web/                                 Astro frontend
```

## How it fits together

```
IGDB API ──daily──► WordPress (games-core plugin) ──REST API──► Astro build ──► static HTML
                         │                                         ▲
                         └──── publish / update ── webhook ────────┘
```

**WordPress (`games-core` plugin)**
- `game` post type with `platform` and `genre` taxonomies, all exposed in the REST API, with extra `orderby=release_date|anticipation` and `after_date` query params.
- Importer (WP-Cron daily, a Tools → Games import button, and `wp games import`) pulls the most-followed main games releasing in the next 90 days in a single IGDB request. Twitch app tokens are cached until they expire.
- Re-imports only refresh facts (date, cover, platforms, anticipation). Anything written by an editor, including a hand-picked trailer, is never overwritten.
- Two dynamic Gutenberg blocks with no build step: **Release countdown** and **Game trailer** (privacy-friendly YouTube embed), both reading from the "Game details" box.
- Calls a deploy webhook when content changes, at most once a minute, with one follow-up so the last edit always ships, and once per import batch.

**Astro (`web/`)**
- Fully static. Reads all games at build time from the REST API.
- Homepage: the most anticipated release in the next two weeks with a live day count, then a calendar grouped by week.
- A page per game and per platform. The Gutenberg blocks render inside the game content and are styled by the frontend.
- Countdowns recount in the browser, so they stay right between rebuilds.
- Light and dark theme.

## Local setup

Requirements: PHP 8.1+, MySQL, Node 22+, [Laravel Herd](https://herd.laravel.com) (or any local PHP server).

```bash
# WordPress
cd cms   # download WordPress core here, then:
herd link games-cms
```

Add to `cms/wp-config.php`:

```php
define( 'GAMES_IGDB_CLIENT_ID', '...' );      // from dev.twitch.tv/console
define( 'GAMES_IGDB_CLIENT_SECRET', '...' );
define( 'GAMES_REBUILD_HOOK', '' );            // deploy URL of the Astro site (optional locally)
```

Activate **Games Core**, then run **Tools → Games import**.

```bash
# Astro
cd web
npm install
npm run dev      # reads http://games-cms.test by default; set WP_URL to change it
```

## Credits

Game data and cover art from [IGDB.com](https://www.igdb.com), used under the Twitch Developer Service Agreement.
