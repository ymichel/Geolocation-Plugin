// Takes the wordpress.org screenshots 7 to 10 into ./out (PNG, 2732 px wide):
// 7 = overview map with a route, 8 = the "Shortcode" help tab of the settings page, 9 = the block in the editor,
// 10 = an overview map with the track of a GPX file (page "Munich to Venice"). Since posts have tracks, screenshot 7 shows the gaps as dashed lines.
// The test instance must run on the address of lib.js in English, with the demo content.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base } = require('./lib');
const W = 1366;

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: W, height: 950 }, deviceScaleFactor: 2 })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const report = {};

  // The page for the screenshot shows one map with a route and a short text.
  await page.goto(base + '/wp-admin/edit.php?post_type=page', { waitUntil: 'load' });
  const link = await page.evaluate(async () => {
    const pages = await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' });
    let p = pages.find(x => x.title.raw === 'Our route');
    const content = '<!-- wp:paragraph --><p>All stops of the trip, in the order we visited them:</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>[geolocation route="1" width="100%" height="560"]</p><!-- /wp:paragraph -->';
    p = await wp.apiFetch({ path: '/wp/v2/pages' + (p ? '/' + p.id : ''), method: 'POST', data: { title: 'Our route', slug: 'our-route', content, status: 'publish' } });
    return p.link;
  });

  // 7: overview map with the route
  await page.goto(link, { waitUntil: 'load' });
  await page.addStyleTag({ content: '#wpadminbar{display:none!important} html{margin-top:0!important}' });
  // Pages which only exist for the tests do not belong into the menu of the screenshot.
  await page.evaluate(() => { document.querySelectorAll('nav li').forEach(li => { if (/test$/i.test(li.textContent.trim())) li.remove(); }); return document.fonts.ready; });
  await page.waitForFunction(() => {
    const tiles = [...document.querySelectorAll('.geolocation-page-map img.leaflet-tile')];
    return tiles.length > 0 && tiles.every(t => t.classList.contains('leaflet-tile-loaded') && t.complete && t.naturalWidth > 0);
  }, null, { timeout: 60000 });
  await page.waitForTimeout(1500);
  const box = await page.locator('.geolocation-page-map').first().boundingBox();
  report.routePoints = await page.evaluate(() => { const p = document.querySelector('.geolocation-page-map .leaflet-overlay-pane path'); return p ? (p.getAttribute('d').match(/[ML]/g) || []).length : 0; });
  await page.screenshot({ path: 'out/screenshot-7.png', clip: { x: 0, y: 0, width: W, height: Math.ceil(box.y + box.height + 60) } });

  // 8: help tab of the settings page
  await page.setViewportSize({ width: W, height: 842 });
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.click('#contextual-help-link');
  await page.waitForTimeout(1500);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  const help = await page.locator('#contextual-help-wrap').boundingBox();
  report.helpRows = await page.locator('#tab-panel-geolocation-shortcode tbody tr').count();
  await page.screenshot({ path: 'out/screenshot-8.png', clip: { x: 0, y: 0, width: W, height: Math.ceil(help.y + help.height + 150) } });

  await page.setViewportSize({ width: W, height: 1010 });
  // 9: the block "Geolocation Map" in the editor, with its settings. The page is not saved.
  await page.goto(link.replace(/\/$/, '') + '/', { waitUntil: 'load' });
  const pageId = await page.evaluate(() => { const m = document.body.className.match(/page-id-(\d+)/); return m ? m[1] : null; });
  await page.goto(base + '/wp-admin/post.php?post=' + pageId + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(4000);
  if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
  report.block = await page.evaluate(async () => {
    const cats = await wp.apiFetch({ path: '/wp/v2/categories?per_page=100' });
    const tour = cats.find(c => c.name === 'Germany Tour');
    const map = wp.blocks.createBlock('geolocation/map', { categories: tour ? [tour.id] : [], width: '100%', height: 400, route: true });
    await wp.data.dispatch('core/block-editor').resetBlocks([map]);
    await wp.data.dispatch('core/block-editor').selectBlock(map.clientId);
    await wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
    return map.attributes;
  });
  // The floating toolbar of the block would cover the title.
  await page.addStyleTag({ content: '.block-editor-block-popover, .components-popover.block-editor-block-list__block-popover{display:none!important}' });
  // The block shows its map in a preview frame inside the editor canvas.
  const previewMap = page.frameLocator('iframe[name="editor-canvas"]').frameLocator('.wp-block-geolocation-map iframe').locator('.geolocation-page-map');
  await previewMap.waitFor({ timeout: 30000 });
  await previewMap.evaluate(m => new Promise(resolve => { const check = () => { const t = [...m.querySelectorAll('img.leaflet-tile')]; if (t.length && t.every(x => x.classList.contains('leaflet-tile-loaded'))) resolve(); else setTimeout(check, 200); }; check(); }));
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'out/screenshot-9.png' });

  // 10: an overview map with the track of a post (page "Munich to Venice" of the demo content).
  await page.setViewportSize({ width: W, height: 1200 });
  await page.goto(base + '/munich-to-venice/', { waitUntil: 'load' });
  await page.addStyleTag({ content: '#wpadminbar{display:none!important} html{margin-top:0!important}' });
  await page.evaluate(() => { document.querySelectorAll('nav li').forEach(li => { if (/test$/i.test(li.textContent.trim())) li.remove(); }); return document.fonts.ready; });
  await page.waitForFunction(() => {
    const tiles = [...document.querySelectorAll('.geolocation-page-map img.leaflet-tile')];
    return tiles.length > 0 && tiles.every(t => t.classList.contains('leaflet-tile-loaded') && t.complete && t.naturalWidth > 0);
  }, null, { timeout: 60000 });
  await page.waitForTimeout(1500);
  report.track = await page.evaluate(() => !!document.querySelector('.geolocation-page-map .leaflet-overlay-pane path'));
  const mapBox = await page.locator('.geolocation-page-map').boundingBox();
  await page.screenshot({ path: 'out/screenshot-10.png', clip: { x: 0, y: 0, width: W, height: Math.ceil(mapBox.y + mapBox.height + 60) } });

  console.log(JSON.stringify(report));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
