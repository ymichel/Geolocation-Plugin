<?php
add_action('wp_enqueue_scripts', 'add_my_scripts');
function add_my_scripts()
{
    wp_enqueue_script(
        'geolocation',
        get_template_directory_uri() . '/js/jquery.elementReady.js',
        array('jquery')
    );
}

function admin_head_google()
{
    global $post;
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
        var $j = jQuery.noConflict();
        $j(function() {
            $j(document).ready(function() {
                var hasLocation = false;
                var center = new google.maps.LatLng(52.5162778, 13.3733267);
                var postLatitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_latitude', true)); ?>';
                var postLongitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_longitude', true)); ?>';
                var isPublic = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_public', true)); ?>';
                var isGeoEnabled = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_enabled', true)); ?>';

                if (isPublic === '0')
                    $j("#geolocation-public").attr('checked', false);
                else
                    $j("#geolocation-public").attr('checked', true);

                if (isGeoEnabled === '0')
                    disableGeo();
                else
                    enableGeo();

                if ((postLatitude !== '') && (postLongitude !== '')) {
                    center = new google.maps.LatLng(postLatitude, postLongitude);
                    hasLocation = true;
                    $j("#geolocation-latitude").val(center.lat());
                    $j("#geolocation-longitude").val(center.lng());
                    reverseGeocode(center);
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

                function placeMarker(location) {
                    marker.setPosition(location);
                    map.setCenter(location);
                    if ((location.lat() !== '') && (location.lng() !== '')) {
                        $j("#geolocation-latitude").val(location.lat());
                        $j("#geolocation-longitude").val(location.lng());
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
                    $j("#geodata").html(postLatitude + ', ' + postLongitude);
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
                                        $j("#geolocation-address").val(address);
                                        placeMarker(location);
                                    }
                                }
                            }
                        });
                    }
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

function add_geo_support_google($posts)
{
    default_settings();
    $zoom = (int) get_option('geolocation_default_zoom');
    global $post_count;
    $post_count = count($posts); ?>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js<?php echo get_google_maps_api_key("?"); ?>"></script>
    <script type="text/javascript">
        var $j = jQuery.noConflict();
        $j(function() {
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

            $j(".geolocation-link").mouseover(function() {
                $j("#map").stop(true, true);
                var lat = $j(this).attr("name").split(",")[0];
                var lng = $j(this).attr("name").split(",")[1];
                var latlng = new google.maps.LatLng(lat, lng);
                placeMarker(latlng);

                var offset = $j(this).offset();
                $j("#map").fadeTo(250, 1);
                $j("#map").css("z-index", "99");
                $j("#map").css("visibility", "visible");
                $j("#map").css("top", offset.top + 20);
                $j("#map").css("left", offset.left);

                allowDisappear = false;
                $j("#map").css("visibility", "visible");
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

            function placeMarker(location) {
                map.setZoom(' . $zoom.');
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

    $script = $script . "<script type=\"text/javascript\" src=\"//maps.googleapis.com/maps/api/js'" . get_google_maps_api_key("?") . "'\"></script>
<script type=\"text/javascript\">
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