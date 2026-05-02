<?php

namespace HnuQuery\Wxpay;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;
use HnuQuery\Xgxt\Dormitory;

class Electricity
{
    private const QUERY_URL = 'http://wxpay.hnu.edu.cn/api/appElectricCharge/checkRoomNo';

    /**
     * @return array{0: int, 1: string, 2: string}
     * @throws HnuQueryException
     */
    public static function parseDormitory(Dormitory $dormitory): array
    {
        $park = $dormitory->getPark();
        $build = $dormitory->getBuild();
        $room = $dormitory->getRoom();

        if ($park === null || $build === null) {
            throw HnuQueryException::parseError("宿舍信息必须成功解析", "宿舍解析失败");
        }

        $parkId = match ($park) {
            '南校区' => 1,
            '财院校区' => 2,
            '天马园区' => 3,
            '德智园区' => 4,
            '德智留学生公寓' => 5,
            '望麓桥学生公寓' => 6,
            '牛头山学生公寓' => 7,
            default => throw HnuQueryException::parseError($park, "未知园区"),
        };

        $buildId = match (true) {
            // 南校区
            $parkId === 1 && $build === '7舍' => '19-1',
            $parkId === 1 && $build === '8舍' => '20',
            $parkId === 1 && $build === '10舍' => '21',
            $parkId === 1 && $build === '11舍' => '21-0',
            $parkId === 1 && $build === '12舍' => '21-1',
            $parkId === 1 && $build === '13舍' => '21-2',
            $parkId === 1 && $build === '14舍' => '22',
            $parkId === 1 && $build === '15舍' => '23',
            $parkId === 1 && $build === '17舍' => '24',
            $parkId === 1 && $build === '18舍' => '25',
            $parkId === 1 && $build === '19舍' => self::parse19BuildRoom($room),
            $parkId === 1 && $build === '南楼' => '26',
            $parkId === 1 && $build === '培训小楼' => '27',
            // 财院校区
            $parkId === 2 && $build === '1' => '01',
            $parkId === 2 && $build === '2' => '02',
            $parkId === 2 && $build === '3' => '02-01',
            $parkId === 2 && $build === '5' => '03',
            $parkId === 2 && $build === '6' => '04',
            $parkId === 2 && $build === '12' => '05',
            $parkId === 2 && $build === 'A' => '06',
            $parkId === 2 && $build === 'B' => '07',
            $parkId === 2 && $build === '7' => '08',
            // 天马园区
            $parkId === 3 && $build === '一区1栋' => '28',
            $parkId === 3 && $build === '一区2栋' => '29',
            $parkId === 3 && $build === '一区3栋' => '30',
            $parkId === 3 && $build === '一区4栋' => '30-1',
            $parkId === 3 && $build === '二区1栋' => '31',
            $parkId === 3 && $build === '二区2栋' => '32',
            $parkId === 3 && $build === '二区3栋' => '33',
            $parkId === 3 && $build === '二区4栋' => '34',
            $parkId === 3 && $build === '二区5栋' => '35',
            $parkId === 3 && $build === '二区6栋' => '36',
            $parkId === 3 && $build === '二区7栋' => '37',
            $parkId === 3 && $build === '三区9栋' => '38',
            $parkId === 3 && $build === '三区10栋' => '39',
            $parkId === 3 && $build === '三区11栋' => '40',
            $parkId === 3 && $build === '三区12栋' => '41',
            $parkId === 3 && $build === '三区13栋' => '42',
            $parkId === 3 && $build === '三区16栋' => '43',
            $parkId === 3 && $build === '三区17栋' => '44',
            $parkId === 3 && $build === '三区18栋' => '45',
            $parkId === 3 && $build === '三区19栋' => '46',
            $parkId === 3 && $build === '三区20栋' => '46-1',
            $parkId === 3 && $build === '四区1栋' => '47',
            $parkId === 3 && $build === '四区2栋' => '48',
            $parkId === 3 && $build === '四区3栋' => '49',
            $parkId === 3 && $build === '四区4栋' => '50',
            // 德智园区
            $parkId === 4 && $build === '2栋' => '09',
            $parkId === 4 && $build === '5栋' => '10',
            $parkId === 4 && $build === '6栋' => '11',
            $parkId === 4 && $build === '7栋' => '12',
            $parkId === 4 && $build === '8栋' => '13',
            $parkId === 4 && $build === '9栋' => '14',
            $parkId === 4 && $build === '10栋' => '15',
            $parkId === 4 && $build === '11栋' => '16',
            $parkId === 4 && $build === '13栋' => '17',
            $parkId === 4 && $build === '15栋' => '17-01',
            $parkId === 4 && $build === '16栋' => '17-02',
            $parkId === 4 && $build === '17栋' => '17-03',
            // 德智留学生公寓
            $parkId === 5 && $build === '10栋' => '17-04',
            // 望麓桥学生公寓
            $parkId === 6 && $build === '1栋' => '51',
            $parkId === 6 && $build === '2栋' => '#2栋',
            $parkId === 6 && $build === '3栋' => '#3栋',
            $parkId === 6 && $build === '4栋' => '57',
            // 牛头山学生公寓
            $parkId === 7 && $build === '2栋' => '60',
            $parkId === 7 && $build === '3栋' => '61',
            $parkId === 7 && $build === '4栋' => '62',
            $parkId === 7 && $build === '5栋' => '63',
            $parkId === 7 && $build === '6栋' => '64',
            $parkId === 7 && $build === '7栋' => '65',
            default => throw HnuQueryException::parseError("{$park} {$build}", "未知楼栋"),
        };

        $roomId = match (true) {
            // 德智园区，在房间号前加上楼栋号
            $parkId === 4 && $build === '2栋' => '2' . $room,
            $parkId === 4 && $build === '5栋' => '5' . $room,
            $parkId === 4 && $build === '6栋' => '6' . $room,
            $parkId === 4 && $build === '7栋' => '7' . $room,
            $parkId === 4 && $build === '8栋' => '8' . $room,
            $parkId === 4 && $build === '9栋' => '9' . $room,
            $parkId === 4 && $build === '10栋' => '10' . $room,
            $parkId === 4 && $build === '11栋' => '11' . $room,
            // 南校区19舍附楼，请在房间号前加上F
            $parkId === 1 && $build === '19舍' => self::parse19Room($room),
            default => $room,
        };

        return [$parkId, $buildId, $roomId];
    }

