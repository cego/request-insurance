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
            if (in_array($key, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()], true)) {
                $value = Carbon::parse($value, 'UTC');
            } else {
                throw new InvalidArgumentException('The given value must be a Carbon instance!');
            }
        }

        return $value->setTimezone('UTC')->toDateTimeString();
    }
}
