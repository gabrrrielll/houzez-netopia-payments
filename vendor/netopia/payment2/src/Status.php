<?php 
namespace Netopia\Payment2;

class Status extends Start{
    public $ntpID;
    public $orderID;

    public function validateParam() {
        if(!isset($this->apiKey) || empty($this->apiKey)){
            throw new \Exception('apiKey is not defined');
        }
        if(!isset($this->posSignature) || empty($this->posSignature)){
            throw new \Exception('posSignature is not defined');
        }
        if(!isset($this->ntpID) || empty($this->ntpID)){
            throw new \Exception('ntpID is not defined');
        }
        if(!isset($this->orderID) || empty($this->orderID)){
            throw new \Exception('orderID is not defined');
        }
    }

    public function setStatus() {
        $paymentStatusParam = [
            "posID" => (string) $this->posSignature,
            "ntpID" => (string) $this->ntpID,
            "orderID" => (string) $this->orderID
        ];

        return (json_encode($paymentStatusParam));
    }

    // Send request to get payment status
    public function getStatus($jsonStr) {
        if(!isset($this->apiKey) || is_null($this->apiKey)) {
            throw new \Exception('INVALID_APIKEY');
        }

        return BaseHttpClient::sendHttpRequest('/operation/status', $jsonStr, 'POST');
    }
}