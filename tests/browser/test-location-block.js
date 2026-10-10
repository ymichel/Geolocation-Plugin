// Checks the block "Post Location": output in a post (map with own size, plain text), no second automatic output,
// nothing on a page, registration and controls in the editor. With --google the website is checked with Google Maps
// (needs a stored API key; OSM is restored afterwards). The content of the post "Trip to Berlin" is restored afterwards.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const POST = id('trip-to-berlin');
const GOOGLE = process.argv[2] === '--google';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 950 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 200)); });
  await page.goto(base + '/', { waitUntil: 'load' });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const out = {};
  const original = await page.evaluate(async (id) => { const p = await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' }); return { content: p.content.raw, link: p.link }; }, POST);
  const setContent = (content) => page.evaluate(async ([id, c]) => { await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: c } }); }, [POST, content]);
  const front = async () => {
    await page.goto(original.link, { waitUntil: 'load' });
    await page.waitForTimeout(1500);
    const m = page.locator('.geolocation-map').first();
    if (await m.count()) { await m.scrollIntoViewIfNeeded(); await page.waitForTimeout(GOOGLE ? 9000 : 3000); }
    return page.evaluate(() => ({
      maps: [...document.querySelectorAll('.geolocation-map')].map(m => ({ w: m.offsetWidth, h: m.offsetHeight, wrapper: m.parentElement.className, leaflet: m.classList.contains('leaflet-container'), google: !!m.querySelector('.gm-style') })),
      plain: [...document.querySelectorAll('.geolocation-plain')].map(e => e.textContent + ' | ' + e.parentElement.className),
      links: document.querySelectorAll('.geolocation-link').length,
      googleError: /can't load Google Maps correctly/.test(document.body.innerText),
      order: (() => { const b = document.querySelector('.wp-block-geolocation-location'); const after = document.querySelector('.marker-after'); return b && after ? !!(b.compareDocumentPosition(after) & Node.DOCUMENT_POSITION_FOLLOWING) : null; })(),
    }));
  };
  const setProvider = async (provider) => {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    await page.selectOption('#geolocation_provider', provider);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  };
  if (GOOGLE) await setProvider('google');
  try {
    out.provider = GOOGLE ? 'google' : 'osm';
    out.before = await front();
    const text = '<!-- wp:paragraph --><p>Before the location.</p><!-- /wp:paragraph -->\n\n';
    const after = '\n\n<!-- wp:paragraph {"className":"marker-after"} --><p class="marker-after">After the location.</p><!-- /wp:paragraph -->';
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    await setContent(text + '<!-- wp:geolocation/location {"display":"map","width":600,"height":320} /-->' + after);
    out.map = await front();
    await page.screenshot({ path: 'out/location-block-front' + (GOOGLE ? '-google' : '') + '.png' });
    {
      // The link with the map shown on hover.
      await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
      await setContent(text + '<!-- wp:geolocation/location {"display":"link"} /-->' + after);
      await page.goto(original.link, { waitUntil: 'load' });
      await page.waitForTimeout(6000);
      await page.locator('a.geolocation-link').scrollIntoViewIfNeeded();
      await page.hover('a.geolocation-link');
      await page.waitForTimeout(5000);
      out.hover = await page.evaluate(() => { const m = document.getElementById('map'); return { wrapper: document.querySelector('a.geolocation-link').closest('.wp-block-geolocation-location') !== null, visible: m && getComputedStyle(m).opacity === '1', google: !!(m && m.querySelector('.gm-style')), leaflet: !!(m && m.classList.contains('leaflet-container')) }; });
      await page.screenshot({ path: 'out/location-block-hover' + (GOOGLE ? '-google' : '') + '.png' });
    }
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    await setContent(text + '<!-- wp:geolocation/location {"display":"plain"} /-->' + after);
    out.plain = await front();
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    await setContent(text + '<!-- wp:geolocation/location /-->' + after);
    out.asSettings = await front();

    // On a page there is no location of a post.
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    out.rest = await page.evaluate(async (id) => {
      const none = await wp.apiFetch({ path: '/wp/v2/block-renderer/geolocation/location?context=edit', method: 'POST', data: { attributes: {} } });
      const post = await wp.apiFetch({ path: '/wp/v2/block-renderer/geolocation/location?context=edit&post_id=' + id, method: 'POST', data: { attributes: { display: 'plain' } } });
      return { withoutPost: none.rendered, withPost: post.rendered.replace(/<[^>]+>/g, '').trim() };
    }, POST);

    // Editor
    await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
    await page.waitForTimeout(4000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
    out.editor = await page.evaluate(async () => {
      const type = wp.blocks.getBlockType('geolocation/location');
      const b = wp.data.select('core/block-editor').getBlocks().find(x => x.name === 'geolocation/location');
      await wp.data.dispatch('core/block-editor').selectBlock(b.clientId);
      await wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
      return { title: type.title, description: type.description, valid: b.isValid, attrs: b.attributes };
    });
    await page.waitForTimeout(2000);
    out.placeholder = await page.frameLocator('iframe[name="editor-canvas"]').locator('.wp-block-geolocation-location').first().innerText().catch(e => 'no iframe: ' + e.message.slice(0, 80));
    out.sidebarBefore = await page.evaluate(() => [...document.querySelectorAll('.block-editor-block-inspector label')].map(e => e.textContent.trim()));
    await page.getByLabel('Display', { exact: true }).selectOption('plain');
    await page.waitForTimeout(500);
    out.sidebarPlain = await page.evaluate(() => [...document.querySelectorAll('.block-editor-block-inspector label')].map(e => e.textContent.trim()));
    await page.getByLabel('Display', { exact: true }).selectOption('map');
    await page.getByLabel('Height', { exact: true }).fill('280');
    await page.waitForTimeout(500);
    out.afterEdit = await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock().attributes);
    await page.screenshot({ path: 'out/location-block-editor.png' });
  } finally {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    await setContent(original.content);
    if (GOOGLE) await setProvider('osm');
    out.restored = await page.evaluate(async (id) => !/geolocation\/location/.test((await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' })).content.raw), POST);
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
