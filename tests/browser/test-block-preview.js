// Checks the preview of both blocks in the editor: the map of "Geolocation Map" on the page "Block test" and the
// location of "Post Location" in the post "Trip to Potsdam" (its content is restored afterwards).
// With --google the previews are checked with Google Maps (needs a stored API key; OSM is restored afterwards).
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const GOOGLE = process.argv[2] === '--google';
const POST = id('trip-to-potsdam');

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
  const openEditor = async (id) => {
    await page.goto(base + '/wp-admin/post.php?post=' + id + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
    await page.waitForTimeout(4000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
  };
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  // What the preview inside the n-th block of a kind shows.
  const preview = async (selector, n) => {
    const block = canvas.locator(selector).nth(n);
    const frame = block.locator('iframe');
    const inner = block.frameLocator('iframe');
    await frame.scrollIntoViewIfNeeded();
    await page.waitForTimeout(GOOGLE ? 9000 : 3500);
    const state = await inner.locator('body').evaluate(() => ({
      empty: (document.querySelector('.geolocation-preview-empty') || {}).textContent || '',
      text: (document.querySelector('.geolocation-link, .geolocation-plain') || {}).textContent || '',
      maps: [...document.querySelectorAll('.geolocation-map')].map(m => ({ w: m.offsetWidth, h: m.offsetHeight, leaflet: m.classList.contains('leaflet-container'), google: !!m.querySelector('.gm-style'), markers: m.querySelectorAll('.leaflet-marker-icon').length, line: !!m.querySelector('.leaflet-overlay-pane path') })),
      details: (document.querySelector('.geolocation-track-details') || {}).firstChild ? document.querySelector('.geolocation-track-details').firstChild.textContent : '',
      profile: !!document.querySelector('.geolocation-elevation'),
      googleError: /can't load Google Maps correctly/.test(document.body.innerText),
    }));
    state.frame = await frame.evaluate(f => ({ height: f.offsetHeight, pointer: f.style.pointerEvents }));
    return state;
  };
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  if (GOOGLE) await setProvider('google');
  let original = null;
  const setContent = async (c) => { await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' }); await page.evaluate(async ([id, c]) => { await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: c } }); }, [POST, c]); };
  try {
    // Block "Geolocation Map"
    await page.goto(base + '/wp-admin/edit.php?post_type=page', { waitUntil: 'load' });
    const pageId = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/pages?per_page=50&context=edit' })).find(x => x.title.raw === 'Block test').id);
    await openEditor(pageId);
    out.mapRoute = await preview('.wp-block-geolocation-map', 0);
    out.mapUnknownCategory = await preview('.wp-block-geolocation-map', 1);
    out.mapWide = await preview('.wp-block-geolocation-map', 2);
    // Selecting the block makes the map usable; changing a setting reloads the preview.
    await page.evaluate(async () => { const b = wp.data.select('core/block-editor').getBlocks().filter(b => b.name === 'geolocation/map')[0]; await wp.data.dispatch('core/block-editor').selectBlock(b.clientId); await wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { height: 260, route: false }); });
    out.mapAfterChange = await preview('.wp-block-geolocation-map', 0);
    await page.screenshot({ path: 'out/preview-map' + (GOOGLE ? '-google' : '') + '.png' });

    // Block "Post Location"
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    original = await page.evaluate(async (id) => (await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' })).content.raw, POST);
    await setContent('<!-- wp:paragraph --><p>The stage of today:</p><!-- /wp:paragraph -->\n\n<!-- wp:geolocation/location {"display":"map","width":600,"height":300} /-->');
    await openEditor(POST);
    out.locationMap = await preview('.wp-block-geolocation-location', 0);
    await page.evaluate(async () => { const b = wp.data.select('core/block-editor').getBlocks().find(b => b.name === 'geolocation/location'); await wp.data.dispatch('core/block-editor').selectBlock(b.clientId); });
    await page.waitForTimeout(500);
    out.locationSelected = (await preview('.wp-block-geolocation-location', 0)).frame;
    await page.screenshot({ path: 'out/preview-location' + (GOOGLE ? '-google' : '') + '.png' });
    await page.evaluate(async () => { const b = wp.data.select('core/block-editor').getSelectedBlock(); await wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { display: 'plain' }); });
    out.locationPlain = await preview('.wp-block-geolocation-location', 0);
    await page.evaluate(async () => { const b = wp.data.select('core/block-editor').getSelectedBlock(); await wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { display: 'link' }); });
    out.locationLink = await preview('.wp-block-geolocation-location', 0);

    // Without a valid nonce the preview page is refused.
    out.withoutNonce = await page.evaluate(async () => (await fetch(window.geolocationBlock.preview.replace(/_wpnonce=[^&]+/, '_wpnonce=wrong') + '&block=map&atts=%7B%7D')).status);
  } finally {
    if (original !== null) await setContent(original);
    if (GOOGLE) await setProvider('osm');
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
