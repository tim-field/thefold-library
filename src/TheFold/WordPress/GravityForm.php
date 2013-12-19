<?php
namespace TheFold\WordPress;

class GravityForm
{
    protected $form;

    public function __construct($form, $entries=null){
        $this->form = $form;
        $this->entries = $entries;
    }

    public function getValue($cssClassOrId){

        $value = null;

        if($id = is_numeric($cssClassOrId) ? $cssClassOrId : $this->getFieldId($cssClassOrId)) {
            
            if (isset($_FILES['input_'.$id]) && $_FILES['input_'.$id]['error'] == UPLOAD_ERR_OK) {
                $value = isset($this->entries[$id]) ? $this->entries[$id] : $_FILES['input_'.$id]['tmp_name'];
            } else if (isset($_POST['input_'.$id])) {
                $value = isset($this->entries[$id]) ? $this->entries[$id] : $_POST['input_'.$id];
            } else if (isset($_POST['input_'.$id.'_1'])) {
                $i = 1;
                $value = [];
                while(isset($_POST['input_'.$id.'_'.$i])) {
                    $value[] = $_POST['input_'.$id.'_'.$i];
                    $i ++;
                }
            }
        }

        return $value;
    }

    public function getFieldId($name) {

        $value = null;

        foreach ($this->form['fields'] as $field) {

            if ( $field['cssClass'] == $name || 
                $field['inputName'] == $name || 
                $field['adminLabel'] == $name) {

                $value = $field['id'];
                break;
            }
        }

        return $value;
    }

}
