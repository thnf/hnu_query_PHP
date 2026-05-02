<?php

namespace HnuQuery\Netflow;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class OrderItem
{
    public string $time;
    public float $downloadUsage;
    public float $uploadUsage;
    public float $overUsage;
    public float $shouldPay;
    public string $updateTime;

    public function __construct(array $data)
    {
        $this->time = $data['time'];
        $this->downloadUsage = $data['download_usage'];
        $this->uploadUsage = $data['upload_usage'];
        $this->overUsage = $data['over_usage'];
        $this->shouldPay = $data['should_pay'];
        $this->updateTime = $data['update_time'];
    }
}

class Order
{
    private const ORDER_URL = 'http://ll.hnu.edu.cn/api/v1/bill/getmonthbilllist';

    /**
     * @return OrderItem[]
     */
    public static function getOrder(NetflowToken $token): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::ORDER_URL, ['headers' => $headers]);
        $rawData = NetflowToken::extractResponseData($response);

        $result = [];

        foreach ($rawData as $item) {
            $result[] = new OrderItem([
                'time' => $item['Month'] ?? '',
                'download_usage' => floatval($item['Download'] ?? 0),
                'upload_usage' => floatval($item['Upload'] ?? 0),
                'over_usage' => floatval($item['RealOverTraffic'] ?? 0),
                'should_pay' => floatval($item['ShouldPay'] ?? 0),
                'update_time' => $item['UpdateTime'] ?? '',
            ]);
        }

        return $result;
    }
}
