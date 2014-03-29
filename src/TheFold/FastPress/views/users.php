<?php 
global $wp_roles;
$roles = $wp_roles->get_names();

asort($roles);
?>
<fieldset>
    <ul>
        <?php foreach ($roles as $name => $label):?>
            <li>
                <label for="role_<?=$name?>">
                    <input type="checkbox" name="<?=$setting_group?>[<?=$field?>][]" value="<?=$name?>" <?= checked(1, in_array($name, (array) $user_roles), false) ?> id="role_<?=$name?>"/> 
                    <?= $label ?>
                </label>
            </li>
        <?php endforeach; ?>
    </ul> 
</fieldset>
