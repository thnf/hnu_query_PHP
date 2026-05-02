<?php

namespace HnuQuery\Gym;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Appointment
{
    private const APPOINT_URL = 'http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Appoint/getStudentClass';
    private const DETAIL_URL = 'http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Appoint/getSchoolFitClassDetail';

    public string $name;
    public string $desc;
    public string $showDate;
    public string $date;
    public string $time;
    public int $testType;
    public int $status;

    private function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->desc = $data['desc'];
        $this->showDate = $data['show_date'];
        $this->date = $data['date'];
        $this->time = $data['time'];
        $this->testType = $data['test_type'];
        $this->status = $data['status'];
    }

    /**
     * @return Appointment[]
     */
    public static function getAppointment(GymToken $token): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::APPOINT_URL, ['headers' => $headers]);
        $listData = GymToken::extractResponseData($response);

        $result = [];

        foreach ($listData as $item) {
            $classId = $item['class_id'] ?? 0;
            $classTime = $item['class_time'] ?? '';
            $testTime = $item['test_time'] ?? '';

            $response = $client->post(self::DETAIL_URL, [
                'headers' => $headers,
                'form_params' => [
                    'class_id' => $classId,
                    'class_time' => $classTime,
                    'test_time' => $testTime,
                ]
            ]);
            $detailData = GymToken::extractResponseData($response);

            $result[] = new self([
                'name' => $item['class_name'] ?? '',
                'desc' => $detailData['class_desc'] ?? '',
                'show_date' => $item['show_time'] ?? '',
                'date' => $classTime,
                'time' => $testTime,
                'test_type' => intval($detailData['appo_type'] ?? 0),
                'status' => intval($item['button_status'] ?? 0),
            ]);
        }

        return $result;
    }
}
