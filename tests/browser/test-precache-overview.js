// Checks the tiles the plugin calculates for overview maps (shortcodes and blocks) against the tiles Leaflet
// really requests, on a desktop and on a phone. Every requested tile has to be among the calculated ones;
// the calculation may contain more, as it covers both widths.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
const SLUGS = ['map', 'our-route', 'munich-to-venice', 'block-test', 'shortcode-test', 'route-test', 'germany-tour'];

(async () => {
  const browser = await chromium.launch();
  const out = {};
  for (const [device, viewport] of [['desktop', { width: 1366, height: 1000 }], ['phone', { width: 375, height: 800 }]]) {
    const page = await (await browser.newContext({ viewport })).newPage();
    await page.goto(base + '/', { waitUntil: 'load' });
    const helper = async (q) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + q, { waitUntil: 'load', timeout: 120000 }); return JSON.parse(await page.locator('body').innerText()).data; };
    await page.goto(base + '/wp-admin/edit.php?post_type=page', { waitUntil: 'load' });
    const pages = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/pages?per_page=100&context=edit' })).map(p => [p.slug, p.id, p.link]));
    out[device] = {};
    for (const slug of SLUGS) {
      const found = pages.find(p => p[0] === slug);
      if (!found) { out[device][slug] = 'no such page'; continue; }
      const requested = new Set();
      const onRequest = r => { if (/osm-tiles\/\w\/\d+\/\d+\/\d+\.png/.test(r.url())) requested.add(r.url().replace(/\?.*$/, '')); };
      page.on('request', onRequest);
      await page.goto(found[2], { waitUntil: 'load' });
      const maps = await page.locator('.geolocation-page-map').all();
      for (const m of maps) { await m.scrollIntoViewIfNeeded(); await page.waitForTimeout(2500); }
      const sizes = await page.evaluate(() => [...document.querySelectorAll('.geolocation-page-map')].map(m => m.clientWidth + 'x' + m.clientHeight));
      page.off('request', onRequest);
      const calc = (await helper('tiles&post=' + found[1])).tiles.map(t => t.url);
      const got = [...requested];
      out[device][slug] = { maps: sizes, requested: got.length, calculated: calc.length, missingInCalculation: got.filter(u => !calc.includes(u)).map(u => u.split('osm-tiles/')[1]) };
    }
    await page.context().close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
