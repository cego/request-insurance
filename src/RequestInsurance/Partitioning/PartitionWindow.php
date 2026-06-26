<?php

namespace Cego\RequestInsurance\Partitioning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PartitionWindow
{
    private function __construct(
        private readonly CarbonImmutable $start,
        private readonly CarbonImmutable $end,
        private readonly string          $name,
    ) {
    }

    public static function forDate(CarbonInterface $date): self
    {
        $start = CarbonImmutable::parse($date->toDateTimeString(), 'UTC')->startOfDay();
        $name = sprintf('p%04d%02d%02d', $start->year, $start->month, $start->day);

        return new self($start, $start->addDay(), $name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    public function next(): self
    {
        return self::forDate($this->end);
    }

    /** @return array<int, self> */
    public static function range(CarbonInterface $from, CarbonInterface $to): array
    {
        $windows = [];
        $cursor = self::forDate($from);
        $last = self::forDate($to);

        while ($cursor->start()->lessThanOrEqualTo($last->start())) {
            $windows[] = $cursor;
            $cursor = $cursor->next();
        }

        return $windows;
    }
}
