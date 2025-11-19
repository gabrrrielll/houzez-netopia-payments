<?php
namespace Netopia\Payment2;

class VerifyAuth extends Request {
    // public $paRes;
    public $postData;

    public function setVerifyAuth() {
        $paymentCartVerifyAuthParam = [
            "authenticationToken" => (string) $this->authenticationToken,
            "ntpID" => (string) $this->ntpID,
            "formData" => $this->postData
        ];

        return (json_encode($paymentCartVerifyAuthParam));
    }

    // Send request to /payment/card/verify-auth 
    public function sendRequestVerifyAuth($jsonStr) {
        return BaseHttpClient::sendHttpRequest('payment/card/verify-auth', $jsonStr, 'POST');
    }
}