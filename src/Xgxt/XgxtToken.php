<?php

namespace HnuQuery\Xgxt;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

enum Gender: string
{
    case Male = '1';
    case Female = '2';
}

enum Level: string
{
    case Undergraduate = '1';
    case Postgraduate = '2';
    case Doctoral = '3';
}

class PersonalInfo
{
    public string $name;
    public int $enterYear;
    public ?int $xz;
    public string $stuId;
    public Gender $gender;
    public Level $level;
    public string $academy;
    public string $major;
    public string $class;
    public Dormitory $dormitory;
    public ?string $politic;
    public ?string $race;
    public ?string $hometown;
    public ?string $phone;
    public ?string $wechat;
    public ?string $qq;
    public ?string $email;

    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? '';
        $this->enterYear = (int)($data['enterYear'] ?? 0);
        $this->xz = isset($data['xz']) ? (int)$data['xz'] : null;
        $this->stuId = $data['stuId'] ?? '';
        $this->gender = $data['gender'];
        $this->level = $data['level'];
        $this->academy = $data['academy'] ?? '';
        $this->major = $data['major'] ?? '';
        $this->class = $data['class'] ?? '';
        $this->dormitory = $data['dormitory'];
        $this->politic = $data['politic'] ?? null;
        $this->race = $data['race'] ?? null;
        $this->hometown = $data['hometown'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->wechat = $data['wechat'] ?? null;
        $this->qq = $data['qq'] ?? null;
        $this->email = $data['email'] ?? null;
    }
}

class XgxtToken
{
    private const XGXT_LOGIN_URL = 'http://cas.hnu.edu.cn/cas/login?service=http://xgxt.hnu.edu.cn/zftal-xgxt-web/teacher/xtgl/index/check.zf';
    private const USER_INFO_URL = 'https://xgxt.hnu.edu.cn/zftal-xgxt-web/dynamic/form/group/userInfo/default.zf?dataId=null';
    private const IN_SCHOOL_INFO_URL = 'https://xgxt.hnu.edu.cn/zftal-xgxt-web/dynamic/form/group/zxxx/default.zf?dataId=null';
    private const CONTACT_INFO_URL = 'https://xgxt.hnu.edu.cn/zftal-xgxt-web/dynamic/form/group/lxfs1/default.zf?dataId=null';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * @throws HnuQueryException
     */
    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();
        $ticketUrl = $casToken->getTicketUrl(self::XGXT_LOGIN_URL);
        
        // 将 http 替换为 https，与 Rust 版本保持一致
        $ticketUrl = str_replace('http://', 'https://', $ticketUrl);

        $res = $client->get($ticketUrl, ['allow_redirects' => false]);

        if ($res->getStatusCode() !== 302) {
            throw HnuQueryException::unexpectedError("获取学工系统失败，HTTP代码 " . $res->getStatusCode());
        }

        $xgxtCookies = HttpClient::parseCookies($res);
        if (empty($xgxtCookies)) {
            throw HnuQueryException::unexpectedError("获取学工系统失败，接收到空的 cookie");
        }

        $cookieString = HttpClient::buildCookieString($xgxtCookies);

        $headers = [
            'Cookie' => $cookieString
        ];

        return new self($headers);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @throws HnuQueryException
     */
    public function getPersonInfo(): PersonalInfo
    {
        $client = HttpClient::getClient();
        $headers = $this->headers;

        $entries = [];

        $urls = [self::USER_INFO_URL, self::IN_SCHOOL_INFO_URL, self::CONTACT_INFO_URL];
        foreach ($urls as $url) {
            $response = $client->get($url, ['headers' => $headers]);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw HnuQueryException::parseError($body, "学工系统数据解析失败");
            }

            $dataField = $data['data'] ?? null;
            if ($dataField === null) {
                throw HnuQueryException::parseError($body, "学工系统响应数据为空");
            }

            $groupFields = $dataField['groupFields'] ?? null;
            if (!is_array($groupFields) || empty($groupFields)) {
                continue;
            }

            $firstGroupField = $groupFields[0] ?? null;
            if ($firstGroupField === null) {
                continue;
            }

            $fields = $firstGroupField['fields'] ?? [];
            foreach ($fields as $field) {
                $fieldName = $field['fieldName'] ?? null;
                $value = $field['defaultValue'] ?? null;
                if ($fieldName !== null && $value !== null && $value !== '') {
                    $entries[$fieldName] = is_string($value) ? $value : (string)$value;
                }
            }
        }

        $entriesStr = json_encode($entries);

        $name = $entries['姓名'] ?? null;
        if ($name === null) {
            throw HnuQueryException::parseError($entriesStr, '获取姓名失败');
        }

        $enterYearStr = $entries['年级'] ?? null;
        if ($enterYearStr === null) {
            throw HnuQueryException::parseError($entriesStr, '获取年级失败');
        }
        $enterYear = intval($enterYearStr);

        $xzStr = $entries['学制(年)'] ?? '';
        $xz = empty($xzStr) ? null : intval($xzStr);

        $stuId = $entries['学号'] ?? null;
        if ($stuId === null) {
            throw HnuQueryException::parseError($entriesStr, '获取学号失败');
        }

        $genderValue = $entries['性别'] ?? null;
        $gender = match($genderValue) {
            '1' => Gender::Male,
            '2' => Gender::Female,
            default => throw HnuQueryException::parseError($entriesStr, "未知性别值: $genderValue"),
        };

        $levelValue = $entries['培养层次'] ?? null;
        $level = match($levelValue) {
            '1' => Level::Undergraduate,
            '2' => Level::Postgraduate,
            '3' => Level::Doctoral,
            default => throw HnuQueryException::parseError($entriesStr, "未知培养层次: $levelValue"),
        };

        $academy = $entries['学院'] ?? null;
        if ($academy === null) {
            throw HnuQueryException::parseError($entriesStr, '获取学院失败');
        }

        $major = $entries['专业'] ?? null;
        if ($major === null) {
            throw HnuQueryException::parseError($entriesStr, '获取专业失败');
        }

        $class = $entries['班级'] ?? null;
        if ($class === null) {
            throw HnuQueryException::parseError($entriesStr, '获取班级失败');
        }

        $dormitoryRaw = $entries['寝室楼'] ?? null;
        if ($dormitoryRaw === null) {
            throw HnuQueryException::parseError($entriesStr, '获取寝室楼失败');
        }

        $room = $entries['寝室号'] ?? null;
        if ($room === null) {
            throw HnuQueryException::parseError($entriesStr, '获取寝室号失败');
        }

        $dormitory = Dormitory::fromRaw($dormitoryRaw, $room);

        return new PersonalInfo([
            'name' => $name,
            'enterYear' => $enterYear,
            'xz' => $xz,
            'stuId' => $stuId,
            'gender' => $gender,
            'level' => $level,
            'academy' => $academy,
            'major' => $major,
            'class' => $class,
            'dormitory' => $dormitory,
            'politic' => $entries['政治面貌'] ?? null,
            'race' => $entries['民族'] ?? null,
            'hometown' => $entries['籍贯'] ?? null,
            'phone' => $entries['手机号码'] ?? null,
            'wechat' => $entries['微信号'] ?? null,
            'qq' => $entries['QQ号码'] ?? null,
            'email' => $entries['电子邮箱'] ?? null,
        ]);
    }
}
