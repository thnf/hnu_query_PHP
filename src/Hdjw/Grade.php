<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class GradeDetailItem
{
    public string $name;
    public string $score;
    public string $percentage;

    public function __construct(string $name, string $score, string $percentage)
    {
        $this->name = $name;
        $this->score = $score;
        $this->percentage = $percentage;
    }
}

class Grade
{
    private const GRADE_URL = 'http://hdjw.hnu.edu.cn/jsxsd/kscj/cjcx_list';
    
    public string $courseId;
    public string $courseName;
    public float $credit;
    public ?string $courseType1;
    public string $courseType2;
    public float $gpa;
    public int $score;
    public ?string $gradeTag;
    public string $gradeType;
    public ?string $jx0404id;

    public function __construct(array $data)
    {
        $this->courseId = $data['courseId'] ?? '';
        $this->courseName = $data['courseName'] ?? '';
        $this->credit = isset($data['credit']) ? (float)$data['credit'] : 0.0;
        $this->courseType1 = $data['courseType1'] ?? null;
        $this->courseType2 = $data['courseType2'] ?? '';
        $this->gpa = isset($data['gpa']) ? (float)$data['gpa'] : 0.0;
        $this->score = isset($data['score']) ? (int)$data['score'] : 0;
        $this->gradeTag = $data['gradeTag'] ?? null;
        $this->gradeType = $data['gradeType'] ?? '';
        $this->jx0404id = $data['jx0404id'] ?? null;
    }

    /**
     * @return Grade[]
     */
    public static function getGrade(HdjwToken $token, int $xn, int $xq): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $nextYear = $xn + 1;
        $url = self::GRADE_URL . '?' . http_build_query([
            'pageNum' => 1,
            'pageSize' => 50,
            'kcxz' => '',
            'kcsx' => '',
            'kcmc' => '',
            'xsfs' => 'all',
            'sfxsbcxq' => 1,
            'kksj' => "{$xn}-{$nextYear}-{$xq}",
        ]);

        try {
            $response = $client->get($url, ['headers' => $headers]);
        } catch (\Exception $e) {
            throw HnuQueryException::networkError($e);
        }

        $data = HdjwToken::extractResponseData($response);

        if (!is_array($data)) {
            throw HnuQueryException::parseError(
                is_string($data) ? $data : json_encode($data),
                "成绩数据格式错误，期望数组类型"
            );
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw HnuQueryException::parseError(
                json_encode($data),
                "成绩数据格式错误，缺少 data 字段或 data 不是数组"
            );
        }

        $grades = [];
        foreach ($data['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            
            $grades[] = new self([
                'courseId' => $row['kch'] ?? $row['courseId'] ?? '',
                'courseName' => $row['kc_mc'] ?? $row['courseName'] ?? '',
                'credit' => $row['xf'] ?? $row['credit'] ?? 0.0,
                'courseType1' => $row['kcsx'] ?? $row['courseType1'] ?? null,
                'courseType2' => $row['kcxzmc'] ?? $row['courseType2'] ?? '',
                'gpa' => $row['jd'] ?? $row['gpa'] ?? 0.0,
                'score' => $row['zcj'] ?? $row['score'] ?? 0,
                'gradeTag' => $row['cjbs'] ?? $row['gradeTag'] ?? null,
                'gradeType' => $row['falb'] ?? $row['gradeType'] ?? '',
                'jx0404id' => $row['jx0404id'] ?? null,
            ]);
        }

        return $grades;
    }

    /**
     * @return GradeDetailItem[]
     */
    public static function getGradeDetail(HdjwToken $token, string $jx0404id): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $url = 'http://hdjw.hnu.edu.cn/jsxsd/kscj/pscj_list.do?' . http_build_query([
            'zcj' => '',
            'jx0404id' => $jx0404id,
        ]);

        try {
            $response = $client->get($url, ['headers' => $headers]);
        } catch (\Exception $e) {
            throw HnuQueryException::networkError($e);
        }

        $rawData = HdjwToken::extractResponseData($response);

        if (!is_string($rawData)) {
            throw HnuQueryException::parseError(
                (string)json_encode($rawData),
                "期望HTML响应，实际获取到: " . gettype($rawData)
            );
        }

        return self::parseGradeDetail($rawData);
    }

    /**
     * @return GradeDetailItem[]
     */
    private static function parseGradeDetail(string $html): array
    {
        $pattern = '/let\s+arr\s*=\s*(.*?);.*?window\.initQzTable\(\s*\{.*?cols:\s*\[([^\]]*)\]\s*\}\s*\);/s';
        
        if (!preg_match($pattern, $html, $matches)) {
            throw HnuQueryException::parseError($html, "成绩详情解析失败，无法匹配JS数据");
        }

        $dataStr = $matches[1];
        $mapStr = $matches[2];

        $data = json_decode($dataStr, true);
        if (!$data || !isset($data[0]) || !is_array($data[0])) {
            throw HnuQueryException::parseError($dataStr, "成绩详情data JSON解析失败");
        }

        $dataRow = $data[0];

        $mapStr = str_replace(["//表头", "'", "field", "title", "type"], 
            ["", "\"", "\"field\"", "\"title\"", "\"type\""], $mapStr);
        $map = json_decode($mapStr, true);
        
        if (!$map || !is_array($map)) {
            throw HnuQueryException::parseError($mapStr, "成绩详情map JSON解析失败");
        }

        $fieldMap = [];
        foreach ($map as $item) {
            if (isset($item['field']) && isset($item['title'])) {
                $fieldMap[$item['field']] = $item['title'];
            }
        }

        $result = [];
        foreach ($dataRow as $key => $value) {
            if (is_string($key) && str_ends_with($key, 'bl')) {
                $scoreKey = rtrim($key, 'bl');
                $score = isset($dataRow[$scoreKey]) ? (string)$dataRow[$scoreKey] : '';
                $name = $fieldMap[$scoreKey] ?? $scoreKey;
                $percentage = (string)$value;

                if ($percentage !== '0%' && $percentage !== '0') {
                    $result[] = new GradeDetailItem($name, $score, $percentage);
                }
            }
        }

        return $result;
    }
}
