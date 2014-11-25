<?php

class Oklink_SimpleApiKeyAuthentication extends Oklink_Authentication
{
    private $_apiKey;

    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    public function getData()
    {
        $data = new stdClass();
        $data->apiKey = $this->_apiKey;
        return $data;
    }
}