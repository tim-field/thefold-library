function TheFoldPage(config) {

    var config = config || {};

    this.components = {};
    this.endpoint = null;
    this.$loadingEl = null;
    this.trigger = '';
    var _this = this;

    this.addComponent = function(component, name) {

        var name = name || component.name;

        this.components[name]=component;
    };

    this.init = function() {

        var name;

        if(typeof config === 'string'){
            this.endpoint = config;
        }
        else if(typeof conf === 'object'){
            _.extend(this,config);
        }

        for(name in this.components){
            this.components[name].init(this);
        }

        if(config.loadingEl){
            this.$loadingEl = jQuery(config.loadingEl);
        }
    };

    this.update = function(trigger, callback){

        this.trigger = trigger;
        var loadingTimeout = null;

        if(this.$loadingEl){
            loadingTimeout = setTimeout(function(){
                _this.$loadingEl.addClass('is-loading');
            },600);
        }

        //Fetch the query string params from each of our components
        var params = this.getEndpointParams();

        jQuery.getJSON(this.ajaxurl, params).done(function(json){

            var name;

            for(name in _this.components){

                var component = _this.components[name];

                if(typeof component.update === 'function') {

                    var data = json[name] ? json[name] : [];

                    if (data) {
                        component.update(data);
                    }
                }
            }
            
            if(typeof callback === 'function'){
                callback();
            }

            if(_this.$loadingEl){
                
                if(loadingTimeout){
                    clearTimeout(loadingTimeout);
                }

                _this.$loadingEl.removeClass('is-loading');
            }
            
            _this.trigger = '';

        });
    };

    this.getEndpointParams = function(){

        var params = {action: this.endpoint}, name;

        for(name in this.components){

            if(typeof this.components[name].getEndpointParams === 'function'){

                if(typeof params[name] === 'undefined'){
                    params[name] = this.components[name].getEndpointParams();
                } else {
                    jQuery.extend(params[name], this.components[name].getEndpointParams());
                }
            }
        }

        return params;
    };
};
