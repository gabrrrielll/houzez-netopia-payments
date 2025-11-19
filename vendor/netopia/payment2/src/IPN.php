<?php
namespace Netopia\Payment2;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;

class IPN extends Request{
   
    public $activeKey;
    public $posSignatureSet;
    public $hashMethod;
    public $alg;
    public $publicKeyStr;

    // Error code defination
    const E_VERIFICATION_FAILED_GENERAL			= 0x10000101;
    const E_VERIFICATION_FAILED_SIGNATURE		= 0x10000102;
    const E_VERIFICATION_FAILED_NBF_IAT			= 0x10000103;
    const E_VERIFICATION_FAILED_EXPIRED			= 0x10000104;
    const E_VERIFICATION_FAILED_AUDIENCE		= 0x10000105;
    const E_VERIFICATION_FAILED_TAINTED_PAYLOAD	= 0x10000106;
    const E_VERIFICATION_FAILED_PAYLOAD_FORMAT	= 0x10000107;

    const ERROR_TYPE_NONE 		= 0x00;
    const ERROR_TYPE_TEMPORARY 	= 0x01;
    const ERROR_TYPE_PERMANENT 	= 0x02;

    /**
     * Available statuses for the purchase class (prcStatus)
     */
    const STATUS_NEW 									= 1;	//0x01;         // new purchase status
    const STATUS_OPENED 								= 2;	//0x02;         // specific to Model_Purchase_Card purchases (after preauthorization) and Model_Purchase_Cash
    const STATUS_PAID 									= 3;	//0x03;         // capturate (card)
    const STATUS_CANCELED 								= 4;	//0x04;         // void
    const STATUS_CONFIRMED 								= 5;	//0x05;         // confirmed status (after IPN)
    const STATUS_PENDING 								= 6;	//0x06;         // pending status
    const STATUS_SCHEDULED 								= 7;	//0x07;         // scheduled status, specific to Model_Purchase_Sms_Online / Model_Purchase_Sms_Offline
    const STATUS_CREDIT 								= 8;	//0x08;         // specific status to a capture & refund state
    const STATUS_CHARGEBACK_INIT 						= 9;	//0x09;         // status specific to chargeback initialization
    const STATUS_CHARGEBACK_ACCEPT 						= 10;	//0x0a;         // status specific when chargeback has been accepted
    const STATUS_ERROR 									= 11;	//0x0b;         // error status
    const STATUS_DECLINED 								= 12;	//0x0c;         // declined status
    const STATUS_FRAUD 									= 13;	//0x0d;         // fraud status
    const STATUS_PENDING_AUTH 							= 14;	//0x0e;         // specific status to authorization pending, awaiting acceptance (verify)
    const STATUS_3D_AUTH 								= 15;	//0x0f;         // 3D authorized status, speficic to Model_Purchase_Card
    const STATUS_CHARGEBACK_REPRESENTMENT 				= 16;	//0x10;
    const STATUS_REVERSED 								= 17;	//0x11;         // reversed status
    const STATUS_PENDING_ANY 							= 18;	//0x12;         // dummy status
    const STATUS_PROGRAMMED_RECURRENT_PAYMENT 			= 19;	//0x13;         // specific to recurrent card purchases
    const STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT 	= 20;	//0x14;         // specific to cancelled recurrent card purchases
    const STATUS_TRIAL_PENDING							= 21;	//0x15;         // specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
    const STATUS_TRIAL									= 22;	//0x16;         // specific to Model_Purchase_Sms_Online; trial period has started
    const STATUS_EXPIRED								= 23;	//0x17;         // cancel a not payed purchase 


