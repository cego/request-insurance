<?php

namespace Cego\RequestInsurance\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Cego\RequestInsurance\Factories\RequestInsuranceLogFactory;

/**
 * Class RequestInsuranceEdit
 *
 * @property int $id
 * @property int $request_insurance_id
 * @property int $old_priority
 * @property int $new_priority
 * @property string $old_url
 * @property string $new_url
 * @property string $old_method
 * @property string $new_method
 * @property string $old_headers
 * @property string $new_headers
 * @property string $old_payload
 * @property string $new_payload
 * @property string $old_encrypted_fields
 * @property string $new_encrypted_fields
 * @property string $admin_user
 * @property Carbon $applied_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static RequestInsurance create($attributes = [])
 */
class RequestInsuranceEdit extends SaveRetryingModel
{
    use HasFactory;

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        // Use the one defined in the config, or the whatever is default
        return Config::get('request-insurance.table_edits') ?? parent::getTable();
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {

    }

    /**
     * Relationship with RequestInsurance
     *
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(get_class(resolve(RequestInsurance::class)), 'request_insurance_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return new RequestInsuranceEditFactory();
    }
}
