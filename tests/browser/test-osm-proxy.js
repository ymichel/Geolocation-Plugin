const { chromium } = require('playwright-core');
const { base, id } = require('./lib');

async function mapInfo(page, sel, label) {
  await page.waitForSelector(sel, { timeout: 20000 });
  await page.locator(sel).scrollIntoViewIfNeeded();
  await page.waitForTimeout(9000);
  return page.evaluate(([s, l]) => {
    const el = document.querySelector(s);
    const tiles = [...el.querySelectorAll('img.leaflet-tile')];
    return {
      where: l,
      tiles: tiles.length,
      loaded: tiles.filter(t => t.complete && t.naturalWidth > 0).length,
      sizes: [...new Set(tiles.map(t => t.naturalWidth + 'x' + t.naturalHeight))],
      sample: tiles[0] ? tiles[0].src : null,
      markers: el.querySelectorAll('.leaflet-marker-icon').length,
      leafletJs: [...document.querySelectorAll('script[src*="leaflet"]')].map(x => x.src.replace(location.origin, '')),
      leafletCss: [...document.querySelectorAll('link[href*="leaflet"]')].map(x => x.href.replace(location.origin, '')),
      leafletVersion: typeof L !== 'undefined' ? L.version : null,
    };
  }, [sel, label]);
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
  const errors = []; const tileResponses = {};
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', r => { const u = r.url(); if (/osm-tiles|tile\.openstreetmap/.test(u)) { const k = r.status() + ' ' + (new URL(u)).host + (u.includes('cache/osm-tiles') ? ' (proxy)' : ''); tileResponses[k] = (tileResponses[k] || 0) + 1; } });
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });

  // 1. activate the proxy plugin
  await page.goto(base + '/wp-admin/plugins.php', { waitUntil: 'load' });
  const row = 'tr[data-plugin="osm-tiles-proxy/osm-tiles-proxy.php"]';
  out.proxyFound = !!(await page.$(row));
  if (await page.$(row + ' .activate a')) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(row + ' .activate a')]);
  }
  out.proxyActive = await page.$eval(row, el => el.classList.contains('active'));
  out.fatal = (await page.content()).includes('Fatal error');

  // 2. geolocation settings: enable the proxy
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  out.useProxyCheckbox = !!(await page.$('#geolocation_osm_use_proxy'));
  if (out.useProxyCheckbox) {
    await page.check('#geolocation_osm_use_proxy');
    await page.check('#geolocation_map_display_map');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  }
  out.settings = await page.evaluate(() => ({
    checked: document.getElementById('geolocation_osm_use_proxy')?.checked,
    osmRows: [...document.querySelectorAll('.osm-urls table tr')].map(tr => tr.innerText.replace(/\s+/g, ' ').trim()),
    tilesField: document.getElementById('geolocation_osm_tiles_url')?.value,
    data: window.geolocationSettings,
  }));
  out.settingsPreview = await mapInfo(page, '#map', 'settings preview');

  // 3. frontend: post map
  await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
  out.front = await page.evaluate(() => window.geolocationFront);
  out.postMap = await mapInfo(page, '.geolocation-map[data-geolocation]', 'post map');

  // 4. page map
  await page.goto(base + '/map/', { waitUntil: 'load' });
  out.pageMap = await mapInfo(page, '.geolocation-page-map', 'page map');

  // 5. editor
  await page.goto(base + '/wp-admin/post.php?post=' + id('trip-to-hamburg') + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.edit-post-meta-boxes-main', { timeout: 30000 });
  await page.waitForTimeout(3000);
  if (await page.$('.components-modal__frame')) await page.keyboard.press('Escape');
  if ((await page.getAttribute('.edit-post-meta-boxes-main button[aria-expanded]', 'aria-expanded')) === 'false') {
    await page.$eval('.edit-post-meta-boxes-main button[aria-expanded]', el => el.click());
  }
  out.editorData = await page.evaluate(() => ({ tilesUrl: geolocationAdmin.tilesUrl }));
  out.editorMap = await mapInfo(page, '#geolocation-map', 'editor');

  // 6. hover mode
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.check('#geolocation_map_display_link');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
  await page.hover('a.geolocation-link');
  out.hoverMap = await mapInfo(page, '#map', 'hover map');

  // back to "map"
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.check('#geolocation_map_display_map');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);

  out.tileResponses = tileResponses;
  out.errors = [...new Set(errors)].slice(0, 10);
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
