// Checks the switches for the key figures and the elevation profile of a track: the two settings, the popup of the
// overview map and the options of the block "Post Location" (post "Trip to Potsdam", restored afterwards).
// With --google everything runs with Google Maps (needs a stored API key; OSM is restored afterwards).
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const GOOGLE = process.argv[2] === '--google';
const POST = id('trip-to-potsdam');
const URL = '/2026/10/04/trip-to-potsdam/';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const settings = async (fn) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); await fn(); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); };
  const switches = (figures, profile) => settings(async () => { await page.setChecked('#geolocation_track_figures', figures); await page.setChecked('#geolocation_track_profile', profile); });
  const post = async () => {
    await page.goto(base + URL, { waitUntil: 'load' });
    const m = page.locator('.geolocation-map').first();
    if (await m.count()) { await m.scrollIntoViewIfNeeded(); await page.waitForTimeout(GOOGLE ? 7000 : 2500); }
    return page.evaluate(() => { const d = document.querySelector('.geolocation-track-details'); const m = document.querySelector('.geolocation-map');
      return { figures: d && d.firstChild && d.firstChild.nodeType === 3 ? d.firstChild.textContent.slice(0, 40) : '', profile: !!document.querySelector('.geolocation-elevation'), details: document.querySelectorAll('.geolocation-track-details').length,
        map: m ? (m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style')) : null, track: m ? !!m.dataset.track : null, googleError: /can't load Google Maps correctly/.test(document.body.innerText) }; });
  };
  const overview = async () => { await page.goto(base + '/our-route/', { waitUntil: 'load' }); await page.waitForTimeout(GOOGLE ? 7000 : 2500); return page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); const ms = JSON.parse(m.dataset.markers); return { lengths: ms.map(x => x.length).filter(Boolean), tracks: ms.filter(x => x.track).length, map: m.classList.contains('leaflet-container') || !!m.querySelector('.gm-style') }; }); };
  const setContent = async (c) => { await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' }); await page.evaluate(async ([id, c]) => { await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: c } }); }, [POST, c]); };
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  if (GOOGLE) await settings(async () => { await page.selectOption('#geolocation_provider', 'google'); });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const original = await page.evaluate(async (id) => (await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' })).content.raw, POST);
  try {
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.defaults = await page.evaluate(() => [document.getElementById('geolocation_track_figures').checked, document.getElementById('geolocation_track_profile').checked]);
    await switches(true, true);
    out.bothOn = [await post(), await overview()];
    await switches(false, true);
    out.figuresOff = [await post(), await overview()];
    await switches(true, false);
    out.profileOff = await post();
    await switches(false, false);
    out.bothOff = await post();
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    out.storedOff = await page.evaluate(() => [document.getElementById('geolocation_track_figures').checked, document.getElementById('geolocation_track_profile').checked]);

    // The block overrules the settings.
    await setContent('<!-- wp:geolocation/location {"display":"map","figures":"show","profile":"show"} /-->');
    out.blockShows = await post();
    await setContent('<!-- wp:geolocation/location {"display":"plain","figures":"show","profile":"show"} /-->');
    out.blockPlainShows = await post();
    await switches(true, true);
    await setContent('<!-- wp:geolocation/location {"display":"map","figures":"hide","profile":"hide"} /-->');
    out.blockHides = await post();
    await setContent('<!-- wp:geolocation/location {"display":"map","figures":"hide"} /-->');
    out.blockHidesFigures = await post();

    // The options of the block in the editor.
    await page.goto(base + '/wp-admin/post.php?post=' + POST + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
    await page.waitForTimeout(4000);
    if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
    await page.evaluate(async () => { const b = wp.data.select('core/block-editor').getBlocks().find(b => b.name === 'geolocation/location'); await wp.data.dispatch('core/block-editor').selectBlock(b.clientId); await wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block'); });
    await page.waitForTimeout(1500);
    const panel = page.locator('.block-editor-block-inspector .components-panel__body', { hasText: 'Tracks' }).first();
    if (!(await panel.locator('select').count())) await panel.locator('button').first().click();
    await page.getByLabel('Key figures of the track').selectOption('show');
    await page.getByLabel('Elevation profile').selectOption('hide');
    await page.waitForTimeout(GOOGLE ? 9000 : 4000);
    out.editor = await page.evaluate(() => ({ valid: wp.data.select('core/block-editor').getSelectedBlock().isValid, attrs: wp.data.select('core/block-editor').getSelectedBlock().attributes }));
    out.editorPreview = await page.frameLocator('iframe[name="editor-canvas"]').locator('.wp-block-geolocation-location').first().frameLocator('iframe').locator('body').evaluate(() => ({ figures: !!(document.querySelector('.geolocation-track-details') || {}).firstChild && document.querySelector('.geolocation-track-details').firstChild.nodeType === 3, profile: !!document.querySelector('.geolocation-elevation'), map: !!document.querySelector('.geolocation-map') }));
    await page.screenshot({ path: 'out/track-switches-editor' + (GOOGLE ? '-google' : '') + '.png' });
  } finally {
    await setContent(original);
    await settings(async () => { await page.setChecked('#geolocation_track_figures', true); await page.setChecked('#geolocation_track_profile', true); if (GOOGLE) await page.selectOption('#geolocation_provider', 'osm'); });
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
