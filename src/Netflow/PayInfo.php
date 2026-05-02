<?php

namespace HnuQuery\Netflow;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class PayInfo
{
    private const PAY_INFO_URL = 'http://ll.hnu.edu.cn/api/v1/pay/getpayinfo';

    public static function getOverduePayment(NetflowToken $token): float
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::PAY_INFO_URL, ['headers' => $headers]);
        $rawData = NetflowToken::extractResponseData($response);

        return floatval($rawData['Total'] ?? 0);
    }
}
