// Checks the block "Geolocation Map": rendering on the website and the block in the editor.
// Creates or updates the page "Block test"; writes control screenshots to ./out.
// With --google the website is also checked with Google Maps.
const { chromium } = require('playwright-core');
const { base } = require('./lib');

(async () => {
  require('fs').mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 950 }, deviceScaleFactor: 1 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 200)); });
  await page.goto(base + '/', { waitUntil: 'load' });
  const report = {};

  await page.goto(base + '/wp-admin/edit.php?post_type=page', { waitUntil: 'load' });
  const info = await page.evaluate(async () => {
    const cats = await wp.apiFetch({ path: '/wp/v2/categories?per_page=100' });
    const pages = await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' });
    let p = pages.find(x => x.title.raw === 'Block test');
    const content = '<!-- wp:paragraph --><p>Block with a route:</p><!-- /wp:paragraph -->\n\n'
      + '<!-- wp:geolocation/map {"width":"100%","height":420,"route":true} /-->\n\n'
      + '<!-- wp:paragraph --><p>Second block, fixed zoom, unknown category:</p><!-- /wp:paragraph -->\n\n'
      + '<!-- wp:geolocation/map {"categories":[99999]} /-->\n\n'
      + '<!-- wp:geolocation/map {"height":250,"zoom":5,"align":"wide"} /-->';
    p = await wp.apiFetch({ path: '/wp/v2/pages' + (p ? '/' + p.id : ''), method: 'POST', data: { title: 'Block test', content, status: 'publish' } });
    return { id: p.id, link: p.link, cats: cats.map(c => c.id + ':' + c.name + ':' + c.count) };
  });
  report.cats = info.cats;

  // Website
  await page.goto(info.link, { waitUntil: 'load' });
  await page.waitForSelector('.geolocation-page-map .leaflet-marker-icon', { timeout: 30000 });
  await page.waitForTimeout(2500);
  report.front = await page.evaluate(() => [...document.querySelectorAll('.geolocation-page-map')].map(m => ({
    id: m.id, wrapper: m.parentElement.className, w: m.offsetWidth, h: m.offsetHeight, zoom: m.dataset.zoom || '', route: m.dataset.route || '',
    markers: JSON.parse(m.dataset.markers).length, leaflet: m.classList.contains('leaflet-container'), line: !!m.querySelector('.leaflet-overlay-pane path'),
  })));
  await page.screenshot({ path: 'out/block-front.png', fullPage: true });

  // Editor
  await page.goto(base + '/wp-admin/post.php?post=' + info.id + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(4000);
  if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
  report.editor = await page.evaluate(async () => {
    const type = wp.blocks.getBlockType('geolocation/map');
    const blocks = wp.data.select('core/block-editor').getBlocks().filter(b => b.name === 'geolocation/map');
    await wp.data.dispatch('core/block-editor').selectBlock(blocks[0].clientId);
    await wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
    return { registered: !!type, title: type && type.title, description: type && type.description, attributes: type && Object.keys(type.attributes), blocks: blocks.map(b => ({ valid: b.isValid, attrs: b.attributes })) };
  });
  await page.waitForTimeout(2500);
  report.sidebar = await page.evaluate(() => [...document.querySelectorAll('.block-editor-block-inspector .components-panel__body-title, .block-editor-block-inspector label')].map(e => e.textContent.trim()).filter(Boolean));
  const frame = page.frameLocator('iframe[name="editor-canvas"]');
  report.placeholder = await frame.locator('.wp-block-geolocation-map').first().innerText().catch(e => 'no iframe: ' + e.message.slice(0, 80));
  await page.screenshot({ path: 'out/block-editor.png' });

  // The block does not depend on a page: render it through the REST API, and check the texts of another language.
  report.rest = await page.evaluate(async () => {
    const r = await wp.apiFetch({ path: '/wp/v2/block-renderer/geolocation/map?context=edit', method: 'POST', data: { attributes: { height: 200, route: true } } });
    return { length: r.rendered.length, map: /geolocation-page-map/.test(r.rendered), route: /data-route="1"/.test(r.rendered), height: /height:200px/.test(r.rendered) };
  });
  report.i18n = { title: await page.evaluate(() => window.geolocationBlock.i18n.title + ' | ' + window.geolocationBlock.i18n.posts + ' | ' + window.geolocationBlock.i18n.placeholder) };

  // Change settings through the sidebar: choose a category, switch off "fit".
  const cat = page.locator('.block-editor-block-inspector .components-form-token-field__input').first();
  await cat.click(); await cat.type('Unc'); await page.waitForTimeout(600); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter'); await page.keyboard.press('Escape');
  await page.waitForTimeout(800);
  await page.getByLabel('Fit the map to the markers').click();
  await page.waitForTimeout(800);
  report.zoomSlider = await page.getByRole('slider', { name: 'Zoom level' }).count();
  await page.getByRole('slider', { name: 'Zoom level' }).fill('9').catch(() => {});
  await page.waitForTimeout(400);
  report.afterEdit = await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock().attributes);
  await page.locator('.block-editor-block-inspector').evaluate(e => { (e.closest('.interface-complementary-area, .editor-sidebar') || e).scrollTop = 9999; });
  await page.screenshot({ path: 'out/block-editor-2.png' });
  // With --google the page is also loaded with Google Maps (needs a stored API key); OSM is restored afterwards.
  if (process.argv[2] === '--google') {
    const setProvider = async (provider) => {
      await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
      await page.selectOption('#geolocation_provider', provider);
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
    };
    await setProvider('google');
    try {
      await page.goto(info.link, { waitUntil: 'load' });
      await page.waitForTimeout(13000);
      report.google = await page.evaluate(() => ({
        errorDialog: /can't load Google Maps correctly/.test(document.body.innerText),
        scripts: [...document.scripts].map(s => s.src).filter(s => /maps\.googleapis|markerclusterer|geolocation-front/.test(s)).map(s => s.replace(/key=[^&]+/, 'key=…').split('/').pop().slice(0, 60)),
        maps: [...document.querySelectorAll('.geolocation-page-map')].map(m => ({ id: m.id, w: m.offsetWidth, h: m.offsetHeight, zoom: m.dataset.zoom || '', route: m.dataset.route || '', gm: !!m.querySelector('.gm-style'), tiles: m.querySelectorAll('img[src*="googleapis.com"], img[src*="gstatic.com"]').length })),
      }));
      await page.screenshot({ path: 'out/block-google.png', fullPage: true });
    } finally {
      await setProvider('osm');
    }
  }
  report.errors = errors;
  console.log(JSON.stringify(report, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
