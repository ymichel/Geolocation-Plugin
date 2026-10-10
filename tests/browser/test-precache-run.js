// Checks a pre-cache run with tiles which have never been requested: the default zoom level is changed for the test,
// so the maps of the posts without a track need new tiles. The files are checked on the disk of the host.
// The zoom level is restored afterwards; the fetched tiles stay in the cache of the test instance.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { base } = require('./lib');
const CACHE = require('./lib').cache;
const ZOOM = process.argv[2] || '18';
// Optional: a larger map, so the run needs several batches.
const SIZE = process.argv[3] ? process.argv[3].split('x') : null;

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (q) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + q, { waitUntil: 'load', timeout: 300000 }); return JSON.parse(await page.locator('body').innerText()).data; };
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const r = await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); return r; };
  const rowText = async () => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); return page.evaluate(() => [...document.getElementById('geolocation-precache').closest('td').querySelectorAll('p')].slice(0, 2).map(p => p.textContent.replace(/\s+/g, ' ').trim())); };
  const onDisk = (tiles) => tiles.filter(t => { const f = path.join(CACHE, t.url.split('osm-tiles/')[1]); return fs.existsSync(f) && fs.statSync(f).size > 500; }).length;
  const out = { zoom: ZOOM };
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const ids = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' })).map(p => p.id));
  const before = await settings(async () => { const v = await page.evaluate(() => [document.querySelector('input[name=geolocation_default_zoom]:checked').value, document.querySelector('[name=geolocation_map_width]').value, document.querySelector('[name=geolocation_map_height]').value]); await page.check('#geolocation_default_zoom_' + ZOOM); if (SIZE) { await page.fill('[name=geolocation_map_width]', SIZE[0]); await page.fill('[name=geolocation_map_height]', SIZE[1]); } return v; });
  out.zoomBefore = before;
  try {
    let tiles = [];
    for (const id of ids) tiles = tiles.concat((await helper('tiles&post=' + id)).tiles);
    const unique = [...new Map(tiles.map(t => [t.url, t])).values()];
    out.tiles = unique.length;
    out.onDiskBefore = onDisk(unique);
    out.rowBefore = await rowText();
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#geolocation-precache')]);
    // WordPress runs the batches itself when pages are requested; they are 20 seconds apart.
    out.runs = [];
    for (let i = 0; i < 40; i++) {
      const s = await helper('tiles&post=' + ids[0]);
      out.runs.push([new Date().toISOString().slice(14, 19), s.precacheStatus.requested, s.precacheStatus.stored, s.precacheStatus.running, onDisk(unique)]);
      if (s.precacheStatus.running === false) break;
      await page.waitForTimeout(8000);
      await page.goto(base + '/', { waitUntil: 'load' });
    }
    out.onDiskAfter = onDisk(unique);
    out.rowAfter = await rowText();
    // A post's map now gets its tiles from the cache.
    await page.goto(base + '/2026/10/04/trip-to-berlin/', { waitUntil: 'load' });
    await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
    await page.waitForTimeout(3500);
    out.berlinMap = await page.evaluate(() => { const t = [...document.querySelectorAll('.geolocation-map img.leaflet-tile')]; return { tiles: t.length, loaded: t.filter(x => x.complete && x.naturalWidth === 256).length, zoom: (t[0].src.match(/\/(\d+)\/\d+\/\d+\.png/) || [])[1] }; });
  } finally {
    await settings(async () => { await page.check('#geolocation_default_zoom_' + before[0]); await page.fill('[name=geolocation_map_width]', before[1]); await page.fill('[name=geolocation_map_height]', before[2]); });
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
