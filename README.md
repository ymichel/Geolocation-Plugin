#  Geolocation WordPress Plugin
This is the main development place for the WordPress Plugin "geolocation".
Since WordPress needs a different [readme.txt](readme.txt), the core information is kept there.

[![GitHub tag](https://img.shields.io/github/tag/ymichel/Geolocation-Plugin?include_prereleases=&sort=semver&color=blue)](https://github.com/ymichel/Geolocation-Plugin/releases/)
[![License](https://img.shields.io/badge/License-GPL_2-blue)](#license)
[![issues - Geolocation-Plugin](https://img.shields.io/github/issues/ymichel/Geolocation-Plugin)](https://github.com/ymichel/Geolocation-Plugin/issues)

## Overview maps on pages

Put the shortcode on a page to show a map with the locations of your posts. Optional attributes filter the posts and set the size of the map:

| Attribute | Meaning | Example |
|---|---|---|
| `cat` | Categories as slugs, names or ids, separated by commas | `[geolocation cat="travel,europe"]` |
| `tag` | Tags as slugs, names or ids, separated by commas | `[geolocation tag="hiking"]` |
| `width` | Width of the map in pixels or percent | `[geolocation width="100%"]` |
| `height` | Height of the map in pixels | `[geolocation height="400"]` |
| `zoom` | Fixed zoom level from 1 to 19; without it the map is fitted to the markers | `[geolocation zoom="6"]` |
| `route` | Connects the locations with a line, in the order of the post dates | `[geolocation route="1"]` |

Attributes can be combined, and a page can contain several maps. Without the attribute `cat` the custom field "category" of the page is still used.

The same reference is available in WordPress under "Help" at the top right of the plugin's settings page.

## Pre-caching tiles

With the [proxy plugin for OSM](https://wordpress.org/plugins/osm-tiles-proxy/) storing tiles on your server, the settings page shows how many tiles of the maps of your posts are stored and offers to pre-cache the missing ones.

- Only the first view of a map is covered. For the map of a post that is its location with the map size and zoom level of the settings, or the view showing its whole track. For an overview map of a shortcode or a block it is the view fitted to its locations and tracks, or its fixed zoom level. Tiles reached by moving or zooming a map are still fetched on demand.
- Maps wider than a phone are also calculated for the width of a phone, where they may use another zoom level. A width in percent refers to the content width of the theme.
- The run works in the background and takes one post after the other, like a visitor opening one map after the other, with a pause of 4 to 8 seconds in between. Posts whose tiles are stored already are passed without a pause. A run stops after 5000 tiles.
- Posts are processed from new to old. While a run is in progress the settings page shows a progress bar, the post being processed, the tiles requested, stored and failed, and the estimated time remaining; it refreshes itself and the run can be cancelled.
- The tiles are also pre-cached automatically when a page or post is saved, including the overview maps, which a new location may change; this can be switched off in the settings.
- Pages and posts with an overview map are processed first, then the posts from new to old.
- Maps with a size set in the block "Post Location" are calculated with the size of the settings.

## Tracks (GPX)

A post can carry the track of a GPX file, e.g. the recording of a hike or a bike tour. Choose the file in the Geolocation box of the post editor.

- The file is read in your browser. Only the simplified line of the track is stored with the post; the file itself, its times and elevations are not uploaded.
- With the display setting "Simple map" the map of the post shows the track; the map in the editor shows it as well.
- The post shows the key figures of the track: its length and, if the file contains elevations, ascent, descent and highest point. Below a map it also shows the elevation profile. Both can be switched off in the settings, and the block "Post Location" can show or hide them for a single post.
- An overview map with `route="1"` draws the tracks as recorded and the gaps between them as dashed lines. The popup of a post shows the length of its track.
- A post without a location gets the end of its track as location.
- The setting "Shorten tracks" hides a distance at the start and at the end of every track, e.g. to keep your home address private.

## Block "Geolocation Map"

In the block editor you can insert the block "Geolocation Map" instead of typing the shortcode. Categories, tags, width, height, zoom and route are chosen in the settings of the block, and the editor shows the map as a preview. The block can be used in pages and posts.

## Block "Post Location"

In a post the block "Post Location" shows the location of the post wherever you place it, as text, as link with a map on hover or as map with its own size. The editor shows a preview of the saved location. The automatic output before or after the post is omitted for posts using the block.

With the position setting "Not automatically" a location is only shown in posts containing the block or the shortcode. Such a post still appears on overview maps if its location is enabled and public.

## Link to the external map

With the setting "External map" a post shows a link "Open in OpenStreetMap" or "Open in Google Maps" below its location, depending on the map provider. It is switched off by default.

- Nothing is requested from the map service until a visitor follows the link, and the service is not told which page the visitor comes from.
- A notice can be shown before the visitor leaves your website. In strict privacy mode it is always shown; without JavaScript the link is then not shown at all.
- The block "Post Location" can show or hide the link for a single post.

## Development

The checks which run for every pull request can be run locally:

```bash
composer install
composer lint   # WordPress coding standard for PHP
composer test   # PHPUnit

npm ci
npm run lint    # ESLint for the plugin's own scripts
npm test        # unit tests for the scripts, with the test runner of Node.js (version 22 or newer)
```

The scripts in `js/` are delivered as they are; there is no build step. `package.json` and `node_modules` are development tools only and not part of the released plugin.