    /**
     * to Verify IPN
     * @return 
     *  - a Json
     */
    public function verifyIPN() {
        /**
        * Definition of default IPN response, 
        * Value will change if there is any problem
        */
        $outputData = array(
            'errorType'		=> self::ERROR_TYPE_NONE,
            'errorCode' 	=> null,
            'errorMessage'	=> ''
        );

        // fetch Verification-token from HTTP header.
        $verificationToken = $this->getSpecificHeader('Verification-Token');

        /**
        * Analising the verification token
        * Just to make sure if Type is JWT & Use right encoding/decoding algorithm 
        * Assign following var 
        *  - $headb64, 
        *  - $bodyb64,
        *  - $cryptob64
        */
        $tks = \explode('.', $verificationToken);
        if (\count($tks) != 3) {
            throw new \Exception('Wrong_Verification_Token');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        $jwtHeader = json_decode(base64_decode(\strtr($headb64, '-_', '+/')));
        
        if($jwtHeader->typ !== 'JWT') {
            throw new \Exception('Wrong_Token_Type'); 
        }

        /**
        * Check if publicKeyStr is defined
        */
        if(isset($this->publicKeyStr) && !is_null($this->publicKeyStr)){
            $publicKey = openssl_pkey_get_public($this->publicKeyStr);
            if($publicKey === false) {
                echo 'IPN__public key is not a valid public key' . PHP_EOL; 
                exit;
            }
        } else {
            echo "IPN__Public key missing" . PHP_EOL; 
            exit;
        }

        /**
        * Get raw data
        */
        $HTTP_RAW_POST_DATA = file_get_contents('php://input');


        /**
        * Verify the alg defined in header of JWT
        * Just in case we set the default algorithm
        * Default alg is RS512
        */
        if(!isset($this->alg) || $this->alg==null){
            throw new \Exception('IDS_Service_IpnController__INVALID_JWT_ALG');
        }
        $jwtAlgorithm = !is_null($jwtHeader->alg) ? $jwtHeader->alg : $this->alg ;

        
        try {
            JWT::$timestamp = time() * 1000; 
        
           /**
            * Decode from JWT v6 API
            */
            $objJwt = JWT::decode($verificationToken, new Key($publicKey, $jwtAlgorithm));
        
            if(strcmp($objJwt->iss, 'NETOPIA Payments') != 0)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_GENERAL');
                }
            
            /**
             * Check active posSignature 
             * Check if given posSignature is in set of signature too
             */
            if(empty($objJwt->aud)){
                throw new \Exception('IDS_Service_IpnController__JWT AUD is Empty');
            }

            /**
            * Check the type of JWT AUD, because the "POET" sent it in diffrent type
            */
            $actualJwtAud = null;
            $jwtAudType = gettype($objJwt->aud);
            switch ($jwtAudType) {
                case 'array':
                    $actualJwtAud = $objJwt->aud[0];
                    break;
                case 'string':
                    $actualJwtAud = $objJwt->aud;
                    break;
                default:
                    throw new \Exception('IDS_Service_IpnController__JWT AUD Type is unknown');
                    break;
            }

            if( $actualJwtAud != $this->activeKey){
                throw new \Exception('IDS_Service_IpnController__INVALID_SIGNATURE'.print_r($objJwt->aud, true).'__'.$this->activeKey);
            }
        
            if(!in_array($actualJwtAud, $this->posSignatureSet,true)) {
                throw new \Exception('IDS_Service_IpnController__INVALID_SIGNATURE_SET');
            }
            
            if(!isset($this->hashMethod) || $this->hashMethod==null){
                throw new \Exception('IDS_Service_IpnController__INVALID_HASH_METHOD');
            }
            
            /**
             * GET HTTP HEADER
             */
            $payload = $HTTP_RAW_POST_DATA;

            /**
             * Validate payload
             * Sutable hash method is SHA512 
             */
            $payloadHash = base64_encode(hash ($this->hashMethod, $payload, true ));

            /**
             * Check IPN data integrity
             */
            if(strcmp($payloadHash, $objJwt->sub) != 0)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_TAINTED_PAYLOAD');
                }
        
            try
                {
                $objIpn = json_decode($payload, false);
                // Here, can make log for debugging.
                }
            catch(\Exception $e)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_PAYLOAD_FORMAT');
                }

