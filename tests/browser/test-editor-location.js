// Checks picking a location in the post editor: click on the map, dragging the marker and "My location".
// Uses post 5 of the test instance and restores its location (Hamburg) and the OSM provider at the end.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const POST = id('trip-to-hamburg');
const HOME = { lat: '53.5511', lng: '9.9937' };

async function openEditor(page) {
  await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.edit-post-meta-boxes-main', { timeout: 30000 });
  await page.waitForTimeout(3000);
  if (await page.$('.components-modal__frame')) await page.keyboard.press('Escape');
  if ((await page.getAttribute('.edit-post-meta-boxes-main button[aria-expanded]', 'aria-expanded')) === 'false') {
    await page.$eval('.edit-post-meta-boxes-main button[aria-expanded]', el => el.click());
  }
  await page.waitForFunction(() => document.getElementById('geolocation-map').offsetHeight > 0);
  await page.locator('#geolocation-map').scrollIntoViewIfNeeded();
  await page.waitForTimeout(3500);
}
const fields = page => page.evaluate(() => ({
  lat: document.getElementById('geolocation-latitude').value, lng: document.getElementById('geolocation-longitude').value,
  addr: document.getElementById('geolocation-address').value.slice(0, 50), status: document.getElementById('geolocation-status').textContent,
  locateVisible: document.getElementById('geolocation-locate').offsetParent !== null, locateLabel: document.getElementById('geolocation-locate').value,
}));
async function setProvider(page, provider) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.selectOption('#geolocation_provider', provider);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
}
async function scenario(page, ctx, label) {
  const r = {};
  await openEditor(page);
  r.initial = await fields(page);
  const box = await page.locator('#geolocation-map').boundingBox();
  // click on the map, away from the zoom buttons and the marker
  await page.mouse.click(box.x + box.width * 0.8, box.y + box.height * 0.75);
  await page.waitForTimeout(4000);
  r.afterClick = await fields(page);
  // drag the marker
  if (label === 'osm') {
    const m = await page.locator('#geolocation-map .leaflet-marker-icon').boundingBox();
    await page.mouse.move(m.x + m.width / 2, m.y + m.height / 2);
    await page.mouse.down(); await page.mouse.move(box.x + box.width * 0.3, box.y + box.height * 0.5, { steps: 8 }); await page.mouse.up();
    await page.waitForTimeout(4000);
    r.afterDrag = await fields(page);
  }
  // "Off": clicks on the map must be ignored
  await page.click('#geolocation-disabled');
  const before = (await fields(page)).lat;
  await page.mouse.click(box.x + box.width * 0.6, box.y + box.height * 0.3);
  await page.waitForTimeout(1500);
  r.clickIgnoredWhenOff = (await fields(page)).lat === before;
  await page.click('#geolocation-enabled');
  // "My location" with a position provided by the browser
  await ctx.setGeolocation({ latitude: 48.8584, longitude: 2.2945 });
  await page.click('#geolocation-locate');
  await page.waitForTimeout(5000);
  r.afterLocate = await fields(page);
  return r;
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, permissions: ['geolocation'], geolocation: { latitude: 48.8584, longitude: 2.2945 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });

  out.osm = await scenario(page, ctx, 'osm');
  // save and reload: the picked position must be stored
  await page.evaluate(async () => { wp.data.dispatch('core/editor').editPost({ title: 'Trip to Hamburg' }); await wp.data.dispatch('core/editor').savePost(); });
  await page.waitForTimeout(8000);
  await openEditor(page);
  out.osmSaved = await page.evaluate(() => ({ lat: geolocationAdmin.latitude, lng: geolocationAdmin.longitude, addr: geolocationAdmin.address }));

  // location denied: a message is shown
  const ctx2 = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const page2 = await ctx2.newPage();
  await page2.goto(base + '/', { waitUntil: 'load' });
  await openEditor(page2);
  await page2.click('#geolocation-locate');
  await page2.waitForTimeout(3000);
  out.locateDenied = await fields(page2);
  await ctx2.close();

  if (process.argv[2] === '--google') {
    await setProvider(page, 'google');
    out.google = await scenario(page, ctx, 'google');
    await setProvider(page, 'osm');
  }

  // restore the demo location
  await openEditor(page);
  await page.evaluate(([lat, lng]) => { document.getElementById('geolocation-latitude').value = lat; document.getElementById('geolocation-longitude').value = lng; document.getElementById('geolocation-address').value = ''; document.getElementById('geolocation-address-reverse').value = ''; }, [HOME.lat, HOME.lng]);
  await page.evaluate(async () => { wp.data.dispatch('core/editor').editPost({ title: 'Trip to Hamburg' }); await wp.data.dispatch('core/editor').savePost(); });
  await page.waitForTimeout(8000);
  await openEditor(page);
  out.restored = await page.evaluate(() => ({ lat: geolocationAdmin.latitude, lng: geolocationAdmin.longitude, addr: geolocationAdmin.address }));

  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
