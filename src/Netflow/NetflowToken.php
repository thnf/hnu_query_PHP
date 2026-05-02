<?php

namespace HnuQuery\Netflow;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class NetflowToken
{
    private const NETFLOW_URL = 'http://cas.hnu.edu.cn/application/sso.zf?login=B5712DC2FA281C96E053026B3E0A80A6';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();

        [$sTicket, $cookies] = $casToken->getSTicket(self::NETFLOW_URL);
        $stuId = $casToken->getStuId();

        $response = $client->post('http://ll.hnu.edu.cn/login/validate', [
            'headers' => ['Cookie' => $cookies],
            'form_params' => [
                's_ticket' => $sTicket,
                'login_id' => $stuId,
                'password' => '',
                'null' => '',
            ],
            'allow_redirects' => false
        ]);

        $cookies = HttpClient::parseCookies($response);
        if (empty($cookies)) {
            throw HnuQueryException::unexpectedError('获取到空的cookies');
        }

        $firstCookie = reset($cookies);
        $lastCookie = end($cookies);
        $cookieString = $firstCookie . '; ' . $lastCookie;

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
            throw HnuQueryException::parseError($body, '校园网系统响应格式错误');
        }

        $data = $json['data'] ?? null;
        if ($data === null) {
            throw HnuQueryException::parseError($body, '校园网系统响应数据为空');
        }

        return $data;
    }
}
