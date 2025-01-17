<?php

namespace pipinstallpip\onencews;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use \ErrorException;
use Psr\Http\Message\ResponseInterface;
use \stdClass;


class OnenceWS extends Client
{

    const ACTIVATED = 'Activated';
    const DISABLED = 'Disabled';
    const APN = 'iot.1nce.net';
    const V1 = 'v1';

    private static $baseUrl = 'https://api.1nce.com/management-api';

    private $clientId;
    private $clientSecret;
    private $encodedAuthorization ;
    private $apiVersion;


    public function __construct($clientId,$clientSecret,$version = self::V1)
    {
        parent::__construct(['base_uri' => self::$baseUrl, 'http_errors' => false]);
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->encodedAuthorization = base64_encode("$this->clientId:$this->clientSecret");
        $this->apiVersion = $version;
    }

    /**
     * @return Request
     */
    private function __getTokenRequest(){
        return new Request('POST',"{$this::$baseUrl}/oauth/token",
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'authorization' => "Basic $this->encodedAuthorization"
            ],
            'grant_type=client_credentials'
        );
    }

    /**
     * @return object with:
     *      authToken => Access token for using the 1NCE API.
     *      tokenType => Type of token
     *      expiresIn => Time in seconds until the API Bearer Token is expired and a new token has to be queried.
     *      tokenScope => Access level of the returned token.
     * @throws ErrorException
     */
    protected function __getAuthToken() {
        $responseContent = json_decode($this->send($this->__getTokenRequest())->getBody()->getContents());
        if((isset($responseContent->access_token))){
            return (object) [
                'authToken' => $responseContent->access_token,
                'tokenType' => $responseContent->token_type,
                'expiresIn' => $responseContent->expires_in,
                'tokenScope' => $responseContent->scope,
            ];
        }else{
            throw new ErrorException('Errore durante il decoding del access token');
        }
    }

    /**
     * @return Request
     * @throws ErrorException
     * @param  string $url
     * @param  string $type
     * @param  array $params
     */
    private function __prepare($url,$type,$params = []){
        $auth = $this->__getAuthToken();
        return new Request($type,
            "{$this::$baseUrl}/$this->apiVersion/$url",
            [
                'Content-Type' => "application/json",
                'Authorization' => "$auth->tokenType $auth->authToken",
                'Accept' => '*/*',
                'Cache-Control' => 'no-cache',
                'Host' => 'api.1nce.com',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ],
            json_encode($params)
        );
    }


    /**
     * @return ResponseInterface|string
     * @throws ErrorException
     * @param  string $url
     * @param  string $type
     * @param  array $params
     */
    private function __standard($url,$type,$params = []){
        return $this->send($this->__prepare($url,$type,$params));
    }

    /**
     * @return stdClass|string
     * @param  string $url
     * @param  array $params
     * @throws ErrorException
     */
    private function __standardGet($url,$params = []){
        return json_decode($this->__standard($url,'GET',$params)->getBody()->getContents());
    }

    /**
     * @return int
     * @param  string $url
     * @param  array $params
     * @throws ErrorException
     */
    private function __standardPost($url,$params = []){
        return $this->__standard($url,'POST',$params)->getStatusCode();
    }

    /**
     * @return int
     * @param  string $url
     * @param  array $params
     * @throws ErrorException
     */
    private function __standardPut($url,$params = []){
        return $this->__standard($url,'PUT',$params)->getStatusCode();
    }

    /**
     * @return stdClass|string
     * @param  string $url
     * @param  array $params
     * @throws ErrorException
     */
    private function __standardDelete($url,$params = []){
        return $this->__standard($url,'DELETE',$params)->getStatusCode();
    }


    //INFO: START GET REQUEST

    /**
     *  get all sims
     */
    public function getSimsList(){
        return $this->__standardGet('sims');
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimReachibility($iccid){
        return $this->__standardGet("sims/$iccid/connectivity_info");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimUsage($iccid)
    {
        return $this->__standardGet("sims/$iccid/usage");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimRemainingData($iccid)
    {
        return $this->__standardGet("sims/$iccid/quota/data");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimRemainingSms($iccid)
    {
        return $this->__standardGet("sims/$iccid/quota/sms");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSmsList($iccid)
    {
        return $this->__standardGet("sims/$iccid/sms");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimInfo($iccid)
    {
        return $this->__standardGet("sims/$iccid");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimStatus($iccid)
    {
        return $this->__standardGet("sims/$iccid/status");
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSimEvents($iccid)
    {
        return $this->__standardGet("sims/$iccid/events");
    }

    /**
     * @param  string $iccid
     * @param  int $idSms
     * @throws  ErrorException
     * @return stdClass
     */
    public function getSmsDetails($iccid,$idSms)
    {
        return $this->__standardGet("sims/$iccid/sms/$idSms");
    }


    //INFO: END GET REQUEST


    //INFO: START POST REQUEST

    /**
     * @param  string $iccid
     * @param  string $sms
     * @param  \data $expiry_date
     * @param  int $source_address
     * @param  string $udh
     * @param  int $dcs
     * @throws  ErrorException
     * @return int
     */
    public function sendSms($iccid,$sms,$expiry_date = null,$source_address = 1234567890,$udh = 'string',$dcs = 8){
        if(is_null($expiry_date))
            $expiry_date = date("Y-m-d", strtotime("+7 days"));

        return $this->__standardPost(
            "sims/$iccid/sms",
            [
                'source_address' => $source_address,
                'payload' => $sms,
                'udh' => $udh,
                'dcs' => $dcs,
                'source_address_type' => [
                    'id' => 145
                ],
                'expiry_date' => $expiry_date.'T18:10:29.000+0000'
            ]
        );
    }

    /**
     * @param  string $iccid
     * @throws  ErrorException
     * @return int
     */
    public function resetSim($iccid){
        return $this->__standardPost("sims/$iccid/reset");
    }
    //INFO: END POST REQUEST

    //INFO: START PUT REQUEST
    /**
     * @param  string $iccid
     * @param  string $newStatus
     * @param  string $newLabel
     * @param  bool $imeiLock
     * @throws  ErrorException
     * @return int
     */
    public function changeSimState($iccid,$newStatus ,$newLabel = '' ,$imeiLock = true){
        return $this->__standardPut(
            "sims/$iccid",[
                "iccid" => $iccid,
                "label" => $newLabel,
                "imei_lock" => $imeiLock,
                "status" => $newStatus
            ]
        );
    }
    //INFO: END PUT REQUEST

    //INFO: START DELETE REQUEST


    /**
     * @param  string $iccid
     * @param  int $smsId
     * @throws  ErrorException
     * @return int
     */
    public function deleteSpecificSms($iccid,$smsId)
    {
        return $this->__standardDelete("sims/$iccid/sms/$smsId");
    }

    //INFO: END DELETE REQUEST

}

