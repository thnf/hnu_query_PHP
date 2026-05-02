<?php

namespace HnuQuery\Cas;

use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;
use phpseclib3\Math\BigInteger;

class CasToken
{
    private const PUBKEY_URL = 'http://cas.hnu.edu.cn/cas/v2/getPubKey';
    private const SERVICE_URL = 'http://cas.hnu.edu.cn/cas/login?service=http://cas.hnu.edu.cn/system/login/login.zf';

    private ?string $cookie;
    private string $stuId;
    private string $password;

    public function __construct(string $stuId, string $password)
    {
        $this->stuId = $stuId;
        $this->password = $password;
        $this->cookie = null;
    }

    public static function fromCookieUnchecked(string $cookie, string $stuId, string $password): self
    {
        $token = new self($stuId, $password);
        $token->cookie = $cookie;
        return $token;
    }

    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    public function getStuId(): string
    {
        return $this->stuId;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public static function rsaEncrypt(string $password, string $exponent, string $modulus): string
    {
        $passwordHex = bin2hex($password);
        $passwordInt = new BigInteger($passwordHex, 16);
        $eInt = new BigInteger($exponent, 16);
        $mInt = new BigInteger($modulus, 16);

        $resultInt = $passwordInt->modPow($eInt, $mInt);
        $result = strtolower($resultInt->toHex());

        return str_pad($result, 128, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{type: 'success'|'skip', data: mixed}
     * @throws HnuQueryException
     */
    private function getLoginParams(string $serviceUrl): array
    {
        $client = HttpClient::getClient();
        $cookieJar = HttpClient::getCookieJar();
        $cookieJar->clear();
        
        $options = ['allow_redirects' => false];
        if ($this->cookie !== null) {
            $options['headers']['Cookie'] = $this->cookie;
        }

        try {
            $loginRes = $client->get($serviceUrl, $options);
        } catch (\Exception $e) {
            $cookieJar->clear();
            $loginRes = $client->get($serviceUrl, ['allow_redirects' => false]);
        }

        $statusCode = $loginRes->getStatusCode();

        if ($statusCode === 302) {
            $ticketUrl = $loginRes->getHeaderLine('Location');
            if (empty($ticketUrl)) {
                throw HnuQueryException::unexpected(new \Exception("没有在 location 中找到 ticket_url"));
            }
            return ['type' => 'skip', 'data' => $ticketUrl];
        }

        if ($statusCode !== 200) {
            throw HnuQueryException::unexpected(new \Exception("响应的状态码异常，应为OK"));
        }

        $cookies = HttpClient::parseCookies($loginRes);
        $cookieString = HttpClient::buildCookieString($cookies);
        $loginText = $loginRes->getBody()->getContents();

        preg_match('/name="execution" value="(.*?)"/', $loginText, $executionMatches);
        $execution = $executionMatches[1] ?? '';

        preg_match('/name="_eventId" value="(.*?)"/', $loginText, $eventIdMatches);
        $eventId = $eventIdMatches[1] ?? '';

        $cookieJar->clear();
        $pubkeyRes = $client->get(self::PUBKEY_URL, [
            'allow_redirects' => false,
            'headers' => ['Cookie' => $cookieString]
        ]);

        $newCookies = HttpClient::parseCookies($pubkeyRes);
        $allCookies = array_merge($cookies, $newCookies);
        
        $pubkeyStr = $pubkeyRes->getBody()->getContents();
        $pubkey = json_decode($pubkeyStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($pubkeyStr, "无法解析公钥JSON");
        }

        $modulus = $pubkey['modulus'];
        $exponent = $pubkey['exponent'];

        return [
            'type' => 'success',
            'data' => [
                'modulus' => $modulus,
                'exponent' => $exponent,
                'execution' => $execution,
                'event_id' => $eventId,
                'cookies' => $allCookies,
            ]
        ];
    }

    /**
     * @throws HnuQueryException
     */
    public function getTicketUrl(string $serviceUrl): string
    {
        $client = HttpClient::getClient();
        $cookieJar = HttpClient::getCookieJar();
        
        $loginParamsResult = $this->getLoginParams($serviceUrl);

        if ($loginParamsResult['type'] === 'skip') {
            return $loginParamsResult['data'];
        }

        $loginParams = $loginParamsResult['data'];
        
        $encryptedPassword = self::rsaEncrypt(
            $this->password,
            $loginParams['exponent'],
            $loginParams['modulus']
        );

        $cookieString = HttpClient::buildCookieString($loginParams['cookies']);
        
        $cookieJar->clear();
        $login = $client->post($serviceUrl, [
            'allow_redirects' => false,
            'headers' => ['Cookie' => $cookieString],
            'form_params' => [
                'authcode' => '',
                'username' => $this->stuId,
                'password' => $encryptedPassword,
                'execution' => $loginParams['execution'],
                '_eventId' => $loginParams['event_id'],
            ],
        ]);

        if ($login->getStatusCode() === 403) {
            throw HnuQueryException::other("账号因多次输错密码被锁定");
        }

        $location = $login->getHeaderLine('Location');
        
        if (empty($location)) {
            throw HnuQueryException::other("密码错误");
        }

        $passwordShouldChangePat = 'cas.hnu.edu.cn/securitycenter/modifyPwd/index.zf';
        if (str_contains($location, $passwordShouldChangePat)) {
            throw HnuQueryException::other("请前往个人门户修改密码后重试");
        }

        $loginCookies = HttpClient::parseCookies($login);
        
        $addition = array_filter($loginParams['cookies'], function($cookie) {
            return str_starts_with($cookie, '_pv0=');
        });
        
        $allCookies = array_merge($loginCookies, $addition);
        $this->cookie = HttpClient::buildCookieString($allCookies);
        
        return $location;
    }

    /**
     * @return array{0: string, 1: string}
     * @throws HnuQueryException
     */
    public function getSTicket(string $serviceUrl): array
    {
        $this->getTicketUrl(self::SERVICE_URL);
        $client = HttpClient::getClient();
        $cookieJar = HttpClient::getCookieJar();

        $nowUrl = $serviceUrl;
        $cookies = $this->cookie ?? '';
        $sTicket = null;

        for ($i = 0; $i < 6; $i++) {
            if (str_starts_with($nowUrl, 'https://cas.hnu.edu.cn/sprcialapp/zf_form/index.zf')) {
                parse_str(parse_url($nowUrl, PHP_URL_QUERY), $queryParams);
                if (isset($queryParams['s_ticket'])) {
                    $sTicket = $queryParams['s_ticket'];
                    break;
                }
            }

            $cookieJar->clear();
            $res = $client->get($nowUrl, [
                'allow_redirects' => false,
                'headers' => ['Cookie' => $cookies]
            ]);

            if ($res->getStatusCode() !== 302) {
                throw HnuQueryException::unexpected(new \Exception("获取s_ticket时失败，HTTP代码 " . $res->getStatusCode()));
            }

            $nowUrl = $res->getHeaderLine('Location');
            if (empty($nowUrl)) {
                throw HnuQueryException::unexpected(new \Exception("获取重定向链接失败"));
            }

            $newCookies = HttpClient::parseCookies($res);
            if (!empty($newCookies)) {
                $newCookieString = HttpClient::buildCookieString($newCookies);
                if (!empty($cookies)) {
                    $cookies .= '; ' . $newCookieString;
                } else {
                    $cookies = $newCookieString;
                }
            }
        }

        if ($sTicket === null) {
            throw HnuQueryException::unexpected(new \Exception("获取s_ticket失败，未找到s_ticket"));
        }

        return [$sTicket, $cookies];
    }
}
