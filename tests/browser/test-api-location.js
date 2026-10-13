// Checks that a post which is not saved in the editor gets the location of its featured image: created through
// the REST API with the image, the image added later, an image without a position, a post which has a location
// already, and the setting which makes such locations public. Runs with both providers, as the address comes from
// the geocoder of the provider. Creates photos with GPS data itself and deletes its posts and photos at the end.
const { chromium } = require('playwright-core');
const { base, chooseProvider } = require('./lib');

// The EXIF block of a photo taken at the given position (north and east).
function exif(lat, lng) {
  const tiff = Buffer.alloc(128);
  const rational = (value, offset) => {
    const deg = Math.floor(value); const min = Math.floor((value - deg) * 60); const sec = Math.round(((value - deg) * 60 - min) * 6000);
    [[deg, 1], [min, 1], [sec, 100]].forEach((pair, i) => { tiff.writeUInt32BE(pair[0], offset + i * 8); tiff.writeUInt32BE(pair[1], offset + i * 8 + 4); });
  };
  const entry = (offset, tag, type, count, value) => { tiff.writeUInt16BE(tag, offset); tiff.writeUInt16BE(type, offset + 2); tiff.writeUInt32BE(count, offset + 4); if (typeof value === 'string') tiff.write(value, offset + 8, 'ascii'); else tiff.writeUInt32BE(value, offset + 8); };
  tiff.write('MM', 0, 'ascii'); tiff.writeUInt16BE(42, 2); tiff.writeUInt32BE(8, 4);
  // The first directory only points to the GPS directory.
  tiff.writeUInt16BE(1, 8); entry(10, 0x8825, 4, 1, 26);
  tiff.writeUInt16BE(4, 26); entry(28, 1, 2, 2, 'N'); entry(40, 2, 5, 3, 80); entry(52, 3, 2, 2, 'E'); entry(64, 4, 5, 3, 104);
  rational(lat, 80); rational(lng, 104);
  const head = Buffer.alloc(10); head.writeUInt16BE(0xFFE1, 0); head.writeUInt16BE(8 + tiff.length, 2); head.write('Exif', 4, 'ascii');
  return Buffer.concat([head, tiff]);
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const helper = (q) => page.evaluate((query) => fetch('/wp-admin/admin-ajax.php?action=geolocation_test&do=' + query, { credentials: 'include' }).then(r => r.json()).then(j => j.data), q);
  const openSettings = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const set = async (provider, isPublic) => { await openSettings(); await chooseProvider(page, provider); await page.setChecked('#geolocation_image_public', isPublic); await save(); await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' }); };

  // A small JPEG drawn by the browser, with and without the position of a camera.
  const plain = Buffer.from(await page.evaluate(() => { const c = document.createElement('canvas'); c.width = 240; c.height = 160; const x = c.getContext('2d'); x.fillStyle = '#2b6cb0'; x.fillRect(0, 0, 240, 160); x.fillStyle = '#fff'; x.font = '24px sans-serif'; x.fillText('test photo', 60, 88); return c.toDataURL('image/jpeg', 0.8).split(',')[1]; }), 'base64');
  const photo = (lat, lng) => Buffer.concat([plain.subarray(0, 2), exif(lat, lng), plain.subarray(2)]);
  const media = []; const posts = [];
  const upload = async (name, bytes) => { const id = await page.evaluate(async ([n, b64]) => { const bytes = Uint8Array.from(atob(b64), ch => ch.charCodeAt(0)); return (await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', headers: { 'Content-Disposition': 'attachment; filename="' + n + '"', 'Content-Type': 'image/jpeg' }, body: new Blob([bytes], { type: 'image/jpeg' }) })).id; }, [name, bytes.toString('base64')]); media.push(id); return id; };
  const post = async (data, id) => { const result = await page.evaluate(async ([d, i]) => (await wp.apiFetch({ path: '/wp/v2/posts' + (i ? '/' + i : ''), method: 'POST', data: d })).id, [data, id || 0]); if (!id) posts.push(result); return result; };
  const meta = async (id) => { const m = (await helper('meta&post=' + id)).meta; return [m.geo_latitude.slice(0, 7), m.geo_longitude.slice(0, 7), 'enabled ' + m.geo_enabled, 'public ' + m.geo_public, m.geo_address].concat(m.geo_removed ? ['removed'] : []); };

  const settings = await (async () => { await openSettings(); return [await page.inputValue('#geolocation_provider'), await page.isChecked('#geolocation_image_public')]; })();
  const out = { settingsBefore: settings };
  try {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    out.canReadPhotos = (await helper('meta&post=0')).exif;
    const leipzig = await upload('geolocation-test-leipzig.jpg', photo(51.3397, 12.3731));
    const rome = await upload('geolocation-test-rome.jpg', photo(41.9028, 12.4964));
    const without = await upload('geolocation-test-plain.jpg', plain);
    for (const provider of ['osm', 'google']) {
      const r = {};
      await set(provider, false);
      // Created with its featured image in one request.
      const a = await post({ title: 'API test with photo (' + provider + ')', status: 'publish', content: '<!-- wp:paragraph --><p>Created through the REST API.</p><!-- /wp:paragraph -->', featured_media: leipzig });
      r.createdWithPhoto = await meta(a);
      // The output of the post, as a logged-in user sees it.
      await page.goto(base + '/?p=' + a, { waitUntil: 'load' });
      r.output = await page.evaluate(() => ({ text: ((document.querySelector('.geolocation-link, .geolocation-plain') || {}).textContent || '').slice(0, 70), map: !!document.querySelector('.geolocation-map[data-geolocation]') }));
      await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
      // The image is set by a second request.
      const b = await post({ title: 'API test, photo later (' + provider + ')', status: 'draft' });
      r.beforePhoto = await meta(b);
      await post({ featured_media: rome }, b);
      r.photoAddedLater = await meta(b);
      // Another photo does not move a location which is there.
      await post({ featured_media: rome }, a);
      r.keptWithOtherPhoto = await meta(a);
      // Removed in the editor (the classic one, for a plain form): later changes through the API do not bring it back.
      await helper('classic_on');
      await page.goto(base + '/wp-admin/post.php?post=' + a + '&action=edit', { waitUntil: 'load' });
      await page.click('#geolocation-remove');
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#publish')]);
      await helper('classic_off');
      r.removedInEditor = await meta(a);
      await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
      await post({ title: 'API test with photo, changed (' + provider + ')' }, a);
      r.staysRemovedAfterApiChange = await meta(a);
      // Saved in the editor again without choosing a location: still none.
      await helper('classic_on');
      await page.goto(base + '/wp-admin/post.php?post=' + a + '&action=edit', { waitUntil: 'load' });
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#publish')]);
      r.staysRemovedAfterEditorSave = await meta(a);
      // A location chosen in the editor is stored and ends the removal.
      await page.evaluate(() => { document.getElementById('geolocation-latitude').value = '48.1372'; document.getElementById('geolocation-longitude').value = '11.5756'; document.getElementById('geolocation-remove-flag').value = ''; });
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#publish')]);
      await helper('classic_off');
      r.chosenInEditorAgain = await meta(a);
      await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
      // A photo without a position.
      const c = await post({ title: 'API test without position (' + provider + ')', status: 'publish', featured_media: without });
      r.photoWithoutPosition = await meta(c);
      // With the setting, the location is public at once.
      await set(provider, true);
      const d = await post({ title: 'API test, public (' + provider + ')', status: 'publish', featured_media: leipzig });
      r.publicBySetting = await meta(d);
      out[provider] = r;
    }
  } finally {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    out.deleted = await page.evaluate(async ([p, m]) => { let n = 0; for (const id of p) { await wp.apiFetch({ path: '/wp/v2/posts/' + id + '?force=true', method: 'DELETE' }); n++; } for (const id of m) { await wp.apiFetch({ path: '/wp/v2/media/' + id + '?force=true', method: 'DELETE' }); n++; } return n; }, [posts, media]).catch(e => 'failed: ' + e.message);
    await set(settings[0], settings[1]);
  }
  await openSettings();
  out.settingsAfter = [await page.inputValue('#geolocation_provider'), await page.isChecked('#geolocation_image_public')];
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
