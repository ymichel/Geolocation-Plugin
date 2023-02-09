<?php

function admin_head_osm()
{
    global $post;
    $post_id = $post->ID;
    $zoom = (int) get_option('geolocation_default_zoom'); ?>
    <link rel="stylesheet" href="<?php echo get_osm_leaflet_css_url(); ?>" />
    <script src="<?php echo get_osm_leaflet_js_url(); ?>"></script>
    <script type="text/javascript">
        var $j = jQuery.noConflict();
        $j(function() {
            $j(document).ready(function() {
                var hasLocation = false;
                var postLatitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_latitude', true)); ?>';
                var postLongitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_longitude', true)); ?>';
                var postAddress = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_address', true)); ?>';
                var isPublic = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_public', true)); ?>';
                var isGeoEnabled = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_enabled', true)); ?>';
                var zoom = '<?php echo $zoom; ?>';
                var image = '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>';
                var iconOptions = {
                    iconUrl: image
                }
                var customIcon = L.icon(iconOptions);
                var markerOptions = {
                    <?php if ((bool) get_option('geolocation_wp_pin')) { ?>
                        icon: customIcon,
                    <?php } ?>
                    clickable: false,
                    draggable: false
                }
                var myMarker = {};

                if (isPublic === '0')
                    $j("#geolocation-public").attr('checked', false);
                else
                    $j("#geolocation-public").attr('checked', true);

                if (isGeoEnabled === '0')
                    disableGeo();
                else
                    enableGeo();


                var lat_lng = [0.00, 0.00];
                var map = L.map(document.getElementById('geolocation-map')).setView(lat_lng, zoom);
                var myMapBounds = [];
                L.tileLayer('<?php echo get_osm_tiles_url(); ?>', {
                    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);
                myMarker = L.marker(lat_lng, markerOptions).addTo(map);
                map.setView(myMarker.getLatLng(), map.getZoom());
                if ((postLatitude !== '') && (postLongitude !== '')) {
                    var lat_lng = [postLatitude, postLongitude];
                    myMarker.setLatLng(lat_lng);
                    map.setView(myMarker.getLatLng(), map.getZoom());
                    hasLocation = true;
                    $j("#geolocation-latitude").val(postLatitude);
                    $j("#geolocation-longitude").val(postLongitude);
                    if (postLatitude !== '') {
                        $j("#geolocation-address").val(postAddress);
                    } else {
                        reverseGeocode(postLatitude, postLongitude);
                    }
                }
                var currentAddress;
                var customAddress = false;
                $j("#geolocation-address").click(function() {
                    currentAddress = $j(this).val();
                    if (currentAddress !== '')
                        $j("#geolocation-address").val('');
                });

                $j("#geolocation-load").click(function() {
                    if ($j("#geolocation-address").val() !== '') {
                        customAddress = true;
                        currentAddress = $j("#geolocation-address").val();
                        geocode(currentAddress);
                    }
                });

                $j("#geolocation-address").keyup(function(e) {
                    if (e.keyCode === 13)
                        $j("#geolocation-load").click();
                });

                $j("#geolocation-enabled").click(function() {
                    enableGeo();
                });

                $j("#geolocation-disabled").click(function() {
                    disableGeo();
                });

                function geocode(address) {
                    $j.getJSON('<?php echo get_osm_nominatim_url(); ?>/search?format=json&accept-language=\'<?php echo getSiteLang(); ?>\'&limit=1&q=' + address, function(data) {
                        $j("#geolocation-latitude").val(data[0].lat);
                        $j("#geolocation-longitude").val(data[0].lon);
                        lat_lng = [data[0].lat, data[0].lon];

                        myMarker.setLatLng(lat_lng);
                        map.setView(myMarker.getLatLng(), map.getZoom());
                        hasLocation = true;
                    });
                }

                function reverseGeocode(lat, lon) {
                    $j.getJSON('<?php echo get_osm_nominatim_url(); ?>/reverse?format=json&accept-language=\'<?php echo getSiteLang(); ?>\'&lat=' + lat + '&lon=' + lon, function(data) {
                        console.log(data);
                        $j("#geolocation-address").val(data.display_name);
                    });
                }

                function enableGeo() {
                    $j("#geolocation-address").removeAttr('disabled');
                    $j("#geolocation-load").removeAttr('disabled');
                    $j("#geolocation-map").css('filter', '');
                    $j("#geolocation-map").css('opacity', '');
                    $j("#geolocation-map").css('-moz-opacity', '');
                    $j("#geolocation-public").removeAttr('disabled');
                    $j("#geolocation-map").removeAttr('readonly');
                    $j("#geolocation-disabled").removeAttr('checked');
                    $j("#geolocation-enabled").attr('checked', 'checked');

                    if (isPublic === '1')
                        $j("#geolocation-public").attr('checked', 'checked');
                }

                function disableGeo() {
                    $j("#geolocation-address").attr('disabled', 'disabled');
                    $j("#geolocation-load").attr('disabled', 'disabled');
                    $j("#geolocation-map").css('filter', 'alpha(opacity=50)');
                    $j("#geolocation-map").css('opacity', '0.5');
                    $j("#geolocation-map").css('-moz-opacity', '0.5');
                    $j("#geolocation-map").attr('readonly', 'readonly');
                    $j("#geolocation-public").attr('disabled', 'disabled');

                    $j("#geolocation-enabled").removeAttr('checked');
                    $j("#geolocation-disabled").attr('checked', 'checked');

                    if (isPublic === '1')
                        $j("#geolocation-public").attr('checked', 'checked');
                }
            });
        });
    </script>
<?php
}

function add_geo_support_osm($posts)
{
    default_settings();
    global $post_count;
    $post_count = count($posts);
    $zoom = (int) get_option('geolocation_default_zoom'); ?>
    <link rel="stylesheet" href="<?php echo get_osm_leaflet_css_url(); ?>" />
    <script src="<?php echo get_osm_leaflet_js_url(); ?>"></script>
    <script type="text/javascript">
        var $j = jQuery.noConflict();
        $j(function() {
            var map = L.map(document.getElementById("map")).setView([51.505, -0.09], '<?php echo $zoom; ?>');
            var myMapBounds = [];
            L.tileLayer('<?php echo get_osm_tiles_url(); ?>', {
                attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var allowDisappear = true;
            var cancelDisappear = false;

            var iconOptions = {
                iconUrl: '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>'
            }
            var customIcon = L.icon(iconOptions);
            var markerOptions = {
                <?php if ((bool) get_option('geolocation_wp_pin')) { ?>
                    icon: customIcon,
                <?php } ?>
                clickable: false,
                draggable: false
            }

            $j(".geolocation-link").mouseover(function() {
                $j("#map").stop(true, true);
                var lat = $j(this).attr("name").split(",")[0];
                var lng = $j(this).attr("name").split(",")[1];
                var lat_lng = [lat, lng];
                L.marker(lat_lng, markerOptions).addTo(map).bindPopup("xyz");
                myMapBounds.push(lat_lng);
                map.setView(new L.LatLng(lat, lng), <?php echo $zoom; ?>);

                var offset = $j(this).offset();
                $j("#map").fadeTo(250, 1);
                $j("#map").css("z-index", "99");
                $j("#map").css("visibility", "visible");
                $j("#map").css("top", offset.top + 20);
                $j("#map").css("left", offset.left);

                allowDisappear = false;
                $j("#map").css("visibility", "visible");
            });

            $j(".geolocation-link").mouseout(function() {
                allowDisappear = true;
                cancelDisappear = false;
                setTimeout(function() {
                    if ((allowDisappear) && (!cancelDisappear)) {
                        $j("#map").fadeTo(500, 0, function() {
                            $j("#map").css("z-index", "-1");
                            allowDisappear = true;
                            cancelDisappear = false;
                        });
                    }
                }, 800);
            });

            $j(".geolocation-link").mouseover(function() {});

            $j(".geolocation-link").mouseout(function() {
                allowDisappear = true;
                cancelDisappear = false;
                setTimeout(function() {
                    if ((allowDisappear) && (!cancelDisappear)) {
                        $j("#map").fadeTo(500, 0, function() {
                            $j("#map").css("z-index", "-1");
                            allowDisappear = true;
                            cancelDisappear = false;
                        });
                    }
                }, 800);
            });

            $j("#map").mouseover(function() {
                allowDisappear = false;
                cancelDisappear = true;
                $j("#map").css("visibility", "visible");
            });

            $j("#map").mouseout(function() {
                allowDisappear = true;
                cancelDisappear = false;
                $j(".geolocation-link").mouseout();
            });
        });
    </script>
<?php
}

function display_location_page_osm($content)
{
    global $post;
    $html = '';
    settype($html, "string");
    $script = '';
    settype($script, "string");
    settype($category, "string");
    $category = (string) get_post_meta($post->ID, 'category', true);
    $category_id = get_cat_ID($category);
    $counter = 0;

    $pargs = array(
        'post_type' => 'post',
        'cat' => $category_id,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'geo_latitude',
                'value' => '0',
                'compare' => '!='
            ),
            array(
                'key' => 'geo_longitude',
                'value' => '0',
                'compare' => '!='
            ),
            array(
                'key' => 'geo_public',
                'value' => '1',
                'compare' => '='
            )
        )
    );
    $zoom = (int) get_option('geolocation_default_zoom');
    $script = $script . "<script src=\"" . get_osm_leaflet_js_url() . "\"></script>";
    $script = $script . "<script type=\"text/javascript\">
    var mymap = L.map('mapid').setView([51.505, -0.09], " . $zoom . ");
    var myMapBounds = [];
    var lat_lng = [];
	L.tileLayer('" . get_osm_tiles_url() . "', {
        attribution: '&copy; <a href=\"http://osm.org/copyright\">OpenStreetMap</a> contributors' 
    }).addTo(mymap);
    var image = '" . esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))) . "';
	var iconOptions = {
	       iconUrl: image
    }
	var customIcon = L.icon(iconOptions);
	var markerOptions = {";
    if ((bool) get_option('geolocation_wp_pin')) {
        $script = $script . "          icon: customIcon,";
    }
    $script = $script . "	       clickable: false,
           draggable: false
     	}";

    $post_query = new WP_Query($pargs);
    while ($post_query->have_posts()) {
        $post_query->the_post();
        $postTitle = get_the_title();
        $post_id = (int) get_the_ID();
        $postLatitude = (string) get_post_meta($post_id, 'geo_latitude', true);
        $postLongitude = (string) get_post_meta($post_id, 'geo_longitude', true);
        $script = $script . "
        lat_lng = [" . $postLatitude . "," . $postLongitude . "];
        L.marker(lat_lng, markerOptions).addTo(mymap).bindPopup('<a href=\"" . esc_attr((string) get_permalink($post_id)) . "\">" . $postTitle . "</a>');
        myMapBounds.push(lat_lng);";
        $counter = $counter + 1;
    }
    wp_reset_postdata();
    $script = $script . "
        mymap.fitBounds(myMapBounds);
    </script>";

    if ($counter > 0) {
        $width = esc_attr((string) get_option('geolocation_map_width_page'));
        $height = esc_attr((string) get_option('geolocation_map_height_page'));
        $html = $html . '<div id="mapid" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
        $html = $html . $script;
    }
    $content = str_replace((string) get_option('geolocation_shortcode'), $html, $content);
    return $content;
}

function pullJSON_osm($latitude, $longitude)
{
    $json = get_osm_nominatim_url() . "/reverse?format=json&accept-language=" . getSiteLang() . "&lat=" . $latitude . "&lon=" . $longitude . "&addressdetails=1";
    $ch = curl_init($json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    $jsonfile = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string) $jsonfile, true);
    return $decoded;
}

include_once ABSPATH . 'wp-admin/includes/plugin.php';

function get_osm_tiles_url()
{
    if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
        $proxy_cached_url   = apply_filters('osm_tiles_proxy_get_proxy_url', $proxy_cached_url);
        return $proxy_cached_url;
    } else {
        $param = (string) get_option('geolocation_osm_tiles_url');
        return $param;
    }
}

function get_osm_leaflet_js_url()
{
    if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
        $leaflet_js_url     = apply_filters('osm_tiles_proxy_get_leaflet_js_url', $leaflet_js_url);
        return $leaflet_js_url;
    } else {
        $param = (string) get_option('geolocation_osm_leaflet_js_url');
        return $param;
    }
}

function get_osm_leaflet_css_url()
{
    if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
        $leaflet_css_url    = apply_filters('osm_tiles_proxy_get_leaflet_css_url', $leaflet_css_url);
        return $leaflet_css_url;
    } else {
        $param = (string) get_option('geolocation_osm_leaflet_css_url');
        return $param;
    }
}

function get_osm_nominatim_url()
{
    $param = (string) get_option('geolocation_osm_nominatim_url');
    return $param;
}

?>