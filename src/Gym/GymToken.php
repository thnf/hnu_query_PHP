<?php

namespace HnuQuery\Gym;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class TokenExpired extends \Exception
{
    public function __construct()
    {
        parent::__construct("体测系统令牌过期");
    }
}

class GymToken
{
    private const GYM_URL_DIRECT_LOGIN = 'http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Login/login';
    private const GYM_URL_FROM_CAS = 'http://cas.hnu.edu.cn/application/sso.zf?login=898A822E9695C137E053026B3E0A65D7';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();

        [$sTicket, $casCookies] = $casToken->getSTicket(self::GYM_URL_FROM_CAS);
        $stuId = $casToken->getStuId();

        $client->get('http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/view/login/loginPage.html', [
            'query' => ['s_ticket' => $sTicket, 'login_id' => $stuId],
            'headers' => ['Cookie' => $casCookies],
            'allow_redirects' => true
        ]);

        $response = $client->post('http://gymos.hnu.edu.cn/bdlp_api_fitness_test_student_h5/public/index.php/index/Login/ticketLogin', [
            'form_params' => ['s_ticket' => $sTicket, 'login_id' => $stuId],
            'allow_redirects' => false
        ]);

        $cookies = HttpClient::parseCookies($response);
        $cookieString = HttpClient::buildCookieString($cookies);

        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '体测系统登录响应格式错误');
        }

        if (($json['info'] ?? '') !== '登录成功') {
            throw HnuQueryException::unexpectedError('体测系统登录失败: ' . ($json['info'] ?? '未知错误'));
        }

        $headers = [
            'Cookie' => $cookieString
        ];

        return new self($headers);
    }

    public static function acquireByDirectLogin(string $stuId, string $password): self
    {
        $client = HttpClient::getClient();

        $response = $client->post(self::GYM_URL_DIRECT_LOGIN, [
            'form_params' => ['student_num' => $stuId, 'password' => $password],
        ]);

        $cookies = HttpClient::parseCookies($response);
        $cookieString = HttpClient::buildCookieString($cookies);

        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '体测系统登录响应格式错误');
        }

        if (($json['info'] ?? '') !== '登录成功') {
            throw HnuQueryException::unexpectedError('体测系统登录失败: ' . ($json['info'] ?? '未知错误'));
        }

        $headers = [
            'Cookie' => $cookieString
        ];

        return new self($headers);
    }

    public static function fromHeadersUnchecked(array $headers): self
    {
        return new self($headers);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function extractResponseData(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, '体测系统响应格式错误');
        }

        if (($json['status'] ?? 0) === -1 && str_contains(($json['info'] ?? ''), '登录失效')) {
            throw new TokenExpired();
        }

        if (($json['status'] ?? 0) !== 1) {
            throw HnuQueryException::parseError($body, '体测系统响应错误: ' . ($json['info'] ?? '未知错误'));
        }

        return $json['data'] ?? [];
    }
}
