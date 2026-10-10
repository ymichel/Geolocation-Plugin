// Checks the overview map of a page: marker clustering and the popup with image, title, date and excerpt.
// Adds two demo posts close to existing ones (Luebeck, Potsdam) and a featured image plus excerpt for "Trip to Hamburg" if missing.
// With --google the map is also checked with Google Maps; the provider is switched back to OSM afterwards.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base } = require('./lib');

async function setProvider(page, provider) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  await page.selectOption('#geolocation_provider', provider);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
}

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });

  // --- demo content ---
  out.setup = await page.evaluate(async () => {
    const log = [];
    const posts = await wp.apiFetch({ path: '/wp/v2/posts?per_page=50&context=edit' });
    const lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
    for (const [city, lat, lng] of [['Luebeck', '53.8655', '10.6866'], ['Potsdam', '52.3906', '13.0645']]) {
      if (posts.some(p => p.title.raw === 'Trip to ' + city)) { log.push(city + ': exists'); continue; }
      const post = await wp.apiFetch({ path: '/wp/v2/posts', method: 'POST', data: { title: 'Trip to ' + city, content: '<!-- wp:paragraph --><p>' + lorem + '</p><!-- /wp:paragraph -->', status: 'publish' } });
      const html = await fetch('/wp-admin/post.php?post=' + post.id + '&action=edit', { credentials: 'include' }).then(r => r.text());
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const url = JSON.parse(html.match(/_wpMetaBoxUrl\s*=\s*("[^"]+")/)[1]);
      const fd = new URLSearchParams();
      doc.querySelectorAll('form.metabox-base-form input, form[class*=metabox-location] input').forEach(el => { if (!el.name) return; if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return; fd.append(el.name, el.value); });
      fd.set('geolocation-latitude', lat); fd.set('geolocation-longitude', lng); fd.set('geolocation-address', ''); fd.set('geolocation-address-reverse', ''); fd.set('geolocation-on', '1'); fd.set('geolocation-public', '1'); fd.set('geolocation-remove', '');
      const res = await fetch(url, { method: 'POST', body: fd, credentials: 'include' });
      log.push(city + ': created ' + post.id + ' meta ' + res.status);
      await new Promise(r => setTimeout(r, 1300));
    }
    const hamburg = posts.find(p => p.title.raw === 'Trip to Hamburg');
    if (hamburg && !hamburg.featured_media) {
      const canvas = document.createElement('canvas'); canvas.width = 600; canvas.height = 400;
      const c = canvas.getContext('2d'); const g = c.createLinearGradient(0, 0, 600, 400); g.addColorStop(0, '#2b6cb0'); g.addColorStop(1, '#90cdf4'); c.fillStyle = g; c.fillRect(0, 0, 600, 400);
      c.fillStyle = '#fff'; c.font = 'bold 48px sans-serif'; c.fillText('Hamburg', 190, 215);
      const blob = await new Promise(r => canvas.toBlob(r, 'image/png'));
      const form = new FormData(); form.append('file', blob, 'hamburg-demo.png'); form.append('title', 'Hamburg demo image');
      const media = await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', body: form });
      await wp.apiFetch({ path: '/wp/v2/posts/' + hamburg.id, method: 'POST', data: { featured_media: media.id, excerpt: 'A short walk from the town hall to the harbour, with coffee on the way.' } });
      log.push('Hamburg: image ' + media.id + ' and excerpt set');
    } else { log.push('Hamburg: image ' + (hamburg ? 'exists' : 'post missing')); }
    return log;
  });

  // --- OSM overview map ---
  await page.goto(base + '/map/', { waitUntil: 'load' });
  await page.waitForSelector('.geolocation-page-map .leaflet-marker-icon', { timeout: 30000 });
  await page.waitForTimeout(7000);
  out.osm = await page.evaluate(() => {
    const m = document.querySelector('.geolocation-page-map');
    return { dataMarkers: JSON.parse(m.getAttribute('data-markers')).length, clusters: [...m.querySelectorAll('.marker-cluster')].map(c => c.innerText.trim()), singleMarkers: m.querySelectorAll('.leaflet-marker-icon:not(.marker-cluster)').length,
      clusterScript: !!document.querySelector('script[src*="leaflet.markercluster"]'), clusterCss: document.querySelectorAll('link[href*="MarkerCluster"]').length, sample: JSON.parse(m.getAttribute('data-markers')).find(x => x.title === 'Trip to Hamburg') };
  });
  await page.screenshot({ path: 'out/overview-osm-clusters.png', clip: { x: 0, y: 0, width: 1366, height: 760 } });
  // open the popup of a single marker (Munich is far away from the others)
  const single = page.locator('.geolocation-page-map .leaflet-marker-icon:not(.marker-cluster)').last();
  await single.click();
  await page.waitForTimeout(1200);
  out.osmPopupSingle = await page.evaluate(() => { const p = document.querySelector('.geolocation-page-map .leaflet-popup-content .geolocation-popup'); return p ? { html: p.innerHTML.slice(0, 300) } : null; });
  // zoom into clusters until the Hamburg marker is separate, then open its popup
  out.osmHamburgPopup = null;
  for (let i = 0; i < 6 && !out.osmHamburgPopup; i++) {
    out.osmHamburgPopup = await page.evaluate(async () => {
      const m = document.querySelector('.geolocation-page-map');
      for (const icon of m.querySelectorAll('.leaflet-marker-icon:not(.marker-cluster)')) {
        icon.click(); await new Promise(r => setTimeout(r, 700));
        const p = m.querySelector('.leaflet-popup-content .geolocation-popup');
        if (p && /Hamburg/.test(p.innerText)) return { title: p.querySelector('.geolocation-popup-title')?.innerText, href: p.querySelector('.geolocation-popup-title')?.getAttribute('href'), date: p.querySelector('.geolocation-popup-date')?.innerText, excerpt: p.querySelector('.geolocation-popup-excerpt')?.innerText, img: p.querySelector('img') ? { src: p.querySelector('img').getAttribute('src').replace(location.origin, ''), loaded: p.querySelector('img').naturalWidth } : null };
      }
      return null;
    });
    if (!out.osmHamburgPopup) {
      const clusters = page.locator('.geolocation-page-map .marker-cluster');
      if (!(await clusters.count())) break;
      // an open popup may cover the cluster
      await page.evaluate(() => { const c = document.querySelector('.leaflet-popup-close-button'); if (c) c.click(); });
      await page.waitForTimeout(400);
      await clusters.first().click({ force: true });
      await page.waitForTimeout(4000);
    }
  }
  out.osmFinal = await page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); return { clusters: m.querySelectorAll('.marker-cluster').length, singleMarkers: m.querySelectorAll('.leaflet-marker-icon:not(.marker-cluster)').length }; });
  await page.waitForTimeout(2500);
  await page.screenshot({ path: 'out/overview-osm-popup.png', clip: { x: 0, y: 0, width: 1366, height: 760 } });

  // --- Google overview map ---
  if (process.argv[2] === '--google') {
    await setProvider(page, 'google');
    await page.goto(base + '/map/', { waitUntil: 'load' });
    await page.waitForTimeout(12000);
    out.google = await page.evaluate(() => ({ mapChildren: document.querySelector('.geolocation-page-map')?.children.length, clusterer: typeof window.markerClusterer, clusterScript: !!document.querySelector('script[src*="markerclusterer"]'), gmErr: !!document.querySelector('.gm-err-container') }));
    await page.screenshot({ path: 'out/overview-google-clusters.png', clip: { x: 0, y: 0, width: 1366, height: 760 } });
    await setProvider(page, 'osm');
  }

  out.errors = [...new Set(errors)].slice(0, 8);
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
