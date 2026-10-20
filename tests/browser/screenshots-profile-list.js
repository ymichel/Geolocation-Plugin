// Takes the wordpress.org screenshots 9 and 10 into ./out (PNG, 2732 px wide): the interactive elevation profile
// below the map of "Trip to Potsdam", with the place of the track marked on the map, and the list of posts with
// the column "Location" and the field of the bulk edit form. The test instance must run on the address of lib.js
// in English, with the demo content, OpenStreetMap and the display setting "map". Nothing is saved.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const W = 1366;

async function tilesLoaded(page, sel) {
  await page.waitForFunction((s) => {
    const tiles = [...document.querySelectorAll(s + ' img.leaflet-tile')];
    return tiles.length > 0 && tiles.every(t => t.classList.contains('leaflet-tile-loaded') && t.complete && t.naturalWidth > 0);
  }, sel, { timeout: 60000 });
  await page.waitForTimeout(1200); // fade-in animation of the tiles
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: W, height: 1000 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const report = {};

  // 9: the elevation profile follows the pointer, the map marks the place.
  await page.goto(base + '/2026/10/04/trip-to-potsdam/', { waitUntil: 'load' });
  await page.addStyleTag({ content: '#wpadminbar{display:none!important} html{margin-top:0!important}' });
  await page.evaluate(() => document.fonts.ready);
  const map = page.locator('.geolocation-map[data-geolocation]');
  await map.scrollIntoViewIfNeeded();
  await tilesLoaded(page, '.geolocation-map[data-geolocation]');
  const label = page.locator('.geolocation-link, .geolocation-plain').first();
  const profile = page.locator('svg.geolocation-elevation');
  await label.evaluate(el => el.scrollIntoView({ block: 'start' }));
  await page.evaluate(() => window.scrollBy(0, -40));
  const box = await profile.boundingBox();
  await page.mouse.move(box.x + box.width * 0.4, box.y + box.height / 2);
  await page.waitForTimeout(500);
  const top = (await label.boundingBox()).y - 30;
  report[9] = await page.evaluate(() => [((document.querySelector('.geolocation-elevation-label') || {}).textContent || ''), !![...document.querySelectorAll('.leaflet-overlay-pane path')].find(p => p.getAttribute('fill') === '#e53e3e')]);
  await page.screenshot({ path: 'out/screenshot-9.png', clip: { x: 0, y: Math.max(0, top), width: W, height: box.y + box.height + 40 - top } });

  // 10: the list of posts with the column "Location" and the field of the bulk edit form; a taller window, as a
  // screenshot only covers what is visible.
  await page.setViewportSize({ width: W, height: 1300 });
  await page.goto(base + '/wp-admin/edit.php?post_type=post&orderby=title&order=asc', { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  for (const slug of ['trip-to-berlin', 'trip-to-dresden', 'trip-to-hamburg']) await page.check('#cb-select-' + id(slug));
  await page.selectOption('#bulk-action-selector-top', 'edit');
  await page.click('#doaction');
  await page.waitForSelector('#bulk-edit #geolocation-bulk-public', { state: 'visible' });
  await page.selectOption('#bulk-edit #geolocation-bulk-public', '1');
  await page.mouse.move(5, 5);
  const lastRow = await page.locator('#the-list tr:not(.hidden):not(#bulk-edit)').nth(5).boundingBox();
  report[10] = await page.evaluate(() => [document.querySelector('thead th.column-geolocation').innerText, document.querySelectorAll('td.column-geolocation').length]);
  await page.screenshot({ path: 'out/screenshot-10.png', clip: { x: 0, y: 0, width: W, height: Math.min(1300, lastRow.y + lastRow.height + 10) } });

  report.errors = errors;
  console.log(JSON.stringify(report));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
