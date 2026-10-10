// Checks that the row "Pre-cache tiles" of the settings page follows the checkbox "Use Proxy" at once, shows a hint
// instead of the status while the proxy is not saved as used, and stays shown or hidden after saving.
// The settings are restored afterwards.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const open = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const row = () => page.evaluate(() => { const r = document.getElementById('geolocation-precache-row'); if (!r) return 'no row'; const vis = r.offsetParent !== null; return { visible: vis, status: !!document.getElementById('geolocation-precache-status'), startButton: !!document.getElementById('geolocation-precache'), hint: (document.getElementById('geolocation-precache-hint') || {}).textContent || '', onSaveBox: !!document.getElementById('geolocation_osm_precache_on_save') }; });
  const out = {};
  await open(); await page.check('#geolocation_osm_use_proxy'); await page.selectOption('#geolocation_provider', 'osm'); await save();
  try {
    await open();
    out.savedOn = await row();
    await page.uncheck('#geolocation_osm_use_proxy');
    out.uncheckedNotSaved = await row();
    await page.check('#geolocation_osm_use_proxy');
    out.recheckedNotSaved = await row();
    await page.uncheck('#geolocation_osm_use_proxy'); await save(); await open();
    out.savedOff = await row();
    await page.check('#geolocation_osm_use_proxy');
    out.checkedNotSaved = await row();
    await save(); await open();
    out.savedOnAgain = await row();
    // With Google Maps the whole OSM section is hidden.
    await page.selectOption('#geolocation_provider', 'google');
    out.googleNotSaved = await row();
  } finally {
    await open(); await page.selectOption('#geolocation_provider', 'osm'); await page.check('#geolocation_osm_use_proxy'); await page.check('#geolocation_osm_precache_on_save'); await save();
  }
  await open();
  out.final = await page.evaluate(() => [document.getElementById('geolocation_provider').value, document.getElementById('geolocation_osm_use_proxy').checked, document.getElementById('geolocation_osm_precache_on_save').checked]);
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
