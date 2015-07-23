crawler
=======

Web crawler using php with curl extension.
This class is written by myself as I am working in Wochacha Inc.


MailProcess Demo:
=============

    require_once 'MailProcess.php';

    $email = new MailProcess('username', 'password', 'pop.exmail.qq.com');

    $total = $email->getTotalMails();

    for($msgid = $total; $msgid > 0; $msgid--) {
        $header = $email->getHeaders($msgid);       //Get the headers
        $chain = $email->getBody( $msgid );
        $email->getAttach($msgid);                  //Get && download the atthments
    }
