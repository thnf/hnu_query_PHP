<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class EmptyClassroom
{
    private const EMPTY_CLASSROOM_URL = 'http://hdjw.hnu.edu.cn/jsxsd/kbxx/jsjy_query2';

    public string $roomName;
    public string $roomType;
    public int $seatCount;
    public int $examSeatCount;

    private function __construct(array $data)
    {
        $this->roomName = $data['room_name'];
        $this->roomType = $data['room_type'];
        $this->seatCount = $data['seat_count'];
        $this->examSeatCount = $data['exam_seat_count'];
    }

    /**
     * @param HdjwToken $token 教务系统令牌
     * @param string $buildingId 楼栋id
     * @param int $week 第几周
     * @param int $day 周几，星期一为1，星期日为7
     * @param int[] $time 大节次数组，支持大节1-5，不支持第6大节
     * @param int $xn 学年
     * @param int $xq 学期
     * @return EmptyClassroom[]
     */
    public static function getEmptyClassroom(
        HdjwToken $token,
        string $buildingId,
        int $week,
        int $day,
        array $time,
        int $xn,
        int $xq
    ): array {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $timeStr = implode(',', array_map(function ($x) {
            return match ($x) {
                1 => '0102',
                2 => '0304',
                3 => '0506',
                4 => '0708',
                5 => '091011',
                default => throw HnuQueryException::unexpectedError("不支持第 {$x} 大节"),
            };
        }, $time));

        $response = $client->post(self::EMPTY_CLASSROOM_URL, [
            'headers' => $headers,
            'form_params' => [
                'xnxqh' => sprintf('%d-%d-%d', $xn, $xn + 1, $xq),
                'jxlbh' => $buildingId,
                'selectZc' => $week,
                'selectXq' => $day,
                'selectJc' => $timeStr,
                'typewhere' => 'jszq',
            ]
        ]);

        $data = HdjwToken::extractResponseData($response);

        if (!is_array($data) || !isset($data[4]) || !is_array($data[4])) {
            throw HnuQueryException::parseError(json_encode($data), '空教室数据格式错误');
        }

        $result = [];

        foreach ($data[4] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $isFree = true;
            $timeCount = count($time);
            for ($i = 1; $i <= $timeCount; $i++) {
                if (isset($item[$i]) && $item[$i] !== null) {
                    $isFree = false;
                    break;
                }
            }

            if (!$isFree) {
                continue;
            }

            $roomName = $item[0] ?? '';
            $seatCountStr = $item[2 + $timeCount] ?? '';
            $roomType = $item[3 + $timeCount] ?? '';

            if (empty($roomName) || empty($seatCountStr) || empty($roomType)) {
                continue;
            }

            // 解析座位数格式：(普通座位数/考试座位数)
            if (strlen($seatCountStr) < 3 || !str_starts_with($seatCountStr, '(') || !str_ends_with($seatCountStr, ')')) {
                continue;
            }

            $seatCountParts = explode('/', substr($seatCountStr, 1, -1));
            if (count($seatCountParts) !== 2) {
                continue;
            }

            $seatCount = intval($seatCountParts[0]);
            $examSeatCount = intval($seatCountParts[1]);

            $result[] = new self([
                'room_name' => $roomName,
                'room_type' => $roomType,
                'seat_count' => $seatCount,
                'exam_seat_count' => $examSeatCount,
            ]);
        }

        return $result;
    }
}
