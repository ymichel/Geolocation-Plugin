// Checks the hint below the strict privacy mode: shown at once when the mode is on without the proxy,
// before and after saving, and that visitors then really get the text only. The settings are restored afterwards.
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
  const hint = () => page.evaluate(() => { const h = document.getElementById('geolocation-strict-hint'); return h.offsetParent !== null ? h.textContent.trim() : false; });
  const front = async () => { await page.goto(base + '/2026/10/04/trip-to-berlin/', { waitUntil: 'load' }); return page.evaluate(() => ({ maps: document.querySelectorAll('.geolocation-map').length, text: (document.querySelector('.geolocation-plain, .geolocation-link') || {}).textContent })); };
  const out = {};
  await open(); await page.check('#geolocation_osm_use_proxy'); await page.uncheck('#geolocation_osm_strict_privacy'); await save();
  try {
    await open();
    out.proxyOnStrictOff = await hint();
    await page.check('#geolocation_osm_strict_privacy');
    out.proxyOnStrictOn = await hint();
    await page.uncheck('#geolocation_osm_use_proxy');
    out.proxyOffStrictOnNotSaved = await hint();
    await page.uncheck('#geolocation_osm_strict_privacy');
    out.proxyOffStrictOff = await hint();
    await page.check('#geolocation_osm_strict_privacy');
    await save(); await open();
    out.proxyOffStrictOnSaved = await hint();
    out.visitorsThen = await front();
    await open(); await page.check('#geolocation_osm_use_proxy');
    out.proxyCheckedAgain = await hint();
    await save();
    out.visitorsWithProxy = await front();
  } finally {
    await open(); await page.check('#geolocation_osm_use_proxy'); await page.uncheck('#geolocation_osm_strict_privacy'); await save();
  }
  await open();
  out.final = await page.evaluate(() => [document.getElementById('geolocation_osm_use_proxy').checked, document.getElementById('geolocation_osm_strict_privacy').checked, document.getElementById('geolocation_osm_precache_on_save').checked, document.getElementById('geolocation_provider').value]);
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
