function LionComponentResultList(config) {

    this.$el = null;
    this.name = 'ResultList';

    this.init = function(page) {

        this.$el = jQuery(config.selector);
    };

    this.update = function(html){

        this.$el.html(jQuery(html).unwrap().html());
    }

    this.getEndpointParams = function() {

        return {};
    };
}
