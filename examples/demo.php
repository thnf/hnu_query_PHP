<?php

require_once __DIR__ . '/../vendor/autoload.php';

use HnuQuery\Cas\CasToken;
use HnuQuery\Ca\CaToken;
use HnuQuery\Ca\Rank as CaRank;
use HnuQuery\Hdjw\HdjwToken;
use HnuQuery\Hdjw\Grade;
use HnuQuery\Hdjw\ClassTable;
use HnuQuery\Hdjw\ExamSchedule;
use HnuQuery\Hdjw\Rank;
use HnuQuery\Hdjw\RankRange;
use HnuQuery\Hdjw\RankMethod;
use HnuQuery\Hdjw\EmptyClassroom;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Xgxt\XgxtToken;
use HnuQuery\Wxpay\Electricity;
use HnuQuery\Gym\GymToken;
use HnuQuery\Gym\Grade as GymGrade;
use HnuQuery\Gym\Appointment as GymAppointment;
use HnuQuery\Lab\LabToken;
use HnuQuery\Lab\Semester as LabSemester;
use HnuQuery\Lab\Course as LabCourse;
use HnuQuery\Lab\LabSchedule as LabSchedule;
use HnuQuery\Lab\LabGrade as LabGrade;
use HnuQuery\Netflow\NetflowToken;
use HnuQuery\Netflow\ThisMonthInfo;
use HnuQuery\Netflow\UserInfo as NetflowUserInfo;
use HnuQuery\Netflow\Detail as NetflowDetail;
use HnuQuery\Netflow\Order as NetflowOrder;
use HnuQuery\Netflow\PayInfo;
use HnuQuery\Pt\PtToken;
use HnuQuery\Pt\CardInfo;
use HnuQuery\Pt\CardHistory;
use HnuQuery\Pt\CardHistoryType;
use HnuQuery\Pt\Email;

$stuId = '';//学号
$password = '';//密码

echo "=== 湖南大学校内系统查询库 PHP 版本 ===\n\n";

