// Checks the attributes of the overview map shortcode: cat, tag, width, height, zoom and several maps on one page.
// Creates the category "North" (Hamburg, Luebeck), the tag "capital" (Berlin) and the page "Shortcode test" if missing.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
const CONTENT = [
  '[geolocation]',
  '[geolocation cat="north" height="250"]',
  '[geolocation tag="capital" width="100%" zoom="8"]',
  '[geolocation cat="does-not-exist"]',
  '[geolocation cat="North, does-not-exist" width="400" height="200"]',
].map(s => '<!-- wp:paragraph --><p>' + s + '</p><!-- /wp:paragraph -->').join('\n\n');

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });

  out.setup = await page.evaluate(async (content) => {
    const log = [];
    const ensure = async (path, name) => {
      const found = (await wp.apiFetch({ path: path + '?per_page=100&search=' + encodeURIComponent(name) })).find(t => t.name === name);
      return found ? found.id : (await wp.apiFetch({ path, method: 'POST', data: { name } })).id;
    };
    const north = await ensure('/wp/v2/categories', 'North');
    const capital = await ensure('/wp/v2/tags', 'capital');
    const posts = await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' });
    for (const p of posts) {
      const city = p.title.raw.replace('Trip to ', '');
      if (['Hamburg', 'Luebeck'].includes(city) && !p.categories.includes(north)) { await wp.apiFetch({ path: '/wp/v2/posts/' + p.id, method: 'POST', data: { categories: [...p.categories, north] } }); log.push(city + ' -> North'); }
      if (city === 'Berlin' && !p.tags.includes(capital)) { await wp.apiFetch({ path: '/wp/v2/posts/' + p.id, method: 'POST', data: { tags: [...p.tags, capital] } }); log.push('Berlin -> capital'); }
    }
    const pages = await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' });
    let test = pages.find(p => p.title.raw === 'Shortcode test');
    test = await wp.apiFetch({ path: '/wp/v2/pages' + (test ? '/' + test.id : ''), method: 'POST', data: { title: 'Shortcode test', slug: 'shortcode-test', content, status: 'publish' } });
    log.push('page ' + test.id + ' ' + test.link);
    return { log, link: test.link };
  }, CONTENT);

  await page.goto(out.setup.link, { waitUntil: 'load' });
  // let every lazily created map come into view once
  const count = await page.locator('.geolocation-page-map').count();
  for (let i = 0; i < count; i++) { await page.locator('.geolocation-page-map').nth(i).scrollIntoViewIfNeeded(); await page.waitForTimeout(2500); }
  out.maps = await page.evaluate(() => [...document.querySelectorAll('.geolocation-page-map')].map(m => ({
    id: m.id, titles: JSON.parse(m.getAttribute('data-markers')).map(x => x.title.replace('Trip to ', '')).sort().join(','),
    width: m.offsetWidth, height: m.offsetHeight, styleWidth: m.style.width, dataZoom: m.getAttribute('data-zoom'),
    initialized: m.classList.contains('leaflet-container'), drawn: m.querySelectorAll('.leaflet-marker-icon').length,
    tileZoom: (m.querySelector('img.leaflet-tile') || { src: '' }).src.match(/\/(\d+)\/\d+\/\d+\.png/)?.[1] || null,
  })));
  out.leftovers = await page.evaluate(() => (document.querySelector('.entry-content, main') || document.body).innerText.includes('[geolocation'));
  out.duplicateIds = await page.evaluate(() => { const ids = [...document.querySelectorAll('.geolocation-page-map')].map(m => m.id); return ids.length !== new Set(ids).size; });

  // the existing page without attributes must be unchanged
  await page.goto(base + '/map/', { waitUntil: 'load' });
  await page.waitForTimeout(3000);
  out.legacyPage = await page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); return m ? { id: m.id, markers: JSON.parse(m.getAttribute('data-markers')).length, style: m.getAttribute('style') } : null; });
  // a single post still shows its own location and no leftover shortcode
  await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
  out.post = await page.evaluate(() => ({ text: (document.querySelector('.geolocation-link, .geolocation-plain') || {}).innerText, map: !!document.querySelector('.geolocation-map[data-geolocation]') }));

  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
