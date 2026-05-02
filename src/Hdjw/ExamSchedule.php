<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class ExamSchedule
{
    private const EXAM_SCHEDULE_URL = 'http://hdjw.hnu.edu.cn/jsxsd/xsks/xsksap_list?pageNum=1&pageSize=20&xqlb=';

    public string $courseId;
    public string $courseName;
    public string $examTime;
    public string $examLocation;
    public string $seatNumber;
    public string $campus;

    public function __construct(array $data)
    {
        $this->courseId = $data['courseId'] ?? '';
        $this->courseName = $data['courseName'] ?? '';
        $this->examTime = $data['examTime'] ?? '';
        $this->examLocation = $data['examLocation'] ?? '';
        $this->seatNumber = $data['seatNumber'] ?? '';
        $this->campus = $data['campus'] ?? '';
    }

    /**
     * @return ExamSchedule[]
     */
    public static function getExamSchedule(HdjwToken $token, int $xn, int $xq): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $url = self::EXAM_SCHEDULE_URL . '&xnxqid=' . $xn . '-' . ($xn + 1) . '-' . $xq;

        $response = $client->get($url, [
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        // 直接解析JSON响应
        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError(
                substr($body, 0, 500),
                "无法解析JSON响应: " . json_last_error_msg()
            );
        }

        // 直接从data字段获取数组
        if (!isset($json['data']) || !is_array($json['data'])) {
            throw HnuQueryException::parseError(json_encode($json), "考试安排数据格式错误");
        }

        $exams = [];
        foreach ($json['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            
            $exams[] = new self([
                'courseId' => $row['kch'] ?? '',
                'courseName' => $row['kskcmc'] ?? '',
                'examTime' => $row['kssj'] ?? '',
                'examLocation' => $row['js_mc'] ?? '',
                'seatNumber' => $row['zwh'] ?? '',
                'campus' => $row['ksxq'] ?? '',
            ]);
        }

        return $exams;
    }
}
