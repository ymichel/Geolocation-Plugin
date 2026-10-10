// Checks that the map of a post is not wider than the screen and that the link to the external map starts at the same edge as the map and the track details above and
// below it, with both providers, on a desktop and on a phone. Keeps the settings as they are, except the provider,
// which ends on the one found. The link has to be switched on.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const provider = async (p) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const was = await page.inputValue('#geolocation_provider'); const linkOn = await page.isChecked('#geolocation_map_link');
    if (p && p !== was) { await page.selectOption('#geolocation_provider', p); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); } return [was, linkOn]; };
  const [was, linkOn] = await provider();
  const out = { providerBefore: was, linkOn };
  try {
    for (const p of ['osm', 'google']) {
      await provider(p);
      for (const [name, w] of [['desktop', 1366], ['phone', 390]]) {
        await page.setViewportSize({ width: w, height: 1000 });
        for (const url of ['/2026/09/20/munich-to-venice-by-bike/', '/2026/10/04/trip-to-berlin/']) {
          await page.goto(base + url, { waitUntil: 'load' }); await page.waitForTimeout(1200);
          out[p + ' ' + name + ' ' + url.split('/')[4]] = await page.evaluate(() => { const l = s => { const e = document.querySelector(s); return e ? Math.round(e.getBoundingClientRect().left) : null; }; return { map: l('.geolocation-map'), link: l('a.geolocation-map-link'), track: l('.geolocation-track-details'), mapWidth: (() => { const m = document.querySelector('.geolocation-map'); return m ? Math.round(m.getBoundingClientRect().width) : null; })(), tiles: document.querySelectorAll('.geolocation-map img.leaflet-tile-loaded, .geolocation-map .gm-style').length > 0, overflow: document.documentElement.scrollWidth > innerWidth }; });
        }
      }
      if (p === was) { await page.setViewportSize({ width: 1366, height: 1000 }); await page.goto(base + '/2026/09/20/munich-to-venice-by-bike/', { waitUntil: 'networkidle' }); await page.locator('.geolocation-map').scrollIntoViewIfNeeded(); await page.screenshot({ path: 'out/map-link-align.png' }); }
    }
  } finally { await provider(was); }
  console.log(JSON.stringify(out, null, 1).replace(/\n\s{2,}/g, ' '));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
