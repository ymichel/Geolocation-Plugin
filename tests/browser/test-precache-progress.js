// Checks the detailed progress of pre-caching on the settings page: the status refreshes itself while a run is in
// progress (the page is not reloaded), posts are processed from new to old, and a run can be cancelled.
// node test-precache-progress.js <zoom> <width>x<height> [cancel] - choose a view whose tiles are not cached yet.
// The settings are restored afterwards.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const ZOOM = process.argv[2] || '9';
const SIZE = (process.argv[3] || '1400x900').split('x');
const CANCEL = process.argv[4] === 'cancel';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (q) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + q, { waitUntil: 'load', timeout: 120000 }); return JSON.parse(await page.locator('body').innerText()).data; };
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const r = await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); return r; };
  const box = () => page.evaluate(() => { const b = document.getElementById('geolocation-precache-status'); const p = document.getElementById('geolocation-precache-progress'); return { running: b.dataset.running, lines: [...b.querySelectorAll('p')].map(x => x.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean), progress: p ? p.value + '/' + p.max : null, sameDocument: window.geolocationTestMarker === true }; });
  const out = { zoom: ZOOM, size: SIZE.join('x'), cancel: CANCEL };
  const before = await settings(async () => { const v = await page.evaluate(() => [document.querySelector('input[name=geolocation_default_zoom]:checked').value, document.querySelector('[name=geolocation_map_width]').value, document.querySelector('[name=geolocation_map_height]').value]); await page.check('#geolocation_default_zoom_' + ZOOM); await page.fill('[name=geolocation_map_width]', SIZE[0]); await page.fill('[name=geolocation_map_height]', SIZE[1]); return v; });
  try {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.idle = (await box()).lines;
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#geolocation-precache')]);
    await page.evaluate(() => { window.geolocationTestMarker = true; });
    out.steps = [];
    let last = '';
    for (let i = 0; i < 60; i++) {
      const b = await box();
      const text = b.lines.join(' | ');
      if (text !== last) { out.steps.push({ t: new Date().toISOString().slice(14, 19), progress: b.progress, lines: b.lines.filter(l => !/^Pre-caching is running|^Cancel$|^Pre-cache the missing/.test(l)), sameDocument: b.sameDocument }); last = text; }
      if (b.running === '0') break;
      if (CANCEL && /Tiles: [1-9]/.test(text)) {
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#geolocation-precache-cancel')]);
        out.cancelNotice = await page.evaluate(() => (document.querySelector('.wrap .notice') || {}).textContent || '');
        out.afterCancel = (await box()).lines;
        const requested = (await helper('tiles&post=' + id('trip-to-berlin'))).precacheStatus.requested;
        await page.waitForTimeout(35000);
        await page.goto(base + '/', { waitUntil: 'load' });
        const later = await helper('tiles&post=' + id('trip-to-berlin'));
        out.cancelHolds = { requestedAtCancel: requested, requestedLater: later.precacheStatus.requested, running: later.precacheStatus.running, scheduled: later.cron[0] };
        break;
      }
      await page.waitForTimeout(4000);
    }
  } finally {
    await settings(async () => { await page.check('#geolocation_default_zoom_' + before[0]); await page.fill('[name=geolocation_map_width]', before[1]); await page.fill('[name=geolocation_map_height]', before[2]); });
  }
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  out.final = await page.evaluate(() => [document.querySelector('input[name=geolocation_default_zoom]:checked').value, document.querySelector('[name=geolocation_map_width]').value, document.querySelector('[name=geolocation_map_height]').value]);
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
