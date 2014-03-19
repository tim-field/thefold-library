<fieldset>
    <ul>
        <?php foreach ( get_post_stati(null,'objects') as $type):?>
            <li>
                <label for="status_<?=$type->name?>">
                    <input type="checkbox" name="<?=$setting_group?>[<?=$field?>][]" value="<?=$type->name?>" <?= checked(1, in_array($type->name, (array) $post_status), false) ?> id="status_<?=$type->name?>"/> 
                    <?= $type->label ?>
                </label>
                

            </li>
        <?php endforeach; ?>
    </ul> 
</fieldset>
