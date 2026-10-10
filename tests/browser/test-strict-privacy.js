const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const proxyRow = 'tr[data-plugin="osm-tiles-proxy/osm-tiles-proxy.php"]';
let external = [];

async function setProxyPlugin(page, active) {
  await page.goto(base + '/wp-admin/plugins.php', { waitUntil: 'load' });
  const link = proxyRow + (active ? ' .activate a' : ' .deactivate a');
  if (await page.$(link)) await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(link)]);
  return page.$eval(proxyRow, el => el.classList.contains('active'));
}
async function saveSettings(page, opts) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  for (const [id, on] of Object.entries(opts)) {
    if (!(await page.$('#' + id))) continue;
    if (on) await page.check('#' + id); else await page.uncheck('#' + id);
  }
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  return page.evaluate(() => ({
    strict: document.getElementById('geolocation_osm_strict_privacy')?.checked,
    useProxy: document.getElementById('geolocation_osm_use_proxy') ? document.getElementById('geolocation_osm_use_proxy').checked : 'not shown',
    strictRow: document.getElementById('geolocation_osm_strict_privacy')?.closest('tr').innerText.replace(/\s+/g, ' ').trim(),
    notices: [...document.querySelectorAll('.notice')].map(n => n.innerText.replace(/\s+/g, ' ').trim()).filter(t => /Geolocation|privacy/i.test(t)),
  }));
}
async function front(page) {
  external = [];
  await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
  if (await page.$('.geolocation-map[data-geolocation]')) await page.locator('.geolocation-map[data-geolocation]').scrollIntoViewIfNeeded();
  await page.waitForTimeout(7000);
  const post = await page.evaluate(() => {
    const m = document.querySelector('.geolocation-map[data-geolocation]'); const tiles = m ? [...m.querySelectorAll('img.leaflet-tile')] : [];
    return { text: (document.querySelector('.geolocation-plain, .geolocation-link') || {}).innerText, cls: (document.querySelector('.geolocation-plain, .geolocation-link') || {}).className,
      map: !!m, tilesLoaded: tiles.filter(t => t.complete && t.naturalWidth === 256).length, tileHost: tiles[0] ? new URL(tiles[0].src).host + (tiles[0].src.includes('cache/osm-tiles') ? ' (proxy)' : '') : null,
      leafletLoaded: document.querySelectorAll('script[src*="leaflet"]').length, pluginCss: !!document.getElementById('geolocation_css-css') };
  });
  await page.goto(base + '/map/', { waitUntil: 'load' });
  await page.waitForTimeout(6000);
  const pageMap = await page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); return { map: !!m, markers: m ? m.querySelectorAll('.leaflet-marker-icon').length : 0, shortcodeLeft: document.body.innerText.includes('[geolocation]'), leafletLoaded: document.querySelectorAll('script[src*="leaflet"]').length }; });
  return { post, pageMap, externalRequests: [...new Set(external)] };
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('request', r => { const h = new URL(r.url()).host; if (!/^127\.0\.0\.1/.test(h) && !/^data:|^blob:/.test(r.url())) external.push(h); });
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });

  out.proxyActive1 = await setProxyPlugin(page, true);
  out.s1_settings = await saveSettings(page, { geolocation_osm_use_proxy: true, geolocation_osm_strict_privacy: true, geolocation_map_display_map: true });
  out.s1_strict_proxy_ok = await front(page);

  out.proxyActive2 = await setProxyPlugin(page, false);
  out.s2_strict_proxy_gone = await front(page);
  await page.goto(base + '/wp-admin/index.php', { waitUntil: 'load' });
  out.s2_notice = await page.evaluate(() => [...document.querySelectorAll('.notice')].map(n => n.innerText.replace(/\s+/g, ' ').trim()).filter(t => /strict privacy/i.test(t)));
  await page.goto(base + '/wp-admin/post.php?post=' + id('trip-to-hamburg') + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.edit-post-meta-boxes-main', { timeout: 30000 });
  await page.waitForTimeout(6000);
  out.s2_editor = await page.evaluate(() => ({ tilesUrl: geolocationAdmin.tilesUrl, markers: document.querySelectorAll('#geolocation-map .leaflet-marker-icon').length }));

  out.s3_settings = await saveSettings(page, { geolocation_osm_strict_privacy: false });
  out.s3_not_strict_no_proxy = await front(page);

  out.proxyActive4 = await setProxyPlugin(page, true);
  out.s4_settings = await saveSettings(page, { geolocation_osm_use_proxy: true, geolocation_osm_strict_privacy: false });
  out.s4_proxy_again = await front(page);

  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
