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

Attributes can be combined, and a page can contain several maps. Without the attribute `cat` the custom field "category" of the page is still used.

The same reference is available in WordPress under "Help" at the top right of the plugin's settings page.
