<fieldset>
    <ul>
        <?php foreach (get_post_types(['public'=>true],'objects') as $type):?>
            <li>
                <label for="type_<?=$type->name?>">
                    <input type="checkbox" name="<?=$setting_group?>[<?=$field?>][]" value="<?=$type->name?>" <?= checked(1, in_array($type->name, (array) $post_types), false) ?> id="type_<?=$type->name?>"/> 
                    <?= $type->labels->name ?>
                </label>
                

            </li>
        <?php endforeach; ?>
    </ul> 
</fieldset>
