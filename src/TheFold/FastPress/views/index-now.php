<fieldset>
    <button id="thefold_solr_index" type="button">Update Index</button><br/>
    <button id="thefold_solr_user_index" type="button">Update User Index</button><br/>
    <button id="thefold_solr_delete_all_users" type="button">Delete Users</button><br/>
    <button id="thefold_solr_delete_all" type="button">Delete All</button>
</fieldset>

<script type="text/javascript" >
jQuery(document).ready(function($) {

    $('#thefold_solr_index').click(function() {
       index(0);
    });
   
    $('#thefold_solr_user_index').click(function() {
       index_users(0);
    });

    function index(page){

        var data = {
            action: '<?=TheFold\FastPress\Admin::AJAX_INDEX?>',
            page: page
        };

        $.post(ajaxurl, data, function(response) {

            if (response.done) {
                
                $('#thefold_solr_index').text('Post Index Complete');

                index_users(0);

            } else if (response.page) {
                index(response.page);
                $('#thefold_solr_index').text('Indexing Posts: '+response.percent+'%');
            }
        });
    }
    
    function index_users(page){

        var data = {
            action: '<?=TheFold\FastPress\Admin::AJAX_INDEX_USERS?>',
            page: page
        };

        $.post(ajaxurl, data, function(response) {

            if (response.done) {
                
                $('#thefold_solr_user_index').text('Index Complete');

            } else if (response.page) {
                index_users(response.page);
                $('#thefold_solr_user_index').text('Indexing Users: '+response.percent+'%');
            }
        });
    }
    
    $('#thefold_solr_delete_all').click(function() {
        delete_all($(this)); 
    });
   
    $('#thefold_solr_delete_all_users').click(function() {
        delete_all($(this), 'wp_class:WP_User'); 
    });

    function delete_all(button, query)
    {
        var data = {
            action: '<?=TheFold\FastPress\Admin::AJAX_DELETE_ALL?>',
            query: query
        }

        if(confirm('Delete users in the index ?')) {

            $.post(ajaxurl, data, function(response) {
                button.text('Deleted All');
            });
        }
    }
});
</script>
