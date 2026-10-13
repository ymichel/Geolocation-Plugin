// Checks "Remove location" in the block editor with the post "Trip to Munich": after saving, the post has no
// location, the location is switched off, the map shows no marker, and nothing comes back when the post is
// saved again. Then a new location is chosen, which works as before. With --google the same with Google Maps.
// Restores the location of the post at the end.
const { chromium } = require('playwright-core');
const { base, id, chooseProvider } = require('./lib');
const GOOGLE = process.argv[2] === '--google';
const POST = id('trip-to-munich');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/wp-admin/', { waitUntil: 'load' });
  const helper = (q) => page.evaluate((query) => fetch('/wp-admin/admin-ajax.php?action=geolocation_test&do=' + query, { credentials: 'include' }).then(r => r.json()).then(j => j.data), q);
  const meta = async () => { const m = (await helper('meta&post=' + POST)).meta; return [m.geo_latitude, m.geo_longitude, m.geo_address, 'enabled ' + m.geo_enabled, 'public ' + m.geo_public].concat(m.geo_removed ? ['removed'] : []); };
  const provider = async (name) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const was = await page.inputValue('#geolocation_provider'); if (name !== was) { await chooseProvider(page, name); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); } return was; };
  const open = async () => { await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' }); await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 }); await page.waitForTimeout(GOOGLE ? 6000 : 4000); if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); } };
  // The marker of Leaflet, or the one of Google Maps, which is an image or a div with the title of the marker.
  const box = () => page.evaluate(() => { const m = document.getElementById('geolocation-map'); return { lat: document.getElementById('geolocation-latitude').value, lng: document.getElementById('geolocation-longitude').value, address: document.getElementById('geolocation-address').value, on: document.getElementById('geolocation-enabled').checked, off: document.getElementById('geolocation-disabled').checked, isPublic: document.getElementById('geolocation-public').checked, addressDisabled: document.getElementById('geolocation-address').disabled, markers: m.querySelectorAll('.leaflet-marker-icon, [title="Post Location"]').length }; });
  // Change the title a little, so the editor has something to save; it saves the Geolocation box afterwards.
  const save = async () => { await page.evaluate(() => { const title = wp.data.select('core/editor').getEditedPostAttribute('title'); return wp.data.dispatch('core/editor').editPost({ title: /\s$/.test(title) ? title.trim() : title + ' ' }); }); await page.evaluate(() => wp.data.dispatch('core/editor').savePost()); await page.waitForTimeout(6000); };

  const was = await provider(GOOGLE ? 'google' : 'osm');
  const out = { provider: GOOGLE ? 'google' : 'osm', before: await meta() };
  try {
    await open();
    out.boxBefore = await box();
    await page.click('#geolocation-remove');
    out.boxAfterClick = await box();
    await save();
    out.storedAfterRemove = await meta();
    await open();
    out.boxAfterReload = await box();
    await save();
    out.storedAfterSecondSave = await meta();
    await page.goto(base + '/?p=' + POST, { waitUntil: 'load' });
    out.website = await page.evaluate(() => ((document.querySelector('.geolocation-link, .geolocation-plain') || {}).textContent || '(no location shown)'));
    // Choosing a location again: switch on, search for the address, save.
    await open();
    await page.check('#geolocation-enabled');
    await page.fill('#geolocation-address', 'Marienplatz, München');
    await page.click('#geolocation-load');
    await page.waitForTimeout(6000);
    out.boxAfterSearch = await box();
    await page.check('#geolocation-public');
    await save();
    out.storedAfterNewLocation = await meta();
  } finally {
    // The location of the demo content.
    await open();
    await page.check('#geolocation-enabled');
    await page.evaluate(() => { document.getElementById('geolocation-latitude').value = '48.1372'; document.getElementById('geolocation-longitude').value = '11.5756'; document.getElementById('geolocation-address').value = 'Munich, Old Town, Germany'; document.getElementById('geolocation-address-reverse').value = ''; document.getElementById('geolocation-remove-flag').value = ''; document.getElementById('geolocation-public').disabled = false; document.getElementById('geolocation-public').checked = true; });
    await save();
    await provider(was);
  }
  out.restored = await meta();
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
