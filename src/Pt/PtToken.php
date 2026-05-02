<?php

namespace HnuQuery\Pt;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class PtToken
{
    private const PT_URL = 'http://cas.hnu.edu.cn/cas/login?service=https://pt.hnu.edu.cn/';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();

        $ticketUrl = $casToken->getTicketUrl(self::PT_URL);

        $response = $client->get($ticketUrl, ['allow_redirects' => false]);

        if ($response->getStatusCode() !== 302) {
            throw HnuQueryException::unexpectedError('登录个人门户失败，HTTP状态码: ' . $response->getStatusCode());
        }

        $cookies = HttpClient::parseCookies($response);
        $cookieString = HttpClient::buildCookieString($cookies);

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
}