    /**
     * @throws HnuQueryException
     */
    private static function parse19BuildRoom(string $room): string
    {
        $unitNo = $room[0] ?? throw HnuQueryException::parseError($room, "19舍房间号格式错误");
        return match ($unitNo) {
            '1' => '25-1',
            '2' => '25-2',
            '3' => '25-3',
            '4' => '25-4',
            default => throw HnuQueryException::parseError($room, "19舍未知单元号"),
        };
    }

    /**
     * @throws HnuQueryException
     */
    private static function parse19Room(string $room): string
    {
        $parts = explode('-', $room);
        if (count($parts) !== 2) {
            throw HnuQueryException::parseError($room, "19舍房间号格式错误");
        }
        
        if (str_starts_with($parts[1], '附')) {
            return 'F' . str_replace('附', '', $parts[1]);
        }
        
        return $parts[1];
    }

    /**
     * @throws HnuQueryException
     */
    private static function rawElectricityData(int $park, string $building, string $room): ?string
    {
        $client = HttpClient::getClient();

        $url = sprintf(
            "%s?parkNo=%d&buildingNo=%s&rechargeType=2&roomNo=%s",
            self::QUERY_URL,
            $park,
            $building,
            $room
        );

        $response = $client->get($url, [
            'headers' => [
                'referer' => 'http://wxpay.hnu.edu.cn/electricCharge/home/',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);

        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, "电量查询响应解析失败");
        }

        return $json['data']['Balance'] ?? null;
    }

    /**
     * @throws HnuQueryException
     */
    public static function getElectricity(Dormitory $dormitory): string
    {
        if (!$dormitory->successfullyParsed()) {
            throw new \InvalidArgumentException("参数 dormitory 必须成功解析");
        }

        [$park, $build, $room] = self::parseDormitory($dormitory);

        if ($build === '#2栋' || $build === '#3栋') {
            $northBuild = ($build === '#2栋') ? '52' : '54';
            $southBuild = ($build === '#2栋') ? '53' : '55';

            $resNorth = null;
            $resSouth = null;

            try {
                $resNorth = self::rawElectricityData($park, $northBuild, $room);
            } catch (\Exception $e) {
            }

            try {
                $resSouth = self::rawElectricityData($park, $southBuild, $room);
            } catch (\Exception $e) {
            }

            if ($resNorth !== null && $resSouth === null) {
                return $resNorth;
            }
            if ($resSouth !== null && $resNorth === null) {
                return $resSouth;
            }

            throw HnuQueryException::unexpected(new \Exception("获取电量信息失败，无法区分宿舍南北"));
        }

        $result = self::rawElectricityData($park, $build, $room);
        if ($result === null) {
            throw HnuQueryException::parseError("获取电量信息失败", "无法获取Balance");
        }

        return $result;
    }
}
