<?php

namespace Cego\RequestInsurance;

use Illuminate\Support\Carbon;
use Spatie\Prometheus\Prometheus;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceMetrics
{
    public function __construct(
        private Prometheus $prometheus
    ) {
    }

    public function registerMetrics(): void
    {
        $this->prometheus->addGauge('request_insurances_count')
            ->namespace('request_insurance')
            ->label('status')
            ->value(fn () => [
                [fn () => RequestInsurance::query()->where('state', State::FAILED)->count(), [State::FAILED]],
                [fn () => RequestInsurance::query()->where('state', State::PENDING)->count(), [State::PENDING]],
                [fn () => RequestInsurance::query()->where('state', State::READY)->count(), [State::READY]],
                [fn () => RequestInsurance::query()->where('state', State::PROCESSING)->count(), [State::PROCESSING]],
                [fn () => RequestInsurance::query()->where('state', State::WAITING)->count(), [State::WAITING]],
            ]);

        $this->prometheus->addGauge('ready_delta_time_lag')
            ->namespace('request_insurance')
            ->helpText('The time, in seconds, since the earliest ready RI in queue was became ready.')
            ->value(function () {
                $now = now();

                return Carbon::parse(RequestInsurance::where('state', '=', State::READY)->min('state_changed_at') ?? $now)->diffInSeconds($now);
            });
    }
}
