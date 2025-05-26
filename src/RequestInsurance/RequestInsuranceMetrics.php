<?php

namespace Cego\RequestInsurance;

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

        $this->prometheus->addGauge('request_insurances_ready_lag_seconds')
            ->namespace('request_insurance')
            ->value(fn () => [
                [
                    fn () => RequestInsurance::query()->where('state', State::READY)
                        ->whereNotNull('ready_at')
                        ->min('ready_at') - now()->timestamp
                ],
            ]);
    }
}
