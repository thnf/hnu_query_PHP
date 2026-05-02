<?php

namespace HnuQuery\Lab;

class LabGradeDetailItem
{
    public string $name;
    public ?float $score;

    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->score = $data['score'];
    }
}