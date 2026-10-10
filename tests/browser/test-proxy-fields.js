// Checks the fields which follow the checkbox "Use Proxy" on the settings page: the address of the proxy, the
// explanation of the own tiles URL and that this URL is read-only while the proxy is used but keeps its value.
// The settings are restored afterwards.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const open = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const st = () => page.evaluate(() => { const v = id => { const e = document.getElementById(id); return e ? e.offsetParent !== null : 'missing'; }; const own = document.getElementById('geolocation_osm_tiles_url'); return { label: own.closest('tr').querySelector('th').textContent.trim(), currentlyUsed: v('geolocation-proxy-tiles'), text: v('geolocation-tiles-fallback') ? document.getElementById('geolocation-tiles-fallback').textContent : document.getElementById('geolocation-tiles-direct').textContent, readOnly: own.readOnly, value: own.value }; });
  const out = {};
  await open(); await page.check('#geolocation_osm_use_proxy'); await save(); await open();
  const original = (await st()).value;
  try {
    out.savedOn = await st();
    await page.fill('#geolocation_osm_tiles_url', 'https://changed.example/{z}/{x}/{y}.png', { force: true, timeout: 3000 }).then(() => { out.typingWhileReadOnly = 'accepted'; }).catch(() => { out.typingWhileReadOnly = 'refused'; });
    await page.uncheck('#geolocation_osm_use_proxy');
    out.unchecked = await st();
    await page.fill('#geolocation_osm_tiles_url', 'https://tile.example.org/{z}/{x}/{y}.png');
    await page.check('#geolocation_osm_use_proxy');
    out.editedThenChecked = await st();
    await save(); await open();
    out.savedWithNewValue = await st();
    // Saving while read-only keeps the value.
    await save(); await open();
    out.savedAgain = (await st()).value;
  } finally {
    await open(); await page.uncheck('#geolocation_osm_use_proxy'); await page.fill('#geolocation_osm_tiles_url', original); await page.check('#geolocation_osm_use_proxy'); await save();
  }
  await open();
  out.final = [(await st()).value === original, await page.evaluate(() => [document.getElementById('geolocation_osm_use_proxy').checked, document.getElementById('geolocation_osm_precache_on_save').checked, document.getElementById('geolocation_osm_strict_privacy').checked])];
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
