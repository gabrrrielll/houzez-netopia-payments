<?php
namespace Netopia\Payment2;

class BaseHttpClient {
    protected $apiKey;
    protected $isLive;
    protected $baseUrl;

    protected function initializeBaseUrl() {
        $this->baseUrl = $this->isLive ? 'https://secure.netopia-payments.com/api/' : 'https://secure-sandbox.netopia-payments.com/';
    }

    protected function sendHttpRequest($endpoint, $payload, $method = 'POST') {
        
        $this->initializeBaseUrl();
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);

        $headers = [
            'Authorization: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!curl_errno($ch)) {
            $response = $this->handleResponse($httpCode, $result, $endpoint);
        } else {
            $response = [
                'status' => 0,
                'code' => 0,
                'message' => "Connection error occurred "." | ".$endpoint,
                'data' => null
            ];
        }

        curl_close($ch);
        return json_encode($response, JSON_FORCE_OBJECT);
    }

    protected function handleResponse($httpCode, $result, $endpoint) {
        $responseData = json_decode($result);
        
        switch ($httpCode) {
            case 200:
                return [
                    'status' => 1,
                    'code' => $httpCode,
                    'message' => "Request successful "." | ".$endpoint,
                    'data' => $responseData
                ];
            case 400:
                return [
                    'status' => 0,
                    'code' => $httpCode,
                    'message' => "Bad Request "." | ".$endpoint,
                    'data' => $responseData
                ];
            case 401:
                return [
                    'status' => 0,
                    'code' => $httpCode,
                    'message' => "Authorization required "." | ".$endpoint,
                    'data' => $responseData
                ];
            case 404:
                return [
                    'status' => 0,
                    'code' => $httpCode,
                    'message' => "Endpoint not found "." | ".$endpoint,
                    'data' => $responseData
                ];
            default:
                return [
                    'status' => 0,
                    'code' => $httpCode,
                    'message' => "Unexpected error occurred "." | ".$endpoint,
                    'data' => $responseData
                ];
        }
    }
}