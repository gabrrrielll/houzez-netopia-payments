<?php
namespace Netopia\Payment2;

class Start extends BaseHttpClient {
    public $posSignature;
    public $notifyUrl;
    public $redirectUrl;
    public $apiKey;
    public $isLive;
    public $backUrl;

    protected function sendRequest($jsonStr) {
        if(!isset($this->apiKey) || is_null($this->apiKey)) {
            throw new \Exception('INVALID_APIKEY');
        }
        return BaseHttpClient::sendHttpRequest('payment/card/start', $jsonStr, 'POST');
    }
}