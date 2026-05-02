<?php

namespace HnuQuery\Hdjw;

class RankMethod
{
    private const ARITHMETIC_AVG = '4';
    private const WEIGHTED_AVG = '2';
    private const GPA = '3';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function ArithmeticAvg(): self
    {
        return new self(self::ARITHMETIC_AVG);
    }

    public static function WeightedAvg(): self
    {
        return new self(self::WEIGHTED_AVG);
    }

    public static function ByGPA(): self
    {
        return new self(self::GPA);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toStr(): string
    {
        return $this->value;
    }
}