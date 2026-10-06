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

- Only the first view of the map of each post is covered, with the map size and zoom level of the settings; for a post with a track, the view showing the whole track. Tiles reached by moving or zooming a map are still fetched on demand.
- The run works in the background in batches of about 20 tiles with 20 seconds in between, and stops after 5000 tiles.
- Posts are processed from new to old. While a run is in progress the settings page shows a progress bar, the post being processed, the tiles requested, stored and failed, and the estimated time remaining; it refreshes itself and the run can be cancelled.
- The tiles of a post are also pre-cached automatically when it is saved; this can be switched off in the settings.
- Overview maps and maps with a size set in the block "Post Location" are not covered.

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
