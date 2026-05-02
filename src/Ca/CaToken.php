<?php

namespace HnuQuery\Ca;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class CaToken
{
    private const CA_URL = 'http://cas.hnu.edu.cn/cas/login?service=https://ca.hnu.edu.cn/student/';
    public const UNDERGRADUATE_MAJOR_ALL_TEMPLATE_ID = '02a70e11bc89b40dc2ef6ed14851ce25';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();

        $ticketUrl = $casToken->getTicketUrl(self::CA_URL);
        $client->get($ticketUrl, ['allow_redirects' => true]);

        $ticket = explode('ticket=', $ticketUrl)[1] ?? '';

        $validateUrl = sprintf(
            'https://ca.hnu.edu.cn/student/cas/client/validateLogin?ticket=%s%%23%%2F&service=https:%%2F%%2Fca.hnu.edu.cn%%2Fstudent%%2F',
            $ticket
        );

        $response = $client->get($validateUrl, ['allow_redirects' => true]);
        $body = $response->getBody()->getContents();

        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError($body, 'Ca token 响应格式错误');
        }

        if (($json['message'] ?? '') !== '登录成功') {
            throw HnuQueryException::unexpectedError('Ca登录失败: ' . ($json['message'] ?? '未知错误'));
        }

        $token = $json['result']['token'] ?? '';
        if (empty($token)) {
            throw HnuQueryException::parseError($body, '无法获取Ca token');
        }

        $headers = [
            'X-Access-Token' => $token,
            'Cookie' => 'X-Access-Token=' . $token
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
