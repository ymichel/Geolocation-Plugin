// Checks converting the shortcode into blocks, started on the settings page. Creates pages with different
// shortcodes and a post, reads what visitors see on each page, converts, and compares: every converted page has to
// show the same posts on a map of the same size. Shortcodes which cannot be converted have to stay. The conversion
// covers all pages of the instance, so the content of every page and post with the shortcode is saved before and
// written back afterwards; the test pages and the post are deleted. With --google the maps are read with Google.
const { chromium } = require('playwright-core');
const { base, chooseProvider } = require('./lib');
const GOOGLE = process.argv[2] === '--google';

const PAGES = {
  plain: '<!-- wp:paragraph --><p>[geolocation]</p><!-- /wp:paragraph -->',
  category: '<!-- wp:paragraph --><p>[geolocation cat="north" height="250"]</p><!-- /wp:paragraph -->',
  tagRoute: '<!-- wp:paragraph --><p>[geolocation tag="capital" width="100%" zoom="8" route="1"]</p><!-- /wp:paragraph -->',
  typographicQuotes: '<!-- wp:paragraph --><p>[geolocation cat=“north”]</p><!-- /wp:paragraph -->',
  shortcodeBlock: '<!-- wp:shortcode -->\n[geolocation width="600"]\n<!-- /wp:shortcode -->',
  classic: '[geolocation height="300"]',
  unknownCategory: '<!-- wp:paragraph --><p>[geolocation cat="does-not-exist"]</p><!-- /wp:paragraph -->',
  mixedCategories: '<!-- wp:paragraph --><p>[geolocation cat="North, does-not-exist" tag="capital, no-such-tag"]</p><!-- /wp:paragraph -->',
  withinText: '<!-- wp:paragraph --><p>Our trip: [geolocation]</p><!-- /wp:paragraph -->'
};

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 1000 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
  const api = (path, method, data) => page.evaluate(([p, m, d]) => wp.apiFetch({ path: p, method: m || 'GET', data: d }), [path, method, data]);
  // What a visitor gets: the posts on each map and its size, read from the HTML without logging in.
  const visitor = async (link) => { const html = await fetch(link, { headers: { Cookie: 'playground_auto_login_already_happened=1' } }).then(r => r.text()); return [...html.matchAll(/<div id="[^"]+" class="geolocation-map geolocation-page-map"([^>]*)>/g)].map(m => { const a = m[1]; const markers = (a.match(/data-markers="([^"]*)"/) || [])[1] || '[]'; return { posts: JSON.parse(markers.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&#039;/g, "'")).map(x => x.title).sort().join(', '), route: /data-route="1"/.test(a), zoom: (a.match(/data-zoom="(\d+)"/) || [])[1] || null, style: (a.match(/style="([^"]*)"/) || [])[1] }; }); };
  const settings = () => page.goto(base + '/wp-admin/options-general.php?page=geolocation.php', { waitUntil: 'load' });
  const save = () => Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#settings input[type=submit]')]);

  await settings();
  const was = await page.inputValue('#geolocation_provider');
  const out = { provider: GOOGLE ? 'google' : 'osm' };
  const created = {}; let post = 0; let saved = [];
  try {
    if (GOOGLE !== (was === 'google')) { await chooseProvider(page, GOOGLE ? 'google' : 'osm'); await save(); }
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    // Everything with the shortcode before the test, to write it back afterwards.
    for (const type of ['pages', 'posts']) {
      const items = await api('/wp/v2/' + type + '?per_page=100&status=publish,draft,future,pending,private&context=edit');
      saved = saved.concat(items.filter(x => /\[geolocation/.test(x.content.raw)).map(x => ({ type, id: x.id, content: x.content.raw })));
    }
    out.savedBefore = saved.length;
    // The pages of the instance with the shortcode, some with the custom field "category": what visitors see before.
    const existing = {};
    for (const item of saved.filter(x => x.type === 'pages')) { const p = await api('/wp/v2/pages/' + item.id + '?context=edit'); existing[item.id] = { title: p.title.raw, link: p.link, before: await visitor(p.link) }; }
    for (const [key, content] of Object.entries(PAGES)) {
      const p = await api('/wp/v2/pages', 'POST', { title: 'Convert test: ' + key, status: 'publish', content });
      created[key] = { id: p.id, link: p.link };
    }
    post = (await api('/wp/v2/posts', 'POST', { title: 'Convert test post', status: 'publish', content: '<!-- wp:paragraph --><p>[geolocation]</p><!-- /wp:paragraph -->' })).id;
    const before = {};
    for (const [key, p] of Object.entries(created)) before[key] = await visitor(p.link);

    await settings();
    await page.check('#geolocation_convert_shortcodes');
    await save();
    out.checkboxAfterSaving = await page.isChecked('#geolocation_convert_shortcodes');
    out.status = await page.evaluate(() => [...document.querySelectorAll('#geolocation-convert-row p.description')].map(p => p.innerText).slice(1));
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    out.pages = {};
    for (const [key, p] of Object.entries(created)) {
      const item = await api('/wp/v2/pages/' + p.id + '?context=edit');
      const after = await visitor(p.link);
      out.pages[key] = {
        content: item.content.raw.replace(/\s+/g, ' ').slice(0, 110),
        sameForVisitors: JSON.stringify(before[key]) === JSON.stringify(after),
        visitorsBefore: before[key].map(m => m.posts.split(', ').length + ' posts, ' + m.style + (m.route ? ', route' : '') + (m.zoom ? ', zoom ' + m.zoom : '')).join(' | ') || '(no map)',
        revisions: (await api('/wp/v2/pages/' + p.id + '/revisions')).length
      };
    }
    out.post = (await api('/wp/v2/posts/' + post + '?context=edit')).content.raw;
    // The existing pages, before their content is written back.
    out.existingPages = {};
    for (const [id, p] of Object.entries(existing)) {
      const after = await visitor(p.link);
      out.existingPages[p.title] = { content: (await api('/wp/v2/pages/' + id + '?context=edit')).content.raw.replace(/\s+/g, ' ').match(/<!-- wp:geolocation\/map[^>]*-->|\[geolocation[^\]]*\]/g), sameForVisitors: JSON.stringify(p.before) === JSON.stringify(after), visitorsBefore: p.before.map(m => m.posts).join(' | ') };
    }
  } finally {
    await page.goto(base + '/wp-admin/edit.php', { waitUntil: 'load' });
    for (const p of Object.values(created)) await api('/wp/v2/pages/' + p.id + '?force=true', 'DELETE').catch(() => {});
    if (post) await api('/wp/v2/posts/' + post + '?force=true', 'DELETE').catch(() => {});
    let restored = 0;
    for (const item of saved) { await api('/wp/v2/' + item.type + '/' + item.id, 'POST', { content: item.content }); restored++; }
    out.restored = restored;
    await settings();
    if (await page.inputValue('#geolocation_provider') !== was) { await chooseProvider(page, was); await save(); }
  }
  out.errors = errors;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
