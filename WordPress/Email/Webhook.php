<?php

namespace TheFold\WordPress\Email;
use TheFold\WordPress;

require_once 'ContextIO/class.contextio.php';

class Webhook
{
    const CALLBACK_URL = 'the-fold/context-io/';
    const CALLBACK_FAIL_URL = 'the-fold/context-io/error';

    protected $KEY;
    protected $SECRET;
    protected $EMAIL;
    protected $ACCOUNT_ID;

    protected $contextIO;

    
    public function __construct($key, $secret, $email){

        $this->KEY = $key; 
        $this->SECRET = $secret; 
        $this->EMAIL = $email; 

        $me = $this;//until 5.4 on server

    }

    public function install()
    {
        $this->getContectextIO()->addWebhook($this->getAccountID(), array(
            'callback_url' => static::CALLBACK_URL,
            'failure_notif_url' => static::CALLBACK_FAIL_URL,
            'filter_to' => $this->EMAIL,
            'include_body' => 1,
            'body_type' => 'text/html'
        ));
    }

    public function addWebhook($onEmailCallback, $onErrorCallback)
    {
        WordPress::init_url_access(array(

            static::CALLBACK_URL => function() use ($onEmailCallback){

                $body = json_decode(file_get_contents('php://input'), true);
                
                if ($body['signature'] == hash_hmac('sha256', $body['timestamp'].$body['token'], $this->SECRET)){
                    call_user_func($onEmailCallback, $body['message_data'], $body); 
                }
            },

            static::CALLBACK_FAIL_URL => function() use ($onErrorCallback){

                $body = json_decode(file_get_contents('php://input'), true);
                WordPress::report_error('Context IO Error:'. $body['data']);  
                
                call_user_func($onErrorCallback, $body); 
            }
        ));
    }
    
    protected function getContectextIO() {

        if (!$this->contextIO) {

            $this->contextIO = new \ContextIO($this->KEY,$this->SECRET);
        }
        return $this->contextIO;
    }

    protected function getAccountID() {

        if (!$this->ACCOUNT_ID){

            $r = $this->getContectextIO()->listAccounts();

            foreach ($r->getData() as $account) {

                if ( in_array($this->EMAIL, $account['email_addresses']) ) {
                    $this->ACCOUNT_ID = $account['id']; 
                    break;
                }
            }

            if(!$this->ACCOUNT_ID) {
                
                throw new \Exception('Can\'t find account id');
            }
        }

        return $this->ACCOUNT_ID;
    }
}
