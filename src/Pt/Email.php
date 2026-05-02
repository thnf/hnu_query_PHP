<?php

namespace HnuQuery\Pt;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Email
{
    private const EMAIL_URL = 'https://pt.hnu.edu.cn/api/v1/email/unRead/count';

    public static function getUnreadEmailCount(PtToken $token): ?int
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::EMAIL_URL, ['headers' => $headers]);
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '未读邮件响应格式错误');
        }

        $emailData = $data['data'] ?? null;
        if ($emailData === null) {
            return null;
        }

        $unReadCount = $emailData['unReadCount'] ?? null;

        return $unReadCount !== null ? intval($unReadCount) : null;
    }
}
