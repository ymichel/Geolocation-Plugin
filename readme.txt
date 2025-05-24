=== Geolocation ===
Contributors: frsh, mdawaffe, automattic, alexterz, ymichel
Tags: map, GPS, travel, location, journey
License: GPL_2
Requires at least: 6.0
Requires PHP: 7.3
Tested up to: 6.8
Stable tag: 1.9.7

Lightweight display the location information of your post in a map (GDPR comliant). Ideal for travelbloggers or anyone who would like to show the location of a post.

== Description ==
The Geolocation plugin allows WordPress users to geotag their posts using the Edit Post page.

Previously, in all WordPress mobile applications such as WordPress for iPhone/iPad, WordPress for Android this was directly available by activating the gps functionality. Unfortunately, the WP-application developers decided to deactivate the feature of storing GPS data in the app but I am hoping for its return.

The automated way to obtain geoinformation is possible by adding a photo as featured image to your post. If you have a featured image attached to you post, its gps coordinates are taken into concideration if there is no geo-information provided already. If you use an iPhone, make sure that you take your photos in jpg. Otherwise the GPS information is stripped from the HEIC when exporting to JPG and uploading. Furthermore, when using the WP app, make sure you have enabled to keep all metadata in the files that are uploade. Otherwise, the app will remove also the GPS information.

Once there is geoinformation added to any of your posts, visitors see a short description of the address either before, after, or at a custom location within the post. Here, you have three options as shown below: plain, link or static information. 
When "link" is chosen, hovering over the address reveals a map that displays the post's exact location. 
If one would only like to show a textual version without accessing any external services or without showing a map when visitors see a post, one can enable a "plain" mode to prevent external access except for authors to set a particular location. 
Furthermore, there is an option to statically show the map whenever there is geoinformation available. (see below for examples)

