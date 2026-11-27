/**
 * Browser-side filter state shared by the Filter sidebar and the views it filters
 * (homepage listing, calendar). Kept in the URL: ?platform=ps5&platform=pc&genre=rpg
 *
 * Inside a group any ticked value matches (PS5 OR PC); across groups all must match
 * (PS5-or-PC AND RPG).
 */

export type Filters = { platform: string[]; genre: string[] };
type Listener = (f: Filters) => void;

const GROUPS = ['platform', 'genre'] as const;
const listeners = new Set<Listener>();

let state: Filters = read();

function read(): Filters {
  const q = new URLSearchParams(typeof location === 'undefined' ? '' : location.search);
  return { platform: q.getAll('platform'), genre: q.getAll('genre') };
}

export const getFilters = () => state;
export const isFiltered = (f: Filters = state) => f.platform.length + f.genre.length > 0;

/** Calls `fn` now with the current state, then on every change. */
export function subscribe(fn: Listener) {
  listeners.add(fn);
  fn(state);
  return () => listeners.delete(fn);
}

export function setFilters(next: Filters) {
  state = { platform: [...next.platform], genre: [...next.genre] };

  const url = new URL(location.href);
  for (const g of GROUPS) {
    url.searchParams.delete(g);
    state[g].forEach((v) => url.searchParams.append(g, v));
  }
  history.replaceState(null, '', url);

  listeners.forEach((fn) => fn(state));
}

export const clearFilters = () => setFilters({ platform: [], genre: [] });

/** Does an element with data-platforms / data-genres (space-separated slugs) pass the filters? */
export function matches(el: HTMLElement, f: Filters = state) {
  const has = (attr: string | undefined, wanted: string[]) =>
    wanted.length === 0 || (attr ?? '').split(' ').some((s) => wanted.includes(s));
  return has(el.dataset.platforms, f.platform) && has(el.dataset.genres, f.genre);
}

/** Carry the current filters over to links (e.g. calendar month tabs). */
export function withFilters(href: string, f: Filters = state) {
  const url = new URL(href, location.href);
  for (const g of GROUPS) {
    url.searchParams.delete(g);
    f[g].forEach((v) => url.searchParams.append(g, v));
  }
  return url.pathname + url.search + url.hash;
}
