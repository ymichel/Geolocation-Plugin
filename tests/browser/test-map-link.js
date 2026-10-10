// Checks the link to an external map with both providers: off by default, the plain link, the notice before
// leaving (chosen, and forced by the strict privacy mode), the behaviour without JavaScript, the three display
// modes, the option of the block "Post Location" and the settings page. Uses the post "Trip to Berlin" (restored).
// The link itself is not followed, so the test does not contact the map services through it.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const POST = id('trip-to-berlin');
const URL_ = '/2026/10/04/trip-to-berlin/';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1366, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const open = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const set = async (o) => { await open(); await page.selectOption('#geolocation_provider', 'osm'); if ('strict' in o) await page.setChecked('#geolocation_osm_strict_privacy', o.strict);
    if ('link' in o) await page.setChecked('#geolocation_map_link', o.link); if ('notice' in o && !(await page.isDisabled('#geolocation_map_link_notice'))) await page.setChecked('#geolocation_map_link_notice', o.notice);
    if (o.display) await page.check('#geolocation_map_display_' + o.display); await page.selectOption('#geolocation_provider', o.provider || 'osm'); await save(); };
  const setContent = async (c) => { await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' }); await page.evaluate(async ([id, c]) => { await wp.apiFetch({ path: '/wp/v2/posts/' + id, method: 'POST', data: { content: c } }); }, [POST, c]); };
  const link = async (p) => { await (p || page).goto(base + URL_, { waitUntil: 'load' }); await (p || page).waitForTimeout(800); return (p || page).evaluate(() => { const a = document.querySelector('a.geolocation-map-link'); if (!a) return null;
    const i = a.querySelector('.geolocation-map-link-icon'); const r = a.getBoundingClientRect();
    return { text: a.textContent.trim(), visible: a.offsetParent !== null && r.height > 0, href: a.getAttribute('href'), dataUrl: a.getAttribute('data-url'), target: a.target, rel: a.rel, icon: i ? i.tagName.toLowerCase() + (i.src ? ':' + i.src.split('/').pop() : '') : null, iconHeight: i ? Math.round(i.getBoundingClientRect().height) : 0, count: document.querySelectorAll('a.geolocation-map-link').length }; }); };
  const out = {};
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const original = await page.evaluate(async (id) => (await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?context=edit' })).content.raw, POST);
  try {
    for (const provider of ['osm', 'google']) {
      const r = {};
      await set({ provider, strict: false, link: false, notice: false, display: 'map' });
      r.off = await link();
      await set({ provider, link: true, notice: false });
      r.plainLink = await link();
      for (const d of ['plain', 'link']) { await set({ provider, display: d }); const l = await link(); r['display_' + d] = l && [l.text, l.visible, !!l.href]; }
      await set({ provider, display: 'map', notice: true });
      r.withNotice = await link();
      // The notice: cancel, then the button which opens the map.
      await page.click('a.geolocation-map-link');
      await page.waitForTimeout(400);
      r.dialog = await page.evaluate(() => { const d = document.querySelector('dialog.geolocation-map-link-notice'); return d ? { open: d.open, text: [...d.querySelectorAll('p')].map(p => p.textContent), buttons: [...d.querySelectorAll('button, a')].map(b => b.textContent), openHref: d.querySelector('a').href, openTarget: d.querySelector('a').target, openRel: d.querySelector('a').rel } : null; });
      if (provider === 'osm') await page.screenshot({ path: 'out/map-link-notice.png', clip: { x: 300, y: 250, width: 760, height: 420 } });
      await page.click('dialog.geolocation-map-link-notice button');
      r.dialogClosed = await page.evaluate(() => !document.querySelector('dialog.geolocation-map-link-notice').open);
      // Without JavaScript the link stays hidden and has no address.
      const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1366, height: 1000 } });
      const p2 = await noJs.newPage();
      await p2.goto(base + URL_, { waitUntil: 'load' });
      r.withoutJavaScript = await p2.locator('a.geolocation-map-link').evaluateAll(a => a.map(x => [x.hasAttribute('hidden'), x.getAttribute('href')]));
      r.withoutJavaScriptVisible = await p2.locator('a.geolocation-map-link').isVisible().catch(() => false);
      await noJs.close();
      // The block overrules the setting.
      await setContent('<!-- wp:geolocation/location {"display":"plain","link":"hide"} /-->');
      r.blockHides = await link();
      await set({ provider, link: false, notice: false });
      await setContent('<!-- wp:geolocation/location {"display":"plain","link":"show"} /-->');
      const b = await link(); r.blockShows = b && [b.text, !!b.href];
      await setContent(original);
      out[provider] = r;
    }
    // Strict privacy mode (OpenStreetMap): the notice is forced.
    await set({ provider: 'osm', strict: true, link: true, notice: false, display: 'map' });
    out.strict = await link();
    await page.click('a.geolocation-map-link').catch(() => {});
    out.strictDialog = await page.evaluate(() => { const d = document.querySelector('dialog.geolocation-map-link-notice'); return d ? d.open : null; });
    await open();
    const box = () => page.evaluate(() => { const n = document.getElementById('geolocation_map_link_notice'); return { checked: n.checked, disabled: n.disabled, forcedText: document.getElementById('geolocation-map-link-forced').offsetParent !== null }; });
    out.settingsStrict = await box();
    await page.uncheck('#geolocation_osm_strict_privacy');
    out.settingsStrictUnchecked = await box();
    await page.check('#geolocation_osm_strict_privacy');
    out.settingsStrictChecked = await box();
    await page.selectOption('#geolocation_provider', 'google');
    out.settingsGoogleSelected = await box();
    // A chosen notice survives a time in strict mode.
    await set({ provider: 'osm', strict: false, link: true, notice: true });
    await set({ provider: 'osm', strict: true });
    await set({ provider: 'osm', strict: false });
    await open();
    out.noticeKeptAfterStrict = await page.isChecked('#geolocation_map_link_notice');
  } finally {
    await setContent(original);
    await set({ provider: 'osm', strict: false, link: false, notice: false, display: 'map' });
  }
  await open();
  out.final = await page.evaluate(() => [document.getElementById('geolocation_provider').value, document.getElementById('geolocation_map_link').checked, document.getElementById('geolocation_map_link_notice').checked, document.getElementById('geolocation_osm_strict_privacy').checked, document.querySelector('input[name=geolocation_map_display]:checked').value, document.getElementById('geolocation_osm_use_proxy').checked]);
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
