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

            $me = $this;

            $emails = array_map ( function ($email,$me) use ($stream, $fetchbody) {
                
                $html_section = null;

                if($fetchbody) { //todo only reads html

                    $raw_parts = imap_fetchstructure($stream, $email->uid, FT_UID);

                    $parts = $me->flattenParts($raw_parts->parts);

                    foreach($parts as $section => $part) {

                        if($part->subtype == 'HTML'){
                            $html_section = $section;
                            break;
                        }
                    }
                }

                return array (
                    'uid'=> $email->uid,
                    'subject'=> $email->subject,
                    'from'=> $email->from,
                    'date'=> $email->date,
                    'body'=> $fetchbody && $html_section ? imap_fetchbody ($stream, $email->uid, $html_section, FT_UID | FT_PEEK ) : ''
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

    protected function flattenParts($messageParts, $flattenedParts = array(), $prefix = '', $index = 1, $fullPrefix = true) {

        foreach($messageParts as $part) {
            $flattenedParts[$prefix.$index] = $part;
            if(isset($part->parts)) {
                if($part->type == 2) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix.$index.'.', 0, false);
                }
                elseif($fullPrefix) {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix.$index.'.');
                }
                else {
                    $flattenedParts = $this->flattenParts($part->parts, $flattenedParts, $prefix);
                }
                unset($flattenedParts[$prefix.$index]->parts);
            }
            $index++;
        }

        return $flattenedParts;

    }
}
