/**
 * Data layer: reads games from the WordPress REST API at build time.
 * Set WP_URL in web/.env (local) or the build environment (server).
 */

const WP = (import.meta.env.WP_URL ?? 'http://games-cms.test').replace(/\/$/, '');

export type Term = { id: number; name: string; slug: string };

export type Game = {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  content: string;
  releaseDate: string; // Y-m-d
  tba: boolean;
  cover: string;
  coverSmall: string;
  hero: string;
  featured: boolean;
  developer: string;
  publisher: string;
  website: string;
  trailer: string;
  anticipation: number;
  sourceUrl: string;
  platforms: Term[];
  genres: Term[];
  stores: { store: string; url: string }[];
};

async function getAll<T>(path: string, params: Record<string, string> = {}): Promise<T[]> {
  const items: T[] = [];
  let page = 1;
  let totalPages = 1;

  do {
    const url = new URL(`${WP}/wp-json/wp/v2/${path}`);
    Object.entries({ per_page: '100', page: String(page), ...params }).forEach(([k, v]) => url.searchParams.set(k, v));

    const res = await fetch(url);
    if (!res.ok) throw new Error(`WordPress API ${res.status} for ${url}`);

    items.push(...((await res.json()) as T[]));
    totalPages = Number(res.headers.get('X-WP-TotalPages') ?? 1);
    page++;
  } while (page <= totalPages);

  return items;
}

/** WordPress returns titles with HTML entities (&#8217; etc.). */
export function decode(text: string): string {
  return text
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)))
    .replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)))
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#039;|&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&nbsp;/g, ' ');
}

const strip = (html: string) => decode(html.replace(/<[^>]+>/g, '')).trim();

let cache: Promise<Game[]> | null = null;

/** All upcoming games (today onwards), soonest first. Fetched once per build. */
export function getGames(): Promise<Game[]> {
  cache ??= load();
  return cache;
}

async function load(): Promise<Game[]> {
  const [raw, platforms, genres] = await Promise.all([
    getAll<any>('games', { _fields: 'id,slug,title,excerpt,content,meta,platform,genre', orderby: 'release_date', order: 'asc' }),
    getAll<Term>('platform', { _fields: 'id,name,slug' }),
    getAll<Term>('genre', { _fields: 'id,name,slug' }),
  ]);

  const byId = (terms: Term[]) => new Map(terms.map((t) => [t.id, { ...t, name: decode(t.name) }]));
  const platformMap = byId(platforms);
  const genreMap = byId(genres);
  const today = new Date().toISOString().slice(0, 10);

  return raw
    .map((g): Game => {
      const m = g.meta ?? {};
      const cover = m.cover_url ?? '';
      return {
        id: g.id,
        slug: g.slug,
        title: decode(g.title.rendered),
        excerpt: strip(g.excerpt.rendered),
        content: g.content.rendered,
        releaseDate: m.release_date ?? '',
        tba: Boolean(m.release_tba),
        cover,
        coverSmall: cover.replace('t_cover_big_2x', 't_cover_big'),
        hero: m.hero_url ?? '',
        featured: Boolean(m.featured),
        developer: m.developer ?? '',
        publisher: m.publisher ?? '',
        website: m.website ?? '',
        trailer: m.trailer_url ?? '',
        anticipation: Number(m.anticipation ?? 0),
        sourceUrl: m.source_url ?? '',
        platforms: (g.platform ?? []).map((id: number) => platformMap.get(id)).filter(Boolean),
        genres: (g.genre ?? []).map((id: number) => genreMap.get(id)).filter(Boolean),
        stores: Array.isArray(m.stores) ? m.stores.filter((s: any) => s?.store && /^https?:\/\//.test(s?.url ?? '')) : [],
      };
    })
    .filter((g) => g.releaseDate && g.releaseDate >= today)
    .sort((a, b) => a.releaseDate.localeCompare(b.releaseDate) || b.anticipation - a.anticipation);
}

/** Slider games: editor-featured first; topped up with the most anticipated of the next 60 days. */
export async function getFeatured(limit = 5): Promise<Game[]> {
  const games = await getGames();
  const picked = games.filter((g) => g.featured);
  const soonest = new Date(Date.now() + 60 * 86_400_000).toISOString().slice(0, 10);
  const fill = games
    .filter((g) => !g.featured && g.releaseDate <= soonest && (g.hero || g.cover))
    .sort((a, b) => b.anticipation - a.anticipation);

  return [...picked, ...fill].slice(0, limit);
}

export async function getPlatforms(): Promise<(Term & { count: number })[]> {
  const counts = new Map<string, Term & { count: number }>();
  for (const game of await getGames()) {
    for (const p of game.platforms) {
      const entry = counts.get(p.slug) ?? { ...p, count: 0 };
      entry.count++;
      counts.set(p.slug, entry);
    }
  }
  return [...counts.values()].sort((a, b) => b.count - a.count);
}

/* ------------------------------------------------------------------ */
/* News: plain WordPress posts. Write them in wp-admin → Posts.        */
/* ------------------------------------------------------------------ */

export type NewsPost = {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  content: string;
  date: string; // Y-m-d
  image: string;
  imageAlt: string;
  categories: Term[];
};

let newsCache: Promise<NewsPost[]> | null = null;

/** Published news posts, newest first. Fetched once per build. */
export function getNews(): Promise<NewsPost[]> {
  newsCache ??= loadNews();
  return newsCache;
}

async function loadNews(): Promise<NewsPost[]> {
  const [raw, categories] = await Promise.all([
    getAll<any>('posts', { _embed: 'wp:featuredmedia', orderby: 'date', order: 'desc' }),
    getAll<Term>('categories', { _fields: 'id,name,slug' }),
  ]);
  const catMap = new Map(categories.map((t) => [t.id, { ...t, name: decode(t.name) }]));

  return raw.map((p): NewsPost => {
    const media = p._embedded?.['wp:featuredmedia']?.[0];
    const sizes = media?.media_details?.sizes ?? {};
    return {
      id: p.id,
      slug: p.slug,
      title: decode(p.title.rendered),
      excerpt: strip(p.excerpt.rendered),
      content: p.content.rendered,
      date: String(p.date).slice(0, 10),
      image: sizes.large?.source_url ?? media?.source_url ?? '',
      imageAlt: media?.alt_text ?? '',
      categories: (p.categories ?? [])
        .map((id: number) => catMap.get(id))
        .filter((t: Term | undefined): t is Term => Boolean(t) && t!.slug !== 'uncategorized'),
    };
  });
}
