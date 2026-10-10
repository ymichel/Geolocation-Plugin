# AGENTS.md

Guidance for AI coding agents working on the WordPress plugin "Geolocation". People find the user documentation in [readme.txt](readme.txt) and [README.md](README.md); this file only holds what an agent has to know in addition.

## Project

- WordPress plugin, slug `geolocation`, published on wordpress.org. It shows the location of posts as text, link or map, overview maps on pages, routes and GPX tracks, with OpenStreetMap (Leaflet) or Google Maps.
- Requires WordPress 6.0 and PHP 7.3. Do not use newer PHP syntax or WordPress functions without a fallback; if a change needs a newer version, say so instead of raising the requirement silently.
- Procedural PHP, every function, option, hook and handle prefixed with `geolocation_`. No classes, no namespaces, no autoloader.
- JavaScript is delivered as written: no build step, no bundler, no JSX. Scripts use `var` and function expressions and are wrapped in an IIFE.
- For simplicity the scripts are plain (vanilla) JavaScript: no jQuery and no other JavaScript library, also not the ones WordPress ships. The only exceptions are the bundled map libraries (Leaflet and the two clustering libraries) and, in the block editor, the `wp.*` packages which blocks cannot do without.

## Layout

| Path | Content |
|---|---|
| `geolocation.php` | Plugin header, hooks, post editor box, saving, output in posts and pages, reverse geocoding |
| `geolocation-settings.php`, `geolocation-settings-page.php` | Settings definition, sanitizing, upgrade, uninstall; the settings screen |
| `geolocation-map-provider-osm.php`, `-google.php` | Everything specific to one map provider; only the selected one is loaded |
| `geolocation-block.php` | Blocks "Geolocation Map" and "Post Location" (rendered by PHP) |
| `geolocation-track.php` | GPX tracks: storing, simplifying, key figures, elevation profile |
| `geolocation-precache.php` | Pre-caching of map tiles through the proxy plugin |
| `geolocation-map-link.php` | Link to the external map and the notice before leaving |
| `js/geolocation-*.js` | Own scripts. `js/leaflet*`, `js/markerclusterer.min.js`, `js/MarkerCluster*.css` are bundled libraries: never edit them |
| `languages/` | `.po` and compiled `.mo` files for ten locales |
| `tests/` | PHPUnit tests with stubbed WordPress functions; `tests/js/` unit tests for the scripts |

## Commands

```bash
composer install && npm ci
composer lint    # WordPress coding standard (PHPCS); must report 0 errors
composer fix     # PHPCBF
composer test    # PHPUnit
npm run lint     # ESLint for the own scripts
npm test         # unit tests of the scripts (Node.js 22 or newer)
```

All four checks run in CI for every pull request and have to pass.

**WPCS has to succeed.** The PHP code follows the WordPress Coding Standards, and the CI check "WPCS" fails the pull request on any error. Run `composer lint` before every commit and fix what it reports, with `composer fix` or by hand. Do not silence a finding with `phpcs:ignore` unless the line is correct as it is; then name the sniff and give the reason in the same comment. The two known warnings about `meta_query` in `geolocation.php` are accepted.

## Rules for code

