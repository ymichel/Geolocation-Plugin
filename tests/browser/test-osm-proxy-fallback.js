const { chromium } = require('playwright-core');
const fs = require('fs');
const { base, muPlugins } = require('./lib');
const mu = (process.argv[2] || muPlugins) + '/zz-geolocation-proxy-test.php';
const scenarios = {
  'proxy cache on (normal)': null,
  'proxy cache off, REST off': "<?php add_filter( 'pre_option_osm-tiles-proxy-cache-enabled', '__return_zero' );",
  'proxy cache off, REST on': "<?php add_filter( 'pre_option_osm-tiles-proxy-cache-enabled', '__return_zero' ); add_filter( 'pre_option_osm-tiles-proxy-rest-api-enabled', function () { return 1; } );",
};
(async () => {
  const browser = await chromium.launch();
  const out = {};
  for (const [name, code] of Object.entries(scenarios)) {
    if (code) fs.writeFileSync(mu, code); else if (fs.existsSync(mu)) fs.unlinkSync(mu);
    const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(String(e)));
    await page.goto(base + '/', { waitUntil: 'load' });
    await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
    await page.locator('.geolocation-map[data-geolocation]').scrollIntoViewIfNeeded();
    await page.waitForTimeout(9000);
    out[name] = await page.evaluate(() => {
      const el = document.querySelector('.geolocation-map[data-geolocation]'); const tiles = [...el.querySelectorAll('img.leaflet-tile')];
      return { tilesUrl: geolocationFront.tilesUrl, tiles: tiles.length, loaded: tiles.filter(t => t.complete && t.naturalWidth === 256).length, sample: tiles[0] && tiles[0].src, markers: el.querySelectorAll('.leaflet-marker-icon').length, leaflet: [...document.querySelectorAll('script[src*="leaflet"]')].map(x => x.src.replace(location.origin, ''))[0] };
    });
    out[name].errors = errors;
    await page.close();
  }
  if (fs.existsSync(mu)) fs.unlinkSync(mu);
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { try { fs.unlinkSync(mu); } catch (x) { /* the file was not written */ } console.error(e); process.exit(1); });
