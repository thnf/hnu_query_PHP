<?php

namespace HnuQuery\Xgxt;

class Dormitory
{
    private ?string $park;
    private ?string $build;
    private string $room;
    private string $rawDormitory;

    private function __construct(?string $park, ?string $build, string $room, string $rawDormitory)
    {
        $this->park = $park;
        $this->build = $build;
        $this->room = $room;
        $this->rawDormitory = $rawDormitory;
    }

    public static function fromParsedValue(string $park, string $build, string $room): self
    {
        return new self(
            $park,
            $build,
            $room,
            $park . $build
        );
    }

    public static function fromRaw(string $rawDormitory, string $room): self
    {
        $result = self::parseDormitoryString($rawDormitory);
        return new self(
            $result['park'],
            $result['build'],
            $room,
            $rawDormitory
        );
    }

    /**
     * @return array{park: ?string, build: ?string}
     */
    private static function parseDormitoryString(string $raw): array
    {
        $park = null;
        $build = null;

        // 按照 Rust 版本的逻辑实现
        if (str_contains($raw, '德智')) {
            $park = '德智园区';
            if (preg_match('/\d+栋/', $raw, $matches)) {
                $build = $matches[0];
            }
        }
        
        if (str_contains($raw, '天马')) {
            $park = '天马园区';
            if (preg_match('/[一二三四]区\d+栋/', $raw, $matches)) {
                $build = $matches[0];
            }
        }
        
        if (str_contains($raw, '望麓桥')) {
            $park = '望麓桥学生公寓';
            if (preg_match('/\d+栋/', $raw, $matches)) {
                $build = $matches[0];
            }
        }
        
        if (str_contains($raw, '牛头山')) {
            $park = '牛头山学生公寓';
            if (preg_match('/\d+栋/', $raw, $matches)) {
                $build = $matches[0];
            }
        }
        
        if (str_contains($raw, '财院校区')) {
            $park = '财院校区';
            if (preg_match('/[1-9AB]/', $raw, $matches)) {
                $build = $matches[0];
            }
        }
        
        if (str_contains($raw, '南校区')) {
            $park = '南校区';
            if (preg_match('/[1-9]+舍/', $raw, $matches)) {
                $build = $matches[0];
            }
        }

        return ['park' => $park, 'build' => $build];
    }

    public function getPark(): ?string
    {
        return $this->park;
    }

    public function getBuild(): ?string
    {
        return $this->build;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function getRawDormitory(): string
    {
        return $this->rawDormitory;
    }

    public function successfullyParsed(): bool
    {
        return $this->park !== null && $this->build !== null;
    }
}
