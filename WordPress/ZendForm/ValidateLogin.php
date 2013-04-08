<?php
namespace TheFold\Wordpress\ZendForm;

class ValidateLogin extends \Zend_Validate_Abstract
{
    const USER_EXISTS='';
    
    protected $_messageTemplates = array(
        self::USER_EXISTS=>'Whoops that didn\'t work. Try again ?'
    );
    
    public function isValid($value, $context=null)
    {
        $this->_setValue($value);
        
        if (!\username_exists($value))
            return true;

        $this->_error(self::USER_EXISTS); 
        return false;
    }
}
