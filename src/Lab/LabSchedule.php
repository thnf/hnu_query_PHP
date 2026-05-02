<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class LabSchedule
{
    private const SCHEDULE_URL = 'http://10.62.106.112/Student/SelectLab/GetStudentLabInfo';

    public string $seat;
    public string $name;
    public string $course;
    public string $teacher;
    public int $week;
    public int $day;
    public string $dateTime;
    public string $place;
    public ?string $phone;
    public ?string $email;

    private function __construct(array $data)
    {
        $this->seat = $data['seat'];
        $this->name = $data['name'];
        $this->course = $data['course'];
        $this->teacher = $data['teacher'];
        $this->week = $data['week'];
        $this->day = $data['day'];
        $this->dateTime = $data['date_time'];
        $this->place = $data['place'];
        $this->phone = $data['phone'];
        $this->email = $data['email'];
    }

    /**
     * @return LabSchedule[]
     */
    public static function getLabSchedule(LabToken $token): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::SCHEDULE_URL, [
            'headers' => $headers,
            'form_params' => ['StudentID' => $token->getStuId()]
        ]);

        $rawData = LabToken::extractResponseData($response);

        $result = [];

        foreach ($rawData as $item) {
            $day = match ($item['WeekName'] ?? '') {
                '星期一' => 1,
                '星期二' => 2,
                '星期三' => 3,
                '星期四' => 4,
                '星期五' => 5,
                '星期六' => 6,
                '星期日' => 7,
                default => throw HnuQueryException::parseError($item['WeekName'] ?? '', '未知星期'),
            };

            $week = intval($item['Weeks'] ?? 0);
            $dateParts = explode(' ', $item['ClassDate'] ?? '');
            $date = $dateParts[0] ?? '';
            $date = str_replace('/', '-', $date);
            $time = $item['StartTime'] ?? '';
            $dateTime = $date . ' ' . $time;

            $result[] = new self([
                'seat' => $item['SeatNo'] ?? '',
                'name' => $item['LabName'] ?? '',
                'course' => $item['CourseName'] ?? '',
                'teacher' => $item['UserName'] ?? '',
                'week' => $week,
                'day' => $day,
                'date_time' => $dateTime,
                'place' => $item['ClassRoom'] ?? '',
                'phone' => ($item['MobileNum'] ?? '') !== '' ? $item['MobileNum'] : null,
                'email' => ($item['Email'] ?? '') !== '' ? $item['Email'] : null,
            ]);
        }

        return $result;
    }
}