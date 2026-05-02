<?php

namespace HnuQuery\Netflow;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

enum UnlockStatus: string
{
    case Locked = 'Locked';
    case Unlocked = 'Unlocked';
    case Unknown = 'Unknown';
}

class UserInfo
{
    private const USER_INFO_URL = 'http://ll.hnu.edu.cn/api/v1/account/getuserinfo';

    public static function getUnlockStatus(NetflowToken $token): UnlockStatus
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::USER_INFO_URL, ['headers' => $headers]);
        $rawData = NetflowToken::extractResponseData($response);

        $isLocked = $rawData['IsLocked'] ?? null;
        if ($isLocked === null) {
            return UnlockStatus::Unknown;
        }

        $isLocked = intval($isLocked);

        return match ($isLocked) {
            0 => UnlockStatus::Unlocked,
            1 => UnlockStatus::Locked,
            default => UnlockStatus::Unknown,
        };
    }
}
