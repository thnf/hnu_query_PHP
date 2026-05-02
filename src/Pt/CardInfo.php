<?php

namespace HnuQuery\Pt;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class CardInfo
{
    private const CARD_INFO_URL = 'https://pt.hnu.edu.cn/api/hndxYkt/getCardUserInfo/info';

    public int $id;
    public float $balance;

    private function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->balance = $data['balance'];
    }

    public static function getCardInfo(PtToken $token): self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::CARD_INFO_URL, ['headers' => $headers]);
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '校园卡信息响应格式错误');
        }

        $cardData = $data['data'] ?? null;
        if ($cardData === null) {
            throw HnuQueryException::parseError($body, '获取校园卡信息失败');
        }

        $account = intval($cardData['account'] ?? 0);
        $balance = floatval($cardData['balance'] ?? 0) / 100.0;

        return new self([
            'id' => $account,
            'balance' => $balance,
        ]);
    }
}