<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class LabGrade
{
    private const SCORE_URL = 'http://10.62.106.112/Student/Score/GetStudentLabScoreForCourse';
    private const SCORE_DETAIL_URL = 'http://10.62.106.112/Student/Score/GetStudentLabScoreDetail';
    private const SCORE_STRUCTURE_URL = 'http://10.62.106.112/Student/Score/GetStudentLabScoreStructure';

    public string $labName;
    public string $score;
    public ?string $attendance;
    /** @var LabGradeDetailItem[] */
    public array $details;

    private function __construct(array $data)
    {
        $this->labName = $data['lab_name'];
        $this->score = $data['score'];
        $this->attendance = $data['attendance'];
        $this->details = $data['details'];
    }

    /**
     * @return LabGrade[]
     */
    public static function getLabGrade(LabToken $token, string $courseId, string $semesterId): array
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::SCORE_URL, [
            'headers' => $headers,
            'form_params' => [
                'CourseID' => $courseId,
                'TermID' => $semesterId,
                'StudentID' => $token->getStuId(),
            ]
        ]);
        $labScore = LabToken::extractResponseData($response);

        $response = $client->post(self::SCORE_DETAIL_URL, [
            'headers' => $headers,
            'form_params' => [
                'CourseID' => $courseId,
                'StudentID' => $token->getStuId(),
            ]
        ]);
        $labScoreDetail = LabToken::extractResponseData($response);

        $response = $client->post(self::SCORE_STRUCTURE_URL, [
            'headers' => $headers,
            'form_params' => ['CourseID' => $courseId]
        ]);
        $labScoreStructure = LabToken::extractResponseData($response);

        $scoreStructureMap = [];
        foreach ($labScoreStructure as $item) {
            $id = intval($item['LabScoreStructureID'] ?? 0);
            $scoreStructureMap[$id] = $item['LabScoreStructureName'] ?? '';
        }

        $labMap = [];
        $result = [];

        foreach ($labScore as $item) {
            $labScoreValue = $item['LabScore'] ?? '';
            $classRoom = $item['ClassRoom'] ?? '';
            if ($labScoreValue === '' || str_contains($classRoom, '虚拟')) {
                continue;
            }

            $labId = intval($item['LabID'] ?? 0);
            $attendance = ($item['AttendanceName'] ?? '') !== '' ? $item['AttendanceName'] : null;

            $result[] = new self([
                'lab_name' => $item['LabName'] ?? '',
                'score' => $labScoreValue,
                'attendance' => $attendance,
                'details' => [],
            ]);
            $labMap[$labId] = count($result) - 1;
        }

        foreach ($labScoreDetail as $item) {
            $labId = intval($item['LabID'] ?? 0);
            $structureId = intval($item['LabScoreStructureID'] ?? 0);
            $structureScore = isset($item['LabStructureScore']) ? floatval($item['LabStructureScore']) : null;

            if ($structureScore === null) {
                continue;
            }

            if (isset($labMap[$labId]) && isset($scoreStructureMap[$structureId])) {
                $index = $labMap[$labId];
                $result[$index]->details[] = new LabGradeDetailItem([
                    'name' => $scoreStructureMap[$structureId],
                    'score' => $structureScore,
                ]);
            }
        }

        return $result;
    }
}