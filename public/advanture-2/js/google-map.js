
function init() {
    // Offline-safe guard: if Google Maps script isn't loaded (or the map element doesn't exist),
    // just do nothing instead of throwing errors.
    if (typeof google === 'undefined' || !google.maps) {
        return;
    }

    var mapElement = document.getElementById('map');
    if (!mapElement) {
        return;
    }

    // Basic options for a simple Google Map
    // For more options see: https://developers.google.com/maps/documentation/javascript/reference#MapOptions
    var myLatlng = new google.maps.LatLng(40.69847032728747, -73.9514422416687);
    
    var mapOptions = {
        // How zoomed in you want the map to start at (always required)
        zoom: 7,

        // The latitude and longitude to center the map (always required)
        center: myLatlng,

        // How you would like to style the map. 
        scrollwheel: false,
        styles: [
            {
                "featureType": "administrative.country",
                "elementType": "geometry",
                "stylers": [
                    {
                        "visibility": "simplified"
                    },
                    {
                        "hue": "#ff0000"
                    }
                ]
            }
        ]
    };

    // Create the Google Map using out element and options defined above
    var map = new google.maps.Map(mapElement, mapOptions);

    // Keep it offline-friendly: place a single marker at a fixed coordinate (no geocoding API calls).
    new google.maps.Marker({
        position: myLatlng,
        map: map
    });
    
}

if (typeof google !== 'undefined' && google.maps && google.maps.event) {
    google.maps.event.addDomListener(window, 'load', init);
}