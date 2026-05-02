<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Rank
{
    public ?string $rank;
    public ?string $score;

    public function __construct(?string $rank = null, ?string $score = null)
    {
        $this->rank = $rank;
        $this->score = $score;
    }

    /**
     * 获取排名
     * 
     * @param HdjwToken $token 教务系统令牌
     * @param array $selections 学年学期数组，格式为 [['xn' => 2025, 'xq' => 1], ...]
     * @param RankRange[] $ranges 课程范围数组
     * @param RankMethod $method 排名计算方式
     * @return self|null
     */
    public static function getRank(HdjwToken $token, array $selections, array $ranges, RankMethod $method): ?self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        // 支持多个学年学期
        $selectionStr = implode(',', array_map(function($sel) {
            return sprintf('%d-%d-%d', $sel['xn'], $sel['xn'] + 1, $sel['xq']);
        }, $selections));

        // 支持多个课程范围
        $rangeStr = implode(',', array_map(function(RankRange $range) {
            return $range->toStr();
        }, $ranges));

        $url = "http://hdjw.hnu.edu.cn/jsxsd/xscjsq/cjpmcx_list.do?pageNum=1&pageSize=20&kkxz=" . $rangeStr . "&pmfs=" . $method->toStr() . "&xnxq=" . urlencode($selectionStr);

        $response = $client->get($url, ['headers' => $headers]);
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError(
                substr($body, 0, 500),
                "无法解析JSON响应: " . json_last_error_msg()
            );
        }

        // 按照 Rust 版本实现，空数组是正常情况
        if (!isset($json['data']) || !is_array($json['data'])) {
            return null;
        }

        $data = $json['data'][0] ?? null;
        if (!$data) {
            return null;
        }

        // 按照 Rust 版本实现，根据排名方法选择对应的分数字段
        $score = match ($method->getValue()) {
            '4' => $data['avgzcj'] ?? null,  // 算术平均分
            '2' => $data['pjxfj'] ?? null,   // 加权平均分
            '3' => $data['pjxfjd'] ?? null,  // 平均学分绩点
            default => null,
        };
        
        // 按照 Rust 版本实现，支持字符串和数字类型的排名数据
        $rank = null;
        if (isset($data['numrow'])) {
            if (is_string($data['numrow'])) {
                $rank = $data['numrow'];
            } elseif (is_numeric($data['numrow'])) {
                $rank = (string)$data['numrow'];
            }
        }

        // 按照 Rust 版本实现，将分数转换为字符串
        if ($score !== null) {
            if (is_string($score)) {
                $score = $score;
            } elseif (is_numeric($score)) {
                $score = (string)$score;
            } else {
                $score = null;
            }
        }

        return new self($rank, $score);
    }
}