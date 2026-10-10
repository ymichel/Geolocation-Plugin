// Checks the "Shortcode" help tab of the settings page, in English and in German.
// Switches the site language to German and back to English.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base } = require('./lib');

async function setLanguage(page, value) {
  await page.goto(base + '/wp-admin/options-general.php', { waitUntil: 'load' });
  await page.selectOption('#WPLANG', value);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120000 }), page.click('#submit')]);
}
async function readHelp(page, shot) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const link = await page.$('#contextual-help-link');
  if (!link) return { helpLink: false };
  await link.click();
  await page.waitForTimeout(1200);
  const info = await page.evaluate(() => {
    const panel = document.querySelector('#tab-panel-geolocation-shortcode');
    return {
      helpLink: true,
      tab: (document.querySelector('#tab-link-geolocation-shortcode') || {}).innerText,
      intro: panel ? panel.querySelector('p').innerText : null,
      headers: panel ? [...panel.querySelectorAll('th')].map(t => t.innerText) : [],
      rows: panel ? [...panel.querySelectorAll('tbody tr')].map(r => [...r.querySelectorAll('td')].map(t => t.innerText).join(' | ')) : [],
      notes: panel ? [...panel.querySelectorAll('p')].pop().innerText : null,
      hint: (document.querySelector('.position .description') || {}).innerText,
    };
  });
  await page.screenshot({ path: shot, clip: { x: 0, y: 0, width: 1366, height: 560 } });
  return info;
}

(async () => {
  fs.mkdirSync('out', { recursive: true });
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  const out = {};
  await page.goto(base + '/', { waitUntil: 'load' });
  out.english = await readHelp(page, 'out/help-en.png');
  await setLanguage(page, 'de_DE');
  out.german = await readHelp(page, 'out/help-de.png');
  await setLanguage(page, '');
  out.languageRestored = await page.evaluate(() => document.documentElement.lang);
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
