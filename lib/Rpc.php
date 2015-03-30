<?php

class Oklink_Rpc
{
    private $_requestor;
    private $authentication;

    public function __construct($requestor, $authentication)
    {
        $this->_requestor = $requestor;
        $this->_authentication = $authentication;
    }

    public function request($method, $url, $params)
    {
        $url = OklinkBase::API_BASE . $url;
        // Initialize CURL
        $curl = curl_init();
        $curlOpts = array();
        // HTTP method
        $method = strtolower($method);
        if ($method == 'get') {
            $curlOpts[CURLOPT_HTTPGET] = 1;
        } else if ($method == 'post') {
            $curlOpts[CURLOPT_POST] = 1;
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
        } else if ($method == 'delete') {
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "DELETE";
        } else if ($method == 'put') {
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "PUT";
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
        }

        // Headers
        $headers = array('User-Agent: OklinkPHP/v1');

        $auth = $this->_authentication->getData();

        // Get the authentication class and parse its payload into the HTTP header.
        $authenticationClass = get_class($this->_authentication);
        switch ($authenticationClass) {
            case 'Oklink_OAuthAuthentication':
                // Use OAuth
                if(time() > $auth->tokens["expire_time"]) {
                    throw new Oklink_TokensExpiredException("The OAuth tokens are expired. Use refreshTokens to refresh them");
                }

                $headers[] = 'Authorization: Bearer ' . $auth->tokens["access_token"];
                break;

            case 'Oklink_ApiKeyAuthentication':
                // Use HMAC API key
                $microseconds = sprintf('%0.0f',round(microtime(true) * 1000000));

                $dataToHash =  $microseconds . $url;
                if (array_key_exists(CURLOPT_POSTFIELDS, $curlOpts)) {
                    $dataToHash .= $curlOpts[CURLOPT_POSTFIELDS];
                }
                $signature = hash_hmac("sha256", $dataToHash, $auth->apiKeySecret);

                $headers[] = "KEY: {$auth->apiKey}";
                $headers[] = "SIGNATURE: $signature";
                $headers[] = "NONCE: $microseconds";
                break;

            default:
                throw new Oklink_ApiException("Invalid authentication mechanism");
                break;
        }
        // Create query string
        if ($params!=null && $method == 'get') {
            $queryString = http_build_query($params);
            $url .= "?" . $queryString;
        }
        // CURL options
        $curlOpts[CURLOPT_URL] = substr(OklinkBase::WEB_BASE,0,-1).$url;
        $curlOpts[CURLOPT_HTTPHEADER] = $headers;

        // $curlOpts[CURLOPT_CAINFO] = dirname(__FILE__) . '/ca-oklink.crt';
        $curlOpts[CURLOPT_RETURNTRANSFER] = true;
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = FALSE;
        $curlOpts[CURLOPT_SSL_VERIFYHOST]=  FALSE;
        // Do request
        curl_setopt_array($curl, $curlOpts);

        $response = $this->_requestor->doCurlRequest($curl);
        // Decode response
        try {
            $body = $response['body'];
            $json = json_decode($body);
        } catch (Exception $e) {
           echo "Invalid response body".$response['statusCode'].$response['body'];
        }
        if($json === null) {
           echo "Invalid response body".$response['statusCode'].$response['body'];
        }
        if(isset($json->error)) {
            throw new Oklink_ApiException($json->error, $response['statusCode'], $response['body']);
        } else if(isset($json->errors)) {
            throw new Oklink_ApiException(implode($json->errors, ', '), $response['statusCode'], $response['body']);
        }

        return $json;
    }
}
