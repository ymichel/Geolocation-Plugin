// Checks that Google Maps is only offered to websites which use it: as long as it is selected or an API key is
// stored. What the check expects depends on the instance: without a key the settings page of a new website and
// a refused attempt to store Google Maps, with a key the select, the key field and the hint.
// A real API key is never read or changed: the steps which store and remove a key only run in an instance
// without one, with a placeholder. Needs an instance with OpenStreetMap selected.
const { chromium } = require('playwright-core');
const { base } = require('./lib');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = []; const requests = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('request', r => requests.push(r.url()));
  await page.goto(base + '/wp-admin/', { waitUntil: 'load' });
  const helper = (q) => page.evaluate((query) => fetch('/wp-admin/admin-ajax.php?action=geolocation_test&do=' + query, { credentials: 'include' }).then(r => r.json()).then(j => j.data), q);
  const settings = async () => {
    requests.length = 0;
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.waitForTimeout(2500);
    return page.evaluate((google) => { const p = document.getElementById('geolocation_provider'); return { field: p ? p.tagName.toLowerCase() + (p.type === 'hidden' ? ' (hidden)' : '') : null, value: p ? p.value : null, options: p && p.options ? [...p.options].map(o => o.value) : [], keyField: (() => { const k = document.querySelector('input[name=geolocation_google_maps_api_key]'); return k ? (k.offsetParent !== null ? 'shown' : 'hidden') : 'none'; })(), hint: ((document.getElementById('geolocation-google-hint') || {}).textContent || '').slice(0, 60), osmSection: (document.querySelector('.osm-urls') || {}).offsetParent !== null && !!document.querySelector('.osm-urls'), preview: document.querySelectorAll('#map img.leaflet-tile').length > 0, googleRequests: google }; }, requests.filter(u => /google|gstatic/.test(u) && !/plugins\/geolocation/.test(u)).length);
  };
  const state = (await helper('state')).state;
  const out = { keyStored: state.apiKey, provider: state.values.geolocation_provider };
  if (out.provider !== 'osm') throw new Error('Select OpenStreetMap in the instance before running this check.');
  out.settingsPage = await settings();
  out.expected = out.keyStored ? 'select with google and osm and the hint; the key field hidden while OpenStreetMap is selected' : 'hidden field, no key field, no request to Google';
  // Saving the page keeps OpenStreetMap, and a forged value is not stored where Google Maps is not offered.
  const forged = await page.evaluate(() => { const p = document.getElementById('geolocation_provider'); if (p.tagName === 'SELECT') return 'not applicable: Google Maps is offered here'; p.value = 'google'; return 'google sent'; });
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  out.afterSaving = [forged, (await helper('state')).state.values.geolocation_provider];
  // The key field follows the select: shown with Google Maps, hidden with OpenStreetMap.
  const follows = async () => { const shown = () => page.evaluate(() => document.querySelector('input[name=geolocation_google_maps_api_key]').offsetParent !== null); await page.selectOption('#geolocation_provider', 'google'); const withGoogle = await shown(); await page.selectOption('#geolocation_provider', 'osm'); return { withGoogle, withOsm: await shown() }; };
  if (out.keyStored) {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.keyFieldFollowsSelect = await follows();
  } else {
    // The helper stores a placeholder as key and selects Google Maps: the website is offered the choice, and
    // administrators see the notice about Google Maps where it belongs.
    await helper('use_google');
    await helper('show_notice');
    const page1 = await settings();
    out.withPlaceholderKey = { field: page1.field, value: page1.value, keyField: page1.keyField, hint: page1.hint !== '' };
    out.keyFieldFollowsSelect = await follows();
    const notice = async (path) => { await page.goto(base + path, { waitUntil: 'load' }); return page.evaluate(() => ((document.querySelector('#geolocation-google-notice p') || {}).innerText || '').replace(/\s+/g, ' ').trim().slice(0, 70) || null); };
    out.notice = { dashboard: await notice('/wp-admin/index.php'), plugins: await notice('/wp-admin/plugins.php'), settings: await notice('/wp-admin/options-general.php?page=geolocation.php'), posts: await notice('/wp-admin/edit.php') };
    // Hidden by this administrator, it stays away.
    await page.goto(base + '/wp-admin/index.php', { waitUntil: 'load' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#geolocation-google-notice-hide')]);
    out.noticeAfterHiding = { address: (await page.evaluate(() => location.pathname + location.search)), dashboard: await notice('/wp-admin/index.php'), settings: await notice('/wp-admin/options-general.php?page=geolocation.php') };
    await helper('show_notice');
    // Removing the key and saving switches to OpenStreetMap; Google Maps is not offered any more, the notice is gone.
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', 'google');
    await page.fill('input[name=geolocation_google_maps_api_key]', '');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
    const page2 = await settings();
    out.afterRemovingKey = { provider: (await helper('state')).state.values.geolocation_provider, field: page2.field, keyField: page2.keyField, notice: await notice('/wp-admin/index.php') };
  }
  // A post still shows its map.
  await page.goto(base + '/2026/10/04/trip-to-berlin/', { waitUntil: 'load' });
  await page.waitForTimeout(3000);
  out.postMap = await page.evaluate(() => document.querySelectorAll('.geolocation-map[data-geolocation] img.leaflet-tile-loaded').length > 0);
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
