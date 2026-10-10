// Checks the translated texts in the browser for all ten languages: settings page, help tab, the Geolocation box
// and both blocks in the editor, and the output of a post. Reports texts which are still English and HTML
// entities shown literally. The site language is restored afterwards.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const LOCALES = ['de_DE', 'de_DE_formal', 'de_AT', 'de_CH', 'de_CH_informal', 'es_ES', 'fr_FR', 'it_IT', 'pt_BR', 'nl_NL'];
// Texts which are the same in a language on purpose.
const SAME = { '*': ['Shortcode', 'Tags', 'Route', 'Leaflet JS', 'Leaflet CSS', 'OSM URLs', 'Position', 'Dimensions', 'Public', 'Posts', 'Track (GPX)', 'Track: %s', 'On', 'Off'] };

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  await page.goto(base + '/', { waitUntil: 'load' });
  // Set through the test helper of the instance, as the REST API only accepts languages with an installed language pack.
  const setLanguage = async (l) => { await page.goto(base + '/wp-admin/admin-ajax.php?action=geolocation_test&do=language&locale=' + l, { waitUntil: 'load' }); return JSON.parse(await page.locator('body').innerText()).data.state.locale; };
  const collect = async () => {
    const t = {};
    await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
    Object.assign(t, await page.evaluate(() => {
      const o = {};
      document.querySelectorAll('#settings th, #settings label, #settings p.description, #settings strong').forEach((e, i) => { const x = e.textContent.replace(/\s+/g, ' ').trim(); if (x) o['settings.' + i] = x; });
      document.querySelectorAll('#tab-panel-geolocation-shortcode p, #tab-panel-geolocation-shortcode th, #tab-panel-geolocation-shortcode td:nth-child(2)').forEach((e, i) => { o['help.' + i] = e.textContent.replace(/\s+/g, ' ').trim(); });
      o['settings.title'] = document.querySelector('.wrap h1').textContent.trim();
      return o;
    }));
    await page.goto(base + '/wp-admin/post.php?post=' + id('trip-to-potsdam') + '&action=edit', { waitUntil: 'load' });
    await page.waitForSelector('#geolocation-track-file', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(2500);
    Object.assign(t, await page.evaluate(() => {
      const o = {};
      document.querySelectorAll('#geolocation_sectionid label, #geolocation_sectionid .taghint, #geolocation_sectionid p.description, #geolocation_sectionid h2').forEach((e, i) => { const x = e.textContent.replace(/\s+/g, ' ').trim(); if (x) o['box.' + i] = x; });
      document.querySelectorAll('#geolocation_sectionid input[type=button]').forEach((e, i) => { o['box.button.' + i] = e.value; });
      o['box.status'] = document.getElementById('geolocation-track-status').textContent;
      for (const [k, v] of Object.entries(window.geolocationAdmin.i18n)) o['admin.' + k] = v;
      for (const [k, v] of Object.entries(window.geolocationBlock.i18n)) o['block.' + k] = v;
      for (const n of ['geolocation/map', 'geolocation/location']) { const b = wp.blocks.getBlockType(n); o[n + '.title'] = b.title; o[n + '.description'] = b.description; }
      return o;
    }));
    await page.goto(base + '/2026/10/04/trip-to-potsdam/', { waitUntil: 'load' });
    Object.assign(t, await page.evaluate(() => ({ 'front.posted': document.querySelector('.geolocation-link').textContent.split('Potsdam')[0], 'front.details': document.querySelector('.geolocation-track-details').firstChild.textContent, 'front.profile': document.querySelector('.geolocation-elevation title').textContent })));
    for (const block of ['map&atts=' + encodeURIComponent('{"categories":[99999]}'), 'location&post=' + id('hello-world') + '&atts=%7B%7D']) {
      await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
      await page.goto(base + '/wp-admin/post.php?post=' + id('trip-to-potsdam') + '&action=edit', { waitUntil: 'load' });
      await page.waitForFunction(() => window.geolocationBlock && window.geolocationBlock.preview, null, { timeout: 30000 });
      const preview = await page.evaluate(() => window.geolocationBlock.preview);
      await page.goto(preview + '&block=' + block, { waitUntil: 'load' });
      t['preview.' + block.split('&')[0]] = await page.evaluate(() => (document.querySelector('.geolocation-preview-empty') || document.body).textContent.trim().slice(0, 200));
    }
    return t;
  };
  const before = '';
  await setLanguage('');
  const english = await collect();
  const report = { texts: Object.keys(english).length, languages: {} };
  try {
    for (const locale of LOCALES) {
      report.languages[locale] = { set: await setLanguage(locale) };
      const t = await collect();
      const same = [], entities = [];
      for (const [k, v] of Object.entries(t)) {
        if (/&[a-zA-Z]+;|&#\d+;/.test(v)) entities.push(k + ': ' + v.slice(0, 60));
        if (english[k] !== undefined && v === english[k] && !SAME['*'].includes(v) && /[a-zA-Z]{4}/.test(v)) same.push(v.slice(0, 70));
      }
      report.languages[locale].untranslated = [...new Set(same)];
      report.languages[locale].entities = entities;
      report.languages[locale].sample = [t['settings.title'], t['box.status'], t['front.details'], t['geolocation/location.title'], t['preview.location']].map(x => (x || '').slice(0, 80));
    }
  } finally {
    report.restored = await setLanguage(before || '');
  }
  console.log(JSON.stringify(report, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
