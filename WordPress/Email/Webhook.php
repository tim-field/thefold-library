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
    }

    public function install()
    {
        $this->getContectextIO()->addWebhook($this->getAccountID(), array(
            'callback_url' => site_url(static::CALLBACK_URL),
            'failure_notif_url' => site_url(static::CALLBACK_FAIL_URL),
            'filter_to' => $this->EMAIL,
            'include_body' => 1,
            'body_type' => 'text/html'
        ));
    }

    public function addWebhook($onEmailCallback, $onErrorCallback)
    {
        $secret = $this->SECRET; // until php5.4

        WordPress::init_url_access(array(
            static::CALLBACK_URL => function() use ($onEmailCallback, $secret) {

                $body = json_decode(file_get_contents('php://input'), true);
                
                if ($body['signature'] == hash_hmac('sha256', $body['timestamp'].$body['token'], $secret)){
                    call_user_func($onEmailCallback, $body['message_data'], $body); 
                } else {
                    WordPress::log('Webhook auth failed');
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

            if(!$r){
                throw new \Exception(print_r($this->getContectextIO()->getLastResponse(), true));
            }

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
