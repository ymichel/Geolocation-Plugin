<?php

/** This is the provider specific pool for the provider "google maps". **/
function admin_head_google() {
    global s$post;
    $post_id = $post->ID;
    $zoom = (int) get_option('geolocation_default_zoom'); ?>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        function initMap() {
            //console.log("google maps is ready.");
        }
    </script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js<?php echo get_google_maps_api_key("?"); ?>&callback=initMap"></script>
    <script type="text/javascript">
        function ready(fn) {
            if (document.readyState != 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }
        ready(() => {
            var hasLocation = false;
            var center = new google.maps.LatLng(52.5162778, 13.3733267);
            var postLatitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_latitude', true)); ?>';
            var postLongitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_longitude', true)); ?>';
            var postAddress = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_address', true)); ?>';
            var isPublic = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_public', true)); ?>';
            var isGeoEnabled = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_enabled', true)); ?>';

            if (isPublic === '0') {
                document.getElementById('geolocation-public').setAttribute('checked', false);
            } else {
                document.getElementById('geolocation-public').setAttribute('checked', true);
            }

            if (isGeoEnabled === '0') {
                disableGeo();
            } else {
                enableGeo();
            }


            if ((postLatitude !== '') && (postLongitude !== '')) {
                center = new google.maps.LatLng(postLatitude, postLongitude);
                hasLocation = true;
                document.getElementById('geolocation-latitude').value = postLatitude;
                document.getElementById('geolocation-longitude').value = postLongitude;
                if (postAddress !== '') {
                    document.getElementById('geolocation-address').value = postAddress;
                } else {
                    reverseGeocode(center);
                }

            }

            var myOptions = {
                'zoom': <?php echo $zoom; ?>,
                'center': center,
                'mapTypeId': google.maps.MapTypeId.ROADMAP
            };
            var image = '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>';
            var shadow = new google.maps.MarkerImage('<?php echo esc_js(esc_url(plugins_url('img/wp_pin_shadow.png', __FILE__))); ?>',
                new google.maps.Size(39, 23),
                new google.maps.Point(0, 0),
                new google.maps.Point(12, 25));

            var map = new google.maps.Map(document.getElementById('geolocation-map'), myOptions);
            var marker = new google.maps.Marker({
                position: center,
                map: map,
                title: 'Post Location'
                <?php if ((bool) get_option('geolocation_wp_pin')) { ?>,
                    icon: image,
                    shadow: shadow
                <?php } ?>
            });

            if ((!hasLocation) && (google.loader.ClientLocation)) {
                center = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
                reverseGeocode(center);
            } else if (!hasLocation) {
                map.setZoom(1);
            }

            google.maps.event.addListener(map, 'click', function(event) {
                placeMarker(event.latLng);
            });

            var currentAddress;
            var customAddress = false;
            document.getElementById('geolocation-address').addEventListener('click', event => {
                currentAddress = document.getElementById('geolocation-address').value;
                if (currentAddress !== '')
                    document.getElementById('geolocation-address').value = '';
            });
            document.getElementById('geolocation-load').addEventListener('click', event => {
                if (document.getElementById('geolocation-address').value !== '') {
                    customAddress = true;
                    currentAddress = document.getElementById('geolocation-address').value;
                    geocode(currentAddress);
                }
            });
            document.getElementById('geolocation-address').addEventListener('keyup', function(e) {
                if (e.key === 'Enter')
                    document.getElementById('geolocation-load').click();
            });
            document.getElementById('geolocation-enabled').addEventListener('click', event => {
                enableGeo();
            });
            document.getElementById('geolocation-disabled').addEventListener('click', event => {
                disableGeo();
            });

            function placeMarker(location) {
                marker.setPosition(location);
                map.setCenter(location);
                if ((location.lat() !== '') && (location.lng() !== '')) {
                    document.getElementById('geolocation-latitude').value = location.lat();
                    document.getElementById('geolocation-longitude').value = location.lng();
                    //console.log(location);
                }

                if (!customAddress)
                    reverseGeocode(location);
            }

            function geocode(address) {
                var geocoder = new google.maps.Geocoder();
                if (geocoder) {
                    geocoder.geocode({
                        "address": address
                    }, function(results, status) {
                        if (status === google.maps.GeocoderStatus.OK) {
                            placeMarker(results[0].geometry.location);
                            if (!hasLocation) {
                                map.setZoom(<?php echo $zoom; ?>);
                                hasLocation = true;
                            }
                        }
                    });
                }
                //document.querySelector("#geodata").innerHTML = postLatitude + ', ' + postLongitude;
            }

            function reverseGeocode(location) {
                var geocoder = new google.maps.Geocoder();
                if (geocoder) {
                    geocoder.geocode({
                        "latLng": location
                    }, function(results, status) {
                        if (status === google.maps.GeocoderStatus.OK) {
                            if (results[1]) {
                                var address = results[1].formatted_address;
                                if (address === "") {
                                    address = results[7].formatted_address;
                                } else {
                                    document.getElementById('geolocation-address').value = address;
                                    placeMarker(location);
                                }
                            }
                        }
                    });
                }
            }

            function enableGeo() {
                document.getElementById('geolocation-address').removeAttribute('disabled');
                document.getElementById('geolocation-load').removeAttribute('disabled');
                document.getElementById('geolocation-map').style.filter = '';
                document.getElementById('geolocation-map').style.opacity = '';
                document.getElementById('geolocation-map').style.MozOpacity = '';
                document.getElementById('geolocation-public').removeAttribute('disabled');
                document.getElementById('geolocation-map').removeAttribute('readonly');
                document.getElementById('geolocation-disabled').removeAttribute('checked');
                document.getElementById('geolocation-enabled').setAttribute('checked', true);
                if (isPublic === '1')
                    document.getElementById('geolocation-public').setAttribute('checked', true);
            }

            function disableGeo() {
                document.getElementById('geolocation-address').setAttribute('disabled', 'disabled');
                document.getElementById('geolocation-load').setAttribute('disabled', 'disabled');
                document.getElementById('geolocation-map').style.filter = 'alpha(opacity=50)';
                document.getElementById('geolocation-map').style.opacity = '0.5';
                document.getElementById('geolocation-map').style.MozOpacity = '0.5';
                document.getElementById('geolocation-public').setAttribute('disabled', 'disabled');
                document.getElementById('geolocation-map').setAttribute('readonly', 'readonly');
                document.getElementById('geolocation-enabled').removeAttribute('checked');
                document.getElementById('geolocation-disabled').setAttribute('checked', true);
                if (isPublic === '1')
                    document.getElementById('geolocation-public').setAttribute('checked', true);
            }
        });
    </script>
<?php
}

function add_geo_support_google($posts)
{
    default_settings();
    global $post_count;
    $post_count = count($posts);

    $zoom = (int) get_option('geolocation_default_zoom'); ?>
    <script type="text/javascript">
        function initMap() {
            //console.log("google maps is ready.");
        }
    </script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js<?php echo get_google_maps_api_key("?"); ?>&callback=initMap"></script>
    <script type="text/javascript">
        function ready(fn) {
            if (document.readyState != 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }
        ready(() => {
            var center = new google.maps.LatLng(0.0, 0.0);
            var myOptions = {
                zoom: <?php echo $zoom; ?>,
                center: center,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            }
            var map = new google.maps.Map(document.getElementById("map"), myOptions);
            var image = "<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>";
            var shadow = new google.maps.MarkerImage("<?php echo plugins_url('img/wp_pin_shadow.png', __FILE__); ?>",
                new google.maps.Size(39, 23),
                new google.maps.Point(0, 0),
                new google.maps.Point(12, 25)
            );
            var marker = new google.maps.Marker({
                position: center,
                map: map,
                <?php if ((bool) get_option('geolocation_wp_pin')) { ?>
                    icon: image,
                    shadow: shadow,
                <?php   } ?>
                title: "Post Location"
            });

            var allowDisappear = true;
            var cancelDisappear = false;

            var geolocationLinks = document.querySelectorAll('.geolocation-link');

            for (var i = 0; i < geolocationLinks.length; i++) {
                geolocationLinks[i].addEventListener('mouseover', function() {
                    //TODO? $j("#map").stop(true, true);
                    var lat = this.getAttribute('name').split(',')[0];
                    var lng = this.getAttribute('name').split(',')[1];
                    var latlng = new google.maps.LatLng(lat, lng);
                    placeMarker(latlng);

                    const rect = this.getBoundingClientRect();
                    const top = rect.top + window.scrollY + 20;
                    const left = rect.left + window.scrollX;

                    document.querySelector('#map').style.opacity = 1;
                    document.querySelector('#map').style.zIndex = '99';
                    document.querySelector('#map').style.visibility = 'visible';
                    document.querySelector("#map").style.top = top + "px";
                    document.querySelector("#map").style.left = left + "px";

                    allowDisappear = false;
                });

                geolocationLinks[i].addEventListener('mouseout', function() {
                    allowDisappear = true;
                    cancelDisappear = false;
                    setTimeout(function() {
                        if ((allowDisappear) && (!cancelDisappear)) {
                            document.querySelector('#map').style.opacity = 0;
                            document.querySelector('#map').style.zIndex = '-1';
                            allowDisappear = true;
                            cancelDisappear = false;
                        }
                    }, 800);
                });
            }

            document.querySelector("#map").addEventListener("mouseover", function() {
                allowDisappear = false;
                cancelDisappear = true;
                this.style.visibility = "visible";
            });

            document.querySelector("#map").addEventListener("mouseout", function() {
                allowDisappear = true;
                cancelDisappear = false;
                document.querySelectorAll(".geolocation-link").forEach(el => el.dispatchEvent(new Event("mouseout")));
            });

            function placeMarker(location) {
                map.setZoom(<?php echo $zoom; ?>);
                marker.setPosition(location);
                map.setCenter(location);
            }

            google.maps.event.addListener(map, "center_changed", function() {
                // 5 seconds after the center of the map has changed, pan back to the
                // marker.
                window.setTimeout(function() {
                    map.panTo(marker.getPosition());
                }, 5000);
            });
            google.maps.event.addListener(map, "click", function() {
                window.location = "https://maps.google.com/maps?q=" + map.center.lat() + ",+" + map.center.lng();
            });
        });
    </script>
<?php }

function display_location_page_google($content)
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

    $script = $script . "<script type=\"text/javascript\">
      var map = new google.maps.Map(
        document.getElementById('mymap'), {
          mapTypeId: google.maps.MapTypeId.ROADMAP
        }
      );
      var bounds = new google.maps.LatLngBounds();";

    $post_query = new WP_Query($pargs);
    while ($post_query->have_posts()) {
        $post_query->the_post();
        $post_id = (int) get_the_ID();
        $postLatitude = (string) get_post_meta($post_id, 'geo_latitude', true);
        $postLongitude = (string) get_post_meta($post_id, 'geo_longitude', true);
        $script = $script . "
      marker = new google.maps.Marker({
            position: new google.maps.LatLng(" . $postLatitude . "," . $postLongitude . "),
            map: map
      });
      bounds.extend(marker.position);";
        $counter = $counter + 1;
    }
    wp_reset_postdata();
    $script = $script . "
       map.fitBounds(bounds);
</script>";

    if ($counter > 0) {
        $width = esc_attr((string) get_option('geolocation_map_width_page'));
        $height = esc_attr((string) get_option('geolocation_map_height_page'));
        $html = $html . '<div id="mymap" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
        $html = $html . $script;
    }
    $content = str_replace((string) get_option('geolocation_shortcode'), $html, $content);
    return $content;
}

function pullJSON_google($latitude, $longitude)
{
    $url = "https://maps.googleapis.com/maps/api/geocode/json" . get_google_maps_api_key("?") . "&language=" . getSiteLang() . "&latlng=" . $latitude . "," . $longitude;
    $decoded = json_decode(wp_remote_get($url)['body']);
    return $decoded;
}

function get_google_maps_api_key($sep)
{
    $apikey = (string) get_option('geolocation_google_maps_api_key');
    if ($apikey != "") {
        return $sep . 'key=' . $apikey;
    }
    return '';
}

?>