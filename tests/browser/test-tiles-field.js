// Checks how the settings page explains the tiles URL: with the proxy in use and with "Use Proxy" switched off.
// Restores "Use Proxy" afterwards.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base } = require('./lib');
const url = base + '/wp-admin/options-general.php?page=geolocation.php';

async function save(page, useProxy) {
  await page.goto(url, { waitUntil: 'load' });
  if (!(await page.$('#geolocation_osm_use_proxy'))) return 'checkbox not shown (proxy plugin inactive)';
  if (useProxy) await page.check('#geolocation_osm_use_proxy'); else await page.uncheck('#geolocation_osm_use_proxy');
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  return 'saved';
}
const read = page => page.evaluate(() => {
  const cell = document.getElementById('geolocation_osm_tiles_url').closest('td');
  return { useProxy: document.getElementById('geolocation_osm_use_proxy')?.checked, text: cell.innerText.replace(/\s+/g, ' ').trim(), field: document.getElementById('geolocation_osm_tiles_url').value };
});

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  out.saveOn = await save(page, true);
  out.withProxy = await read(page);
  await page.locator('#geolocation_osm_tiles_url').scrollIntoViewIfNeeded();
  await page.locator('.osm-urls').screenshot({ path: 'out/tiles-field-proxy.png' });
  out.saveOff = await save(page, false);
  out.withoutProxy = await read(page);
  await page.locator('.osm-urls').screenshot({ path: 'out/tiles-field-direct.png' });
  out.restore = await save(page, true);
  out.restored = (await read(page)).useProxy;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