- **Coding standard:** PHP is written to the WordPress Coding Standards (tabs, spaces inside parentheses, Yoda conditions, aligned assignments, a docblock for every function). `composer lint` must report 0 errors.
- **Security:** check a nonce and the capability in every admin-ajax, admin-post and save handler; sanitize every input (`sanitize_text_field( wp_unslash( ... ) )` or a dedicated callback); escape every output at the place where it is printed (`esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`). In scripts insert content of posts as text (`textContent`), never as HTML.
- **Settings:** every option is listed in `geolocation_get_settings_definition()` with type, sanitize callback and default. An option also has to be created on upgrade and removed on uninstall. Switches which are on by default store `'1'`/`'0'` and are read with `'0' !== (string) get_option( ... )`.
- **Assets:** enqueue scripts and styles only where a location is actually shown. Pass data to scripts with `wp_add_inline_script`, never by printing `<script>` tags. The map library is loaded on demand by `js/geolocation-front-common.js`; do not enqueue Leaflet or the Google Maps API in the front end.
- **Both providers:** every feature and every fix has to work with OpenStreetMap and with Google Maps. Keep the legacy `google.maps.Marker`.
- **Privacy:** the plugin must not cause requests to third parties which the site owner has not chosen. In strict privacy mode (`geolocation_strict_privacy()`, `geolocation_maps_blocked()`) no browser, of visitors or authors, may contact an external map or geocoding server. Stay compatible with the plugin `osm-tiles-proxy` and its filters.
- **Performance:** no remote request while rendering a page unless its result is cached; cache with transients; no query per post in loops over many posts.
- **Comments:** every function has a docblock with real types. Comments say why, in English.

## Translations

- Text domain `geolocation`; every string for people goes through `__()`, `esc_html__()` and friends. Strings passed to scripts go through `geolocation_block_text()`.
- A new or changed string has to be translated in all ten `.po` files (`de_DE`, `de_DE_formal`, `de_AT`, `de_CH`, `de_CH_informal`, `es_ES`, `fr_FR`, `it_IT`, `nl_NL`, `pt_BR`) and compiled with `msgfmt -o file.mo file.po`.
- The German files use CRLF line ends and HTML entities for umlauts; keep both. `de_DE_formal` and `de_CH` address the reader formally, the Swiss files write "ss" instead of "ß".

## Tests

- Add a PHPUnit test for every pure function (calculation, sanitizing, formatting) and a test in `tests/js/` for calculating code in scripts. `tests/bootstrap.php` stubs the WordPress functions the tests need; add a stub there instead of loading WordPress.
- Behaviour in the browser (maps, editor, settings screen) cannot be checked by these tests, which only stub WordPress.

## Pull requests

- **Every pull request has to contain proof of a successful test in a real instance:** a running WordPress with the plugin active (a local installation, WordPress Playground or a staging site), not the stubbed unit tests. Passing CI is not such proof.
- Proof is a screenshot, or the output of a test run against the instance, which shows the changed behaviour working. Attach it to the description of the pull request and say which WordPress version, which map provider and which browser were used.
- A change which touches maps is proven with OpenStreetMap and with Google Maps. Only the strict privacy mode is OpenStreetMap only.
- Also list what was not tested. If no real instance is available, say so plainly and do not present the change as tested.
- A change without visible behaviour (comments, development tools, documentation) needs no screenshot; state instead that the plugin still activates and shows a map in the instance.

## Versions and releases

- Do not open a new version on your own. Ask whether a change belongs to the unreleased version or to a new one.
- A version is set in three places: `Version:` and `GEOLOCATION__VERSION` in `geolocation.php`, `Stable tag:` in `readme.txt`.
- The changelog in `readme.txt` lists only what a site owner notices, a few aggregated lines per version, prefixed with `new:`, `fix:` or `performance:`. Internal changes get no entry.
- Never commit to `master`. Work on a branch, open a pull request, merge only after all checks have completed successfully.
- Pushing a tag deploys to wordpress.org and cannot be taken back. Tag only on request and only after the merge.
- Files which must not reach wordpress.org are listed in `.distignore` and `.gitattributes`; add new development files to both.

## Do not

- Do not add a build step, a framework, a JavaScript library or any other runtime dependency. Solve it with plain JavaScript and the browser's own APIs.
- Do not load anything from a CDN.
- Do not rename options, post meta keys (`geo_latitude`, `geo_longitude`, `geo_address`, `geo_enabled`, `geo_public`, `geo_track*`), shortcode attributes or block attributes: existing sites depend on them.
- Do not commit API keys or other secrets, and do not put them into tests or documentation.
