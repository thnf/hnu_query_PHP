<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Semester
{
    private const SEMESTER_URL = 'http://10.62.106.112/BaseInfo/BaseInfo/BindTermStudentByStuNO';

    public int $xn;
    public int $xq;
    public string $id;

    private function __construct(array $data)
    {
        $this->xn = $data['xn'];
        $this->xq = $data['xq'];
        $this->id = $data['id'];
    }

    /**
     * @return Semester[]
     */
    public static function getSemester(LabToken $token): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::SEMESTER_URL, [
            'headers' => $headers,
            'form_params' => ['StudentID' => $token->getStuId()]
        ]);

        $rawData = LabToken::extractResponseData($response);

        $result = [];

        foreach ($rawData as $item) {
            $text = $item['text'] ?? '';
            $parts = preg_split('/[-_\s]/', $text);
            if (count($parts) < 3) {
                throw HnuQueryException::parseError($text, '学期信息格式错误');
            }
            $xn = intval($parts[0]);
            $xq = intval($parts[2]);

            $result[] = new self([
                'xn' => $xn,
                'xq' => $xq,
                'id' => $item['id'] ?? '',
            ]);
        }

        return $result;
    }
}
