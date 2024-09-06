<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class OAuth2ClientService
{
    protected $httpClient;
    protected $apiUrl;
    protected $stagingUrl;
    protected $clientIDStaging;
    protected $clientSecretStaging;


    public function __construct()
    {
        $this->httpClient = new Client();
        $this->apiUrl = env('RME_URL'); //prod
        $this->stagingUrl = env('RME_STAGING'); //staging
        $this->clientIDStaging = env('CLIENT_ID_STAGING');
        $this->clientSecretStaging = env('CLIENT_SECRET_STAGING');
    }


    public function getToken()
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $options = [
            'form_params' => [
                'client_id' => "$this->clientIDStaging",
                'client_secret' => "$this->clientSecretStaging",
            ],
        ];

        $url = "$this->stagingUrl" . '/accesstoken?grant_type=client_credentials';
        $request = new Request('POST', $url, $headers);

        try {
            $res = $this->httpClient->sendAsync($request, $options)->wait();
            $contents = json_decode($res->getBody()->getContents());
            $code = $res->getStatusCode();

            return [$code, $contents->access_token];

        } catch (ClientException $e) {
            $res = json_decode($e->getResponse()->getBody()->getContents());
            $issue_information = $res->issue[0]->details->text;

            return $issue_information;
        }
    }
}
