<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class Course
{
    private const COURSE_URL = 'http://10.62.106.112/BaseInfo/Course/BindCourseNameForStudent';

    public string $name;
    public ?string $score;
    public string $id;

    private function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->score = $data['score'];
        $this->id = $data['id'];
    }

    /**
     * @return Course[]
     */
    public static function getCourseList(LabToken $token, string $semesterId): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::COURSE_URL, [
            'headers' => $headers,
            'form_params' => [
                'TermID' => $semesterId,
                'StudentID' => $token->getStuId(),
            ]
        ]);

        $rawData = LabToken::extractResponseData($response);

        $result = [];

        foreach ($rawData as $item) {
            $score = ($item['CourseFinalScore'] ?? '') !== '' ? $item['CourseFinalScore'] : null;

            $result[] = new self([
                'name' => $item['CourseName'] ?? '',
                'score' => $score,
                'id' => $item['CourseID'] ?? '',
            ]);
        }

        return $result;
    }
}
