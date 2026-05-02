<?php

namespace HnuQuery\Lab;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

enum LoginIssue: string
{
    case PasswordError = '密码错误';
    case CaptchaError = '验证码错误';
    case OtherError = '其他错误';
}

interface CaptchaResolver
{
    public function resolve(string $imgBytes): string;
}

class LabToken
{
    private const LOGIN_URL = 'http://10.62.106.112/BaseInfo/Login/ValidateLogin';
    private const CAPTCHA_URL = 'http://10.62.106.112/Ashx/CheckCode.ashx?t=0.29911677684547566';

    private array $headers;
    private string $stuId;

    private function __construct(array $headers, string $stuId)
    {
        $this->headers = $headers;
        $this->stuId = $stuId;
    }

    private static function labEncrypt(string $password): string
    {
        $key = 'DEE5AF962A5D8968B76C37E157D9116B';
        $iv = '1B3A50D231C1851C';
        $encrypted = openssl_encrypt(
            $password,
            'AES-128-CBC',
            hex2bin($key),
            OPENSSL_RAW_DATA,
            hex2bin($iv)
        );
        return bin2hex($encrypted);
    }

    public static function acquireByLogin(
        string $stuId,
        string $password,
        CaptchaResolver $captchaResolver,
        int $maxTried
    ): self {
        $client = HttpClient::getClient();
        $encryptedPassword = self::labEncrypt($password);
        $tried = 0;
        $checkcode = '';
        $allCookies = '';
        $loopResult = null;

        while ($tried < $maxTried) {
            $response = $client->post(self::LOGIN_URL, [
                'form_params' => [
                    'uname' => $stuId,
                    'pwd' => $encryptedPassword,
                    'checkcode' => $checkcode,
                ],
                'headers' => ['Cookie' => $allCookies]
            ]);

            $cookies = HttpClient::parseCookies($response);
            if (!empty($cookies)) {
                $cookieStr = HttpClient::buildCookieString($cookies);
                $allCookies = $allCookies ? $allCookies . '; ' . $cookieStr : $cookieStr;
            }

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw HnuQueryException::parseError($body, '大物实验登录响应格式错误');
            }

            $code = intval($data['RTNCode'] ?? -999);

            if ($code === -2) {
                $res = $client->get(self::CAPTCHA_URL, [
                    'headers' => ['Cookie' => $allCookies]
                ]);
                $imgBytes = $res->getBody()->getContents();
                $checkcode = $captchaResolver->resolve($imgBytes);
                $tried++;
            } else {
                $loopResult = [$code, $data, $allCookies];
                break;
            }
        }

        if ($loopResult === null) {
            throw HnuQueryException::loginError(LoginIssue::CaptchaError->value);
        }

        [$code, $data, $cookies] = $loopResult;

        switch ($code) {
            case 1:
                if (empty($cookies)) {
                    throw HnuQueryException::unexpectedError('Cookie为空');
                }
                $headers = ['Cookie' => $cookies];
                return new self($headers, $stuId);
            case -1:
                throw HnuQueryException::loginError(LoginIssue::PasswordError->value);
            default:
                $msg = $data['Data'] ?? null;
                throw HnuQueryException::loginError(LoginIssue::OtherError->value . ': ' . ($msg ?? '未知错误'));
        }
    }

    public static function fromHeadersUnchecked(array $headers, string $stuId): self
    {
        return new self($headers, $stuId);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStuId(): string
    {
        return $this->stuId;
    }

    public static function extractResponseData(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '大物实验响应格式错误');
        }

        if (intval($data['RTNCode'] ?? 0) !== 1) {
            throw HnuQueryException::parseError($body, '大物实验响应错误: ' . ($data['Message'] ?? '未知错误'));
        }

        return $data['Data'] ?? [];
    }
}
