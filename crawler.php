<?php

/** * ****************************************************************************
 *                         Crawler  Class
 * ******************************************************************************
 *      Author:     Ross Xu
 *      Email:      xuli13366@gmail.com
 *      Website:    http://www.jackpakistan.com
 *
 *      File:       crawler.php
 *      Version:    1.0.0
 *      Copyright:  (c) 2014
 *      @ChangeHistory:
 *      1. 2014-03-20, add the timeout strategy to avoid the network hangup to improve the performance.
 *
 * ******************************************************************************
 *  VERION HISTORY:
 *
 *      v1.0.0 [18/02/2014] - Initial Version
 *
 * ******************************************************************************
 *  DESCRIPTION:
 *
 *      This class aids in Web content extract and analyze the information, finally
 *      return the result to the caller. It is based on the curl extension of php
 *      and encapsulate it according to our own need. Then the data has been filtered
 *      by Regular Expression as well as the formula of the requirements.
 *
 *      A screenshot and sample script can be found on my website.
 *
 * ******************************************************************************
 */

class crawler{

    /**
     * DebugLevel:
     *      3: display raw html content;
     *      2: display parse detail;
     *      1: brief info (url , failurl , total numbers, final result array.)
     *      0: no debug info..
     */
    public $debugLevel = 0;
    protected $curlHandler;
    protected $options;
    protected $status = 0;
    protected $match_offset = 0;

    public function __construct($conf = array()) {
        $this->open();

        if (!empty($conf)) {
            $this->setOption($conf);
        }
    }

    public function __destruct() {
        //close curl resource to free up system reources
        $this->close();
    }

    public function open() {
        //create curl reource
        $this->curlHandler = curl_init();
        $this->initOption();
    }

    public function close() {
        curl_close($this->curlHandler);
    }

    //initialize the curl options
    public function initOption() {
        $this->options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120
        );
    }

    /**
     * function fetch to obtain the web page and return it
     * @return webpage
     */
    public function fetch($url, $data = '', $method = "GET") {
        $data = is_array($data) ? http_build_query($data) : $data;
        if ($method == "POST") {
            $this->options[CURLOPT_POSTFIELDS] = $data;
            $this->options[CURLOPT_POST] = true;
            $this->options[CURLOPT_URL] = $url;
        } else {
            $this->options[CURLOPT_POST] = false;
            $this->options[CURLOPT_URL] = $url;
        }
        curl_setopt_array($this->curlHandler, $this->options);
        $webpage = curl_exec($this->curlHandler);
        if (curl_errno($this->curlHandler)) {
            return false;
        } else {
            return $webpage;
        }
    }

    /**
     * get the response code of the web page
     * @return integer
     */
    public function getHttpStatus() {
        return $this->status;
    }

    /**
     * parse the options passed by the user to curl options
     * @param type $options
     */
    public function setOption($options = array()) {
        foreach ($options as $key => $value) {
            switch ($key) {
                case CURLOPT_COOKIE:
                    $this->options[CURLOPT_COOKIE] = is_array($value) ? http_build_query($value) : $value;
                    break;
                case 'cookieFileLocation':
                    $this->options[CURLOPT_COOKIEJAR] = $value;
                    $this->options[CURLOPT_COOKIEFILE] = $value;
                    break;
                case 'ssl':
                    if ($value == true) {
                        $this->options[CURLOPT_SSL_VERIFYHOST] = false;
                        $this->options[CURLOPT_SSL_VERIFYPEER] = false;
                    }
                    break;
                default :
                    $this->options[$key] = $value;
            }
        }
    }

    /**
     * The getRegexpInfo function aims to extract the required items from
     * the target using regular expression.
     *
     * @param string $pattern
     * @param string $source
     * @param integer $match_offset
     * @return mixed|boolean
     */
    function getRegexpInfo($pattern, $source, $match_offset = NULL) {
        $match = array();
        if (is_null($match_offset)) {
            $ret = preg_match($pattern, $source, $matches);
        } else {
            $ret = preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE, $match_offset);
        }
        if ($ret == 1) {
            if (2 == count($matches)) {
                $result = $matches[1];
            } elseif (count($matches) > 2) {
                foreach ($matches as $key => $value) {
                    if ($key >= 1) {
                        $match[] = $value;
                    }
                }
                $result = $match;
            } else {
                $result = $matches[0];
            }
        } else {
            $result = '';
        }
        return $result;
    }


    //download files from file url using curl.
    function downloadFile($targetFolder, $targetFile, $url) {
        $fp = fopen($targetFolder . '/' . $targetFile, 'w+');
        curl_setopt_array(
                $this->curlHandler, array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
                )
        );
        curl_exec($this->curlHandler);
        fclose($fp);

        // must reset cURL file handle back to stdout
        curl_setopt($this->curlHandler, CURLOPT_FILE, fopen('php://stdout', 'w'));
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curlHandler, CURLOPT_URL, 'http://localhost/');
        curl_exec($this->curlHandler);
    }

    /**
     * function to convert the charset of the particular string
     * @param string $charset
     * @param string $str
     * @return the converted string
     */
    function convert($charset, $str) {
        $charset = strtoupper($charset);
        return iconv($charset, "UTF-8//IGNORE", $str);
    }

    /**
     * Get the host of the url
     * @param string $url
     * @return the assembled host
     */
    function parseUrl($url) {
        $block = parse_url($url);
        return $block['scheme'] . "://" . $block['host'];
    }
}
