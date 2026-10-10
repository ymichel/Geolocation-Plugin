// Checks the block "Post Location" inside a query loop (page "Loop test"): every post shows its own location,
// on the website and in the previews of the editor. With --google the maps are checked with Google Maps.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
const GOOGLE = process.argv[2] === '--google';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 200)); });
  await page.goto(base + '/', { waitUntil: 'load' });
  const setProvider = async (provider) => {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', provider);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  };
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  if (GOOGLE) await setProvider('google');
  try {
    await page.goto(base + '/wp-admin/edit.php?post_type=page', { waitUntil: 'load' });
    const info = await page.evaluate(async () => {
      const pages = await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' });
      let p = pages.find(x => x.title.raw === 'Loop test');
      const loop = (attrs) => '<!-- wp:query {"queryId":1,"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"asc","orderBy":"date","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"level":3} /--><!-- wp:geolocation/location ' + attrs + ' /--><!-- /wp:post-template --></div><!-- /wp:query -->';
      const content = loop('{"display":"map","width":400,"height":180}') + '\n\n' + loop('{"display":"plain"}');
      p = await wp.apiFetch({ path: '/wp/v2/pages' + (p ? '/' + p.id : ''), method: 'POST', data: { title: 'Loop test', content, status: 'publish' } });
      return { id: p.id, link: p.link };
    });
    await page.goto(info.link, { waitUntil: 'load' });
    await page.waitForTimeout(2000);
    for (const m of await page.locator('.geolocation-map').all()) { await m.scrollIntoViewIfNeeded(); await page.waitForTimeout(GOOGLE ? 3500 : 1200); }
    out.front = await page.evaluate(() => ({
      items: [...document.querySelectorAll('.wp-block-post')].map(li => ({ title: (li.querySelector('.wp-block-post-title') || {}).textContent, text: (li.querySelector('.geolocation-link, .geolocation-plain') || {}).textContent || '', map: (() => { const m = li.querySelector('.geolocation-map'); return m ? [m.id, m.dataset.geolocation, m.offsetWidth, m.offsetHeight, m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style'), !!m.dataset.track] : null; })(), details: (li.querySelector('.geolocation-track-details') || {}).firstChild ? li.querySelector('.geolocation-track-details').firstChild.textContent : '' })),
      googleError: /can't load Google Maps correctly/.test(document.body.innerText),
    }));
    await page.screenshot({ path: 'out/loop-front' + (GOOGLE ? '-google' : '') + '.png', fullPage: true });

    await page.goto(base + '/wp-admin/post.php?post=' + info.id + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
    await page.waitForTimeout(GOOGLE ? 14000 : 9000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    const blocks = canvas.locator('.wp-block-geolocation-location');
    out.editorBlocks = await blocks.count();
    out.editor = [];
    for (let i = 0; i < Math.min(out.editorBlocks, 8); i++) {
      const b = blocks.nth(i);
      if (!(await b.locator('iframe').count())) { out.editor.push('no preview frame: ' + (await b.innerText()).slice(0, 60)); continue; }
      out.editor.push(await b.frameLocator('iframe').locator('body').evaluate(() => ((document.querySelector('.geolocation-link, .geolocation-plain, .geolocation-preview-empty') || {}).textContent || '').slice(0, 70) + (document.querySelector('.geolocation-map') ? ' [map]' : '')));
    }
    await page.screenshot({ path: 'out/loop-editor' + (GOOGLE ? '-google' : '') + '.png' });
  } finally {
    if (GOOGLE) await setProvider('osm');
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
