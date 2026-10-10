// Takes the wordpress.org screenshot 1 (editing a post) into ./out (PNG, 2732 px wide), using the post
// "Munich to Venice by bike" with its track. The demo content creates the post, "npm run wp:seed" writes out/munich-venice.gpx.
// With --google it writes a control image of the editor with Google Maps instead (OSM is restored afterwards).
const { chromium } = require('playwright-core');
const { base } = require('./lib');
const GOOGLE = process.argv[2] === '--google';

(async () => {
  require('fs').mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const setProvider = async (provider) => {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', provider);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  };
  const report = {};
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  report.positionLabel = await page.locator('label[for=geolocation_map_position_shortcode]').innerText();
  if (GOOGLE) await setProvider('google');
  try {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    const id = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' })).find(p => p.title.raw === 'Munich to Venice by bike').id);
    await page.goto(base + '/wp-admin/post.php?post=' + id + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
    await page.waitForTimeout(4000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
    await page.evaluate(() => wp.data.dispatch('core/edit-post').closeGeneralSidebar());
    if ((await page.getAttribute('.edit-post-meta-boxes-main button[aria-expanded]', 'aria-expanded')) === 'false') {
      await page.$eval('.edit-post-meta-boxes-main button[aria-expanded]', el => el.click());
    }
    await page.waitForFunction(() => document.getElementById('geolocation-map').offsetHeight > 0);
    if (GOOGLE) {
      await page.waitForTimeout(9000);
      report.google = await page.evaluate(() => ({ gm: !!document.querySelector('#geolocation-map .gm-style'), error: /can't load Google Maps correctly/.test(document.body.innerText) }));
    } else {
      await page.waitForFunction(() => { const t = [...document.querySelectorAll('#geolocation-map img.leaflet-tile')]; return t.length > 0 && t.every(x => x.classList.contains('leaflet-tile-loaded') && x.complete && x.naturalWidth > 0); }, null, { timeout: 60000 });
      await page.waitForTimeout(1500);
      report.osm = await page.evaluate(() => ({ line: !!document.querySelector('#geolocation-map .leaflet-overlay-pane path'), marker: !!document.querySelector('#geolocation-map .leaflet-marker-icon') }));
    }
    report.box = await page.evaluate(() => ({ status: document.getElementById('geolocation-track-status').textContent, address: document.getElementById('geolocation-address').value, bottom: Math.round(document.getElementById('geolocation-track-file').closest('div').getBoundingClientRect().bottom), viewport: window.innerHeight }));
    await page.screenshot({ path: GOOGLE ? 'out/editor-google.png' : 'out/screenshot-1.png' });

    // Removing the track removes its line from the map; nothing is saved.
    await page.evaluate(() => document.getElementById('geolocation-track-remove').click());
    await page.waitForTimeout(GOOGLE ? 1500 : 500);
    if (GOOGLE) await page.screenshot({ path: 'out/editor-google-removed.png' });
    else report.lineAfterRemove = await page.evaluate(() => !!document.querySelector('#geolocation-map .leaflet-overlay-pane path'));
    // Choosing a file draws it again.
    await page.setInputFiles('#geolocation-track-file', 'out/munich-venice.gpx');
    await page.waitForTimeout(GOOGLE ? 2500 : 1500);
    if (GOOGLE) await page.screenshot({ path: 'out/editor-google-chosen.png' });
    else report.lineAfterChoose = await page.evaluate(() => !!document.querySelector('#geolocation-map .leaflet-overlay-pane path'));
  } finally {
    if (GOOGLE) await setProvider('osm');
  }
  report.errors = errors;
  console.log(JSON.stringify(report));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