            switch($objIpn->payment->status)
                {
                case self::STATUS_NEW:                          // Is new, Do nothing
                    /**
                     * Initial status for the payment
                     */
                break;
                case self::STATUS_CHARGEBACK_INIT:              // chargeback initiat
                case self::STATUS_CHARGEBACK_ACCEPT:            // chargeback acceptat
                case self::STATUS_SCHEDULED:
                case self::STATUS_CHARGEBACK_REPRESENTMENT:
                case self::STATUS_REVERSED:
                case self::STATUS_PENDING_ANY:
                case self::STATUS_PROGRAMMED_RECURRENT_PAYMENT:
                case self::STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT:
                case self::STATUS_TRIAL_PENDING:                // specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
                case self::STATUS_TRIAL:                        // specific to Model_Purchase_Sms_Online; trial period has started
                case self::STATUS_EXPIRED:                      // cancel a not payed purchase 
                case self::STATUS_OPENED:                       // preauthorizate (card)
                case self::STATUS_PENDING:
                case self::STATUS_ERROR:                        // error
                case self::STATUS_DECLINED:                     // declined
                    /**
                     * payment declined / has error / Not yet terminated...
                     */
                    $orderLog = 'payment declined'; // Here, can create a log for your order...
                break;
                case self::STATUS_FRAUD:                        // fraud
                    /**
                     * payment status is in fraud, reviw the payment
                     */
                    $orderLog = 'payment in reviwing'; // Here, can create a log for your order...
                break;
                case self::STATUS_3D_AUTH:
                    /**
                     * need Verify Auth
                     */
                    $orderLog = 'The payment needs to be signed by the user.'; // Here, can create a log for your order...
                break;
                case self::STATUS_PENDING_AUTH: // in asteptare de verificare pentru tranzactii autorizate
                    /**
                     * update payment status, last modified date&time in your system
                     */
                    $orderLog = 'update payment status, last modified date&time in your system'; // Here, can create a log for your order...;
                break;
                case self::STATUS_PAID: // capturate (card)
                case self::STATUS_CONFIRMED:
                    /**
                     * payment was confirmed; deliver goods
                     */
                    $orderLog = 'payment was confirmed; deliver goods'; // Here, can create a log for your order...
                break;
                case self::STATUS_CREDIT: // capturate si apoi refund
                    /**
                     * a previously confirmed payment eas refinded; cancel goods delivery
                     */
                    $orderLog = 'a previously confirmed payment eas refinded; cancel goods delivery'; // Here, can create a log for your order...
                break;
                case self::STATUS_CANCELED: // void
                    /**
                     * payment was cancelled; do not deliver goods
                     */
                    $orderLog = 'payment was cancelled; do not deliver goods'; // Here, can create a log for your order...
                break;
            }            
        } catch(\Exception $e)
        {
            $outputData['errorType']	= self::ERROR_TYPE_PERMANENT;
            $outputData['errorCode']	= ($e->getCode() != 0) ? $e->getCode() : self::E_VERIFICATION_FAILED_GENERAL;
            $outputData['errorMessage']	= $e->getMessage();
            
            $exceptionLog = [
                            "IPN - Error"  =>  "Hash Data is not matched with subject",
                            "ipnMsgError"  => 'ERROR_TYPE_PERMANENT -> E_VERIFICATION_FAILED_GENERAL'
                            ];
            // Here, can create a log for your debugging... 
        }

        return $outputData;
    }

    /**
    *  get HTTP headers
    */
    private function getSpecificHeader(string $headerName): ?string {
        // Attempt 1: Use getallheaders() if available.
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, $headerName) === 0) {
                    return $value;
                }
            }
        }

        // Attempt 2: to get value from http header.
        $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($headerName));
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }
        
        return null;
    }

    

    
}