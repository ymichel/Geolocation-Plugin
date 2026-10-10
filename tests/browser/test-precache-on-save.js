// Checks that the tiles of a post are pre-cached when it is saved: it is on by default, the tiles arrive on the disk
// of the host, and nothing is scheduled when it is switched off. Uses the post "Trip to Dresden" with zoom level 18
// and a larger map, whose tiles are not cached before the first run (pass another size as argument for a rerun).
// The settings are restored afterwards.
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { base, id } = require('./lib');
const CACHE = require('./lib').cache;
const POST = id('trip-to-dresden');
const SIZE = (process.argv[2] || '900x500').split('x');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (q) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + q, { waitUntil: 'load', timeout: 120000 }); return JSON.parse(await page.locator('body').innerText()).data; };
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const r = await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); return r; };
  const savePost = async () => { await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' }); await page.evaluate(async (id) => { const p = await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' }); await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: p.content.raw } }); }, POST); };
  const onDisk = (tiles) => tiles.filter(t => { const f = path.join(CACHE, t.url.split('osm-tiles/')[1]); return fs.existsSync(f) && fs.statSync(f).size > 500; }).length;
  const out = {};
  const before = await settings(async () => { const v = await page.evaluate(() => [document.querySelector('input[name=geolocation_default_zoom]:checked').value, document.getElementById('geolocation_osm_precache_on_save').checked, document.querySelector('[name=geolocation_map_width]').value, document.querySelector('[name=geolocation_map_height]').value]); await page.check('#geolocation_default_zoom_18'); await page.fill('[name=geolocation_map_width]', SIZE[0]); await page.fill('[name=geolocation_map_height]', SIZE[1]); return v; });
  out.before = { zoom: before[0], onSaveChecked: before[1] };
  try {
    const tiles = (await helper('tiles&post=' + POST)).tiles;
    out.tiles = tiles.length;
    out.onDiskBefore = onDisk(tiles);
    await savePost();
    out.scheduledAfterSave = (await helper('tiles&post=' + POST)).cron[1];
    out.wait = [];
    for (let i = 0; i < 10 && onDisk(tiles) < tiles.length; i++) { await page.waitForTimeout(5000); await page.goto(base + '/', { waitUntil: 'load' }); out.wait.push(onDisk(tiles)); }
    out.onDiskAfter = onDisk(tiles);

    // Let the task of the first save finish, so it is not mistaken for a new one.
    for (let i = 0; i < 12 && (await helper('tiles&post=' + POST)).cron[1]; i++) { await page.waitForTimeout(4000); await page.goto(base + '/', { waitUntil: 'load' }); }
    out.firstTaskDone = !(await helper('tiles&post=' + POST)).cron[1];

    // Switched off: saving schedules nothing.
    await settings(async () => { await page.uncheck('#geolocation_osm_precache_on_save'); });
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.storedOff = await page.evaluate(() => document.getElementById('geolocation_osm_precache_on_save').checked);
    await savePost();
    out.scheduledWhenOff = (await helper('tiles&post=' + POST)).cron[1];
  } finally {
    await settings(async () => { await page.check('#geolocation_default_zoom_' + before[0]); await page.fill('[name=geolocation_map_width]', before[2]); await page.fill('[name=geolocation_map_height]', before[3]); await page.check('#geolocation_osm_precache_on_save'); });
  }
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  out.final = await page.evaluate(() => [document.querySelector('input[name=geolocation_default_zoom]:checked').value, document.getElementById('geolocation_osm_precache_on_save').checked, document.getElementById('geolocation_provider').value]);
  console.log(JSON.stringify(out));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
