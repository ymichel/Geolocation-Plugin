// Checks that the map library (Leaflet or the Google Maps API) is only loaded when it is needed: not before a map
// is about to scroll into view, not before the visitor points at a location link, clustering only on overview
// maps, and nothing at all on a page without a location. Runs with both providers; the display mode and the
// provider are restored afterwards.
const { chromium } = require('playwright-core');
const { base } = require('./lib');
const POST = '/2026/10/04/trip-to-berlin/';
const isLibrary = u => /leaflet(\.min)?\.js|leaflet\.css|maps\.googleapis\.com\/maps\/api\/js|osm-tiles-proxy.*leaflet/.test(u);
const isCluster = u => /markercluster|MarkerCluster/.test(u);

(async () => {
  const browser = await chromium.launch();
  const admin = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await admin.goto(base + '/', { waitUntil: 'load' });
  const read = async () => { await admin.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); return [await admin.inputValue('#geolocation_provider'), await admin.inputValue('input[name=geolocation_map_display]:checked')]; };
  const set = async (provider, display) => { await read(); await admin.check('#geolocation_map_display_' + display); await admin.selectOption('#geolocation_provider', provider); await Promise.all([admin.waitForNavigation({ waitUntil: 'load' }), admin.click('#settings input[type=submit]')]); };
  const visit = async (url, height) => {
    const context = await browser.newContext({ viewport: { width: 1366, height } });
    await context.addCookies((await admin.context().cookies()));
    const page = await context.newPage();
    const requests = []; const errors = [];
    page.on('request', r => requests.push(r.url()));
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(base + url, { waitUntil: 'load' });
    await page.waitForTimeout(1500);
    const state = () => ({ library: requests.filter(isLibrary).length, cluster: requests.filter(isCluster).length, google: requests.filter(u => /google|gstatic/.test(u)).length });
    const rendered = sel => page.evaluate(s => { const m = document.querySelector(s); return !!m && (m.querySelectorAll('img.leaflet-tile-loaded').length > 0 || !!m.querySelector('.gm-style')); }, sel);
    return { context, page, state, rendered, errors };
  };
  const [providerBefore, displayBefore] = await read();
  const out = { before: [providerBefore, displayBefore] };
  try {
    for (const provider of ['osm', 'google']) {
      const r = {};
      await set(provider, 'map');
      // A post whose map is below the visible part of a very low window.
      let v = await visit(POST, 300);
      r.mapOffsetTop = await v.page.evaluate(() => Math.round(document.querySelector('.geolocation-map[data-geolocation]').getBoundingClientRect().top));
      r.beforeScroll = v.state();
      await v.page.locator('.geolocation-map[data-geolocation]').scrollIntoViewIfNeeded();
      await v.page.waitForTimeout(4000);
      r.afterScroll = v.state(); r.mapRendered = await v.rendered('.geolocation-map[data-geolocation]'); r.errors = v.errors.slice(); await v.context.close();
      // The same post in a window showing the map at once.
      v = await visit(POST, 1400); await v.page.waitForTimeout(2500);
      r.visibleAtOnce = [v.state().library > 0, await v.rendered('.geolocation-map[data-geolocation]')]; await v.context.close();
      // Overview map: clustering is loaded as well.
      v = await visit('/germany-tour/', 1400); await v.page.waitForTimeout(3500);
      r.overview = { ...v.state(), rendered: await v.rendered('.geolocation-page-map'), markers: await v.page.evaluate(() => document.querySelectorAll('.leaflet-marker-icon, .geolocation-page-map [role=button], .geolocation-page-map img[src*="spotlight"], .geolocation-page-map div[title]').length), errors: v.errors.slice() }; await v.context.close();
      // A page without any location.
      v = await visit('/sample-page/', 1000);
      r.pageWithoutLocation = { ...v.state(), pluginFiles: await v.page.evaluate(() => [...document.querySelectorAll('script[src*="plugins/geolocation"], link[href*="plugins/geolocation"]')].length) }; await v.context.close();
      // Display mode "link": nothing until the visitor points at the link.
      await set(provider, 'link');
      v = await visit(POST, 1000);
      r.linkBeforeHover = v.state();
      await v.page.hover('a.geolocation-link');
      await v.page.waitForTimeout(4500);
      r.linkAfterHover = v.state();
      r.popup = await v.page.evaluate(() => { const m = document.getElementById('map'); const c = getComputedStyle(m); return { opacity: c.opacity, visibility: c.visibility, zIndex: c.zIndex }; });
      r.popupRendered = await v.rendered('#map');
      if (provider === 'osm') await v.page.screenshot({ path: 'out/lazy-hover.png' });
      // Pointing a second time works without loading again.
      await v.page.mouse.move(5, 5); await v.page.waitForTimeout(1500);
      const n = v.state().library; await v.page.hover('a.geolocation-link'); await v.page.waitForTimeout(800);
      r.secondHover = [v.state().library === n, await v.page.evaluate(() => getComputedStyle(document.getElementById('map')).opacity)];
      r.linkErrors = v.errors.slice(); await v.context.close();
      out[provider] = r;
    }
  } finally { await set(providerBefore, displayBefore); }
  out.after = await read();
  console.log(JSON.stringify(out, null, 1).replace(/\n\s{3,}/g, ' '));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
