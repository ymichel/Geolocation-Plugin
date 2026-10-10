// Checks the Google Maps provider with an API key stored in the test instance:
// overview map (clusters, popup) and editor (click, dragging the marker, address lookup).
// Switches the provider to Google and back to OSM and restores the location of post 5.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base, id } = require('./lib');
const POST = id('trip-to-hamburg');
const HOME = { lat: '53.5511', lng: '9.9937' };

async function setProvider(page, provider) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const keySet = await page.$eval('input[name=geolocation_google_maps_api_key]', el => el.value.length > 0);
  await page.selectOption('#geolocation_provider', provider);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  return keySet;
}
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
  await page.waitForTimeout(6000);
}
// Google renders a marker's title on an <area>; the clickable box is one of its ancestors.
const markerBox = (page, scope, title) => page.evaluate(([sc, t]) => {
  let n = document.querySelector(sc + ' [title="' + t + '"]');
  while (n && n.getBoundingClientRect().width === 0) n = n.parentElement;
  if (!n) return null;
  const r = n.getBoundingClientRect();
  return { x: r.left, y: r.top, width: r.width, height: r.height };
}, [scope, title]);
const fields = page => page.evaluate(() => ({ lat: document.getElementById('geolocation-latitude').value, lng: document.getElementById('geolocation-longitude').value, addr: document.getElementById('geolocation-address').value }));

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 200)); });
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  out.keyStored = await setProvider(page, 'google');

  // --- overview map ---
  await page.goto(base + '/map/', { waitUntil: 'load' });
  await page.waitForTimeout(12000);
  out.overview = await page.evaluate(() => {
    const m = document.querySelector('.geolocation-page-map');
    return { errorDialog: /can't load Google Maps correctly/.test(document.body.innerText), gmErr: !!document.querySelector('.gm-err-container'),
      titledMarkers: [...m.querySelectorAll('[title^="Trip to"]')].map(e => e.getAttribute('title')), clusterer: typeof window.markerClusterer };
  });
  await page.screenshot({ path: 'out/google-overview.png', clip: { x: 0, y: 0, width: 1366, height: 760 } });
  const single = await markerBox(page, '.geolocation-page-map', 'Trip to Munich');
  out.overviewMarkerBox = single;
  if (single) {
    await page.mouse.click(single.x + single.width / 2, single.y + single.height / 2);
    await page.waitForTimeout(2500);
    out.overviewPopup = await page.evaluate(() => { const p = document.querySelector('.geolocation-page-map .geolocation-popup'); return p ? { title: p.querySelector('.geolocation-popup-title')?.innerText, date: p.querySelector('.geolocation-popup-date')?.innerText } : null; });
    await page.screenshot({ path: 'out/google-overview-popup.png', clip: { x: 0, y: 0, width: 1366, height: 760 } });
  }

  // --- single post map ---
  await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
  await page.locator('.geolocation-map[data-geolocation]').scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(8000);
  out.postMap = await page.evaluate(() => { const m = document.querySelector('.geolocation-map[data-geolocation]'); return m ? { children: m.children.length, errorDialog: /can't load Google Maps correctly/.test(m.innerText), images: m.querySelectorAll('img').length } : null; });

  // --- editor ---
  await openEditor(page);
  out.editorInitial = await fields(page);
  const box = await page.locator('#geolocation-map').boundingBox();
  await page.mouse.click(box.x + box.width * 0.75, box.y + box.height * 0.8);
  await page.waitForTimeout(5000);
  out.editorAfterClick = await fields(page);
  const mb = await markerBox(page, '#geolocation-map', 'Post Location');
  out.editorMarkerFound = !!mb;
  if (mb) {
    await page.mouse.move(mb.x + mb.width / 2, mb.y + mb.height / 2);
    await page.mouse.down(); await page.mouse.move(box.x + box.width * 0.25, box.y + box.height * 0.45, { steps: 10 }); await page.mouse.up();
    await page.waitForTimeout(5000);
    out.editorAfterDrag = await fields(page);
  }
  await page.locator('#geolocation-map').screenshot({ path: 'out/google-editor.png' });

  // --- restore ---
  await setProvider(page, 'osm');
  await openEditor(page);
  await page.evaluate(([lat, lng]) => { document.getElementById('geolocation-latitude').value = lat; document.getElementById('geolocation-longitude').value = lng; document.getElementById('geolocation-address').value = ''; document.getElementById('geolocation-address-reverse').value = ''; }, [HOME.lat, HOME.lng]);
  await page.evaluate(async () => { wp.data.dispatch('core/editor').editPost({ title: 'Trip to Hamburg' }); await wp.data.dispatch('core/editor').savePost(); });
  await page.waitForTimeout(8000);
  await openEditor(page);
  out.restored = await page.evaluate(() => ({ lat: geolocationAdmin.latitude, addr: geolocationAdmin.address, tiles: geolocationAdmin.tilesUrl ? 'osm' : 'none' }));

  out.errors = [...new Set(errors)].slice(0, 8);
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
