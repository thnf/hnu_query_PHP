<?php

namespace HnuQuery\Hdjw;

class RankRange
{
    private const GENERAL_REQUIRED = '11';
    private const GENERAL_ELECTIVE = '15';
    private const MAJOR_ELECTIVE = '05';
    private const MAJOR_BASIC = '03';
    private const MAJOR_CORE = '16';
    private const CLUSTER_CORE = '12';
    private const GATEWAY_CORE = '08';
    private const PRACTICE = '10';
    private const INNOVATION = '17';
    private const INTERNATIONAL = '88';
    private const MARXISM_CLASSIC = '07';
    private const SCIENCE_AND_ART_CLASSIC = '09';
    private const WESTERN_CLASSIC = '13';
    private const CHINESE_CLASSIC = '14';
    private const OTHER = '18';
    private const UNKNOWN_19 = '19';
    private const UNKNOWN_20 = '20';

    private string $value;
    public string $name;

    private function __construct(string $value, string $name)
    {
        $this->value = $value;
        $this->name = $name;
    }

    public static function GeneralRequired(): self
    {
        return new self(self::GENERAL_REQUIRED, '通识必修');
    }

    public static function GeneralElective(): self
    {
        return new self(self::GENERAL_ELECTIVE, '通识选修');
    }

    public static function MajorElective(): self
    {
        return new self(self::MAJOR_ELECTIVE, '专业选修');
    }

    public static function MajorBasic(): self
    {
        return new self(self::MAJOR_BASIC, '专业基础');
    }

    public static function MajorCore(): self
    {
        return new self(self::MAJOR_CORE, '专业核心');
    }

    public static function ClusterCore(): self
    {
        return new self(self::CLUSTER_CORE, '集群核心');
    }

    public static function GatewayCore(): self
    {
        return new self(self::GATEWAY_CORE, '门径核心');
    }

    public static function Practice(): self
    {
        return new self(self::PRACTICE, '实践类');
    }

    public static function Innovation(): self
    {
        return new self(self::INNOVATION, '创新性实践课');
    }

    public static function International(): self
    {
        return new self(self::INTERNATIONAL, '国际化课');
    }

    public static function MarxismClassic(): self
    {
        return new self(self::MARXISM_CLASSIC, '马克思主义经典著作导读');
    }

    public static function ScienceAndArtClassic(): self
    {
        return new self(self::SCIENCE_AND_ART_CLASSIC, '科学艺术经典导读');
    }

    public static function WesternClassic(): self
    {
        return new self(self::WESTERN_CLASSIC, '西方经典导读');
    }

    public static function ChineseClassic(): self
    {
        return new self(self::CHINESE_CLASSIC, '中华经典导读');
    }

    public static function Other(): self
    {
        return new self(self::OTHER, '其他');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toStr(): string
    {
        return $this->value;
    }

    public static function coreV2024Course(): array
    {
        return [
            self::MajorBasic(),
            self::MajorCore(),
        ];
    }
}