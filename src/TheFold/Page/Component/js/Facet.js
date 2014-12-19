function TheFoldComponentFacet(config) {

    this.$el = null;
    this.name = 'facet';

    this.init = function(page) {

        this.$el = jQuery(config.selector);

        this.$el.change(function(){

            page.update();
        });
    };

    this.update = function(html) {
        
        this.$el.html(jQuery(html).unwrap().html());
    }

    this.getEndpointParams = function() {

        var value, params = {};

        this.$el.each(function(){

            var select = jQuery(this);

            if (value = select.val()) {
                params[select.attr('name')] = value;
            }
        });

        return params;
    };
}
