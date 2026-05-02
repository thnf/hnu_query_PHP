<?php

namespace HnuQuery\Netflow;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class ThisMonthInfo
{
    private const THIS_MONTH_URL = 'http://ll.hnu.edu.cn/api/v1/history/gettrafficinfobythismonth';

    public string $totalUsage;
    public string $uploadUsage;
    public string $downloadUsage;
    public float $basePackageAmount;
    public float $basePackageUsage;
    public float $basePackageUsagePercentage;
    public float $basePackageSurplus;
    public float $extendPackageAmount;
    public float $extendPackageUsage;
    public float $extendPackageUsagePercentage;
    public float $extendPackageSurplus;

    private function __construct(array $data)
    {
        $this->totalUsage = $data['total_usage'];
        $this->uploadUsage = $data['upload_usage'];
        $this->downloadUsage = $data['download_usage'];
        $this->basePackageAmount = $data['base_package_amount'];
        $this->basePackageUsage = $data['base_package_usage'];
        $this->basePackageUsagePercentage = $data['base_package_usage_percentage'];
        $this->basePackageSurplus = $data['base_package_surplus'];
        $this->extendPackageAmount = $data['extend_package_amount'];
        $this->extendPackageUsage = $data['extend_package_usage'];
        $this->extendPackageUsagePercentage = $data['extend_package_usage_percentage'];
        $this->extendPackageSurplus = $data['extend_package_surplus'];
    }

    private static function tryAddGbSuffix(string &$str): void
    {
        if (!str_ends_with($str, 'GB')) {
            $str .= 'GB';
        }
    }

    public static function getThisMonthInfo(NetflowToken $token): self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->get(self::THIS_MONTH_URL, ['headers' => $headers]);
        $rawData = NetflowToken::extractResponseData($response);

        $totalUsage = $rawData['allTraffic'] ?? '';
        $uploadUsage = $rawData['uploadTraffic'] ?? '';
        $downloadUsage = $rawData['downloadTraffic'] ?? '';

        self::tryAddGbSuffix($totalUsage);
        self::tryAddGbSuffix($uploadUsage);
        self::tryAddGbSuffix($downloadUsage);

        return new self([
            'total_usage' => $totalUsage,
            'upload_usage' => $uploadUsage,
            'download_usage' => $downloadUsage,
            'base_package_amount' => floatval($rawData['allBasePackageAmount'] ?? 0),
            'base_package_usage' => floatval($rawData['basePackageUsed'] ?? 0),
            'base_package_usage_percentage' => floatval($rawData['basePackageUsedPer'] ?? 0),
            'base_package_surplus' => floatval($rawData['surplusBasePackage'] ?? 0),
            'extend_package_amount' => floatval($rawData['allExtendPackageAmount'] ?? 0),
            'extend_package_usage' => floatval($rawData['extendPackageUsed'] ?? 0),
            'extend_package_usage_percentage' => floatval($rawData['extendPackageUsedPer'] ?? 0),
            'extend_package_surplus' => floatval($rawData['surplusExtendPackage'] ?? 0),
        ]);
    }
}
