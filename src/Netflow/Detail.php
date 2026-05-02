<?php

namespace HnuQuery\Netflow;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class DetailItem
{
    public string $app;
    public float $total;
    public float $download;
    public float $upload;
    public float $percentage;

    public function __construct(array $data)
    {
        $this->app = $data['app'];
        $this->total = $data['total'];
        $this->download = $data['download'];
        $this->upload = $data['upload'];
        $this->percentage = $data['percentage'];
    }
}

class Detail
{
    private const DETAIL_URL = 'http://ll.hnu.edu.cn/api/v1/history/query';

    public float $total;
    public float $upload;
    public float $download;
    /** @var DetailItem[] */
    public array $items;

    private function __construct(array $data)
    {
        $this->total = $data['total'];
        $this->upload = $data['upload'];
        $this->download = $data['download'];
        $this->items = $data['items'];
    }

    public static function getMonthDetail(NetflowToken $token, int $year, int $month): self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::DETAIL_URL, [
            'headers' => $headers,
            'json' => [
                'type' => 'float',
                'searchType' => 'month',
                'yearMonth' => sprintf('%d-%02d', $year, $month),
            ]
        ]);

        return self::convertResponse($response);
    }

    public static function getDayDetail(NetflowToken $token, int $year, int $month, int $day): self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::DETAIL_URL, [
            'headers' => $headers,
            'json' => [
                'type' => 'float',
                'searchType' => 'day',
                'yearMonthDay' => sprintf('%d-%02d-%02d', $year, $month, $day),
            ]
        ]);

        return self::convertResponse($response);
    }

    private static function convertResponse(\Psr\Http\Message\ResponseInterface $response): self
    {
        $rawData = NetflowToken::extractResponseData($response);

        $items = [];
        foreach ($rawData['FloatDetailList'] ?? [] as $item) {
            $items[] = new DetailItem([
                'app' => $item['App'] ?? '',
                'total' => floatval($item['Total'] ?? 0),
                'download' => floatval($item['Download'] ?? 0),
                'upload' => floatval($item['Upload'] ?? 0),
                'percentage' => floatval($item['Per'] ?? 0),
            ]);
        }

        return new self([
            'total' => floatval($rawData['AllTotal'] ?? 0),
            'upload' => floatval($rawData['AllUpload'] ?? 0),
            'download' => floatval($rawData['AllDownload'] ?? 0),
            'items' => $items,
        ]);
    }
}
