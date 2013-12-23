<?php
namespace TheFold\WordPress\Setting;

/**
$auth = new Setting\Section('auth','Authorisation', null, 'email_to_circ');
$filter = new Setting\Section('filter','Filter', function(){ echo 'Comma seperate words'; }, 'email_to_circ');

new Setting\Page('email_to_circ','Email to Circular Options',array(

    new Setting\Field( 'user','Gmail User Name', $auth ),
    new Setting\Field( 'password','Gmail Password', $auth ),
    new Setting\Field( 'exclude','Exclude Keywords', $filter),
));

*/
    
class Page
{
    function __construct($setting, $display_name, $setting_fields, $page_callback=null)
    {
        add_action('admin_menu', function() use($display_name, $setting, $page_callback ) {

            add_options_page($display_name, $display_name, 'manage_options', $setting, function() use ($page_callback, $setting, $display_name) {

                if($page_callback)
                    $page_callback();
                else { ?>
                    <div class="wrap">
                        <?php screen_icon(); ?>
                        <h2><?=$display_name?></h2>
                            <form method="post" action="options.php">
                            <?php
                                settings_fields($setting); 
                                do_settings_sections($setting);
                                submit_button();
                            ?>
                            </form>
                    </div>
<?php
                }
            });
        });

        add_action('admin_init', function() use($setting, $setting_fields, $display_name){

            register_setting( $setting, $setting );

            foreach($setting_fields as $field) {

                $field->get_section()->add();

                add_settings_field($field->name, $field->title, $field->get_display_callback($setting), $setting, $field->get_section()->get_id());
            }
        });

    }
}
