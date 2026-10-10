# Testing the plugin in a real instance

The unit tests (`composer test`, `npm test`) only cover code which calculates. Whether maps appear, the editor works and settings take effect can only be seen in a running WordPress. This file describes how to start such an instance on your computer, fill it with demo content and run the browser checks in `tests/browser/` against it.

## Requirements

- Node.js 22 or newer and an internet connection: the instance downloads WordPress, and the maps load tiles.
- Nothing has to be installed in WordPress by hand and no database is needed.

## Set up

```bash
npm ci
npm run wp:browser   # once: downloads a headless Chromium for the checks
npm run wp:start     # starts WordPress on http://127.0.0.1:9400 and keeps running
```

In a second terminal, as soon as the first one reports that WordPress is running:

```bash
npm run wp:seed      # creates the demo content and resets the settings of the plugin
```

What you get:

- [WordPress Playground](https://wordpress.github.io/wordpress-playground/) with the latest WordPress. You are logged in as administrator automatically.
- This checkout is mounted as the plugin "geolocation", so every change to a file takes effect at once.
- The plugin [osm-tiles-proxy](https://wordpress.org/plugins/osm-tiles-proxy/) is installed and active, and "Use Proxy" is switched on.
- Demo content: nine posts with locations ("Trip to Berlin", "Trip to Hamburg", ...), three of them with a track, and pages with overview maps ("Map", "Germany Tour", "Our route", "Munich to Venice", ...). It is defined in `tests/browser/mu-plugins/geolocation-test-content.json`.
- The WordPress files and the database are kept in `tests/browser/.instance/`, which Git ignores. The instance survives a restart; delete the folder to start from scratch.

`npm run wp:seed` can be repeated at any time. It overwrites the posts and pages of the demo content and the settings of the plugin, and keeps a stored Google Maps API key.

## Run a check

```bash
node tests/browser/test-map-link.js
node tests/browser/test-overview-map.js --google
```

- A check prints what it measured as JSON; read it and compare it with what you expect. An empty list `"errors"` means that the pages raised no JavaScript error. Control images are written to `tests/browser/out/`.
- Most checks change settings or a post and restore them at the end. If one is interrupted, `npm run wp:seed` brings the instance back to the starting point.
- **Both map providers:** a change which touches maps has to be checked with OpenStreetMap and with Google Maps. Checks which know the option `--google` repeat their steps with Google Maps; the others switch the provider themselves or are independent of it. Only the strict privacy mode is OpenStreetMap only.
- **Google Maps needs your own API key.** Enter it yourself under Settings > Geolocation in the instance. It is stored in the database of the instance only; never put a key into a file of the repository.
- The checks for pre-caching request tiles from the OpenStreetMap servers. Run them sparingly.

Another instance can be used with `GEOLOCATION_TEST_URL` (its address) and `GEOLOCATION_TEST_DIR` (the folder of its WordPress files, needed by the pre-caching checks). It needs the test helper as must-use plugin and the demo content. `npm run wp:start -- --port=9401` starts the instance on another port.

## What to put into a pull request

Every pull request needs proof of a successful test in a real instance. Add the output of the checks you ran, or a control image from `tests/browser/out/`, and name the WordPress version, the map provider and the browser (the checks use headless Chromium). List what you did not test.

## The checks

| Script | Purpose |
|---|---|
| `test-block-preview.js` | Checks the preview of both blocks in the editor: map, empty message, wide alignment and reload after a change for "Geolocation Map"; map with track, key figures and elevation profile, plain text and link for "Post Location"; the preview page refuses a wrong nonce. With `--google` the previews are checked with Google Maps. |
| `test-block.js` | Checks the block "Geolocation Map": rendering on the website and through the REST API, registration and the controls in the editor. Creates or updates the page "Block test". |
| `test-classic-editor.js` | Checks the Geolocation box in the classic editor (post "Trip to Dresden"): choosing a GPX file, saving, the output on the website, removing. Switches the block editor off through the test helper. With `--google` also with Google Maps. |
| `test-editor-location.js` | Checks picking a location in the post editor: click on the map, dragging the marker, "My location" (with a simulated browser position and with the permission denied) and the "Off" state. With `--google` it repeats the checks for Google Maps. Restores the demo location of "Trip to Hamburg" afterwards. |
| `test-google.js` | Checks the Google Maps provider. Needs an API key stored in the test instance. Covers the overview map (clusters, popup), the map of a single post and the editor (click, dragging the marker, address lookup). Switches the provider to Google and back to OSM. |
| `test-help-tab.js` | Opens the "Shortcode" help tab of the settings page in English and in German and prints its content. Switches the site language to German and back. |
| `test-languages.js` | Checks the translated texts of all ten languages in the browser (settings, help tab, Geolocation box, both blocks, output of a post, preview page) and reports texts which are still English or show HTML entities. Uses the test helper to switch the language. |
| `test-lazy-library.js` | Checks with both providers that the map library (Leaflet or the Google Maps API) is only loaded when it is needed: not before a map is about to scroll into view, not before the visitor points at a location link, clustering only on overview maps, and nothing on a page without a location. Restores the provider and the display mode. |
| `test-location-block.js` | Checks the block "Post Location" in the post "Trip to Berlin": map with its own size, plain text, the display of the settings, no second automatic output, rendering through the REST API and the controls in the editor. Restores the content of the post afterwards. |
| `test-map-link-align.js` | Checks with both providers, on a desktop and on a phone, that the map of a post is not wider than the screen and that the link to the external map starts at the same edge as the map and the track details. The link has to be switched on; the settings stay as they are. |
| `test-map-link.js` | Checks the link to the external map with both providers: off by default, the plain link, the notice before leaving (chosen and forced by the strict privacy mode), no link without JavaScript, the three display modes, the option of the block "Post Location" and the checkboxes of the settings page. The link is not followed. Restores the settings and the post "Trip to Berlin". |
| `test-osm-proxy-fallback.js` | Checks the tile URL when the proxy's cache or REST delivery is switched off. Writes a temporary file into the must-use plugins of the instance (`tests/browser/mu-plugins`), which it removes again. |
| `test-osm-proxy.js` | Activates the plugin `osm-tiles-proxy`, enables "Use Proxy" and checks that settings preview, post map, page map, editor and hover map load tiles and Leaflet through the proxy. The proxy plugin must be installed in the test instance. |
| `test-overview-map.js` | Checks the overview map of a page: marker clustering and the popup with image, title, date and excerpt. Adds two demo posts (Luebeck, Potsdam) and a featured image plus excerpt for "Trip to Hamburg" if they are missing, and writes control screenshots to `out/`. With `--google` it also loads the map with Google Maps. |
| `test-precache-on-save.js` | Checks that the tiles of a post are pre-cached when it is saved: on by default, the tiles arrive on the disk of the host, nothing is scheduled when switched off. Uses "Trip to Dresden" with zoom level 18 and a larger map (`node test-precache-on-save.js 1100x700` for a rerun with new tiles); settings are restored afterwards. |
| `test-precache-overview.js` | Checks the tiles the plugin calculates for overview maps (shortcodes and blocks on seven pages) against the tiles Leaflet really requests, on a desktop and on a phone. Every requested tile has to be among the calculated ones. |
| `test-precache-progress.js` | Checks the detailed progress of pre-caching on the settings page: the status refreshes itself without reloading the page, posts are processed from new to old, and with the argument `cancel` a run is cancelled and stays cancelled. `node test-precache-progress.js <zoom> <width>x<height> [cancel]` - choose a view whose tiles are not cached yet. |
| `test-precache-row.js` | Checks that the row "Pre-cache tiles" of the settings page follows the checkbox "Use Proxy" at once, shows a hint instead of the status while the proxy is not saved as used, and stays shown or hidden after saving. |
| `test-precache-run.js` | Checks a pre-cache run with tiles never requested before: `node test-precache-run.js 18` changes the default zoom level for the test, `node test-precache-run.js 9 1000x600` also the map size, so the run needs several batches. WordPress runs the batches itself; the files are checked on the disk of the host. Settings are restored afterwards. |
| `test-precache-switch.js` | Checks that the switch "pre-cache when saved" survives saving the settings while its row is not shown (proxy off, Google Maps), in both positions. |
| `test-precache.js` | Checks pre-caching of tiles: the tiles the plugin calculates for four posts against the tiles Leaflet really requests, the row on the settings page, pre-caching when a post is saved, and that nothing is offered without the proxy or with Google Maps. It deletes tile files on the host, which the PHP workers of WordPress Playground do not notice reliably, so its numbers of stored tiles are not trustworthy; use `test-precache-run.js` for the run itself. |
| `test-proxy-fields.js` | Checks the fields which follow the checkbox "Use Proxy" on the settings page: the address of the proxy, the explanation of the own tiles URL, and that this URL is read-only while the proxy is used but keeps its value. |
| `test-query-loop.js` | Checks the block "Post Location" inside a query loop (page "Loop test"): every post shows its own location, on the website and in the previews of the editor. With `--google` also with Google Maps. |
| `test-route.js` | Checks the route line of an overview map (`route="1"`). Gives the demo posts times in the order of a round trip and creates the page "Route test" if missing; writes control screenshots to `out/`. With `--google` it also loads the map with Google Maps. |
| `test-shortcode-attributes.js` | Checks the attributes of the overview map shortcode (`cat`, `tag`, `width`, `height`, `zoom`) and several maps on one page. Creates the category "North", the tag "capital" and the page "Shortcode test" if they are missing. |
| `test-strict-admin.js` | Checks the strict privacy mode in the admin area: which hosts the browser of an author contacts in the editor and on the settings page with and without the proxy, that the address search works through the site, that the preview follows the checkboxes, and the unchanged behaviour without the strict mode and with Google Maps. Nothing is saved to the post. |
| `test-strict-hint.js` | Checks the hint below the strict privacy mode: shown at once when the mode is on without the proxy, before and after saving, and that visitors then get the location text only. |
| `test-strict-privacy.js` | Checks the strict privacy mode: working proxy, deactivated proxy plugin (text only, no external requests, admin notice), and the mode switched off. Ends with the proxy active, "Use Proxy" on and the strict mode off. |
| `test-tiles-field.js` | Shows how the settings page explains the tiles URL with the proxy in use and with "Use Proxy" switched off; restores "Use Proxy" afterwards. |
| `test-track-switches.js` | Checks the switches for the key figures and the elevation profile of a track: both settings in all combinations, the popup of the overview map, the options of the block "Post Location" overruling the settings, and the options in the editor. Restores the settings and the post "Trip to Potsdam" afterwards. With `--google` everything runs with Google Maps. |
| `test-track.js` | Checks the GPX track of a post: reading a file in the editor (also one without a track), saving, the map of the post, the overview map with a route, shortening and removing. Writes its GPX files to `out/` and leaves "Trip to Hamburg" and "Trip to Potsdam" with a track. With `--google` the maps are also loaded with Google Maps. |
| `test-upgrade-uninstall.js` | Checks the upgrade from the state of version 1.9.9 and the uninstall routine through the test helper, which backs up the plugin's options and geo data in the database and compares the restored data with it. |
| `screenshot-1.js` | Takes screenshot 1 (editing a post) into `out/`, using the post "Munich to Venice by bike" with its track. Also checks that the track is drawn on the editor map, removed and drawn again. With `--google` it writes control images of the editor with Google Maps instead. |
| `screenshots-route.js` | Takes the screenshots 7 (overview map with a route), 8 ("Shortcode" help tab of the settings page) 9 (the block in the editor, not saved) and 10 (overview map with the track of the page "Munich to Venice") into `out/`. Since the demo posts have tracks, a new screenshot 7 shows the gaps as dashed lines. Creates or updates the page "Our route". |
| `screenshots.js` | Takes the wordpress.org screenshots 2 to 6 into `out/` (PNG, 2732 px wide). It waits until all map tiles are loaded and switches the display mode (plain, link, map) through the settings page; the instance ends on "map". |

`lib.js` holds what all checks share: the address of the instance and the lookup of posts by their slug, as ids differ between instances.

## The test helper

`tests/browser/mu-plugins/geolocation-test-helper.php` is a must-use plugin of the test instance and not part of the released plugin. It offers `admin-ajax.php?action=geolocation_test&do=...` for administrators: `seed`, `classic_on`/`classic_off`, `language&locale=...`, `tiles&post=...`, `run_cron`, `state`, `backup`, `restore`, `simulate_old` and `uninstall_test`.

## Things to know

- Never uninstall or delete the plugin through the WordPress screens of the instance: its folder is your checkout. `test-upgrade-uninstall.js` runs the uninstall routine through the helper, which keeps a verified backup in the database and restores it.
- Playground logs every visitor in, so the view of logged-out visitors cannot be checked this way.
- Playground answers a request without cookies by a redirect which logs in. The helper adds the cookie to the requests the site sends to itself, so pre-caching works.
- Do not delete tile files by hand to test pre-caching: the PHP workers of Playground do not notice that reliably. Use a zoom level or map size whose tiles have never been requested.
- Playground sometimes answers the request for a tile which has just been stored with "500 Internal Server Error". Pre-caching then reports these tiles as failed, which is correct; it is not an error of the plugin or the proxy.
- The posts of the demo content load photos from Wikimedia Commons, so the checks for the strict privacy mode list `upload.wikimedia.org` among the external requests.

## Screenshots for wordpress.org

`screenshots.js`, `screenshot-1.js` and `screenshots-route.js` write the screenshots as PNG, 2732 pixels wide, to `tests/browser/out/`. On macOS they are converted with:

```bash
cd tests/browser && for i in 1 2 3 4 5 6 7 8 9 10; do sips -s format jpeg -s formatOptions 85 out/screenshot-$i.png --out ../../.wordpress-org/screenshot-$i.jpg; done
```
