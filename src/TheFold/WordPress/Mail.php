<?php

namespace TheFold\Wordpress;

use TheFold\WordPress;

class Mail
{
    static function send($to,$subject,$template,$variables=[],$headers=[],$layout=null)
    {
        $headers = array_merge(['content-type: text/html'],$headers);

        $message = Wordpress::render_template($template,null,$variables,true);

        if($layout){
            
            $message = Wordpress::render_template($layout,
                null,
                ['email_content'=>$message],
               true);
        }

        return wp_mail($to, $subject, $message, $headers);
    }
}
