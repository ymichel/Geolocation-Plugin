// Checks pre-caching of tiles: the tiles the plugin calculates for a post against the tiles Leaflet really requests,
// the row on the settings page, a run in the background (through the test helper, which runs the scheduled tasks),
// pre-caching when a post is saved, and that nothing is offered with Google Maps.
// Deletes the stored tiles of the post "Trip to Berlin" from the cache of the test instance to have something to fetch.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { base, id } = require('./lib');
const CACHE = require('./lib').cache;
const POSTS = { berlin: [id('trip-to-berlin'), '/2026/10/04/trip-to-berlin/'], hamburg: [id('trip-to-hamburg'), '/2026/10/04/trip-to-hamburg/'], potsdam: [id('trip-to-potsdam'), '/2026/10/04/trip-to-potsdam/'], munich: [0, '/2026/09/20/munich-to-venice-by-bike/'] };

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (q) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + q, { waitUntil: 'load' }); return JSON.parse(await page.locator('body').innerText()).data; };
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); };
  const out = {};
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  POSTS.munich[0] = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' })).find(p => p.title.raw === 'Munich to Venice by bike').id);

  // 1. Calculated tiles against the tiles Leaflet requests for the map of a post.
  out.compare = {};
  for (const [name, [id, url]] of Object.entries(POSTS)) {
    const requested = new Set();
    const onRequest = r => { const m = r.url().match(/\/(\d+)\/(\d+)\/(\d+)\.png/); if (m && /osm-tiles|tile\.openstreetmap/.test(r.url())) requested.add(r.url().replace(/\?.*$/, '')); };
    page.on('request', onRequest);
    await page.goto(base + url, { waitUntil: 'load' });
    await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
    await page.waitForTimeout(3500);
    page.off('request', onRequest);
    const calc = await helper('tiles&post=' + id);
    const want = calc.tiles.map(t => t.url);
    const got = [...requested];
    out.compare[name] = { calculated: want.length, requested: got.length, missingInCalculation: got.filter(u => !want.includes(u)).map(u => u.split('osm-tiles/')[1] || u), notRequested: want.filter(u => !got.includes(u)).map(u => u.split('osm-tiles/')[1] || u), zoom: calc.tiles[0] && calc.tiles[0].tile.split('/')[0] };
    out.tilesUrl = calc.tilesUrl;
  }

  // 2. Settings page and a run in the background.
  const berlin = (await helper('tiles&post=' + POSTS.berlin[0])).tiles;
  for (const t of berlin) { const f = path.join(CACHE, t.url.split('osm-tiles/')[1]); if (fs.existsSync(f)) fs.unlinkSync(f); }
  out.berlinAfterDelete = (await helper('tiles&post=' + POSTS.berlin[0])).tiles.filter(t => t.stored).length + ' of ' + berlin.length + ' stored';
  const row = async () => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); return page.evaluate(() => { const b = document.getElementById('geolocation-precache'); return b ? [...b.closest('td').querySelectorAll('p')].map(p => p.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean) : null; }); };
  out.rowBefore = await row();
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#geolocation-precache')]);
  out.notice = await page.evaluate(() => (document.querySelector('.wrap .notice') || {}).textContent || '');
  out.runs = [];
  for (let i = 0; i < 6; i++) {
    const r = await helper('run_cron');
    const s = (await helper('tiles&post=' + POSTS.berlin[0]));
    out.runs.push({ ran: r.ran || [], status: s.precacheStatus, scheduled: s.cron });
    if (!s.cron[0]) break;
  }
  out.berlinAfterRun = (await helper('tiles&post=' + POSTS.berlin[0])).tiles.filter(t => t.stored).length + ' of ' + berlin.length + ' stored';
  out.filesOnDisk = berlin.filter(t => fs.existsSync(path.join(CACHE, t.url.split('osm-tiles/')[1]))).length;
  out.rowAfter = await row();
  // The map of the post shows the tiles which have been stored in advance.
  await page.goto(base + POSTS.berlin[1], { waitUntil: 'load' });
  await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
  await page.waitForTimeout(3000);
  out.berlinMap = await page.evaluate(() => { const t = [...document.querySelectorAll('.geolocation-map img.leaflet-tile')]; return [t.length, t.filter(x => x.complete && x.naturalWidth === 256).length]; });

  // 3. Pre-caching when a post is saved.
  for (const t of berlin) { const f = path.join(CACHE, t.url.split('osm-tiles/')[1]); if (fs.existsSync(f)) fs.unlinkSync(f); }
  await settings(async () => { await page.check('#geolocation_osm_precache_on_save'); });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  await page.evaluate(async (id) => { const p = await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' }); await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: p.content.raw } }); }, POSTS.berlin[0]);
  out.onSaveScheduled = (await helper('tiles&post=' + POSTS.berlin[0])).cron;
  out.onSaveRan = (await helper('run_cron')).ran || [];
  out.berlinAfterSave = (await helper('tiles&post=' + POSTS.berlin[0])).tiles.filter(t => t.stored).length + ' of ' + berlin.length + ' stored';
  await settings(async () => { await page.uncheck('#geolocation_osm_precache_on_save'); });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  await page.evaluate(async (id) => { const p = await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' }); await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: p.content.raw } }); }, POSTS.berlin[0]);
  out.offNotScheduled = (await helper('tiles&post=' + POSTS.berlin[0])).cron;

  // 4. Nothing is offered without the proxy or with Google Maps.
  await settings(async () => { await page.uncheck('#geolocation_osm_use_proxy'); });
  out.withoutProxy = [await row(), (await helper('tiles&post=' + POSTS.berlin[0])).tilesUrl];
  await settings(async () => { await page.check('#geolocation_osm_use_proxy'); await page.selectOption('#geolocation_provider', 'google'); });
  try {
    out.withGoogle = [await row(), (await helper('tiles&post=' + POSTS.berlin[0])).tilesUrl];
    await page.goto(base + '/wp-admin/admin-post.php?action=geolocation_precache', { waitUntil: 'load' });
    out.withoutNonce = (await page.locator('body').innerText()).slice(0, 80);
  } finally {
    await settings(async () => { await page.selectOption('#geolocation_provider', 'osm'); });
  }
  out.final = await page.evaluate(() => [document.getElementById('geolocation_provider').value, document.getElementById('geolocation_osm_use_proxy').checked, document.getElementById('geolocation_osm_precache_on_save').checked]);
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
