function TheFoldPage(attributes) {

    var attrs = attributes || {};

    this.components = {};
    this.endpoint = null;
    this.trigger = '';

    this.addComponent = function(component, name) {

        var name = name || component.name;

        this.components[name]=component;
    };

    this.init = function() {

        var name;

        if(typeof attrs === 'string'){
            this.endpoint = attrs;
        }
        else if(typeof attrs === 'object'){
            _.extend(this,attrs);
        }

        for(name in this.components){
            this.components[name].init(this);
        }
    };

    this.update = function(trigger, callback){

        this.trigger = trigger;

        //Fetch the query string params from each of our components
        var params = this.getEndpointParams();

        var _this = this;

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
