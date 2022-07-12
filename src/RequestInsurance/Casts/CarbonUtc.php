<?php

namespace Cego\RequestInsurance\Casts;

use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class CarbonUtc implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @return Carbon|null
     */
    public function get($model, $key, $value, $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     *
     * @return ?string
     */
    public function set($model, $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ( ! $value instanceof Carbon) {
            throw new InvalidArgumentException('Invalid value. Value must be of type Carbon!');
        }

        return $value->setTimezone('UTC')->toIso8601ZuluString();
    }
}
