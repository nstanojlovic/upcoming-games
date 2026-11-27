/** Date helpers. All dates are plain Y-m-d strings, compared in UTC. */

const DAY = 86_400_000;
const utc = (ymd: string) => Date.UTC(+ymd.slice(0, 4), +ymd.slice(5, 7) - 1, +ymd.slice(8, 10));
const todayUtc = () => {
  const n = new Date();
  return Date.UTC(n.getUTCFullYear(), n.getUTCMonth(), n.getUTCDate());
};

export const daysUntil = (ymd: string) => Math.round((utc(ymd) - todayUtc()) / DAY);

export function formatDate(ymd: string, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long', year: 'numeric' }) {
  return new Date(utc(ymd)).toLocaleDateString('en-GB', { timeZone: 'UTC', ...opts });
}

export function countdownText(ymd: string) {
  const d = daysUntil(ymd);
  if (d <= 0) return 'Out today';
  if (d === 1) return 'Tomorrow';
  return `In ${d} days`;
}

/** Monday of the week a date falls in, as Y-m-d. */
export function weekStart(ymd: string) {
  const t = utc(ymd);
  const dow = (new Date(t).getUTCDay() + 6) % 7; // Monday = 0
  return new Date(t - dow * DAY).toISOString().slice(0, 10);
}

export type Week<T> = { start: string; end: string; label: string; range: string; items: T[] };

export function groupByWeek<T extends { releaseDate: string }>(items: T[]): Week<T>[] {
  const thisWeek = weekStart(new Date().toISOString().slice(0, 10));
  const nextWeek = new Date(utc(thisWeek) + 7 * DAY).toISOString().slice(0, 10);
  const weeks = new Map<string, Week<T>>();

  for (const item of items) {
    const start = weekStart(item.releaseDate);
    if (!weeks.has(start)) {
      const end = new Date(utc(start) + 6 * DAY).toISOString().slice(0, 10);
      const sameMonth = start.slice(5, 7) === end.slice(5, 7);
      const range = sameMonth
        ? `${+start.slice(8)}–${formatDate(end, { day: 'numeric', month: 'short' })}`
        : `${formatDate(start, { day: 'numeric', month: 'short' })} – ${formatDate(end, { day: 'numeric', month: 'short' })}`;
      const label = start === thisWeek ? 'This week' : start === nextWeek ? 'Next week' : formatDate(start, { month: 'long' });
      weeks.set(start, { start, end, label, range, items: [] });
    }
    weeks.get(start)!.items.push(item);
  }

  return [...weeks.values()];
}

export type Month = { key: string; label: string; offset: number; days: number; counts: Record<string, number> };

/** Month grids for the sidebar calendar: from this month to the month of the last release. */
export function monthsFor<T extends { releaseDate: string }>(items: T[], max = 4): Month[] {
  const counts: Record<string, number> = {};
  for (const i of items) counts[i.releaseDate] = (counts[i.releaseDate] ?? 0) + 1;

  const now = new Date();
  const last = items.at(-1)?.releaseDate ?? now.toISOString().slice(0, 10);
  const months: Month[] = [];

  for (let i = 0; i < max; i++) {
    const first = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + i, 1));
    const key = first.toISOString().slice(0, 7);
    if (key > last.slice(0, 7)) break;
    const days = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
    months.push({
      key,
      label: first.toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'long', year: 'numeric' }),
      offset: (first.getUTCDay() + 6) % 7, // Monday-first grid
      days,
      counts: Object.fromEntries(Object.entries(counts).filter(([d]) => d.startsWith(key))),
    });
  }

  return months;
}

export type CalendarDay<T> = { date: string; day: number; inMonth: boolean; past: boolean; today: boolean; items: T[] };
export type CalendarMonth<T> = { key: string; label: string; short: string; total: number; weeks: CalendarDay<T>[][] };

/**
 * Full month grids for the /calendar/ pages: Monday-first, padded with the
 * neighbouring months' days so every row has 7 cells. From this month to the
 * month of the last release (at most `max` months).
 */
export function calendarMonths<T extends { releaseDate: string }>(items: T[], max = 6): CalendarMonth<T>[] {
  const byDate = new Map<string, T[]>();
  for (const i of items) byDate.set(i.releaseDate, [...(byDate.get(i.releaseDate) ?? []), i]);

  const now = new Date();
  const today = now.toISOString().slice(0, 10);
  const last = items.at(-1)?.releaseDate ?? today;
  const months: CalendarMonth<T>[] = [];

  for (let m = 0; m < max; m++) {
    const first = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + m, 1);
    const key = new Date(first).toISOString().slice(0, 7);
    if (key > last.slice(0, 7)) break;

    const lastDay = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + m + 1, 0);
    const start = first - ((new Date(first).getUTCDay() + 6) % 7) * DAY;   // Monday on/before the 1st
    const end = lastDay + (6 - ((new Date(lastDay).getUTCDay() + 6) % 7)) * DAY; // Sunday on/after the last

    const weeks: CalendarDay<T>[][] = [];
    let total = 0;
    for (let t = start; t <= end; t += DAY) {
      const date = new Date(t).toISOString().slice(0, 10);
      const inMonth = date.startsWith(key);
      const dayItems = inMonth ? byDate.get(date) ?? [] : [];
      total += dayItems.length;
      if ((t - start) % (7 * DAY) === 0) weeks.push([]);
      weeks.at(-1)!.push({ date, day: +date.slice(8), inMonth, past: date < today, today: date === today, items: dayItems });
    }

    months.push({
      key,
      label: new Date(first).toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'long', year: 'numeric' }),
      short: new Date(first).toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'short' }),
      total,
      weeks,
    });
  }

  return months;
}
