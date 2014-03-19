<div class="wrap">
    <h2>Import Posts Here</h2>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div class="postbox-container">
                <div class="postbox" id="posttypes">
                    <div class="handlediv" title="Click to toggle"><br/></div>
                    <h3 class="hndle"><span>Post Types</span></h3>
                    <div class="inside">
                        <ul>
                        <?php foreach (get_post_types(['public'=>true],'objects') as $type):?>
                            <li>
                                <label for="<?=$type->name?>">
                                    <input type="checkbox" value="<?=$type->name?>" id="<?=$type->name?>"/> 
                                <label> <?=$type->labels->name?> <a href='#'>+</a>
                                <ul>
                                    <?php foreach(WordPress::get_custom_fields($type->name) as $field):?>
                                    <li>
                                        <label for="customfield_<?=$field?>">
                                            <input type="checkbox" id="customfield_<?=$field?>" name="customfields[]" value="<?=$field?>">
                                            <?=$field?>
                                        </label>
                                    </li>
                                    <?php endforeach;?>
                                </ul>
                            </li>
                        <?php endforeach;?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
