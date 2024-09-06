<?php

namespace App\Http\Controllers;

use App\Models\SettingApi;
use App\Services\OAuth2ClientService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;

class SatuSehatController extends Controller
{
    protected $oauth2Client;
    protected $stagingUrl;
    protected $clientIDStaging;
    protected $clientSecretStaging;

    public function __construct(OAuth2ClientService $oauth2Client)
    {
        $this->oauth2Client = $oauth2Client;
        $this->stagingUrl = env('RME_STAGING');
        $this->clientIDStaging = env('CLIENT_ID_STAGING');
        $this->clientSecretStaging = env('CLIENT_SECRET_STAGING');
    }

    function _kfa($url)
    {
        $client = new Client();
        $bearer = SettingApi::firstOrNew(['key' => 'Bearer']);

        // If the bearer token does not exist, fetch a new token.
        if (!$bearer->exists) {
            [$kode, $reply] = $this->oauth2Client->getToken();
            $bearer->value = $reply;
            $bearer->save();
        }

        try {
            // Initial request
            $res = $this->sendRequest($client, $url, $bearer);
            $statusCode = $res->getStatusCode();

            $response = json_decode($res->getBody()->getContents());
            return [$statusCode, $response];
        } catch (ClientException $e) {
            // Handle exceptions and check for 401 status code
            $statusCode = $e->getResponse()->getStatusCode();
            // Get a new token and retry the request
            [$kode, $reply] = $this->oauth2Client->getToken();
            $bearer->value = $reply;

            // Save the new token and ensure it's saved properly
            if (!$bearer->save()) {
                throw new \Exception('Failed to save new bearer token');
            }

            try {
                $res = $this->sendRequest($client, $url, $bearer);
                $statusCode = $res->getStatusCode();
                $response = json_decode($res->getBody()->getContents());
                return [$statusCode, $response];
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $res = json_decode($e->getResponse()->getBody()->getContents());
                return [$statusCode, $res];
            }
        } catch (\Exception $e) {
            return [
                'statusCode' => 500,
                'error' => 'Failed to fetch data from API',
                'message' => $e->getMessage()
            ];
        }
    }

    function sendRequest($client, $url, $bearer)
    {
        $headers = [
            'Authorization' => 'Bearer ' . $bearer->value,
        ];

        $request = new Request('GET', $url, $headers);
        return $client->sendAsync($request)->wait();
    }
}
