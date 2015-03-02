<div id="user_conditions">
    <div class="conditions">
    <?php
        $condition_count =  @$_REQUEST['condition_count'] ?: 1;

        for($i = 0; $i < $condition_count; $i++):
    ?>
    <fieldset class="user_query_ui" style="clear:both">
            <a href='#' class="remove_condition">x</a>
            <select name="user_query_field[]">
                <option value=""></option>
            <?php foreach(NZGirl\UserAdmin\get_filter_fields() as $field):?>
                <option value="<?=$field->get_name()?>" <?php selected( $field->get_name(), @$_REQUEST['user_query_field'][$i]); ?>><?=$field->get_label()?></option>
            <?php endforeach; ?>
            </select>

            <select name="user_query_condition[]">
            <?php foreach(NZGirl\UserAdmin\get_conditions() as $condition => $label):?>
                <option value="<?=$condition?>" <?php selected( $condition, @$_REQUEST['user_query_condition'][$i]); ?>><?=$label?></option>
            <?php endforeach; ?>
            </select>

            <input type="text" name="user_query_value[]" value="<?= @$_REQUEST['user_query_value'][$i] ?>"/>

            <span class="and">and<span>
    </fieldset>

    <?php endfor; ?>
    </div>

<input type="button" id="add_condition" class="button" value="and +"/>
<input type="submit" class="button" value="Search"/>
<input type="button" style="float:right" id="export-users-csv" class="button" value="Export CSV"/>
<input type="hidden" id="condition_count" name="condition_count" value="<?=$condition_count?>"/>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($){

        $('.search-box').replaceWith($('#user_conditions'));

        $('#add_condition').click(function(event){

            event.preventDefault();

            $('.user_query_ui:last').clone().appendTo('.conditions');
            
            clear_form_elements($('.user_query_ui:last'));
            
            $('#condition_count').val($('.user_query_ui').length);
        }); 
        
        $('#user_conditions').on('click','.remove_condition', function(event){

            event.preventDefault();
            
            if($('.user_query_ui').length > 1)
            {
                $(this).parent('.user_query_ui').remove();

                $('#condition_count').val($('.user_query_ui').length);
            }
        }); 

        $('#export-users-csv').click(function(event){

            event.preventDefault();

            form = $(this.form);

            var action = form.attr('action');

            form.attr('action','/user-admin-csv-export');

            //hack around advanced solr shit box plugin which hijacks any query with 's' in query string
            $('#user-search-input').attr('name','search');

            form.submit();
            
            $('#user-search-input').attr('name','s');

            //Reset form action    
            form.attr('action',action);
        });

        function clear_form_elements(ele) {

            ele.find(':input').each(function() {
                switch(this.type) {
                case 'password':
                case 'select-multiple':
                case 'select-one':
                case 'text':
                case 'textarea':
                    $(this).val('');
                    break;
                case 'checkbox':
                case 'radio':
                    this.checked = false;
                }
            });
        }
    });
</script>
