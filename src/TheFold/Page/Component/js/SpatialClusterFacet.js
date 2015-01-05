function TheFoldPageComponentMap(config) {

    this.name = config.name;
    this.foldMap = null;
    this.selector = config.selector;
    this.ignoreIdle = false;
    this.clickedMarker = null;
    this.page = null;
    this.infowindow = new google.maps.InfoWindow();
    var _this = this;
    
    this.init = function(page) {

        this.page = page;
        this.foldMap = new theFoldGoogleMap(config);
        this.foldMap.renderMap( jQuery(this.selector) );
        
        var update = _.throttle(function(e){

            if(_this.ignoreIdle){
                return;
            }
                        
            _this.clickedMarker = null;

            _this.page.update(_this.name);

        },1000);

        google.maps.event.addListener(this.foldMap.map, 'idle', update);
    };

    this.update = function(markers) {
        
        var current_level = markers[0].level;

        jQuery.each(this.foldMap.markers, function(post_id, marker){

            if(marker.level != current_level){

                _this.foldMap.deleteMarker(post_id);
            }
        });

        for ( var i=0, len=markers.length ; i<len ; i++ ) {

            if(markers[i].count > 1) {
                markers[i].icon = '/marker-image/?count='+markers[i].count;
            }

            this.foldMap.addMarker(
                    markers[i].lat, 
                    markers[i].lng, 
                    markers[i].post_id, 
                    markers[i].html, 
                    markers[i],
                    [ markers[i].count > 1 ? this.markerGeohashZoom : this.markerInfoWindow ]
            );
        }
    };
    
    this.getEndpointParams = function() {

        var params = {};

        if(this.clickedMarker){
            params = {
                geohash: this.clickedMarker.post_id
            }
        }
        else {
            params = {
                bounds: this.foldMap.map.getBounds().toUrlValue(),
                zoom: this.foldMap.map.getZoom()
            };
        }
                        
        console.log('zoom' + this.foldMap.map.getZoom());

        return params;
    };

    /**
     * Added to google.maps.event.addListener, see the addMarker call in this.update
     */
    this.markerGeohashZoom = function() {
        
        _this.clickedMarker = this;
        
        _this.page.update(_this.name, function(){
            _this.ignoreIdle = true;
            _this.foldMap.centerMap();

            _.delay(function(){
                _this.ignoreIdle = false;
            },1000);

            _this.clickedMarker = null;
        });
    };

    /**
     * Added to google.maps.event.addListener, see the addMarker call in this.update
     */
    this.markerInfoWindow = function() {
        var marker = this;
        jQuery.get('/marker-window/',{geohash: this.post_id}, function(html){
            console.log(html);
            console.log(marker.post_id);
            _this.infowindow.setContent(html);
            _this.infowindow.open( _this.foldMap.map, marker );
        });
    };
}
