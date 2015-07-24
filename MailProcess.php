<?php
/************************************************************************************
 *                       Mail Receive Class
 * **********************************************************************************
 *      Author:  Ross Xu
 *      Email:   xul@caicv.com
 *      File:    MailProcess.php
 *      Version: 1.0.0
 *      @ChangeHistory:
 *      1. 2015-07-24, 支持多类型附件存储，精简文件流传输与存储代码
 * *********************************************************************************
 *DESCRIPTION:
 *
 *      使用IMAP/POP3协议进行邮件接收相关操作，并支持附件存取与头部文件判断等操作。
 *
 */

class MailProcess {
    private $server = '';
    private $username = '';
    private $password = '';
    protected $mbox;
    private $connectString = '';

    function __construct ( $username, $password, $mailserver = 'localhost', $servertype = 'pop3', $port = '110', $ssl = false){
        $folder = 'INBOX';
        $this->connectString = sprintf('{%s:995/pop3/ssl/novalidate-cert}%s', $mailserver, $folder);
        $this->server        = $mailserver;
        $this->username      = $username;
        $this->password      = $password;

        $this->connect();
    }

    /**
     * 连接邮件服务器
     */
    public function connect(){
        $this->mbox = imap_open( $this->connectString, $this->username, $this->password ) or die(
            implode(', ', imap_errors())
        );
    }

    /**
     * 关闭与邮件服务器的连接
     * @return bool
     */
    public function close (){
        return imap_close( $this->mbox);
    }

    /**
     * 检查连接状态
     */
    public function checkConnect (){
        if( !imap_ping($this->mbox) ) {
            $this->close();
            $this->connect();
        }
        return true;
    }

    /**
     * 获取邮件数目
     * @return int
     */
    public function getTotalMails() {
        $count = imap_num_msg( $this->mbox );
        return $count;
    }

    /**
     * 获取头部信息
     * @param $msgid
     * @return array
     */
    public function getHeaders( $msgid ) {
        $this->checkConnect();

        $mailHeader = imap_header( $this->mbox, $msgid );
        $sender     = imap_mime_header_decode( $mailHeader->fromaddress );
        $subject    = imap_mime_header_decode($mailHeader->Subject);

        $stack = array(
            'sender' => $sender,
            'subject' => $subject
        );
        return $stack;
    }

    /**
     * 获取邮件正文部分
     * @param $msgid
     */
    public function getBody( $msgid ) {
        $this->checkConnect();

        $result = array(
            'text' => null,
            'html' => null,
            'attachments' => array()
        );

        $structure = imap_fetchstructure($this->mbox, $msgid, FT_UID);
        if( $structure ) {
            if( is_array($structure->parts)) {
                foreach ($structure->parts as $key => $part) {
                    if(($part->type >= 2) || ($part->ifdisposition == 1) && (strtoupper( $part->disposition ) == 'ATTACHMENT')) {
                        $file = null;
                        if($part->ifparameters == 1) {
                            $total_parameters = count($part->parameters);
                            for($i = 0;$i < $total_parameters; $i++) {
                                if(($part->parameters[$i]->attribute == 'NAME') || ($part->parameters[$i]->attribute == 'FILENAME')) {
                                    $file = imap_mime_header_decode($part->parameters[$i]->value);
                                    break;
                                }
                            }
                            if(is_null($file)) {
                                if($part->ifdparameters == 1) {
                                    $total_parameters = count($part->dparameters);
                                    for($i = 0; $i < $total_parameters; $i++) {
                                        if(($part->dparameters[$i]->attribute == 'NAME') || ($part->dparameters[$i]->attribute == 'FILENAME')) {
                                            $file = $part->dparameters[$i]->value;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        $result['attachments'][] = array(
                            'file' => $file,
                            'content' => imap_fetchbody($this->mbox, $msgid, ($key + 1), FT_UID)
                        );
                    } else{
                        if($part->subtype == 'PLAIN') {
                            $result['text'] = imap_fetchbody($this->mbox, $msgid, ($key + 1), FT_UID);
                        } elseif($part->subtype == 'HTML') {
                            foreach ($part->parts as $alternative_key => $alternative_part) {
                                if($alternative_part->subtype == 'PLAIN') {
                                    $result['text'] = imap_fetchbody($this->mbox, $msgid, ($key + 1).'.'. ($alternative_key + 1), FT_UID );
                                } elseif($alternative_part->subtype == 'HTML') {
                                    $result['html'] = imap_fetchbody($this->mbox, $msgid, ($key + 1).'.'.($alternative_key + 1), FT_UID);
                                }
                            }
                        }else{
                            $message = imap_fetchbody($this->mbox, $msgid, 2);
                            if($part->encoding == 3) {
                                $message = imap_base64($message);
                            } elseif($part->encoding == 1) {
                                $message = imap_8bit($message);
                            } else{
                                $message = imap_qprint($message);
                            }
                        }
                    }
                }
            }
//            $result['text'] = imap_qprint($result['text']);
//                    $result['html'] = imap_qprint(imap_8bit($result['html']));
        }

    }

    /**
     * 获取并存储附件
     * * @param $msgid
     * @param string $path
     */
    function getAttach( $msgid, $dirPath = ''){
        $this->checkConnect();
        $structure = imap_fetchstructure($this->mbox, $msgid);
        if(isset($structure->parts)) {
            foreach($structure->parts as $key => $part) {
                if($part->ifdparameters == 1) {
                    foreach($part->parameters as $param) {
                        if($param->attribute == 'NAME') {
                            $originName = $param->value;
                            $nameArr = imap_mime_header_decode($originName);
                            $nameEnc = $nameArr[0]->charset;
                            if(strtoupper($nameEnc) !== 'GBK' && $nameEnc != 'default') {
                                $filename = iconv($nameArr[0]->charset, 'GBK', $nameArr[0]->text);
                            }else{
                                $filename = $nameArr[0]->text;
                            }
                        }
                    }
                    $handle = fopen($dirPath .'/'.$filename ,'w');
                    stream_filter_append($handle,'convert.base64-decode',STREAM_FILTER_WRITE);
                    imap_savebody ($this->mbox, $handle, $msgid, $key + 1);
                    fclose($handle);
                }
            }
        }
    }

}