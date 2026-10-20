// Checks that the elevation profile below the map of "Trip to Potsdam" follows the pointer, a tap and the arrow
// keys, and that the map shows the same place of the track: at the start of the profile the first position of
// the drawn track, at its end the last one. With a shortened track ("Shorten tracks") the hidden ends show no
// place on the map. With --google the profile follows as well, without a place on the map (Google Maps gets
// no new functions). Restores the provider and "Shorten tracks".
const { chromium } = require('playwright-core');
const { base, chooseProvider } = require('./lib');
const GOOGLE = process.argv[2] === '--google';
const POST = '/2026/10/04/trip-to-potsdam/';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1366, height: 1000 }, hasTouch: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  const settings = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);
  const setTrim = async (value) => { await settings(); await page.fill('#geolocation_track_trim', String(value)); await save(); };
  const open = async () => {
    await page.goto(base + POST, { waitUntil: 'load' });
    await page.locator('.geolocation-map[data-geolocation]').scrollIntoViewIfNeeded();
    await page.waitForTimeout(GOOGLE ? 5000 : 3500);
    await page.locator('svg.geolocation-elevation').scrollIntoViewIfNeeded();
  };
  // The state of profile and map: the label, the place on the map and the ends of the drawn track, in pixels of the map.
  const read = () => page.evaluate(() => {
    const svg = document.querySelector('svg.geolocation-elevation');
    const label = svg.querySelector('.geolocation-elevation-label');
    const spot = [...document.querySelectorAll('.geolocation-map[data-geolocation] .leaflet-overlay-pane path')].find(p => p.getAttribute('fill') === '#e53e3e');
    const line = [...document.querySelectorAll('.geolocation-map[data-geolocation] .leaflet-overlay-pane path')].find(p => p.getAttribute('fill') === 'none');
    const centre = (p) => { if (!p) return null; const r = p.getBoundingClientRect(); return [Math.round(r.left + r.width / 2), Math.round(r.top + r.height / 2)]; };
    let ends = null;
    if (line) { const len = line.getTotalLength(); const m = line.getScreenCTM(); const at = (d) => { const pt = line.getPointAtLength(d); return [Math.round(pt.x * m.a + m.e), Math.round(pt.y * m.d + m.f)]; }; ends = [at(0), at(len)]; }
    return { label: label && label.style.display !== 'none' ? label.textContent : null, spot: centre(spot), ends, data: [svg.dataset.start, svg.dataset.end, svg.dataset.km] };
  });
  const at = async (share) => { const box = await page.locator('svg.geolocation-elevation').boundingBox(); await page.mouse.move(box.x + Math.max(1, Math.min(box.width - 1, box.width * share)), box.y + box.height / 2); await page.waitForTimeout(200); return read(); };
  const near = (a, b) => !!a && !!b && Math.abs(a[0] - b[0]) <= 3 && Math.abs(a[1] - b[1]) <= 3;

  await settings();
  const was = await page.inputValue('#geolocation_provider');
  const trimWas = await page.inputValue('#geolocation_track_trim');
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  try {
    if (GOOGLE !== (was === 'google')) { await chooseProvider(page, GOOGLE ? 'google' : 'osm'); await save(); }
    await setTrim(0);
    await open();
    out.before = await read();
    const s = await at(0); const m = await at(0.5); const e = await at(1);
    out.atStart = { label: s.label, spotAtFirstPosition: GOOGLE ? null : near(s.spot, s.ends && s.ends[0]) };
    out.atMiddle = { label: m.label, spotShown: !!m.spot };
    out.atEnd = { label: e.label, spotAtLastPosition: GOOGLE ? null : near(e.spot, e.ends && e.ends[1]) };
    await page.mouse.move(5, 5); await page.waitForTimeout(200);
    out.afterLeaving = await read();
    // Keyboard: focus, then two steps to the right.
    await page.focus('svg.geolocation-elevation');
    const focused = await read();
    await page.keyboard.press('ArrowRight'); await page.keyboard.press('ArrowRight');
    out.keyboard = [focused.label, (await read()).label];
    await page.keyboard.press('Tab');
    // A tap on a touch screen.
    const box = await page.locator('svg.geolocation-elevation').boundingBox();
    await page.touchscreen.tap(box.x + box.width * 0.3, box.y + box.height / 2); await page.waitForTimeout(300);
    out.tap = { label: (await read()).label, spotShown: !!(await read()).spot };
    await page.screenshot({ path: 'out/elevation-follow' + (GOOGLE ? '-google' : '') + '.png', clip: { x: Math.max(0, box.x - 20), y: Math.max(0, box.y - 340), width: Math.min(1366, box.width + 40), height: 480 } });
    // Shortened by 5 km: no place on the map at the hidden ends.
    await setTrim(5000);
    await open();
    const t0 = await at(0.01); const t5 = await at(0.5); const t1 = await at(0.99);
    out.shortened = { data: t5.data, startHidden: !t0.spot, middleShown: !!t5.spot, endHidden: !t1.spot, labelAtStart: t0.label };
  } finally {
    await setTrim(trimWas);
    await settings();
    if (await page.inputValue('#geolocation_provider') !== was) { await chooseProvider(page, was); await save(); }
  }
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
