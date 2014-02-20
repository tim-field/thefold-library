<?php
use \TheFold\WordPress;
?>
<fieldset>
    <ul>
        <?php foreach(WordPress::get_custom_fields($options['post_types']) as $custom_field):?>
        <li>
            <label for="customfield_<?=$custom_field?>">
                <input type="checkbox" id="customfield_<?=$custom_field?>" name="<?=$setting_group?>[<?=$field?>][]" value="<?=$custom_field?>" <?= checked(1, in_array($custom_field,(array)$custom_fields), false) ?>  >
                 <?=ucwords(str_replace('_',' ',$custom_field))?>
            </label>
        </li>
        <?php endforeach;?>
    </ul>
</fieldset>
