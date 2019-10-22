=== Geolocation ===
Contributors: frsh, mdawaffe, automattic, ymichel
Tags: geolocation, maps, geotag, google maps, travel
Requires at least: 2.9.2
Tested up to: 5.2
Stable tag: 0.6.1

The Geolocation plugin allows WordPress users to geotag their posts using the Edit Post page or any geo-enabled WordPress mobile applications.

== Description ==

The Geolocation plugin allows WordPress users to geotag their posts using the Edit Post page or any geo-enabled WordPress mobile applications such as WordPress for iPhone, WordPress for Android, or WordPress for BlackBerry.

Visitors see a short description of the address either before, after, or at a custom location within the post. Hovering over the address reveals a map that displays the post's exact location. 
If one would only like to show a textual version without accessing any external services when visitors see a post, one can enable a "plain" mode to prevent external access except for authors to set a particular location. That way, the Google APIs are only used when a logged in user is accessing the site or when a post is being made.
Furthermore, there is the option to use the tag [geolocation] also on a page in order to provide a map with multiple entries (e.g. from a journey) on one map altogether. This can be achieved, by placing a user defined field called "category" and give it the name (not the slag!) of the category to be shown.

Since Google changed their policy in terms of maps usage, one needs to have a Goole Maps API key to use this plugin.
You may obtain a key via google cloud plattform. Make sure, you have activated "Maps JavaScript API" as well as "Geocoding API".

== Installation ==

1. Upload the `geolocation` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Insert the Google Maps API key on the Settings > Geolocation page.
4. Modify the display settings as needed on the Settings > Geolocation page.
5. Start posting with geolocation data :-)

== Screenshots ==

1. Editing a post
2. Viewing the location in a post
3. Showing all posts providing location informatiob
4. Showing all posts of a particular category by adding a custom field

== Changelog ==

= 0.6.1 =
* code cleanup minor things

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

= 0.1.1 =
* Added ability to turn geolocation on and off for individual posts.
* Admin Panel no longer shows up when editing a page.
* Removed display of latitude and longitude on mouse hover.
* Map link color now defaults to your theme.
* Clicking map link now (properly) does nothing.

= 0.1 =
* Initial release.
