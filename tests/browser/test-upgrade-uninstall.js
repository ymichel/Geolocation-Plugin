// Checks the upgrade from an old version and the uninstall routine with the test helper of the instance (mu-plugin).
// The helper keeps a backup of the plugin's options and geo data in the database and compares the restored data
// with it; the uninstall test runs in one request. Values are never printed.
// The maps are checked with Google Maps as well if an API key is stored.
const { chromium } = require('playwright-core');
const { base } = require('./lib');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const helper = async (action) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=' + action, { waitUntil: 'load' }); const r = JSON.parse(await page.locator('body').innerText()); if (!r.success) throw new Error(action + ': ' + JSON.stringify(r.data)); return r.data; };
  const setProvider = async (provider) => {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', provider);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  };
  const maps = async (google) => {
    const r = {};
    await page.goto(base + '/map/', { waitUntil: 'load' });
    await page.waitForTimeout(google ? 9000 : 3500);
    r.overview = await page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); return m ? [JSON.parse(m.dataset.markers).length, m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style')] : null; });
    await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
    await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
    await page.waitForTimeout(google ? 8000 : 3000);
    r.post = await page.evaluate(() => { const m = document.querySelector('.geolocation-map'); return [document.querySelector('.geolocation-link').textContent, m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style'), !!m.dataset.track, /can't load Google Maps correctly/.test(document.body.innerText)]; });
    return r;
  };
  const brief = (s) => ({ version: s.version, options: s.options, values: s.values, apiKey: s.apiKey, meta: s.meta, transients: s.transients, cron: s.cron });
  const out = {};
  const initial = (await helper('state')).state;
  out.initial = brief(initial);

  // Upgrade: the state of version 1.9.9, then any admin page runs the upgrade.
  await helper('backup');
  try {
    out.old = brief((await helper('simulate_old')).state);
    await page.goto(base + '/wp-admin/index.php', { waitUntil: 'load' });
    out.upgraded = brief((await helper('state')).state);
    out.upgradedOsm = await maps(false);
    if (initial.apiKey) {
      await setProvider('google');
      out.upgradedGoogle = await maps(true);
      await setProvider('osm');
    } else {
      out.upgradedGoogle = 'skipped: no API key stored';
    }
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.settingsPage = await page.evaluate(() => ({ title: document.querySelector('.wrap h1').textContent, pageWidth: document.querySelector('[name=geolocation_map_width_page]').value, trim: document.querySelector('[name=geolocation_track_trim]').value, position: document.querySelector('input[name=geolocation_map_position]:checked').value }));
  } finally {
    out.restoredAfterUpgrade = (await helper('restore')).state.checksum === initial.checksum;
  }

  // Uninstall: the routine is called directly; deleting the plugin through WordPress would remove the mounted repository.
  const test = await helper('uninstall_test');
  out.uninstalled = brief(test.uninstalled);
  out.restoredAfterUninstall = test.identical && test.state.checksum === initial.checksum;
  out.backupLeft = test.state.backup;
  out.afterRestoreOsm = await maps(false);
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