You can chose between two map providers: Google Maps and Open Streetmaps.  If you use Open Streetmaps as mapprovider in combination with the [proxy plugin for OSM](https://wordpress.org/plugins/osm-tiles-proxy/ "proxy plugin for OSM"), the tiles are delivered from the local proxy so that the visitors cannot be tracked from a third party. If no proxy is installed or Google Maps is used as the provider, this is not feasable, i.e., the tiles are pulled directly from the map service to your visitor's browser.

Furthermore, there is the option to use the tag [geolocation] also on a page in order to provide a map with multiple entries (e.g. from a journey) on one map altogether. The set of shown locations can be filtered per page, by placing a user defined field called "category" and give it the name (not the slag!) of the category to be shown. This way, you can also hide the location information per post (by chosing the "code option" without using it) and only show an overview map if needed. If the page is not restricted by any of the categories, all locations are shown that were tagged "public" and have the switch set to "enabled". (see example below)

By default, this plugin uses Open-Streetmap but as an alternative (and backwards-compatibility) google maps can also be used. However, one needs to have a Goole Maps API key to use this plugin with google maps. You may obtain a key via google cloud plattform. Make sure, you have activated "Maps JavaScript API" as well as "Geocoding API".

If you struggle while installing it or have feature requests, please feel free to drop a [support request](https://wordpress.org/support/plugin/geolocation/ "support request") anytime. I am more than happy to help you. Also if you would want to give a [review](https://wordpress.org/support/plugin/geolocation/reviews/ "review") if you are happy with the plugin, I would appreciate the feedback.

== Installation ==

1. Upload the `geolocation` directory to the `/wp-content/plugins/` directory. (or simply install it from the official package repo)
2. Activate the plugin through the 'Plugins' menu in WordPress. In case you would want to use Google Maps, chose Google Map as your provider an insert the Google Maps API key on the Settings > Geolocation page.
3. Optionally (if you are using OSM as per default setting): Install and activate the [OSM proxy](https://wordpress.org/plugins/osm-tiles-proxy/ "OSM tile proxy") to make use of local delivery without tracking options for the source to your visitors.
4. Modify the display settings as needed on the Settings > Geolocation page. The chosen settings can directly be seen in the OSM preview of the settings page.
5. Start posting with geolocation data.
6. Leave a rating on the plugin-page. :-)

== Screenshots ==

1. Editing a post
2. Viewing the location in a post; setting: plain text
3. Viewing the location in a post; setting: simple link w/hover
4. Viewing the location in a post; setting: simple map (static)
5. Editing a page for embedding a map for all (filtered) locations
6. Viweing all posts providing location information

== Changelog ==

= 1.9.7 =
* fixing javascript calls and settings initialization

= 1.9.6 =
* enhancing address attribute options for OSM display of locations.
* fixing nonce array verify in case not yet filled.

= 1.9.5 =
* fixing GPS location detection from featured image.

= 1.9.4 = 
* fixing unneeded call for location (thx @alexterz)

= 1.9.3 = 
* fixing issue with osm reverse geocode 

= 1.9.2 = 
* fixing issue with page display

= 1.9.1 = 
* fixing page display to also reflect 'public' flag accordingly: no logged in user --> only public locations; if logged in --> all locations

= 1.9.0 = 
* eliminating the presence of the geo-div on non-geo pages
* removing orphaned code elements
* fixing missing div-tag for geolocation-link
* fixing 'public' flag functionality: if geo is switched on, a location is always shown if the flag is enabled but when it is not enabled, the geoinformation is only shown to logged in users. 
* adding new display option: Simple map (static) that is always shown and not only when hovering over the link.

= 1.8.2 =
* fixing clean_coordinates

= 1.8.1 =
* fixing add_geo_support

= 1.8.0 = 
* conditional load for js-libs for page and posts display (only loaded if geo data is available).

= 1.7.6 = 
* extending reverse geocoding: if 'load' is used, the name of the location will be determinded automatically. One can overwrite the name however if updating the text-field and saving the post without re-applying the 'load' button.

= 1.7.5 =
* fix: skipping additional reverse geocode when saving if address is already available
* fix: lib/css usage at footer

= 1.7.4 =
* reverse geocode fixes

= 1.7.3 =
* sanitizing fixes

= 1.7.2 = 
* fixing save method 

= 1.7.1 =
* fixing timing for osm page display

= 1.7 = 
* enabling live preview of all settings in settings panel
* fixing custom image incl. shadow, i.e, WP-pin display
* enforcing WPCS rules for this plugin 

= 1.6 = 
* removal of jQuery usage (vanilla JavaScript)
* reducing calls for reverseGeocode in OpenStreetMap
* embedding leaflet js and css to ommit external request to 3rd parties
* adjusting google maps scripts to reflect latest API changes
* removing orphaned js-lib
* fixing several minor bugs including code cleanup

= 1.5.3 =
* bugfix for deriving the geodata from the featured image

= 1.5.2 =
* fixing typos :-(

= 1.5.1 =
* fixing reverseGeocode on empty address

= 1.5 =
* new function: when there is no geoinformation at a particular post, the plugin tries to receive it from the featured image gps data instead.
* code cleanup

= 1.4 =
* translations
* split funcitons by providers
* fixing zoom issue for osm when hovering over link

= 1.3 =
* code cleanup
* enhancing plugin options in install functionality

= 1.2 =
* introduce dynamic preview in settings page to directly see the effect instead of displaying fixed images

= 1.1.1  = 
* bugfix for OSM urls when searching for a location or the location is reverse geocoded from lat and lon

= 1.1 = 
* enabling the usage of the osm proxy [Tiles Proxy for OpenStreetMap](https://wordpress.org/plugins/osm-tiles-proxy/ "Tiles Proxy for OpenStreetMap")

= 1.0 = 
* introducing OSM as an alternative for google maps by using leaflet-api
* for new installations, OSM is the default
* preparing readyness for osm tile proxy plugin to overcome DSGVO/GDPR tracking

= 0.7.4 = 
* fixing issue with missing reset in subquery within THE_LOOP

= 0.7.3 = 
* disabling unfinished osm support

= 0.7.2 =
* settings bugfix

= 0.7.1 =
* various tiny bug fixes

= 0.7 =
* code reorganization
* preparation of variable SHORTCODE
* preparation of OSM usage within plugin
* on plugin deletion, options and addresses are removed
* jQuery refrerence was fixed for compatibility with WP_DEBUG switch

= 0.6.2 =
* fixed issue in admin panel where map was not displayed

= 0.6.1 =
* code cleanup minor things

= 0.6.2 =
* fixed issue in admin panel where map was not displayed

= 0.6 =
* optimizing 'update all Addresses'
* introducing 'page mode', i.e., usage of [geolocation] in a page to provide a map with multiple locations shown together

= 0.5.3 =
* fixing 'update all Addresses' to really process al posts providing geolocation information (and not just the first few entries).

= 0.5.2 =
* fixed bugs
* moved screenshots from plugin to asset folder (shown on description and thus not locally neccessary)
* added plugin icon ;-)

= 0.5.1 =
* fixed bugs

= 0.5 =
* improved "plain" option: google-apis are no longer loaded for a visiting user but only if a backend user is logged in.
* reverse geocoding now uses the website language for the texts being shown and locally stored (also to be seen in admin panel)
* added feature to "re-run" address determination, i.e., update all geodata posts with proper address information (also respecting the language of the given site)

= 0.4.2 = 
* fixing bug in saving geolocation to post_meta

= 0.4.1 = 
* starting GDPR/DSGVO compliant "show only" mode without accessing any external services
* fixing http to https accesses
* fixed reverse geocoding

= 0.4 =
* visualization enhanced: display geolocation either as plain text or as simple text incl. map w/mouse over (default till now)
* since Google changed their policy and an API key is required, the plugin will now show an error message if this key is missing. 

= 0.3.7 =
* re-enabled the usage without API key

= 0.3.6 =
* fixed reverse geocoding

= 0.3.5 =
* fixed default_settings

= 0.3.4 =
* fixed update hook

= 0.3.3 =
* fixed display by applying update hook

= 0.3.2 =
* fixed display 

= 0.3.1 =
* fixes Google Maps API key option
* fixed Google Link
* performance/code optimizations

= 0.3 = 
* introduced Google Maps API key option
* starting i18n for EN and DE

= 0.2.2 =
* code optimizations

= 0.2.1 =
* fixed some left overs from the previus release

= 0.2 =
* updated Google API calls to recent version
* taking over ownership for plugin :-)

= 0.1.1 =
* Added ability to turn geolocation on and off for individual posts.
* Admin Panel no longer shows up when editing a page.
* Removed display of latitude and longitude on mouse hover.
* Map link color now defaults to your theme.
* Clicking map link now (properly) does nothing.

= 0.1 =
* Initial release.
