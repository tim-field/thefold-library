function TheFoldSpatialClusterFacet(config) {

    this.name = config.name;
    this.foldMap = null;
    this.selector = config.selector;
    this.ignoreIdle = false;
    this.clickedMarker = null;
    this.page = null;
    this.infowindow = new google.maps.InfoWindow();
    var _this = this;
    
    this.init = function(page) {

        if(config.styles){
            config.styles = window[config.styles];
        }

        this.page = page;
        this.foldMap = new theFoldGoogleMap(config);
        this.foldMap.renderMap( jQuery(this.selector) );
        
        var update = _.debounce(function(e){

            if(_this.ignoreIdle || _this.foldMap.centeringMap){
                return;
            }

            //console.log(_this.foldMap.map.getZoom());
                        
            _this.clickedMarker = null;

            _this.page.update(_this.name);

        },300);

        google.maps.event.addListener(this.foldMap.map, 'idle', update);
    };

    this.update = function(markers) {
        
        var existing_ids = _.keys(this.foldMap.markers),
            new_ids = markers.map(function(marker){
                return marker.post_id;
            }),
            to_delete = _.difference(existing_ids, new_ids); 
        
        for (var i=0; i<to_delete.length; i++) {
            this.foldMap.deleteMarker(to_delete[i]);
        }

        for ( var i=0, len=markers.length ; i<len ; i++ ) {

            if(markers[i].count > 1) {
                markers[i].icon = config.markerCountUrl ? config.markerCountUrl.replace('{count}',markers[i].count) : '/marker-image/?count='+markers[i].count;
            }
            else if(config.singleMarkerIcon) {
                markers[i].icon = config.singleMarkerIcon;
            }

            this.foldMap.addMarker(
                    markers[i].lat, 
                    markers[i].lng, 
                    markers[i].post_id, 
                    markers[i].html, 
                    markers[i],
                    [ markers[i].count > 1 ? this.markerZoom : this.markerInfoWindow ]
            );
        }
    };
    
    this.getEndpointParams = function() {

        var params = {};

        if(this.clickedMarker){
            params = {
                geohash: this.clickedMarker.geohash
            }
        }
        else {
            params = {
                bounds: this.foldMap.map.getBounds().toUrlValue(),
                zoom: this.foldMap.map.getZoom()
            };
        }

        return params;
    };

    /**
     * Added to google.maps.event.addListener, see the addMarker call in this.update
     */
    this.markerZoom = function() {
        
        //console.log(this.post_id);
        //_this.foldMap.map.setCenter(this.);
        _this.foldMap.map.panTo(this.getPosition());
        _this.foldMap.map.setZoom(_this.foldMap.map.getZoom() + 2);
    };

    this.markerGeohash = function() {
        
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
        jQuery.get('/marker-window/',{geohash: this.geohash}, function(html){
            _this.infowindow.setContent(html);
            _this.infowindow.open( _this.foldMap.map, marker );
        });
    };
}
