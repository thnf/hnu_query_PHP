<?php

namespace HnuQuery\Error;

class HnuQueryException extends \Exception
{
    public const UNEXPECTED = 1;
    public const NETWORK_ERROR = 2;
    public const PARSE_ERROR = 3;
    public const OTHER = 4;

    private ?string $reason;
    private ?string $data;

    public static function unexpected(\Throwable $previous, string $file = '', int $line = 0): self
    {
        $e = new self(
            "出现了意料之外的错误，详情信息请查看异常信息，如果问题存在可靠的复现方式，请向开发者反馈",
            self::UNEXPECTED,
            $previous
        );
        $e->reason = null;
        $e->data = null;
        return $e;
    }

    public static function networkError(\Throwable $previous): self
    {
        $e = new self("网络请求错误", self::NETWORK_ERROR, $previous);
        $e->reason = null;
        $e->data = null;
        return $e;
    }

    public static function parseError(string $data, ?string $reason = null, ?\Throwable $previous = null): self
    {
        $e = new self("数据解析错误", self::PARSE_ERROR, $previous);
        $e->data = $data;
        $e->reason = $reason;
        return $e;
    }

    public static function other(string $message, int $code = 0, ?\Throwable $previous = null): self
    {
        $e = new self($message, self::OTHER, $previous);
        $e->reason = null;
        $e->data = null;
        return $e;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getData(): ?string
    {
        return $this->data;
    }
}
