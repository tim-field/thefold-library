function TheFoldComponentReplace(config) {

    this.$el = null;
    this.name = config.name;
    this.$paging = null;
    this.page_number = null;
    _this = this;

    this.init = function(page) {

        this.$el = jQuery(config.selector);
        this.page = page;
        
        if(config.paging){
            
            jQuery(document).on('click',config.paging,function(e){
                e.preventDefault();
                
                var $a = jQuery(this);
                
                var m = $a.attr('href').match(/page=([0-9]+)/);
                _this.page_number = m[1];
                
                _this.page.update();
            });
        }
    };

    this.update = function(html){

        this.$el.html(jQuery(html).unwrap().html());
        
    }

    this.getEndpointParams = function() {

        var params = {};

        if(this.page_number){
            params.page = this.page_number;
            this.page_number = null;
        }

        return params;
    };
}
