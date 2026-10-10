// Checks the GPX track of a post: reading the file in the editor, saving, the map of the post,
// the overview map with a route (page "Our route"), shortening the tracks and removing a track.
// Leaves "Trip to Hamburg" and "Trip to Potsdam" with a track; writes control screenshots to ./out.
// With --google the maps are also loaded with Google Maps (needs a stored API key); OSM is restored afterwards.
const { chromium } = require('playwright-core');
const fs = require('fs');
const { base, id } = require('./lib');

// A winding track between two places, with times and elevations as a recorder would write them.
function gpx(name, from, to, count, ele) {
  let pts = '';
  for (let i = 0; i <= count; i++) {
    const t = i / count;
    const lat = from[0] + (to[0] - from[0]) * t + Math.sin(t * 40) * 0.006 + Math.sin(t * 7) * 0.02 * Math.sin(Math.PI * t);
    const lng = from[1] + (to[1] - from[1]) * t + Math.cos(t * 33) * 0.008 * Math.sin(Math.PI * t);
    pts += `<trkpt lat="${lat.toFixed(7)}" lon="${lng.toFixed(7)}"><ele>${(ele ? ele(t) : 20 + Math.sin(t * 9) * 15).toFixed(1)}</ele><time>2026-10-04T${String(8 + Math.floor(t * 5)).padStart(2, '0')}:${String(Math.floor((t * 300) % 60)).padStart(2, '0')}:00Z</time></trkpt>\n`;
  }
  return `<?xml version="1.0" encoding="UTF-8"?>\n<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1"><metadata><name>${name}</name></metadata><trk><name>${name}</name><trkseg>\n${pts}</trkseg></trk></gpx>\n`;
}

