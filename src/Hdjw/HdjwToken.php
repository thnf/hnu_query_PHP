<?php

namespace HnuQuery\Hdjw;

use HnuQuery\Cas\CasToken;
use HnuQuery\Error\HnuQueryException;
use HnuQuery\Utils\HttpClient;

class TokenExpired extends \Exception
{
    public function __construct()
    {
        parent::__construct("教务系统令牌过期");
    }
}

class HdjwToken
{
    private const HDJW_FROM_CAS_URL = 'http://cas.hnu.edu.cn/cas/login?service=http://hdjw.hnu.edu.cn/gld/sso.jsp';
    private const HDJW_ENTER_URL = 'http://hdjw.hnu.edu.cn/gld/sso.jsp';
    private const HDJW_MAIN_URL = 'http://hdjw.hnu.edu.cn/jsxsd/';

    private array $headers;

    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    private static function decodeBody($body, $response): string
    {
        $contentEncoding = $response->getHeaderLine('Content-Encoding');

        if (str_contains($contentEncoding, 'gzip')) {
            $decoded = @gzdecode($body);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $body;
    }

    /**
     * 手动跟随重定向并收集 cookies
     */
    private static function followRedirectWithCookies($client, $url, $cookies): array
    {
        $maxRedirects = 10;
        $currentUrl = $url;
        $currentCookies = $cookies;

        for ($i = 0; $i < $maxRedirects; $i++) {
            $res = $client->get($currentUrl, [
                'allow_redirects' => false,
                'headers' => ['Cookie' => $currentCookies],
            ]);

            $statusCode = $res->getStatusCode();

            $newCookies = HttpClient::parseCookies($res);
            if (!empty($newCookies)) {
                $newCookiesStr = HttpClient::buildCookieString($newCookies);
                $currentCookies .= '; ' . $newCookiesStr;
            }

            if ($statusCode >= 300 && $statusCode < 400) {
                $location = $res->getHeaderLine('Location');
                if (empty($location)) {
                    break;
                }

                if (!str_starts_with($location, 'http')) {
                    if (str_starts_with($location, '/')) {
                        $parsed = parse_url($currentUrl);
                        $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
                    } else {
                        $currentDir = dirname($currentUrl);
                        $location = $currentDir . '/' . $location;
                    }
                }

                $currentUrl = $location;
            } else {
                break;
            }
        }

        return [$currentUrl, $currentCookies];
    }

    /**
     * 处理VPN验证页面，返回更新后的cookies
     */
    private static function handleVpnVerification($body, $client, $cookies)
    {
        if (preg_match('/locationUrl = "([^"]+)"/', $body, $matches)) {
            $vpnUrl = $matches[1];

            $query = parse_url($vpnUrl, PHP_URL_QUERY);
            if ($query) {
                $params = [];
                parse_str($query, $params);
                if (isset($params['t'])) {
                    $jwt = $params['t'];

                    $jwtParts = explode('.', $jwt);
                    if (count($jwtParts) >= 2) {
                        $jwtPayload = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwtParts[1]));

                        $payload = json_decode($jwtPayload, true);

                        if (isset($payload['returnURL'])) {
                            $returnUrl = $payload['returnURL'];
                            [$finalUrl, $finalCookies] = self::followRedirectWithCookies($client, $returnUrl, $cookies);
                            return $finalCookies;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * @throws HnuQueryException
     */
    public static function acquireByCasLogin(CasToken $casToken): self
    {
        $client = HttpClient::getClient();

        $res = $client->get(self::HDJW_ENTER_URL, ['allow_redirects' => false]);
        $initialCookies = HttpClient::parseCookies($res);
        $cookies = HttpClient::buildCookieString($initialCookies);

        $ticketUrl = $casToken->getTicketUrl(self::HDJW_FROM_CAS_URL);

        $res = $client->get($ticketUrl, [
            'allow_redirects' => false,
            'headers' => ['Cookie' => $cookies],
        ]);

        $ticketCookies = HttpClient::parseCookies($res);
        $ticketCookiesStr = HttpClient::buildCookieString($ticketCookies);
        if (!empty($ticketCookiesStr)) {
            $cookies .= '; ' . $ticketCookiesStr;
        }

        $res = $client->get(self::HDJW_ENTER_URL, [
            'allow_redirects' => false,
            'headers' => ['Cookie' => $cookies],
        ]);

        $statusCode = $res->getStatusCode();

        if ($statusCode !== 302) {
            $body = self::decodeBody($res->getBody()->getContents(), $res);

            if (str_contains($body, 'locationUrl') && str_contains($body, 'webvpn')) {
                $newCookies = self::handleVpnVerification($body, $client, $cookies);
                if ($newCookies) {
                    $cookies = $newCookies;

                    $res = $client->get(self::HDJW_ENTER_URL, [
                        'allow_redirects' => false,
                        'headers' => ['Cookie' => $cookies],
                    ]);
                    $statusCode = $res->getStatusCode();

                    if ($statusCode !== 302) {
                        $body = self::decodeBody($res->getBody()->getContents(), $res);
                    }
                }
            }

            if ($statusCode !== 302) {
                $body = self::decodeBody($res->getBody()->getContents(), $res);
                if (str_contains($body, 'jsxsd') || str_contains($body, '教务系统')) {
                    $responseCookies = HttpClient::parseCookies($res);
                    $responseCookiesStr = HttpClient::buildCookieString($responseCookies);
                    if (!empty($responseCookiesStr)) {
                        $cookies .= '; ' . $responseCookiesStr;
                    }

                    $headers = [
                        'Cookie' => $cookies,
                        'Referer' => 'http://hdjw.hnu.edu.cn/jsxsd/',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ];
                    return new self($headers);
                }

                throw HnuQueryException::unexpected(
                    new \Exception("获取教务系统失败，HTTP代码 " . $statusCode)
                );
            }
        }

        $targetUrl = $res->getHeaderLine('Location');
        if (empty($targetUrl)) {
            throw HnuQueryException::unexpected(
                new \Exception("获取重定向链接失败")
            );
        }

        $res = $client->get($targetUrl, [
            'allow_redirects' => false,
            'headers' => ['Cookie' => $cookies],
        ]);
        $newCookies = HttpClient::parseCookies($res);
        $newCookiesStr = HttpClient::buildCookieString($newCookies);

        $allCookies = $cookies;
        if (!empty($newCookiesStr)) {
            $allCookies .= '; ' . $newCookiesStr;
        }

        $headers = [
            'Cookie' => $allCookies,
            'Referer' => 'http://hdjw.hnu.edu.cn/jsxsd/',
            'X-Requested-With' => 'XMLHttpRequest',
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

    public function getCookies(): array
    {
        return [];
    }

    /**
     * @throws HnuQueryException
     * @throws TokenExpired
     */
    public static function extractResponseData(\Psr\Http\Message\ResponseInterface $response): mixed
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $body = $response->getBody()->getContents();
            throw HnuQueryException::parseError(
                $body,
                "HTTP请求失败，状态码: {$statusCode}"
            );
        }

        $body = $response->getBody()->getContents();
        $contentEncoding = $response->getHeaderLine('Content-Encoding');

        if (str_contains($contentEncoding, 'gzip')) {
            $body = self::decodeBody($body, $response);
        }

        if (str_contains($body, 'window.initQzTable') || str_contains($body, 'let arr =')) {
            return $body;
        }

        if (empty(trim($body))) {
            throw HnuQueryException::parseError($body, "响应体为空");
        }

        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HnuQueryException::parseError(
                substr($body, 0, 500),
                "无法解析JSON响应: " . json_last_error_msg()
            );
        }

        if (!is_array($json)) {
            throw HnuQueryException::parseError(
                json_encode($json),
                "响应不是数组类型"
            );
        }

        if (isset($json['flag1']) && $json['flag1'] === 2) {
            throw new TokenExpired();
        }

        return $json;
    }
}
