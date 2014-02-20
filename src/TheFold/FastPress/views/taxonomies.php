<fieldset>
    <ul>
            <?php foreach (get_taxonomies([],'object') as $taxonomie): 
?>
                <li>
                    <label for="taxonomie_<?=$taxonomie->name?>">
                        <input type="checkbox" name="<?=$setting_group?>[<?=$field?>][]" value="<?=$taxonomie->name?>" <?= checked(1, in_array($taxonomie->name, (array) $taxonomies), false) ?> id="taxonomie_<?=$taxonomie->name?>"/> 
                        <?=$type->labels->singular_name ?> <?= $taxonomie->labels->name ?> (<?=$taxonomie->name?>)
                    </label>
                </li>
        <?php endforeach; ?>
    </ul> 
</fieldset>
