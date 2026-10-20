// Checks reading the locations of existing posts from their featured images, started on the settings page.
// Creates posts with photos which carry GPS data and takes their locations away again, as posts saved before
// version 1.18.0 outside the editor look: one with a photo of Leipzig, one of Rome, one with a photo without a
// position, one whose location counts as removed in the editor, and one which keeps its location. The background
// job is run through the test helper. With --google the addresses come from Google. Deletes its posts and photos.
const { chromium } = require('playwright-core');
const { base, exif, chooseProvider } = require('./lib');
const GOOGLE = process.argv[2] === '--google';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const helper = (q) => page.evaluate((query) => fetch('/wp-admin/admin-ajax.php?action=geolocation_test&do=' + query, { credentials: 'include' }).then(r => r.json()).then(j => j.data), q);
  const meta = async (id) => { const m = (await helper('meta&post=' + id)).meta; return [m.geo_latitude.slice(0, 7), m.geo_longitude.slice(0, 7), 'enabled ' + m.geo_enabled, 'public ' + m.geo_public, m.geo_address].concat(m.geo_removed ? ['removed'] : []); };
  const settings = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const status = () => page.evaluate(() => ((document.getElementById('geolocation-derive-status') || {}).textContent || '').trim());

  const plain = Buffer.from(await page.evaluate(() => { const c = document.createElement('canvas'); c.width = 240; c.height = 160; const x = c.getContext('2d'); x.fillStyle = '#2b6cb0'; x.fillRect(0, 0, 240, 160); return c.toDataURL('image/jpeg', 0.8).split(',')[1]; }), 'base64');
  const photo = (lat, lng) => Buffer.concat([plain.subarray(0, 2), exif(lat, lng), plain.subarray(2)]);
  const media = []; const posts = [];
  const upload = async (name, bytes) => { const id = await page.evaluate(async ([n, b64]) => { const bytes = Uint8Array.from(atob(b64), ch => ch.charCodeAt(0)); return (await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', headers: { 'Content-Disposition': 'attachment; filename="' + n + '"', 'Content-Type': 'image/jpeg' }, body: new Blob([bytes], { type: 'image/jpeg' }) })).id; }, [name, bytes.toString('base64')]); media.push(id); return id; };
  const post = async (title, image) => { const id = await page.evaluate(async ([t, m]) => (await wp.apiFetch({ path: '/wp/v2/posts', method: 'POST', data: { title: t, status: 'publish', featured_media: m } })).id, [title, image]); posts.push(id); return id; };

  await settings();
  const was = await page.inputValue('#geolocation_provider');
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  try {
    if (GOOGLE !== (was === 'google')) { await chooseProvider(page, GOOGLE ? 'google' : 'osm'); await save(); }
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    const leipzig = await upload('geolocation-test-leipzig.jpg', photo(51.3397, 12.3731));
    const rome = await upload('geolocation-test-rome.jpg', photo(41.9028, 12.4964));
    const without = await upload('geolocation-test-plain.jpg', plain);
    const p = {
      leipzig: await post('Derive test: Leipzig', leipzig),
      rome: await post('Derive test: Rome', rome),
      withoutPosition: await post('Derive test: photo without a position', without),
      removed: await post('Derive test: removed in the editor', leipzig),
      keeps: await post('Derive test: keeps its location', rome)
    };
    // Old posts: a featured image, but no location.
    for (const key of ['leipzig', 'rome', 'withoutPosition']) await helper('forget_location&post=' + p[key]);
    await helper('forget_location&removed=1&post=' + p.removed);
    out.before = {}; for (const [k, id] of Object.entries(p)) out.before[k] = await meta(id);

    await settings();
    out.statusBefore = await status();
    await page.check('#geolocation_derive_locations');
    await save();
    out.checkboxAfterSaving = await page.isChecked('#geolocation_derive_locations');
    out.statusWhileQueued = await status();
    // Run the background job, batch by batch.
    for (let i = 0; i < 20; i++) { const r = await helper('run_cron&hook=geolocation_derive'); if (!r.ran) break; }
    await settings();
    out.statusAfter = await status();
    out.after = {}; for (const [k, id] of Object.entries(p)) out.after[k] = await meta(id);
    // Started again, the posts with a location are not checked again.
    await page.check('#geolocation_derive_locations');
    await save();
    for (let i = 0; i < 20; i++) { const r = await helper('run_cron&hook=geolocation_derive'); if (!r.ran) break; }
    await settings();
    out.secondRun = await status();
  } finally {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    out.deleted = await page.evaluate(async ([ps, ms]) => { for (const id of ps) await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?force=true', method: 'DELETE' }); for (const id of ms) await wp.apiFetch({ path: '/wp/v2/media/' + id + '?force=true', method: 'DELETE' }); return ps.length + ms.length; }, [posts, media]).catch(e => 'failed: ' + e.message);
    await settings();
    if (await page.inputValue('#geolocation_provider') !== was) { await chooseProvider(page, was); await save(); }
  }
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
