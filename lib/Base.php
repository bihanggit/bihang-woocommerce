<?php

if(!function_exists('curl_init')) {
    throw new Exception('The Bihang client library requires the CURL PHP extension.');
}

require_once(dirname(__FILE__) . '/Exception.php');
require_once(dirname(__FILE__) . '/ApiException.php');
require_once(dirname(__FILE__) . '/ConnectionException.php');
require_once(dirname(__FILE__) . '/Requestor.php');
require_once(dirname(__FILE__) . '/Rpc.php');
require_once(dirname(__FILE__) . '/OAuth.php');
require_once(dirname(__FILE__) . '/TokensExpiredException.php');
require_once(dirname(__FILE__) . '/Authentication.php');
require_once(dirname(__FILE__) . '/SimpleApiKeyAuthentication.php');
require_once(dirname(__FILE__) . '/OAuthAuthentication.php');
require_once(dirname(__FILE__) . '/ApiKeyAuthentication.php');


class BihangBase
{
    const API_BASE = '/api/v1/';
    const WEB_BASE = 'https://www.bihang.com/';
    private $_rpc;
    private $_authentication;


    // This constructor is deprecated.
    public function __construct($authentication, $tokens=null, $apiKeySecret=null)
    {
        // First off, check for a legit authentication class type
        if (is_a($authentication, 'Bihang_Authentication')) {
            $this->_authentication = $authentication;
        } else {
            // Here, $authentication was not a valid authentication object, so
            // analyze the constructor parameters and return the correct object.
            // This should be considered deprecated, but it's here for backward compatibility.
            // In older versions of this library, the first parameter of this constructor
            // can be either an API key string or an OAuth object.
            if ($tokens !== null) {
                $this->_authentication = new Bihang_OAuthAuthentication($authentication, $tokens);
            } else if ($authentication !== null && is_string($authentication)) {
                $apiKey = $authentication;
                if ($apiKeySecret === null) {
                    // Simple API key
                    $this->_authentication = new Bihang_SimpleApiKeyAuthentication($apiKey);
                } else {
                    $this->_authentication = new Bihang_ApiKeyAuthentication($apiKey, $apiKeySecret);
                }
            } else {
                throw new Bihang_ApiException('Could not determine API authentication scheme');
            }
        }

        $this->_rpc = new Bihang_Rpc(new Bihang_Requestor(), $this->_authentication);
    }

    // Used for unit testing only
    public function setRequestor($requestor)
    {
        $this->_rpc = new Bihang_Rpc($requestor, $this->_authentication);
        return $this;
    }

    public function get($path, $params=array())
    {
        return $this->_rpc->request("GET", $path, $params);
    }

    public function post($path, $params=array())
    {
        return $this->_rpc->request("POST", $path, $params);
    }

    public function delete($path, $params=array())
    {
        return $this->_rpc->request("DELETE", $path, $params);
    }

    public function put($path, $params=array())
    {
        return $this->_rpc->request("PUT", $path, $params);
    }

    public function checkCallback(){
        $signature  = $_SERVER["HTTP_SIGNATURE"];
        $post       = file_get_contents('php://input');
        return $signature == hash_hmac("sha256", $post, $this->_authentication->getData()->apiKeySecret);  
    }

 }
