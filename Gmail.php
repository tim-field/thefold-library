<?php
namespace TheFold;

/**
$host = '{imap.gmail.com:993/imap/ssl}INBOX';
$user = 'tim@thefold.co.nz';            
$pass = 'xxxxxx';

$gm = new Gmail($host, $user, $pass);
$gm->search();
 */

class Gmail {

    protected $HOSTNAME;
    protected $USERNAME;
    protected $PASSWORD;

    public function __construct ($USERNAME, $PASSWORD,$HOSTNAME='{imap.gmail.com:993/imap/ssl}INBOX') {

        $this->HOSTNAME = $HOSTNAME;
        $this->USERNAME = $USERNAME;
        $this->PASSWORD = $PASSWORD;
    }
    
    public function search ($criteria='ALL', $fetchbody=true) {

        $stream = $this->getStream ();   
        $emails = imap_search ($stream, $criteria, SE_UID);

        if ($emails) {

            $fetched = imap_fetch_overview ($stream, implode(',',$emails), FT_UID);

            $emails = array_map ( function ($email) use ($stream, $fetchbody) {

                return array (
                    'uid'=> $email->uid,
                    'subject'=> $email->subject,
                    'from'=> $email->from,
                    'date'=> $email->date,
                    'body'=> $fetchbody ? imap_fetchbody ($stream, $email->uid, 2, FT_UID | FT_PEEK ) : ''
                );

            }, $fetched );
        }

        return $emails;
    }

    protected function getStream () {

        static $stream;

        if (!$stream) {

            $stream = imap_open(
                $this->HOSTNAME,
                $this->USERNAME,
                $this->PASSWORD,
                OP_READONLY);

            if (!$stream) {
                throw new \Exception(print_r(imap_errors(),true));
            }
        }

        return $stream;
    }
}
