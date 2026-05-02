<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class CourseSchedule
{
    public int $week;
    public int $day;
    public string $place;
    public array $time;

    public function __construct(int $week, int $day, string $place, array $time)
    {
        $this->week = $week;
        $this->day = $day;
        $this->place = $place;
        $this->time = $time;
    }
}

class ClassTable
{
    private const CLASS_TABLE_URL = 'http://hdjw.hnu.edu.cn/jsxsd/xskb/xskb_list.do?viweType=1&needData=1&pageNum=1&pageSize=50&demoStr=&baseUrl=%2Fjsxsd&sfykb=2&xsflMapListJsonStr=%E8%AE%B2%E8%AF%BE%E5%AD%A6%E6%97%B6%2C%E6%8C%87%E5%AF%BC%E5%AD%A6%E6%97%B6%2C%E5%AE%9E%E9%AA%8C%E5%AD%A6%E6%97%B6%2C%E5%85%B6%E4%BB%96%2C&zc=&kbjcmsid=1';

    public string $courseName;
    public string $courseId;
    public string $courseType;
    public string $className;
    public string $area;
    public ?string $teacher;
    public float $credit;
    public ?string $extra;
    public int $people;
    public array $schedule;

    public function __construct(array $data)
    {
        $this->courseName = $data['courseName'] ?? '';
        $this->courseId = $data['courseId'] ?? '';
        $this->courseType = $data['courseType'] ?? '';
        $this->className = $data['className'] ?? '';
        $this->area = $data['area'] ?? '';
        $this->teacher = $data['teacher'] ?? null;
        $this->credit = (float)($data['credit'] ?? 0.0);
        $this->extra = $data['extra'] ?? null;
        $this->people = (int)($data['people'] ?? 0);
        $this->schedule = $data['schedule'] ?? [];
    }

    /**
     * @return ClassTable[]
     */
    public static function getClassTable(HdjwToken $token, int $xn, int $xq): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $url = self::CLASS_TABLE_URL . '&xnxqh=' . $xn . '-' . ($xn + 1) . '-' . $xq;
        $response = $client->get($url, [
            'headers' => $headers,
        ]);

        $data = HdjwToken::extractResponseData($response);

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw HnuQueryException::parseError(json_encode($data), "课表数据格式错误");
        }

        $courses = [];
        $pattern = '/周(.)第(.*)节.*\{第(.*)周\}/u';

        foreach ($data['data'] as $item) {
            $places = explode(';', $item['skddmc'] ?? '');
            $detailTimes = explode(';', $item['sktime'] ?? '');
            
            $scheduleMap = [];
            
            foreach ($detailTimes as $i => $time) {
                if (empty($time)) {
                    continue;
                }

                if (!preg_match($pattern, $time, $caps)) {
                    continue;
                }

                $dayChar = $caps[1];
                $day = match($dayChar) {
                    '一' => 1,
                    '二' => 2,
                    '三' => 3,
                    '四' => 4,
                    '五' => 5,
                    '六' => 6,
                    '日', '七' => 7,
                    default => throw HnuQueryException::parseError($time, "上课时间: 未知的星期字符: {$dayChar}"),
                };

                $timeRanges = explode('、', $caps[2]);
                $timeList = [];
                foreach ($timeRanges as $timeRange) {
                    $parts = explode('-', $timeRange);
                    $timeL = (int)$parts[0];
                    $timeR = isset($parts[1]) ? (int)$parts[1] : $timeL;
                    for ($t = $timeL; $t <= $timeR; $t++) {
                        $timeList[] = $t;
                    }
                }

                $weekRanges = explode(',', $caps[3]);
                $weekList = [];
                foreach ($weekRanges as $weekRange) {
                    $parts = explode('-', $weekRange);
                    $weekL = (int)$parts[0];
                    $weekR = isset($parts[1]) ? (int)$parts[1] : $weekL;
                    for ($w = $weekL; $w <= $weekR; $w++) {
                        $weekList[] = $w;
                    }
                }

                $place = $places[$i] ?? '';
                
                foreach ($weekList as $week) {
                    $key = "{$week}-{$day}-{$place}";
                    if (!isset($scheduleMap[$key])) {
                        $scheduleMap[$key] = [
                            'week' => $week,
                            'day' => $day,
                            'place' => $place,
                            'time' => [],
                        ];
                    }
                    $scheduleMap[$key]['time'] = array_unique(array_merge($scheduleMap[$key]['time'], $timeList));
                    sort($scheduleMap[$key]['time']);
                }
            }

            $schedule = [];
            foreach ($scheduleMap as $s) {
                $schedule[] = new CourseSchedule($s['week'], $s['day'], $s['place'], $s['time']);
            }

            $courses[] = new self([
                'courseName' => $item['kc_mc'] ?? '',
                'courseId' => $item['kch'] ?? '',
                'courseType' => $item['kclb'] ?? '',
                'className' => $item['kt_mc'] ?? '',
                'area' => $item['skxqmc'] ?? '',
                'teacher' => empty($item['jg0101mc']) ? null : $item['jg0101mc'],
                'credit' => $item['xf'] ?? 0.0,
                'extra' => empty($item['fzmc']) ? null : $item['fzmc'],
                'people' => $item['xkrs'] ?? 0,
                'schedule' => $schedule,
            ]);
        }

        return $courses;
    }

    public static function getClassTableExtra(HdjwToken $token, int $xn, int $xq): array
    {
        return self::getClassTable($token, $xn, $xq);
    }
}
