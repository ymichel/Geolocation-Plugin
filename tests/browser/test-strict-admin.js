// Checks the strict privacy mode in the admin area: which hosts the browser of an author contacts in the editor
// and on the settings page, with and without the proxy, and that the address search still works through the site.
// Also the unchanged behaviour without the strict mode and with Google Maps. Nothing is saved to the post;
// the settings are restored afterwards.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const POST = id('trip-to-dresden');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  let hosts = new Set();
  let geocode = 0;
  page.on('request', r => { const u = new URL(r.url()); if (!/^(127\.0\.0\.1|localhost)$/.test(u.hostname) && u.protocol.startsWith('http')) hosts.add(u.hostname); if (/action=geolocation_geocode/.test(r.url())) geocode++; });
  await page.goto(base + '/', { waitUntil: 'load' });
  const open = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  // The checkboxes are only visible while OpenStreetMap is selected.
  const set = async (provider, proxy, strict) => { await open(); await page.selectOption('#geolocation_provider', 'osm'); await page.setChecked('#geolocation_osm_use_proxy', proxy); await page.setChecked('#geolocation_osm_strict_privacy', strict); await page.selectOption('#geolocation_provider', provider); await save(); };
  // What the browser contacts in the editor, and whether looking up an address works.
  const editor = async (google) => {
    hosts = new Set(); geocode = 0;
    await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('#geolocation-address', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(google ? 8000 : 4000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(400); }
    const before = await page.evaluate(() => [document.getElementById('geolocation-latitude').value, document.getElementById('geolocation-longitude').value]);
    await page.evaluate(() => { const a = document.getElementById('geolocation-address'); a.value = 'Leipzig, Markt'; document.getElementById('geolocation-load').click(); });
    await page.waitForTimeout(google ? 5000 : 6000);
    return page.evaluate(([before, hosts, geocode]) => { const m = document.getElementById('geolocation-map');
      return { mapShown: m.offsetParent !== null, tiles: m.querySelectorAll('img.leaflet-tile').length, google: !!m.querySelector('.gm-style'), note: (document.getElementById('geolocation-map-blocked') || {}).textContent || '',
        searchMoved: document.getElementById('geolocation-latitude').value !== before[0], position: [document.getElementById('geolocation-latitude').value.slice(0, 6), document.getElementById('geolocation-longitude').value.slice(0, 6)], address: document.getElementById('geolocation-address').value.slice(0, 40),
        throughSite: geocode, externalHosts: hosts }; }, [before, [...hosts].sort(), geocode]);
  };
  const settingsPage = async () => {
    hosts = new Set();
    await open();
    await page.waitForTimeout(4000);
    return page.evaluate((hosts) => { const m = document.getElementById('map'); return { preview: m.querySelectorAll('img.leaflet-tile').length ? 'osm map' : (m.querySelector('.gm-style') ? 'google map' : m.textContent.trim()), tilesFrom: (m.querySelector('img.leaflet-tile') || { src: '' }).src.split('/').slice(0, 3).join('/'), externalHosts: hosts }; }, [...hosts].sort());
  };
  const out = {};
  try {
    await set('osm', true, true);
    out.strictWithProxy = { editor: await editor(false), settings: await settingsPage() };
    await set('osm', false, true);
    out.strictWithoutProxy = { editor: await editor(false), settings: await settingsPage() };
    // The preview follows the checkboxes before saving.
    hosts = new Set();
    await page.check('#geolocation_osm_use_proxy'); await page.waitForTimeout(3000);
    out.previewAfterCheckingProxy = await page.evaluate((h) => ({ tilesFrom: (document.querySelector('#map img.leaflet-tile') || { src: '' }).src.split('/').slice(0, 3).join('/'), externalHosts: h }), [...hosts].sort());
    await page.uncheck('#geolocation_osm_use_proxy'); await page.waitForTimeout(1500);
    out.previewAfterUnchecking = await page.evaluate(() => document.getElementById('map').textContent.trim());
    await set('osm', false, false);
    out.notStrictWithoutProxy = { editor: await editor(false), settings: await settingsPage() };
    await set('google', true, true);
    out.google = { editor: await editor(true), settings: await settingsPage() };
  } finally {
    await set('osm', true, false);
  }
  await open();
  out.final = await page.evaluate(() => [document.getElementById('geolocation_provider').value, document.getElementById('geolocation_osm_use_proxy').checked, document.getElementById('geolocation_osm_strict_privacy').checked, document.getElementById('geolocation_osm_precache_on_save').checked]);
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
