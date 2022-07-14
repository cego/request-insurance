<?php

namespace Cego\RequestInsurance\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class RequestInsuranceEdit
 *
 * @property int $id
 * @property int $request_insurance_id
 * @property int $required_number_of_approvals
 * @property int $old_priority
 * @property int $new_priority
 * @property string $old_url
 * @property string $new_url
 * @property string $old_method
 * @property string $new_method
 * @property string|null $old_headers
 * @property string|null $new_headers
 * @property string|null $old_payload
 * @property string|null $new_payload
 * @property string|null $old_encrypted_fields
 * @property string|null $new_encrypted_fields
 * @property string $admin_user
 * @property Carbon|null $applied_at
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
     * Relationship with RequestInsurance
     *
     * @return BelongsTo
     */
    public function requestInsurance()
    {
        return $this->belongsTo(RequestInsurance::class);
    }

    /**
     * Relationship with RequestInsuranceEditApproval
     *
     * @return HasMany
     */
    public function approvals()
    {
        return $this->hasMany(RequestInsuranceEditApproval::class);
    }
}
