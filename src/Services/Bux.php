<?php

namespace FriendsOfBotble\Bux\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class Bux
{
    public function callAPI(string $url, array $params): ResponseInterface
    {
        $apiKey = get_payment_setting('api_key', BUX_PAYMENT_METHOD_NAME);
        $clientId = get_payment_setting('client_id', BUX_PAYMENT_METHOD_NAME);
        $apiURL = setting(
            'payment_' . BUX_PAYMENT_METHOD_NAME . '_mode'
        ) == 0 ? 'https://api.bux.ph/v1/api/sandbox' : 'https://api.bux.ph/v1/api';

        $client = new Client();

        $params['client_id'] = $clientId;

        return $client->post($apiURL . $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-key' => $apiKey,
            ],
            'json' => $params,
            'verify' => false,
        ]);
    }
}
