<?php
namespace TheFold\Wordpress\ZendForm;

class ValidateEmail extends \Zend_Validate_Abstract
{
    const EMAIL_EXISTS='';
    
    protected $_messageTemplates = array(
        self::EMAIL_EXISTS=>'This email address is already registered'
    );
    
    public function isValid($value, $context=null)
    {
        $this->_setValue($value);
     
        $user_id = \email_exists($value);
        $current_user = \wp_get_current_user();
     
        if (!$user_id || $current_user->ID == $user_id)
            return true;

        $this->_error(self::EMAIL_EXISTS); 
        return false;
    }
}
