<?php

namespace Cego\RequestInsurance\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class RequestInsuranceEditApproval
 *
 * @property int $id
 * @property int $request_insurance_edit_id
 * @property string $approver_admin_user
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static RequestInsurance create($attributes = [])
 */
class RequestInsuranceEditApproval extends SaveRetryingModel
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
        return Config::get('request-insurance.table_edit_approvals') ?? parent::getTable();
    }

    /**
     * Relationship with RequestInsuranceEdit
     *
     * @return BelongsTo
     */
    public function requestInsuranceEdit()
    {
        return $this->belongsTo(RequestInsuranceEdit::class);
    }
}
