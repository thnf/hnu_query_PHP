<?php

namespace HnuQuery\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

class HttpClient
{
    private static ?Client $instance = null;
    private static ?CookieJar $cookieJar = null;

    public static function getClient(): Client
    {
        if (self::$instance === null) {
            self::$cookieJar = new CookieJar();
            self::$instance = new Client([
                'verify' => false,
                'timeout' => 60,
                'connect_timeout' => 10,
                'allow_redirects' => false,
                'decode_content' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                ],
                'proxy' => null,
                'cookies' => self::$cookieJar,
                'force_ip_resolve' => 'v4',
            ]);
        }
        return self::$instance;
    }

    public static function getCookieJar(): CookieJar
    {
        if (self::$cookieJar === null) {
            self::getClient();
        }
        return self::$cookieJar;
    }

    public static function parseCookies(ResponseInterface $response): array
    {
        $cookies = [];
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        foreach ($setCookieHeaders as $cookie) {
            $parts = explode(';', $cookie);
            $pair = explode('=', $parts[0], 2);

            if (count($pair) === 2 && $pair[1] !== '') {
                $cookies[] = trim($pair[0]) . '=' . trim($pair[1]);
            }
        }

        return $cookies;
    }

    public static function buildCookieString(array $cookies): string
    {
        return implode('; ', $cookies);
    }

    public static function getCookiesFromJar(): string
    {
        $jar = self::getCookieJar();
        $cookies = $jar->toArray();
        $cookieStr = [];
        foreach ($cookies as $cookie) {
            if (isset($cookie['Value']) && $cookie['Value'] !== '') {
                $cookieStr[] = $cookie['Name'] . '=' . $cookie['Value'];
            }
        }
        return implode('; ', $cookieStr);
    }
}
