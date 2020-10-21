<?php

namespace Cego\RequestInsurance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class RequestInsuranceLog
 *
 * @property int $id
 * @property string|array $response_headers
 * @property string $response_body
 * @property int $response_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RequestInsuranceLog extends Model
{
    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        // We need to hook into the saving event to manipulate data before it is stored in the database
        static::saving(function (RequestInsuranceLog $request) {
            // We make sure to json encode response headers to json if passed as an array
            if (is_array($request->response_headers)) {
                $request->response_headers = json_encode($request->response_headers, JSON_THROW_ON_ERROR);
            }
        });
    }

    /**
     * Relationship with RequestInsurance
     *
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(RequestInsurance::class, 'request_insurance_id');
    }
}
