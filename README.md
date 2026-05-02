# HNU Query PHP 版本

湖南大学校内系统查询库 - PHP版本

本项目是 Rust 版本 [hnu_query](https://github.com/qnxg/hnu_query) 的 PHP 转写，提供湖南大学校内各系统的数据查询能力。

**注意：需要在湖南大学校园网内或使用湖南大学VPN才能使用本项目。**

## 项目状态

✅ **已完成功能**：
- 教务系统（课表、成绩、考试安排、空教室、排名）
- 学工系统（个人信息、宿舍信息）
- 校园网流量系统
- 个人门户（校园卡、邮箱）
- 体测系统
- 宿舍电量查询

⚠️ **当前版本**：v1.0.0 (严格按照Rust版本实现)

## 系统要求

- PHP 8.1+
- Composer
- 校园VPN连接

## 功能支持

### 可信电子凭证平台（当前版本存在错误，等待修复）
- 获取本科生主修课程的可信电子凭证排名信息

### 体测系统
- 获取体测预约信息
- 获取体测成绩

### 教务系统
- 获取课表
- 获取无课表课程
- 获取空教室
- 获取考试安排
- 获取课程成绩
- 获取课程成绩详情分数
- 获取排名

### 大物实验平台（当前版本未构建）
- 获取课程列表
- 获取实验安排
- 获取实验成绩

### 校园网流量系统
- 获取校园网流量明细
- 获取校园网流量账单
- 获取校园网欠费金额
- 获取当月校园网流量的使用情况
- 获取校园网流量锁定状态

### 个人门户
- 获取校园卡信息
- 获取校园卡消费历史
- 获取学校邮箱的未读邮件数

### 学工系统
- 获取个人信息

### 其他
- 获取宿舍电量

## 安装

1. 克隆项目或下载源代码
2. 安装依赖：

```bash
composer install
```

3. 配置学号和密码（可选，也可以在代码中设置）

## 快速开始

### 基础使用示例

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use HnuQuery\Cas\CasToken;
use HnuQuery\Hdjw\HdjwToken;
use HnuQuery\Hdjw\Grade;
use HnuQuery\Error\HnuQueryException;

$stuId = '你的学号';
$password = '个人门户密码';

try {
    // 1. 创建统一身份认证系统的令牌
    $casToken = new CasToken($stuId, $password);
    
    // 2. 通过统一身份认证系统登录来获得教务系统的令牌
    $hdjwToken = HdjwToken::acquireByCasLogin($casToken);
    
    // 3. 获取 2025-2026 学年秋季学期的课程成绩
    $grades = Grade::getGrade($hdjwToken, 2025, 1);
    
    echo "=== 课程成绩 ===\n";
    foreach ($grades as $grade) {
        echo "课程: {$grade->courseName}, 成绩: {$grade->score}, 学分: {$grade->credit}\n";
    }
} catch (HnuQueryException $e) {
    echo "错误: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
}
```

### 完整功能演示

运行完整的演示程序查看所有功能：

```bash
php examples/demo.php
```

演示程序将展示以下功能：
1. 统一身份认证登录
2. 教务系统功能（成绩、课表、考试安排、空教室、排名）
3. 学工系统个人信息
4. 宿舍电量查询
5. 可信电子凭证排名
6. 体测系统信息
7. 大物实验平台信息
8. 校园网流量信息
9. 个人门户信息

## 详细使用说明

### 1. 统一身份认证 (CAS)

所有系统都通过统一身份认证系统进行登录：

```php
use HnuQuery\Cas\CasToken;

$casToken = new CasToken('你的学号', '你的密码');
```

### 2. 教务系统功能

#### 获取成绩
```php
use HnuQuery\Hdjw\HdjwToken;
use HnuQuery\Hdjw\Grade;

$hdjwToken = HdjwToken::acquireByCasLogin($casToken);
$grades = Grade::getGrade($hdjwToken, 2025, 1); // 2025-2026学年秋季学期
```

#### 获取课表
```php
use HnuQuery\Hdjw\ClassTable;

$classTable = ClassTable::getClassTable($hdjwToken, 2025, 1);
```

#### 获取考试安排
```php
use HnuQuery\Hdjw\ExamSchedule;

$examSchedule = ExamSchedule::getExamSchedule($hdjwToken, 2025, 1);
```

#### 获取空教室
```php
use HnuQuery\Hdjw\EmptyClassroom;

// 查询综合楼第1周周一的空教室
$emptyRooms = EmptyClassroom::getEmptyClassroom($hdjwToken, '106', 1, 1, [1], 2025, 1);
```

#### 获取排名
```php
use HnuQuery\Hdjw\Rank;
use HnuQuery\Hdjw\RankRange;
use HnuQuery\Hdjw\RankMethod;

// 获取专业核心课排名（加权平均分）
$selections = [['xn' => 2025, 'xq' => 1]];
$ranges = RankRange::coreV2024Course();
$rank = Rank::getRank($hdjwToken, $selections, $ranges, RankMethod::WeightedAvg());
```

### 3. 学工系统功能

#### 获取个人信息
```php
use HnuQuery\Xgxt\XgxtToken;

$xgxtToken = XgxtToken::acquireByCasLogin($casToken);
$personInfo = $xgxtToken->getPersonInfo();

echo "姓名: {$personInfo->name}\n";
echo "学号: {$personInfo->stuId}\n";
echo "宿舍: {$personInfo->dormitory->getRawDormitory()} {$personInfo->dormitory->getRoom()}\n";
```

#### 查询宿舍电量
```php
use HnuQuery\Wxpay\Electricity;

if ($personInfo->dormitory->successfullyParsed()) {
    $electricity = Electricity::getElectricity($personInfo->dormitory);
    echo "宿舍电量: {$electricity} kWh\n";
}
```

### 4. 可信电子凭证排名（存在错误）

```php
use HnuQuery\Ca\CaToken;
use HnuQuery\Ca\Rank as CaRank;

$caToken = CaToken::acquireByCasLogin($casToken);
$caRank = CaRank::getGradeRank($caToken);

echo "全部课程GPA: {$caRank->allGpa} (排名: {$caRank->allGpaRank})\n";
echo "全部课程加权: {$caRank->allWeighted} (排名: {$caRank->allWeightedRank})\n";
```

### 5. 体测系统

```php
use HnuQuery\Gym\GymToken;
use HnuQuery\Gym\Grade as GymGrade;

$gymToken = GymToken::acquireByCasLogin($casToken);
$gymGrade = GymGrade::getGrade($gymToken, 2025);

echo "体测总分: {$gymGrade->score}分 ({$gymGrade->grade})\n";
```

### 6. 大物实验平台（未开放）

```php
use HnuQuery\Lab\LabToken;
use HnuQuery\Lab\LabGrade;

$labToken = LabToken::acquireByCasLogin($casToken);
$labGrade = LabGrade::getGrade($labToken);

echo "大物实验成绩: {$labGrade->score}分\n";
```

### 7. 校园网流量系统

```php
use HnuQuery\Netflow\NetflowToken;
use HnuQuery\Netflow\ThisMonthInfo;

$netflowToken = NetflowToken::acquireByCasLogin($casToken);
$netflowInfo = ThisMonthInfo::getThisMonthInfo($netflowToken);

echo "本月已用流量: {$netflowInfo->used}MB\n";
echo "剩余流量: {$netflowInfo->remaining}MB\n";
```

### 8. 个人门户

```php
use HnuQuery\Pt\PtToken;
use HnuQuery\Pt\CardInfo;

$ptToken = PtToken::acquireByCasLogin($casToken);
$cardInfo = CardInfo::getCardInfo($ptToken);

echo "校园卡余额: {$cardInfo->balance}元\n";
```

## 模块说明

### Cas 统一身份认证
- `CasToken` - 统一身份认证令牌，负责CAS登录和获取各系统的ticket
- 所有系统都通过CAS进行统一认证
- 支持自动重试和错误处理

### Hdjw 教务系统
- `HdjwToken` - 教务系统令牌
- `Grade` - 成绩查询（支持多学期查询）
- `ClassTable` - 课表查询（支持周次和学期）
- `ExamSchedule` - 考试安排查询
- `EmptyClassroom` - 空教室查询（支持教学楼、周次、时间段）
- `Rank` - 排名查询（支持多种排名方式和课程范围）
- `RankRange` - 排名范围定义（专业核心课、通识课等）
- `RankMethod` - 排名方法定义（加权平均、算术平均、GPA）

### Gym 体测系统
- `GymToken` - 体测系统令牌
- `Grade` - 体测成绩查询（总分、各项目成绩）
- `Appointment` - 体测预约信息查询

### Lab 大物实验（未开放）
- `LabToken` - 大物实验系统令牌
- `Semester` - 学期信息查询
- `Course` - 实验课程查询
- `LabSchedule` - 实验安排查询
- `LabGrade` - 实验成绩查询

### Netflow 校园网流量
- `NetflowToken` - 校园网流量系统令牌
- `UserInfo` - 用户基本信息
- `ThisMonthInfo` - 当月流量使用情况
- `Detail` - 流量使用明细
- `Order` - 流量套餐订单
- `PayInfo` - 缴费信息

### Pt 个人门户
- `PtToken` - 个人门户令牌
- `CardInfo` - 校园卡信息（余额、状态）
- `CardHistory` - 校园卡消费历史
- `Email` - 学校邮箱未读邮件数

### Xgxt 学工系统
- `XgxtToken` - 学工系统令牌
- `PersonInfo` - 个人信息查询（姓名、学号、宿舍等）
- `Dormitory` - 宿舍信息解析

### Wxpay 宿舍电量
- `Electricity` - 宿舍电量查询（需要有效的宿舍信息）

### Ca 可信电子凭证（存在错误）
- `CaToken` - 可信电子凭证系统令牌
- `Rank` - 可信电子凭证排名查询（GPA、加权平均分等）

## 错误处理

### 异常类型

所有异常都抛出 `HnuQueryException`，包含以下错误类型：

- `UNEXPECTED` - 意料之外的错误
- `NETWORK_ERROR` - 网络请求错误
- `PARSE_ERROR` - 数据解析错误
- `OTHER` - 其他错误

### 错误处理示例

```php
try {
    $casToken = new CasToken($stuId, $password);
    $hdjwToken = HdjwToken::acquireByCasLogin($casToken);
    $grades = Grade::getGrade($hdjwToken, 2025, 1);
} catch (HnuQueryException $e) {
    echo "错误类型: " . $e->getType() . "\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误原因: " . ($e->getReason() ?? '无') . "\n";
    
    if ($e->getPrevious()) {
        echo "原始错误: " . $e->getPrevious()->getMessage() . "\n";
    }
}
```

### 常见错误及解决方法

1. **网络连接错误**
   - 确保在校园网环境或使用VPN
   - 检查网络连接状态

2. **认证失败**
   - 检查学号和密码是否正确
   - 确认账户状态正常

3. **服务器错误**
   - 可能是系统维护或接口变更
   - 稍后重试或联系系统管理员

日志包含以下信息：
- API请求URL和参数
- 响应状态码和内容
- 错误信息和堆栈跟踪
- 数据处理过程

## 注意事项

1. **安全性**：不要在公共代码中硬编码学号和密码
2. **网络环境**：必须在校园网或VPN环境下使用
3. **接口稳定性**：校内系统接口可能随时变更
4. **使用频率**：避免频繁请求，以免被系统限制
5. **数据准确性**：数据仅供参考，以官方系统为准

## 贡献指南

1. Fork 项目
2. 创建功能分支
3. 提交更改
4. 推送到分支
5. 创建 Pull Request

## 更新日志

### v1.0.0 (2026-05-02)
- 初始版本发布
- 按照Rust版本实现所有功能
- 修复已知的数据解析错误
- 添加详细的调试日志功能

## 错误处理

所有异常都抛出 `HnuQueryException`，包含以下错误类型：

- `UNEXPECTED` - 意料之外的错误
- `NETWORK_ERROR` - 网络请求错误
- `PARSE_ERROR` - 数据解析错误
- `OTHER` - 其他错误

## 许可证

本项目基于 AGPL-3.0 协议。所有基于本项目的代码必须开源。
