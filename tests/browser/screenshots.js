// Takes the wordpress.org screenshots 2 to 6 of the Playground test instance into ./out (PNG, 2732 px wide).
// Screenshot 1 is taken by screenshot-1.js, 7 to 10 by screenshots-route.js.
// The test instance must be running on the address of lib.js with the English demo content.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const W = 1366;
const SHORTCODE_5 = '[geolocation cat="travel" height="400" route="1"]';

async function tilesLoaded(page, sel) {
  await page.waitForFunction((s) => {
    const tiles = [...document.querySelectorAll(s + ' img.leaflet-tile')];
    return tiles.length > 0 && tiles.every(t => t.classList.contains('leaflet-tile-loaded') && t.complete && t.naturalWidth > 0);
  }, sel, { timeout: 60000 });
  await page.waitForTimeout(1200); // fade-in animation of the tiles
  return page.evaluate((s) => document.querySelectorAll(s + ' img.leaflet-tile-loaded').length, sel);
}

async function setDisplay(page, mode) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.check('#geolocation_map_display_' + mode);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
}

async function front(page, url) {
  await page.goto(url, { waitUntil: 'load' });
  await page.addStyleTag({ content: '#wpadminbar{display:none!important} html{margin-top:0!important}' });
  await page.evaluate(() => document.fonts.ready);
}

async function editor(page, id) {
  await page.goto(base + '/wp-admin/post.php?post=' + id + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(4000);
  if (await page.$('.components-modal__frame')) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }
  await page.evaluate(() => wp.data.dispatch('core/edit-post').closeGeneralSidebar());
  await page.waitForTimeout(600);
  await page.evaluate(() => document.fonts.ready);
}

(async () => {
  require('fs').mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: W, height: 950 }, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  const post = { id: id('trip-to-hamburg'), url: base + '/2026/10/04/trip-to-hamburg/' };
  const mapPage = { id: id('map'), url: base + '/map/' };
  const report = {};

  // 2: plain text.
  await setDisplay(page, 'plain');
  await front(page, post.url);
  await page.waitForSelector('.geolocation-plain');
  await page.screenshot({ path: 'out/screenshot-2.png', clip: { x: 0, y: 0, width: W, height: 620 } });

  // 3: link with hover.
  await setDisplay(page, 'link');
  await front(page, post.url);
  await page.hover('a.geolocation-link');
  report[3] = await tilesLoaded(page, '#map');
  await page.screenshot({ path: 'out/screenshot-3.png', clip: { x: 0, y: 0, width: W, height: 900 } });

  // 4: static map.
  await setDisplay(page, 'map');
  await front(page, post.url);
  await page.locator('#map' + post.id).scrollIntoViewIfNeeded();
  report[4] = await tilesLoaded(page, '#map' + post.id);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'out/screenshot-4.png', clip: { x: 0, y: 0, width: W, height: 900 } });

  // 6: page with all locations.
  await front(page, mapPage.url);
  report[6] = await tilesLoaded(page, '.geolocation-page-map');
  await page.screenshot({ path: 'out/screenshot-6.png', clip: { x: 0, y: 0, width: W, height: 717 } });

  // The editor screenshots use a smaller window, so the meta box fits completely.
  await page.setViewportSize({ width: W, height: 842 });

  // 1: editing a post - taken by screenshot-1.js, which uses the post with the GPX track.

  // 5: editing the page with the shortcode.
  await editor(page, mapPage.id);
  // Show a shortcode with attributes; the change is not saved, so screenshot 6 keeps the plain shortcode.
  report[5] = await page.evaluate((code) => {
    const find = (blocks) => { for (const b of blocks) { if (/\[geolocation/.test(String(b.attributes.content || ''))) return b; const i = find(b.innerBlocks); if (i) return i; } return null; };
    const block = find(wp.data.select('core/block-editor').getBlocks());
    if (!block) return 'shortcode block not found';
    wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: code });
    wp.data.dispatch('core/block-editor').clearSelectedBlock();
    return code;
  }, SHORTCODE_5);
  await page.waitForTimeout(800);
  await page.screenshot({ path: 'out/screenshot-5.png', clip: { x: 0, y: 0, width: W, height: 420 } });

  console.log(JSON.stringify(report));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
