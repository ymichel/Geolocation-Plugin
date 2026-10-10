// Checks the route line of an overview map (shortcode attribute route="1").
// Gives the demo posts times in the order of a round trip and creates the page "Route test" if missing.
// With --google the map is also loaded with Google Maps (needs a stored API key); OSM is restored afterwards.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base } = require('./lib');
const ORDER = ['Luebeck', 'Hamburg', 'Berlin', 'Potsdam', 'Dresden', 'Munich', 'Cologne'];

async function setProvider(page, provider) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.selectOption('#geolocation_provider', provider);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
}

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });

  out.setup = await page.evaluate(async (order) => {
    const log = [];
    const posts = await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' });
    for (const [i, city] of order.entries()) {
      const p = posts.find(x => x.title.raw === 'Trip to ' + city);
      const date = '2026-10-04T' + String(8 + i).padStart(2, '0') + ':00:00';
      if (p && p.date !== date) { await wp.apiFetch({ path: '/wp/v2/posts/' + p.id, method: 'POST', data: { date } }); log.push(city + ' ' + date.slice(11, 16)); }
    }
    const pages = await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' });
    let test = pages.find(p => p.title.raw === 'Route test');
    const content = '<!-- wp:paragraph --><p>[geolocation route="1" width="100%" height="420"]</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>[geolocation height="200"]</p><!-- /wp:paragraph -->';
    test = await wp.apiFetch({ path: '/wp/v2/pages' + (test ? '/' + test.id : ''), method: 'POST', data: { title: 'Route test', slug: 'route-test', content, status: 'publish' } });
    return { log, link: test.link };
  }, ORDER);

  await page.goto(out.setup.link, { waitUntil: 'load' });
  await page.waitForSelector('.geolocation-page-map .leaflet-marker-icon', { timeout: 30000 });
  await page.waitForTimeout(7000);
  out.osm = await page.evaluate(() => [...document.querySelectorAll('.geolocation-page-map')].map(m => {
    const markers = JSON.parse(m.getAttribute('data-markers'));
    const path = m.querySelector('.leaflet-overlay-pane path');
    return { id: m.id, dataRoute: m.getAttribute('data-route'), order: markers.slice().sort((a, b) => a.time - b.time).map(x => x.title.replace('Trip to ', '')).join(' > '),
      line: !!path, linePoints: path ? (path.getAttribute('d').match(/[ML]/g) || []).length : 0, stroke: path ? path.getAttribute('stroke') : null };
  }));
  await page.screenshot({ path: 'out/route-osm.png', clip: { x: 0, y: 0, width: 1366, height: 900 } });

  if (process.argv[2] === '--google') {
    await setProvider(page, 'google');
    await page.goto(out.setup.link, { waitUntil: 'load' });
    await page.waitForTimeout(13000);
    out.google = await page.evaluate(() => ({ errorDialog: /can't load Google Maps correctly/.test(document.body.innerText), maps: [...document.querySelectorAll('.geolocation-page-map')].map(m => [m.id, m.getAttribute('data-route'), m.children.length]) }));
    await page.screenshot({ path: 'out/route-google.png', clip: { x: 0, y: 0, width: 1366, height: 900 } });
    await setProvider(page, 'osm');
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
