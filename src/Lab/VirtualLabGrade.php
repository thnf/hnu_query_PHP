<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class VirtualLabGrade
{
    private const VIRTUAL_LAB_SCORE_URL = 'http://10.62.106.112/XPK/StudentScoreSearch/GetStudentFZLabScore';

    public string $labName;
    public ?string $score;

    private function __construct(string $labName, ?string $score)
    {
        $this->labName = $labName;
        $this->score = $score;
    }

    /**
     * @return VirtualLabGrade[]
     */
    public static function getVirtualLabGrade(LabToken $token): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::VIRTUAL_LAB_SCORE_URL, [
            'headers' => $headers,
            'form_params' => [
                'page' => '1',
                'rows' => '15',
                'SemID' => '0',
                'CourseID' => '0',
                'UserID' => $token->getStuId(),
            ]
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '虚拟实验成绩响应格式错误');
        }

        $rows = $data['rows'] ?? [];
        $result = [];

        foreach ($rows as $item) {
            $labName = $item['LabName'] ?? '';
            $score = ($item['LabScore'] ?? '') !== '' ? $item['LabScore'] : null;
            $result[] = new self($labName, $score);
        }

        usort($result, fn($a, $b) => strcmp($a->labName, $b->labName));
        $dedupResult = [];
        $seen = [];
        foreach ($result as $grade) {
            if (!isset($seen[$grade->labName])) {
                $seen[$grade->labName] = true;
                $dedupResult[] = $grade;
            }
        }

        return $dedupResult;
    }
}