async function openEditor(page, postId) {
  await page.goto(base + '/wp-admin/post.php?post=' + postId + '&action=edit', { waitUntil: 'load' });
  await page.waitForSelector('.editor-visual-editor, .edit-post-visual-editor', { timeout: 30000 });
  await page.waitForTimeout(4000);
  if (await page.$('.components-modal__frame')) { await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
  await page.waitForSelector('#geolocation-track-file', { state: 'attached' });
}

async function save(page) {
  await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
  await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/edit-post').isSavingMetaBoxes(), null, { timeout: 30000 });
  await page.waitForTimeout(1000);
}

const editorState = (page) => page.evaluate(() => ({
  status: document.getElementById('geolocation-track-status').textContent,
  points: (() => { try { return JSON.parse(document.getElementById('geolocation-track').value).length; } catch (e) { return 0; } })(),
  km: document.getElementById('geolocation-track-km').value,
  ele: (() => { try { const e = JSON.parse(document.getElementById('geolocation-track-ele').value); return [e.profile.length, e.up, e.down, Math.min(...e.profile), Math.max(...e.profile)]; } catch (e) { return null; } })(),
  removeShown: document.getElementById('geolocation-track-remove').style.display !== 'none',
  stored: window.geolocationAdmin.trackKm,
}));

async function setOption(page, name, value) {
  await page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  if (name === 'geolocation_provider') await page.selectOption('#geolocation_provider', value); else await page.fill('#' + name, value);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
}

(async () => {
  fs.mkdirSync('out', { recursive: true });
  fs.writeFileSync('out/luebeck-hamburg.gpx', gpx('Luebeck to Hamburg', [53.8655, 10.6866], [53.5511, 9.9937], 3000));
  // A mountain stage: from 600 m over a pass at 1900 m down to 250 m, with some noise of the recording.
  fs.writeFileSync('out/berlin-potsdam.gpx', gpx('Berlin to Potsdam', [52.52, 13.405], [52.3906, 13.0645], 1500, t => 600 + 1300 * Math.exp(-Math.pow((t - 0.45) / 0.18, 2)) - 350 * t + Math.sin(t * 900) * 1.5));
  // A file whose elevations are all 0, as some planning tools write it.
  fs.writeFileSync('out/flat-zero.gpx', gpx('No elevations', [52.52, 13.405], [52.3906, 13.0645], 300, () => 0));
  fs.writeFileSync('out/no-track.gpx', '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><wpt lat="53.5" lon="10"><name>only a waypoint</name></wpt></gpx>');
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 950 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const out = {};
  await setOption(page, 'geolocation_track_trim', '0');

  // Editor: a file without a track, then a real track; save and reload.
  await openEditor(page, id('trip-to-hamburg'));
  await page.setInputFiles('#geolocation-track-file', 'out/no-track.gpx');
  await page.waitForTimeout(800);
  out.invalid = await editorState(page);
  await page.setInputFiles('#geolocation-track-file', 'out/luebeck-hamburg.gpx');
  await page.waitForTimeout(1500);
  out.chosen = await editorState(page);
  await page.locator('#geolocation_sectionid').scrollIntoViewIfNeeded().catch(() => {});
  await page.screenshot({ path: 'out/track-editor.png' });
  await save(page);
  await openEditor(page, id('trip-to-hamburg'));
  out.reloaded = await editorState(page);

  await openEditor(page, id('trip-to-potsdam'));
  await page.setInputFiles('#geolocation-track-file', 'out/berlin-potsdam.gpx');
  await page.waitForTimeout(1500);
  await save(page);

  out.potsdamChosen = await editorState(page);
  await page.setInputFiles('#geolocation-track-file', 'out/flat-zero.gpx');
  await page.waitForTimeout(1200);
  out.zeroElevations = await editorState(page);
  await page.setInputFiles('#geolocation-track-file', 'out/berlin-potsdam.gpx');
  await page.waitForTimeout(1500);
  await save(page);

  // Add and remove a track at another post.
  await openEditor(page, id('trip-to-dresden'));
  await page.setInputFiles('#geolocation-track-file', 'out/berlin-potsdam.gpx');
  await page.waitForTimeout(1500);
  await save(page);
  await openEditor(page, id('trip-to-dresden'));
  out.dresdenAdded = await editorState(page);
  await page.evaluate(() => document.getElementById('geolocation-track-remove').click());
  await save(page);
  await openEditor(page, id('trip-to-dresden'));
  out.dresdenRemoved = await editorState(page);

  const postMap = async () => {
    await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
    // The map is created when it scrolls into view.
    await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
    await page.waitForTimeout(4000);
    return page.evaluate(() => { const m = document.querySelector('.geolocation-map'); const t = JSON.parse(m.dataset.track || '[]'); return { points: t.length, first: t[0], last: t[t.length - 1], line: !!m.querySelector('.leaflet-overlay-pane path'), zoom: m.querySelector('.leaflet-proxy') ? m.querySelector('.leaflet-proxy').style.transform : '' }; });
  };
  out.postMap = await postMap();
  const details = async (url) => { await page.goto(base + url, { waitUntil: 'load' }); return page.evaluate(() => { const d = document.querySelector('.geolocation-track-details'); const s = d && d.querySelector('svg'); return d ? { text: d.firstChild.textContent, svg: s ? [...s.querySelectorAll('text')].map(x => x.textContent) : null } : null; }); };
  out.detailsHamburg = await details('/2026/10/04/trip-to-hamburg/');
  out.detailsPotsdam = await details('/2026/10/04/trip-to-potsdam/');
  out.detailsBerlin = await details('/2026/10/04/trip-to-berlin/');
  await page.screenshot({ path: 'out/track-post.png' });

  const routeMap = async () => {
    await page.goto(base + '/our-route/', { waitUntil: 'load' });
    await page.waitForTimeout(4000);
    return page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); const ms = JSON.parse(m.dataset.markers);
      return { tracks: ms.filter(x => x.track).map(x => [x.title, x.track.length, x.length]), lengths: ms.map(x => x.length).filter(Boolean),
        paths: [...m.querySelectorAll('.leaflet-overlay-pane path')].map(p => [(p.getAttribute('d').match(/[ML]/g) || []).length, p.getAttribute('stroke-dasharray') || 'solid']) }; });
  };
  out.routeMap = await routeMap();
  await page.screenshot({ path: 'out/track-route.png' });
  await page.goto(base + '/map/', { waitUntil: 'load' });
  out.mapWithoutRoute = await page.evaluate(() => { const m = document.querySelector('.geolocation-page-map'); const ms = JSON.parse(m.dataset.markers); return { tracks: ms.filter(x => x.track).length, bytes: m.dataset.markers.length }; });

  // Shortening the tracks by one kilometre at both ends.
  await setOption(page, 'geolocation_track_trim', '1000');
  out.trimmedPost = await postMap();
  out.trimmedRoute = (await routeMap()).tracks;
  await setOption(page, 'geolocation_track_trim', '0');

  if (process.argv[2] === '--google') {
    await setOption(page, 'geolocation_provider', 'google');
    try {
      await page.goto(base + '/2026/10/04/trip-to-hamburg/', { waitUntil: 'load' });
      await page.waitForTimeout(3000);
      await page.locator('.geolocation-map').scrollIntoViewIfNeeded();
      await page.waitForTimeout(8000);
      out.googlePost = await page.evaluate(() => ({ errorDialog: /can't load Google Maps correctly/.test(document.body.innerText), gm: !!document.querySelector('.geolocation-map .gm-style') }));
      await page.screenshot({ path: 'out/track-post-google.png' });
      await page.goto(base + '/our-route/', { waitUntil: 'load' });
      await page.waitForTimeout(12000);
      out.googleRoute = await page.evaluate(() => ({ errorDialog: /can't load Google Maps correctly/.test(document.body.innerText), gm: !!document.querySelector('.geolocation-page-map .gm-style') }));
      await page.screenshot({ path: 'out/track-route-google.png' });
    } finally {
      await setOption(page, 'geolocation_provider', 'osm');
    }
  }
  out.errors = [...new Set(errors)];
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
