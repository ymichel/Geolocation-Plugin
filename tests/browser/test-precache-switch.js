// Checks that the switch "pre-cache when saved" survives saving the settings while its row is not shown
// (proxy switched off, Google Maps), in both positions. The settings are restored afterwards.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); };
  const state = async () => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); return page.evaluate(() => { const c = document.getElementById('geolocation_osm_precache_on_save'); return c ? c.checked : 'row hidden, kept as ' + document.querySelector('input[type=hidden][name=geolocation_osm_precache_on_save]').value; }); };
  const out = {};
  for (const on of [true, false]) {
    const r = [];
    await settings(async () => { await page.check('#geolocation_osm_use_proxy'); await page.selectOption('#geolocation_provider', 'osm'); });
    await settings(async () => { await page.setChecked('#geolocation_osm_precache_on_save', on); });
    r.push(await state());
    await settings(async () => { await page.uncheck('#geolocation_osm_use_proxy'); });
    r.push(await state());
    await settings(async () => {});
    await settings(async () => { await page.selectOption('#geolocation_provider', 'google'); });
    r.push(await state());
    await settings(async () => { await page.selectOption('#geolocation_provider', 'osm'); await page.check('#geolocation_osm_use_proxy'); });
    r.push(await state());
    out[on ? 'switchedOn' : 'switchedOff'] = r;
  }
  await settings(async () => { await page.check('#geolocation_osm_precache_on_save'); });
  out.final = [await state(), await page.evaluate(() => [document.getElementById('geolocation_provider').value, document.getElementById('geolocation_osm_use_proxy').checked])];
  console.log(JSON.stringify(out));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
