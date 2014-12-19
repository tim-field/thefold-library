function TheFoldPageComponentMap(config) {

    this.name = 'Map';
    this.foldMap = null;
    this.selector = config.selector;
    this.userEvent = false;
    
    this.init = function(page) {

        var _this = this;

        this.foldMap = new theFoldGoogleMap(config);
        this.foldMap.renderMap( jQuery(this.selector) );
        this.foldMap.markerClusterer.repaint()
        
        var update = _.throttle(function(e){

            if(!_this.userEvent){
                return;
            }

            page.update(_this.name);

        },1000);

        google.maps.event.addListener(this.foldMap.map, 'idle', update);
        google.maps.event.addListener(this.foldMap.map, 'mouseover', function(){
            _this.userEvent = true;
        });
        google.maps.event.addListener(this.foldMap.map, 'mouseout', function(){
            _this.userEvent = false;
        });
        
        this.foldMap.initLocationButton(function(button){

            google.maps.event.addDomListener(button, 'mouseover', function(){
                _this.userEvent = true;
            });
            
            google.maps.event.addDomListener(button, 'mouseout', function(){
                _this.userEvent = false;
            });
        }); 
    };

    this.update = function(markers) {
        
        if(config.cluster){
            return;
        }

        var existing_post_ids = _.keys(this.foldMap.markers).map(function( num ){ return parseInt( num ) }), new_post_ids = markers.map(function(marker){
            return parseInt(marker.post_id);
        });

        var to_delete = _.difference(existing_post_ids, new_post_ids); 

        for ( var i=0, len=markers.length ; i<len ; i++ ) {

            var marker = markers[i];

            this.foldMap.addMarker(marker.lat, marker.lng, marker.post_id, marker.html);
        }


        for (var i=0; i<to_delete.length; i++) {
            this.foldMap.deleteMarker(to_delete[i]);
        }

        if(!this.userEvent){
            // Only center the map if the update wasn't done by us dragging it
            this.foldMap.centerMap();
        }
        
        /*if(config.cluster) {
            this.foldMap.markerClusterer.repaint()
        }*/
    };
    
    this.getEndpointParams = function() {

        var params = {};

        if(this.userEvent){
            params = {
                bounds: this.foldMap.map.getBounds().toUrlValue()
            };
        }

        return params;
    };
}
