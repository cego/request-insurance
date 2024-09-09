<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Application;
use Spatie\Prometheus\PrometheusServiceProvider;
use Cego\RequestInsurance\Models\RequestInsurance;

class MetricsTest extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PrometheusServiceProvider::class,
        ];
    }

    public function test_it_gets_metrics_for_request_insurances_count()
    {

        $response = $this->get('prometheus');

        $response->assertStatus(200);

        // Assert that the response contains the metrics for request_insurances_count
        $response->assertSee('request_insurances_count{status="FAILED"} 0', false);
        $response->assertSee('request_insurances_count{status="PENDING"} 0', false);
        $response->assertSee('request_insurances_count{status="READY"} 0', false);
        $response->assertSee('request_insurances_count{status="PROCESSING"} 0', false);
        $response->assertSee('request_insurances_count{status="WAITING"} 0', false);
    }

    public function test_it_gets_metrics_for_request_insurances_count_with_data()
    {
        RequestInsurance::factory()->createMany([
            ['state' => 'FAILED'],
            ['state' => 'PENDING'],
            ['state' => 'READY'],
            ['state' => 'PROCESSING'],
            ['state' => 'WAITING'],
        ]);

        $response = $this->get('prometheus');

        $response->assertStatus(200);

        // Assert that the response contains the metrics for request_insurances_count
        $response->assertSee('request_insurances_count{status="FAILED"} 1', false);
        $response->assertSee('request_insurances_count{status="PENDING"} 1', false);
        $response->assertSee('request_insurances_count{status="READY"} 1', false);
        $response->assertSee('request_insurances_count{status="PROCESSING"} 1', false);
        $response->assertSee('request_insurances_count{status="WAITING"} 1', false);
    }
}
