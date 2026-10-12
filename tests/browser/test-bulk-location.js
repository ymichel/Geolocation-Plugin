// Checks the list of posts: the column "Location" and the field of the bulk edit form which publishes locations or takes that back.
// Uses "Trip to Berlin", "Trip to Dresden" and a post without a location which it creates and deletes. The overview
// map of the page "Map" is read as a logged-out visitor, who only sees public locations. Runs with both providers
// and sets the two locations to public again at the end, as in the demo content.
const { chromium } = require('playwright-core');
const { base, id } = require('./lib');
const BERLIN = id('trip-to-berlin');
const DRESDEN = id('trip-to-dresden');

// The titles on the overview map as a visitor gets them: no login, only the cookie which keeps Playground from logging in.
async function visitorSees() {
  const html = await fetch(base + '/map/', { headers: { Cookie: 'playground_auto_login_already_happened=1' } }).then(r => r.text());
  const match = html.match(/data-markers="([^"]*)"/);
  if (!match) return [];
  return JSON.parse(match[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&#039;/g, "'")).map(m => m.title).sort();
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1500, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const list = () => page.goto(base + '/wp-admin/edit.php?post_type=post&posts_per_page=50', { waitUntil: 'load' });
  const helper = (q) => page.evaluate((query) => fetch('/wp-admin/admin-ajax.php?action=geolocation_test&do=' + query, { credentials: 'include' }).then(r => r.json()).then(j => j.data), q);
  const isPublic = async (post) => (await helper('meta&post=' + post)).meta.geo_public;
  const cell = (post) => page.evaluate((p) => { const c = document.querySelector('#post-' + p + ' td.column-geolocation'); return c ? c.innerText.replace(/\n/g, ' | ') : null; }, post);
  const provider = async (name) => { await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' }); const was = await page.inputValue('#geolocation_provider'); if (name && name !== was) { await page.selectOption('#geolocation_provider', name); await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]); } return was; };
  // Edit several posts at once: choose them, open the form, set the field "Location" and update.
  const bulk = async (choice, posts) => {
    await list();
    for (const post of posts) await page.check('#cb-select-' + post);
    await page.selectOption('#bulk-action-selector-top', 'edit');
    await page.click('#doaction');
    await page.waitForSelector('#bulk-edit #geolocation-bulk-public', { state: 'visible' });
    const field = await page.evaluate(() => { const f = document.querySelector('#bulk-edit #geolocation-bulk-public'); return { label: f.closest('label').querySelector('.title').textContent, options: [...f.options].map(o => o.value + ': ' + o.textContent.trim()), chosen: f.value }; });
    await page.selectOption('#bulk-edit #geolocation-bulk-public', choice);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#bulk_edit')]);
    return { field, notice: await page.evaluate(() => ((document.querySelector('#message p, .notice.updated p') || {}).textContent || '').trim()) };
  };

  const was = await provider();
  const out = { providerBefore: was };
  let without = 0;
  try {
    await list();
    without = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/posts', method: 'POST', data: { title: 'Bulk test without location', status: 'draft' } })).id);
    await list();
    out.list = { column: await page.evaluate(() => (document.querySelector('thead th.column-geolocation') || {}).innerText || null), berlin: await cell(BERLIN), dresden: await cell(DRESDEN), withoutLocation: await cell(without) };
    for (const name of ['osm', 'google']) {
      await provider(name);
      const r = { visitorBefore: (await visitorSees()).length };
      // Take the locations of two posts back; the post without a location is not counted.
      r.notPublic = await bulk('0', [BERLIN, DRESDEN, without]);
      r.afterNotPublic = { berlin: await cell(BERLIN), dresden: await cell(DRESDEN), withoutLocation: await cell(without), meta: [await isPublic(BERLIN), await isPublic(DRESDEN)] };
      const hidden = await visitorSees();
      r.visitorAfterNotPublic = [hidden.length, hidden.includes('Trip to Berlin'), hidden.includes('Trip to Dresden')];
      // "No change" leaves the locations as they are.
      r.noChange = (await bulk('', [BERLIN, DRESDEN])).notice;
      r.afterNoChange = [await isPublic(BERLIN), await isPublic(DRESDEN)];
      // Publish them.
      r.public = (await bulk('1', [BERLIN, DRESDEN, without])).notice;
      r.afterPublic = { berlin: await cell(BERLIN), dresden: await cell(DRESDEN), meta: [await isPublic(BERLIN), await isPublic(DRESDEN)] };
      const shown = await visitorSees();
      r.visitorAfterPublic = [shown.length, shown.includes('Trip to Berlin'), shown.includes('Trip to Dresden')];
      out[name] = r;
    }
    await list();
    await page.screenshot({ path: 'out/bulk-location.png', clip: { x: 0, y: 0, width: 1500, height: 560 } });
  } finally {
    await list();
    if (without) await page.evaluate((p) => wp.apiFetch({ path: '/wp/v2/posts/' + p + '?force=true', method: 'DELETE' }), without).catch(() => {});
    if ((await isPublic(BERLIN)) !== '1' || (await isPublic(DRESDEN)) !== '1') await bulk('1', [BERLIN, DRESDEN]);
    await provider(was);
  }
  out.final = { provider: await provider(), meta: [await isPublic(BERLIN), await isPublic(DRESDEN)] };
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
