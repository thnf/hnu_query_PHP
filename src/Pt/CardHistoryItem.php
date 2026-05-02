<?php

namespace HnuQuery\Pt;

class CardHistoryItem
{
    public string $dateTime;
    public string $journalTime;
    public string $status;
    public int $id;
    public float $nowBalance;
    public float $amount;
    public ?string $location;
    public string $name;

    public function __construct(array $data)
    {
        $this->dateTime = $data['date_time'];
        $this->journalTime = $data['journal_time'];
        $this->status = $data['status'];
        $this->id = $data['id'];
        $this->nowBalance = $data['now_balance'];
        $this->amount = $data['amount'];
        $this->location = $data['location'];
        $this->name = $data['name'];
    }
}