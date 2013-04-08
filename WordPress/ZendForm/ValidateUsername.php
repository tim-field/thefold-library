<?php
namespace TheFold\Wordpress\ZendForm;

class ValidateUsername extends \Zend_Validate_Abstract
{
    const USER_EXISTS='';
    
    protected $_messageTemplates = array(
        self::USER_EXISTS=>'This Username is already registered'
    );
    
    public function isValid($value, $context=null)
    {
        $this->_setValue($value);

        $user_id = \username_exists($value);
        $current_user = \wp_get_current_user();
        
        if (!$user_id || $user_id == $current_user->ID)
            return true;

        $this->_error(self::USER_EXISTS); 
        return false;
    }
}
