function TheFoldComponentFacet(config) {

    this.$el = null;
    this.name = config.name;

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

        return this.$el.val();
    };
}
