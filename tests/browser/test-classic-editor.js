// Checks the Geolocation box in the classic editor: location, choosing a GPX file, saving, removing the track.
// Uses the test helper of the instance (mu-plugin) to switch the block editor off, and the post "Trip to Dresden".
// With --google the editor map is checked with Google Maps (OSM is restored afterwards).
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const GOOGLE = process.argv[2] === '--google';
const POST = id('trip-to-dresden');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (action) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + action, { waitUntil: 'load' }); return JSON.parse(await page.locator('body').innerText()); };
  const setProvider = async (provider) => {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', provider);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  };
  const open = async () => {
    await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('#geolocation-track-file', { state: 'attached' });
    await page.locator('#geolocation_sectionid').scrollIntoViewIfNeeded();
    await page.waitForTimeout(GOOGLE ? 8000 : 3000);
    return page.evaluate(() => ({
      classic: !!document.getElementById('postdivrich') && !document.querySelector('.block-editor'),
      address: document.getElementById('geolocation-address').value,
      status: document.getElementById('geolocation-track-status').textContent,
      map: document.getElementById('geolocation-map').classList.contains('leaflet-container') || !!document.querySelector('#geolocation-map .gm-style'),
      line: !!document.querySelector('#geolocation-map .leaflet-overlay-pane path'),
      googleError: /can't load Google Maps correctly/.test(document.body.innerText),
    }));
  };
  const save = async () => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#publish')]); };
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  if (GOOGLE) await setProvider('google');
  await helper('classic_on');
  try {
    out.before = await open();
    await page.setInputFiles('#geolocation-track-file', 'out/berlin-potsdam.gpx');
    await page.waitForTimeout(GOOGLE ? 2500 : 1500);
    out.chosen = await page.evaluate(() => ({ status: document.getElementById('geolocation-track-status').textContent, points: JSON.parse(document.getElementById('geolocation-track').value).length, ele: !!document.getElementById('geolocation-track-ele').value, line: !!document.querySelector('#geolocation-map .leaflet-overlay-pane path') }));
    await page.screenshot({ path: 'out/classic-editor' + (GOOGLE ? '-google' : '') + '.png' });
    await save();
    out.saved = await open();
    await page.goto(base + '/2026/10/04/trip-to-dresden/', { waitUntil: 'load' });
    await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
    await page.waitForTimeout(GOOGLE ? 8000 : 3000);
    out.front = await page.evaluate(() => { const m = document.querySelector('.geolocation-map'); const d = document.querySelector('.geolocation-track-details'); return { track: JSON.parse(m.dataset.track || '[]').length, map: m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style'), details: d ? d.firstChild.textContent : '', profile: !!document.querySelector('.geolocation-elevation') }; });
    await open();
    await page.click('#geolocation-track-remove');
    await save();
    out.removed = await open();
    await page.goto(base + '/2026/10/04/trip-to-dresden/', { waitUntil: 'load' });
    out.frontRemoved = await page.evaluate(() => ({ track: !!document.querySelector('.geolocation-map').dataset.track, details: !!document.querySelector('.geolocation-track-details') }));
  } finally {
    await helper('classic_off');
    if (GOOGLE) await setProvider('osm');
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
