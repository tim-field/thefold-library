<?php
namespace TheFold;

class Gmail {

    protected $HOSTNAME;
    protected $USERNAME;
    protected $PASSWORD;

    public function __construct ($HOSTNAME, $USERNAME, $PASSWORD) {

        $this->HOSTNAME = $HOSTNAME;
        $this->USERNAME = $USERNAME;
        $this->PASSWORD = $PASSWORD;
    }
    
    public function search ($criteria) {

        $stream = $this->getStream ();   
        $emails = imap_search ($stream, $criteria, SE_UID);

        if ($emails) {

            $emails = array_map ( function ($email) {

                return array(
                    'uid'=> $email->uid,
                    'subject'=> $email->subject,
                    'from'=> $email->from,
                    'date'=> $email->date,
                    'text'=> function () use ($steam, $email) {
                        return imap_fetchbody ($stream, $email->uid, FT_UID | FT_PEEK );
                    }
                );

            }, imap_fetch_overview ($stream, implode(',',$emails), FT_UID)
            );
        }

        return $emails;
    }

    protected function getStream () {

        static $stream;

        if (!$stream) {

            $stream = imap_open($this->HOSTNAME, $this->USERNAME, $this->PASSWORD, OP_READONLY);

            if (!$stream) {
                throw new \Exception(print_r(imap_errors(),true));
            }
        }

        return $stream;
    }
}
