<?php

namespace HnuQuery\Pt;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class CardHistory
{
    private const CARD_HISTORY_URL = 'https://pt.hnu.edu.cn/api/hndxYkt/getAccHisConsubDzzfLog/detail';
    private const CSRF_TOKEN_URL = 'https://pt.hnu.edu.cn/api/security/token';

    public float $total;
    public int $count;
    /** @var CardHistoryItem[] */
    public array $items;

    private function __construct(array $data)
    {
        $this->total = $data['total'];
        $this->count = $data['count'];
        $this->items = $data['items'];
    }

    public static function getCardHistory(
        PtToken $token,
        int $year,
        int $month,
        CardHistoryType $historyType
    ): self {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $trancode = match ($historyType) {
            CardHistoryType::Consumption => '15',
            CardHistoryType::Recharge => '16',
        };

        $csrfResponse = $client->get(self::CSRF_TOKEN_URL, ['headers' => $headers]);
        $csrfBody = $csrfResponse->getBody()->getContents();
        $csrfData = json_decode($csrfBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($csrfBody, 'CSRF token响应格式错误');
        }

        $csrfToken = $csrfData['data'] ?? null;
        if ($csrfToken === null) {
            throw HnuQueryException::parseError($csrfBody, '获取CSRF token失败');
        }

        $beginDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = sprintf('%d-%02d-31', $year, $month);

        $response = $client->post(self::CARD_HISTORY_URL, [
            'headers' => array_merge($headers, ['X-XSRF-TOKEN' => $csrfToken]),
            'form_params' => [
                'beginDate' => $beginDate,
                'endDate' => $endDate,
                'pageSize' => '100000',
                'trancode' => $trancode,
            ]
        ]);

        $body = $response->getBody()->getContents();
        $rawData = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '校园卡历史响应格式错误');
        }

        $data = $rawData['data'] ?? null;
        if ($data === null) {
            throw HnuQueryException::parseError($body, '获取校园卡历史失败');
        }

        $rawItems = $data['webTrjnDTO'] ?? [];
        $items = [];

        foreach ($rawItems as $item) {
            $dateTime = str_replace('/', '-', $item['effectdate'] ?? '');
            $journalTime = str_replace('/', '-', $item['jndatetime'] ?? '');
            $location = isset($item['sysname1']) ? trim($item['sysname1']) : null;

            $items[] = new CardHistoryItem([
                'date_time' => $dateTime,
                'journal_time' => $journalTime,
                'status' => $item['jourName'] ?? '',
                'id' => intval($item['usedcardnum'] ?? 0),
                'now_balance' => floatval($item['nowAmt'] ?? 0),
                'amount' => floatval($item['fTranAmt'] ?? 0),
                'location' => $location,
                'name' => $item['tranname'] ?? '',
            ]);
        }

        return new self([
            'total' => floatval($data['amt'] ?? 0) / 100.0,
            'count' => intval($data['count'] ?? 0),
            'items' => $items,
        ]);
    }
}