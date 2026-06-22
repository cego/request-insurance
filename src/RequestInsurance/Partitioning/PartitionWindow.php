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
        private readonly string          $granularity,
    ) {
    }

    public static function forDate(CarbonInterface $date, string $granularity): self
    {
        PartitionGranularity::assertValid($granularity);

        $utc = CarbonImmutable::parse($date->toDateTimeString(), 'UTC');

        switch ($granularity) {
            case PartitionGranularity::WEEKLY:
                $start = $utc->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
                $end = $start->addWeek();
                $name = sprintf('p%04dw%02d', $start->isoWeekYear, $start->isoWeek);

                break;
            case PartitionGranularity::MONTHLY:
                $start = $utc->startOfMonth();
                $end = $start->addMonthNoOverflow();
                $name = sprintf('p%04d%02d', $start->year, $start->month);

                break;
            case PartitionGranularity::DAILY:
            default:
                $start = $utc->startOfDay();
                $end = $start->addDay();
                $name = sprintf('p%04d%02d%02d', $start->year, $start->month, $start->day);

                break;
        }

        return new self($start, $end, $name, $granularity);
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
        return self::forDate($this->end, $this->granularity);
    }

    /** @return array<int, self> */
    public static function range(CarbonInterface $from, CarbonInterface $to, string $granularity): array
    {
        $windows = [];
        $cursor = self::forDate($from, $granularity);
        $last = self::forDate($to, $granularity);

        while ($cursor->start()->lessThanOrEqualTo($last->start())) {
            $windows[] = $cursor;
            $cursor = $cursor->next();
        }

        return $windows;
    }
}