try {
    echo "1. 创建统一身份认证令牌...\n";
    $casToken = new CasToken($stuId, $password);
    echo "   令牌创建成功\n\n";

    echo "2. 获取教务系统令牌...\n";
    $hdjwToken = HdjwToken::acquireByCasLogin($casToken);
    echo "   教务系统登录成功\n\n";

      echo "3. 获取 2025-2026 学年秋季学期课程成绩...\n";
    try {
        $grades = Grade::getGrade($hdjwToken, 2025, 1);
        echo "   共获取到 " . count($grades) . " 门课程成绩：\n";
        foreach ($grades as $grade) {
            echo "   - {$grade->courseName}: {$grade->score}分 (绩点: {$grade->gpa}, 学分: {$grade->credit}, 类型: {$grade->gradeType})\n";
            if ($grade->gradeTag) {
                echo "     * 成绩标识: {$grade->gradeTag}\n";
            }
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
        echo "   错误代码: {$e->getCode()}\n";
        if ($e->getReason()) {
            echo "   原因: {$e->getReason()}\n";
        }
        if ($e->getData()) {
            $data = json_decode($e->getData(), true);
            if ($data && is_array($data)) {
                echo "   响应数据预览: " . json_encode(array_slice($data, 0, 5), JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "   响应数据: " . substr($e->getData(), 0, 200) . "...\n";
            }
        }
    }
    echo "\n";

    echo "4. 获取 2025-2026 学年秋季学期课表...\n";
    try {
        $classes = ClassTable::getClassTable($hdjwToken, 2025, 1);
        echo "   共获取到 " . count($classes) . " 门课程：\n";
        foreach ($classes as $class) {
            echo "   - {$class->courseName} (教师: {$class->teacher}, 学分: {$class->credit})\n";
            foreach ($class->schedule as $s) {
                $timeStr = implode(',', $s->time);
                echo "     * 第{$s->week}周 周{$s->day} 第{$timeStr}节 @ {$s->place}\n";
            }
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "5. 获取考试安排...\n";
    try {
        $exams = ExamSchedule::getExamSchedule($hdjwToken, 2025, 2);
        echo "   共获取到 " . count($exams) . " 门考试安排：\n";
        foreach ($exams as $exam) {
            echo "   - {$exam->courseName}: {$exam->campus} {$exam->examTime} (地点: {$exam->examLocation}, 座位: {$exam->seatNumber})\n";
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "6. 获取专业核心课排名 (2024方案)...\n";
    try {
        //支持多个学年学期和多个课程范围
        $selections = [['xn' => 2025, 'xq' => 1]];
        $ranges = RankRange::coreV2024Course();
        
        //使用加权平均分
        $rankWeighted = Rank::getRank($hdjwToken, $selections, $ranges, RankMethod::WeightedAvg());
        if ($rankWeighted) {
            echo "   专业核心课排名 (加权平均分):\n";
            echo "     分数: {$rankWeighted->score}, 排名: {$rankWeighted->rank}\n";
        }
        
        // 算术平均分
        $rankArithmetic = Rank::getRank($hdjwToken, $selections, $ranges, RankMethod::ArithmeticAvg());
        if ($rankArithmetic) {
            echo "   专业核心课排名 (算术平均分):\n";
            echo "     分数: {$rankArithmetic->score}, 排名: {$rankArithmetic->rank}\n";
        }
        
        // 平均学分绩点
        $rankGPA = Rank::getRank($hdjwToken, $selections, $ranges, RankMethod::ByGPA());
        if ($rankGPA) {
            echo "   专业核心课排名 (平均学分绩点):\n";
            echo "     分数: {$rankGPA->score}, 排名: {$rankGPA->rank}\n";
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "7. 查询空教室 (综合楼 第1周 周一 第1大节)...\n";
    try {
        $emptyRooms = EmptyClassroom::getEmptyClassroom($hdjwToken, '106', 1, 1, [1], 2025, 1);
        echo "   共找到 " . count($emptyRooms) . " 个空教室：\n";
        foreach (array_slice($emptyRooms, 0, 5) as $room) {
            echo "   - {$room->roomName} (类型: {$room->roomType}, 座位: {$room->seatCount})\n";
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "8. 获取学工系统个人信息...\n";
    try {
        $xgxtToken = XgxtToken::acquireByCasLogin($casToken);
        $personInfo = $xgxtToken->getPersonInfo();
        echo "   姓名: {$personInfo->name}\n";
        echo "   学号: {$personInfo->stuId}\n";
        echo "   年级: {$personInfo->enterYear}\n";
        echo "   性别: {$personInfo->gender->name}\n";
        echo "   培养层次: {$personInfo->level->name}\n";
        echo "   宿舍: {$personInfo->dormitory->getRawDormitory()} {$personInfo->dormitory->getRoom()}\n";
        if ($personInfo->phone) echo "   手机: {$personInfo->phone}\n";
        if ($personInfo->email) echo "   邮箱: {$personInfo->email}\n";

        if ($personInfo->dormitory->successfullyParsed()) {
            echo "\n9. 查询宿舍电量...\n";
            try {
                $electricity = Electricity::getElectricity($personInfo->dormitory);
                echo "   电量: {$electricity} kWh\n";
            } catch (HnuQueryException $e) {
                echo "   查询失败: {$e->getMessage()}\n";
                if ($e->getPrevious()) {
                    echo "   原始错误: {$e->getPrevious()->getMessage()}\n";
                }
                if ($e->getReason()) {
                    echo "   原因: {$e->getReason()}\n";
                }
            }
        } else {
            echo "\n9. 查询宿舍电量...\n";
            echo "   跳过: 宿舍信息解析失败\n";
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";
    echo "10. 获取可信电子凭证排名...\n";
    try {
        $caToken = CaToken::acquireByCasLogin($casToken);
        $caRank = CaRank::getGradeRank($caToken);
        echo "   全部课程GPA: {$caRank->allGpa} (排名: {$caRank->allGpaRank})\n";
        echo "   全部课程加权: {$caRank->allWeighted} (排名: {$caRank->allWeightedRank})\n";
        echo "   必修课GPA: {$caRank->mustGpa}\n";
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "11. 获取体测系统信息...\n";
try {
        $gymToken = GymToken::acquireByCasLogin($casToken);
        $gymGrade = GymGrade::getGrade($gymToken, 2025);
        echo "   体测总分: {$gymGrade->score}分 ({$gymGrade->grade})\n";
        echo "   50米: {$gymGrade->shortRun->grade} ({$gymGrade->shortRun->score}分)\n";
        echo "   长跑: {$gymGrade->run->grade} ({$gymGrade->run->score}分)\n";
        echo "   BMI: {$gymGrade->bmi->grade}\n";
        echo "   肺活量: {$gymGrade->vc->grade}\n";

        $gymAppointments = GymAppointment::getAppointment($gymToken);
        echo "   体测预约: " . count($gymAppointments) . " 项预约\n";
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "12. 获取校园网流量信息...\n";
    try {
        $netflowToken = NetflowToken::acquireByCasLogin($casToken);
        $usage = ThisMonthInfo::getThisMonthInfo($netflowToken);
        echo "   总流量使用: {$usage->totalUsage}\n";
        echo "   上传: {$usage->uploadUsage}, 下载: {$usage->downloadUsage}\n";
        echo "   免费包: {$usage->basePackageUsage}/{$usage->basePackageAmount}GB (使用率: " . round($usage->basePackageUsagePercentage * 100, 2) . "%)\n";
        echo "   超出资费包: {$usage->extendPackageUsage}/{$usage->extendPackageAmount}GB\n";

        $unlockStatus = NetflowUserInfo::getUnlockStatus($netflowToken);
        echo "   账号状态: {$unlockStatus->name}\n";

        $overdue = PayInfo::getOverduePayment($netflowToken);
        echo "   欠费金额: {$overdue}元\n";
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "13. 获取个人门户校园卡和邮件信息...\n";
    try {
        $ptToken = PtToken::acquireByCasLogin($casToken);
        $cardInfo = CardInfo::getCardInfo($ptToken);
        echo "   校园卡账号: {$cardInfo->id}\n";
        echo "   校园卡余额: {$cardInfo->balance}元\n";

        $unreadEmail = Email::getUnreadEmailCount($ptToken);
        if ($unreadEmail !== null) {
            echo "   未读邮件: {$unreadEmail}封\n";
        } else {
            echo "   未绑定邮箱，请前往个人门户绑定\n";
        }
    } catch (HnuQueryException $e) {
        echo "   获取失败: {$e->getMessage()}\n";
    }
    echo "\n";

    echo "14. 大物实验系统 (需手动提供账号密码)...\n";
    echo "   请实例化CaptchaResolver接口后使用:\n";
    echo "   LabToken::acquireByLogin(\$stuId, \$password, \$captchaResolver, 5)\n";
    echo "   支持功能: 学期列表、课程列表、实验安排、实验成绩\n";
    echo "\n";

    echo "=== 所有查询完成 ===\n";
    echo "\n模块清单:\n";
    echo "  ✅ CAS统一身份认证\n";
    echo "  ✅ 教务系统 (成绩, 课表, 考试, 排名, 空教室)\n";
    echo "  ✅ 学工系统 (个人信息, 宿舍解析)\n";
    echo "  ✅ 宿舍电量查询 (完整ID映射表)\n";
    echo "  ✅ 可信电子凭证排名 (PDF解析)\n";
    echo "  ✅ 体测系统 (成绩, 预约)\n";
    echo "  ✅ 大物实验系统 (验证码识别, 学期/课程/安排/成绩)\n";
    echo "  ✅ 校园网系统 (本月流量, 日/月明细, 账单, 欠费, 锁定状态)\n";
    echo "  ✅ 个人门户 (校园卡余额/消费记录, 未读邮件)\n";

} catch (HnuQueryException $e) {
    echo "\n错误: {$e->getMessage()}\n";
    echo "错误代码: {$e->getCode()}\n";
    if ($e->getPrevious()) {
        echo "原始错误: {$e->getPrevious()->getMessage()}\n";
    }
    if ($e->getReason()) {
        echo "原因: {$e->getReason()}\n";
    }
} catch (\Exception $e) {
    echo "\n异常: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n";
}
