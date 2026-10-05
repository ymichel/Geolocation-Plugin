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

## Tracks (GPX)

A post can carry the track of a GPX file, e.g. the recording of a hike or a bike tour. Choose the file in the Geolocation box of the post editor.

- The file is read in your browser. Only the simplified line of the track is stored with the post; the file itself, its times and elevations are not uploaded.
- With the display setting "Simple map" the map of the post shows the track.
- An overview map with `route="1"` draws the tracks as recorded and the gaps between them as dashed lines. The popup of a post shows the length of its track.
- A post without a location gets the end of its track as location.
- The setting "Shorten tracks" hides a distance at the start and at the end of every track, e.g. to keep your home address private.

## Block "Geolocation Map"

In the block editor you can insert the block "Geolocation Map" instead of typing the shortcode. Categories, tags, width, height, zoom and route are chosen in the settings of the block. The block can be used in pages and posts.

## Block "Post Location"

In a post the block "Post Location" shows the location of the post wherever you place it, as text, as link with a map on hover or as map with its own size. The automatic output before or after the post is omitted for posts using the block.
