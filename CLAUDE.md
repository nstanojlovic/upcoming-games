# Upcoming (upcoming-games)

Upcoming video game releases for the next 90 days, week by week. Portfolio project for Nemanja Stanojlović (WordPress/Laravel developer, Belgrade), meant to show headless WordPress + Gutenberg + a modern frontend. Portfolio first; may be monetized later (featured slots, store affiliate links).

- Live (planned): https://games.anonemus.dev (frontend), https://cms.games.anonemus.dev (WordPress)
- Repo: github.com/nstanojlovic/upcoming-games (public)

## Working with Nemanja

- Describes himself as new to AI-assisted dev tooling. Give exact commands and say where to run them; explain *why* briefly.
- Prefers that you just do the work, then summarize what changed and what he needs to run.
- Commits go under his personal identity: `Nemanja Stanojlović <nstanojlovic@hotmail.com>` (set automatically by `includeIf` for `~/Personal-Sites/`). Remote uses the `github-personal` SSH host alias.

## Layout

```
upcoming-games/
├── cms/                         WordPress install (only the plugin below is tracked in git)
│   └── wp-content/plugins/games-core/
│       ├── games-core.php       bootstrap
│       ├── includes/
│       │   ├── PostTypes.php    `game` CPT, `platform` + `genre` taxonomies, REST meta, ?orderby=release_date|anticipation, ?after_date=
│       │   ├── Igdb.php         IGDB client: Twitch token (cached transient), one Apicalypse query, normalize()
│       │   ├── Importer.php     daily WP-Cron import + Tools → Games import page
│       │   ├── Rebuild.php      deploy-webhook trigger (1/min lock + one deferred follow-up)
│       │   ├── Blocks.php       registers blocks + "Game details" meta box
│       │   └── Cli.php          `wp games import`, `wp games rebuild`
│       └── blocks/{countdown,trailer}/   dynamic blocks, plain JS, no build step (block.json + index.asset.php)
└── web/                         Astro 7 static site
    └── src/
        ├── lib/wp.ts            fetch + normalize games from REST (getGames, getFeatured, getPlatforms)
        ├── lib/dates.ts         UTC date helpers, groupByWeek, monthsFor (sidebar calendar)
        ├── lib/platforms.ts     short platform labels + device family
        ├── layouts/Base.astro   header/footer, color tokens, countdown script
        ├── components/          FeaturedSlider, Browse (listing + sidebar + genre filter), GameCard, PlatformIcon, StoreButtons
        └── pages/               index, games/[slug], platform/[slug], 404
```

## Data model (meta on `game`, all in REST under `meta`)

`release_date` (Y-m-d), `release_tba`, `cover_url`, `hero_url` (wide artwork), `featured` (editor flag → homepage slider), `developer`, `publisher`, `website`, `trailer_url`, `anticipation` (IGDB hypes), `source_id`, `source_url` (IGDB page), `stores` (array of `{store, url}`).

Importer rules: re-imports only refresh facts (date, cover, hero, anticipation, stores, platforms, genres). Never overwrite editor content, and only fill `trailer_url` if empty. New games are created as `publish` (override with `GAMES_IMPORT_STATUS`).

## Key decisions

- **IGDB, not RAWG.** IGDB is free for non-commercial use under the Twitch Developer Service Agreement; commercial use needs a partnership (partner@igdb.com). The site must show "Data from IGDB.com" (footer does).
- **No brand logos.** Platform icons and store buttons use generic device/bag icons plus text ("PS5", "View on Steam"). Console makers' and stores' logos are trademarks. Keep it that way.
- No Nintendo eShop links exist in IGDB.
- Blocks have no build step on purpose (no npm needed for the plugin).
- Astro is fully static; countdowns recount in the browser between builds. Genre filter is client-side with `?genre=` in the URL.

## Design

- Fonts (Bunny Fonts): **Unbounded** for dates/countdown numbers only, **Instrument Sans** for everything else.
- Tokens in `Base.astro`: `--paper`, `--raised`, `--ink`, `--muted`, `--line`, and the slime green split into `--accent` (#4DFF5E, fills only), `--accent-ink` (text on green), `--accent-text` (green used as text: deep green in light mode, neon in dark).
- Light + dark via `prefers-color-scheme`. No autoplay, no decorative motion; respect reduced motion.

## Local development

- WordPress: **http://games-cms.test** (`herd link games-cms` inside `cms/`). MySQL on 127.0.0.1:3306, user root, same password as his other local sites. DB `upcoming_games`.
- `cms/wp-config.php` needs: `GAMES_IGDB_CLIENT_ID`, `GAMES_IGDB_CLIENT_SECRET`, optional `GAMES_REBUILD_HOOK`.
- Import: wp-admin → Tools → Games import → Run import now.
- Frontend: `cd web && npm run dev` (Node ≥ 22.23). Reads `WP_URL`, default `http://games-cms.test`.

## Deploy plan (not done yet)

Forge server `161.35.174.2` (user `forge`, his personal server, also hosts anonemus.dev and job-agent).

1. **Node 22 on the server** (currently 18 default; anonemus uses nvm 21.6.2). `nvm install 22`, then `nvm use 22` in this site's deploy script.
2. **cms.games.anonemus.dev:** WordPress site, MySQL DB, wp-config constants above, real cron instead of WP-Cron (`DISABLE_WP_CRON` + Forge scheduler running `wp cron event run --due-now`), deploy plugin from this repo.
3. **games.anonemus.dev:** static site; deploy script `cd web && npm ci && WP_URL=https://cms.games.anonemus.dev npm run build`, web root `web/dist`.
4. Put the static site's Forge deploy-trigger URL into `GAMES_REBUILD_HOOK` so publishing in WordPress rebuilds the site.

## Open items

- Verify store buttons and slider artwork after a fresh import (both depend on fields added to the importer later; old posts need a re-import).
- Verify the new slider + sidebar layout renders correctly (written without a local preview).
- Deploy (above). Then add the project to anonemus.dev `sideProjects`.
- Possible next: a "Where to buy" Gutenberg block so editors can override stores, affiliate tags, more filters.
