// @ts-check
import { defineConfig } from 'astro/config';

// Static site, built from the WordPress REST API (see src/lib/wp.ts).
export default defineConfig({
  site: 'https://games.anonemus.dev',
  trailingSlash: 'always',
});
