<?php

namespace HnuQuery\Gym;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

enum GradeItemColor: string
{
    case Red = 'red';
    case Yellow = 'yellow';
    case Green = 'green';
    case Gray = 'gray';
}

class EyeGrade
{
    public string $eyesightRight;
    public string $eyesightLeft;
    public string $eyesightRightDetail;
    public string $eyesightLeftDetail;
    public string $eyeMirrorRight;
    public string $eyeMirrorRightDetail;
    public string $eyeMirrorLeft;
    public string $eyeMirrorLeftDetail;
    public string $eyeAmetropiaRight;
    public string $eyeAmetropiaRightDetail;
    public string $eyeAmetropiaLeft;
    public string $eyeAmetropiaLeftDetail;

    public function __construct(array $data)
    {
        $this->eyesightRight = $data['eyesight_right'];
        $this->eyesightLeft = $data['eyesight_left'];
        $this->eyesightRightDetail = $data['eyesight_right_detail'];
        $this->eyesightLeftDetail = $data['eyesight_left_detail'];
        $this->eyeMirrorRight = $data['eye_mirror_right'];
        $this->eyeMirrorRightDetail = $data['eye_mirror_right_detail'];
        $this->eyeMirrorLeft = $data['eye_mirror_left'];
        $this->eyeMirrorLeftDetail = $data['eye_mirror_left_detail'];
        $this->eyeAmetropiaRight = $data['eye_ametropia_right'];
        $this->eyeAmetropiaRightDetail = $data['eye_ametropia_right_detail'];
        $this->eyeAmetropiaLeft = $data['eye_ametropia_left'];
        $this->eyeAmetropiaLeftDetail = $data['eye_ametropia_left_detail'];
    }
}

class GradeItem
{
    public GradeItemColor $color;
    public string $rank;
    public string $grade;
    public float $score;

    public function __construct(array $data)
    {
        $this->color = $data['color'];
        $this->rank = $data['rank'];
        $this->grade = $data['grade'];
        $this->score = $data['score'];
    }
}

class Grade
{
    private const GRADE_SUMMARY_URL = 'http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Report/getStudentScore';
    private const GRADE_DETAIL_URL = 'http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Report/getEyeDetails';

    public string $name;
    public string $stuId;
    public string $grade;
    public float $score;
    public string $reportDesc;
    public string $reportStatus;
    public string $reportType;
    public EyeGrade $eye;
    public GradeItem $shortRun;
    public GradeItem $bmi;
    public GradeItem $jump;
    public GradeItem $pullAndSit;
    public GradeItem $run;
    public GradeItem $sitAndReach;
    public GradeItem $vc;

    private function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->stuId = $data['stu_id'];
        $this->grade = $data['grade'];
        $this->score = $data['score'];
        $this->reportDesc = $data['report_desc'];
        $this->reportStatus = $data['report_status'];
        $this->reportType = $data['report_type'];
        $this->eye = $data['eye'];
        $this->shortRun = $data['short_run'];
        $this->bmi = $data['bmi'];
        $this->jump = $data['jump'];
        $this->pullAndSit = $data['pull_and_sit'];
        $this->run = $data['run'];
        $this->sitAndReach = $data['sit_and_reach'];
        $this->vc = $data['vc'];
    }

    private static function itemGradeIntoColor(string $grade): GradeItemColor
    {
        return match ($grade) {
            '优秀' => GradeItemColor::Green,
            '良好' => GradeItemColor::Green,
            '及格' => GradeItemColor::Yellow,
            '不及格' => GradeItemColor::Red,
            '缺项' => GradeItemColor::Gray,
            default => GradeItemColor::Gray,
        };
    }

    private static function itemClassIntoColor(string $class): GradeItemColor
    {
        return match ($class) {
            'c100' => GradeItemColor::Green,
            'c80' => GradeItemColor::Green,
            'c60' => GradeItemColor::Yellow,
            'c0' => GradeItemColor::Red,
            default => GradeItemColor::Gray,
        };
    }

    public static function getGrade(GymToken $token, int $yearNum): self
    {
        $client = HttpClient::getClient();
        $headers = $token->getHeaders();

        $response = $client->post(self::GRADE_SUMMARY_URL, [
            'headers' => $headers,
            'form_params' => ['year_num' => $yearNum]
        ]);
        $summaryData = GymToken::extractResponseData($response);

        $response = $client->post(self::GRADE_DETAIL_URL, [
            'headers' => $headers,
            'form_params' => ['year_num' => $yearNum]
        ]);
        $detailData = GymToken::extractResponseData($response);

        $eyeGrade = new EyeGrade([
            'eyesight_right' => $detailData['eyesight_right'] ?? '',
            'eyesight_left' => $detailData['eyesight_left'] ?? '',
            'eyesight_right_detail' => $detailData['eyesight_right_detail'] ?? '',
            'eyesight_left_detail' => $detailData['eyesight_left_detail'] ?? '',
            'eye_mirror_right' => $detailData['eye_mirror_right'] ?? '',
            'eye_mirror_right_detail' => $detailData['eye_mirror_right_detail'] ?? '',
            'eye_mirror_left' => $detailData['eye_mirror_left'] ?? '',
            'eye_mirror_left_detail' => $detailData['eye_mirror_left_detail'] ?? '',
            'eye_ametropia_right' => $detailData['eye_ametropia_right'] ?? '',
            'eye_ametropia_right_detail' => $detailData['eye_ametropia_right_detail'] ?? '',
            'eye_ametropia_left' => $detailData['eye_ametropia_left'] ?? '',
            'eye_ametropia_left_detail' => $detailData['eye_ametropia_left_detail'] ?? '',
        ]);

        $createGradeItem = function ($prefix, $summaryData, $detailData) {
            $class = $summaryData["{$prefix}_class"] ?? '';
            $score = $detailData["{$prefix}_score"] ?? 0;
            return new GradeItem([
                'color' => self::itemClassIntoColor($class),
                'rank' => '',
                'grade' => $class,
                'score' => floatval($score),
            ]);
        };

        return new self([
            'name' => $detailData['student_name'] ?? '',
            'stu_id' => $detailData['student_num'] ?? '',
            'grade' => $detailData['total_grade'] ?? '',
            'score' => floatval($detailData['total_score'] ?? 0),
            'report_desc' => $summaryData['report_desc'] ?? '',
            'report_status' => $summaryData['report_status'] ?? '',
            'report_type' => $summaryData['report_type'] ?? '',
            'eye' => $eyeGrade,
            'short_run' => $createGradeItem('short_run', $summaryData, $detailData),
            'bmi' => $createGradeItem('bmi', $summaryData, $detailData),
            'jump' => $createGradeItem('jump', $summaryData, $detailData),
            'pull_and_sit' => $createGradeItem('pull_and_sit', $summaryData, $detailData),
            'run' => $createGradeItem('run', $summaryData, $detailData),
            'sit_and_reach' => $createGradeItem('sit_and_reach', $summaryData, $detailData),
            'vc' => $createGradeItem('vc', $summaryData, $detailData),
        ]);
    }
}
