<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Nbj\RequestInsurance\Contracts\HttpRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nbj\RequestInsurance\Exceptions\MethodNotAllowedForRequestInsurance;
use function Couchbase\defaultDecoder;

/**
 * Class RequestInsurance
 *
 * @property int $id
 * @property int $priority
 * @property string $url
 * @property string $method
 * @property string $headers
 * @property string $payload
 * @property string $response_headers
 * @property string $response_body
 * @property int $response_code
 * @property Carbon $completed_at
 * @property Carbon $locked_at
 * @property Carbon $paused_at
 * @property Carbon $abandoned_at
 * @property int $retry_count
 * @property int $retry_factor
 * @property int $retry_cap
 * @property Carbon $retry_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RequestInsurance extends Model
{
    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'completed_at',
        'abandoned_at',
        'locked_at',
        'paused_at',
        'retry_at',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        // We need to hook into the saving event to manipulate data before it is stored in the database
        static::saving(function (RequestInsurance $request) {
            // We make sure to json encode headers to json if passed as an array
            if (is_array($request->headers)) {
                $request->headers = json_encode($request->headers);
            }

            // We make sure to json encode response headers to json if passed as an array
            if (is_array($request->response_headers)) {
                $request->response_headers = json_encode($request->response_headers);
            }
        });
    }

    /**
     * Query scope for getting all RequestInsurances that are ready to be processed
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeReadyToBeProcessed(Builder $query)
    {
        return $query
            ->where('completed_at', null)
            ->where('paused_at', null)
            ->where('locked_at', null)
            ->where('abandoned_at', null)
            ->where(function ($query) {
                return $query
                    ->where('retry_at', null)
                    ->orWhere('retry_at', '<=', Carbon::now());
            })
            ->orderBy('priority', 'asc');
    }

    /**
     * Unlocks the RequestInsurance instance
     *
     * @return $this
     */
    public function unlock()
    {
        Log::debug(sprintf('Unlocking request with id: [%d]', $this->id));

        $this->locked_at = null;
        $this->save();

        return $this;
    }

    /**
     * Tells if the RequestInsurance instance has been locked
     *
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked_at !== null;
    }

    /**
     * Abandons the RequestInsurance instance
     *
     * @return $this
     */
    public function abandon()
    {
        Log::debug(sprintf('Abandoning request with id: [%d]', $this->id));

        $this->abandoned_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Tells if the RequestInsurance instance has been abandoned
     *
     * @return bool
     */
    public function isAbandoned()
    {
        return $this->abandoned_at !== null;
    }

    /**
     * Pauses the RequestInsurance instance
     *
     * @return $this
     */
    public function pause()
    {
        Log::debug(sprintf('Pausing request with id: [%d]', $this->id));

        $this->paused_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Unpauses the RequestInsurance instance
     *
     * @return $this
     */
    public function resume()
    {
        Log::debug(sprintf('Resuming request with id: [%d]', $this->id));

        $this->paused_at = null;
        $this->save();

        return $this;
    }

    /**
     * Tells if the RequestInsurance instance has been paused
     *
     * @return bool
     */
    public function isPaused()
    {
        return $this->paused_at !== null;
    }

    /**
     * Syntactic sugar for negating isPaused()
     *
     * @return bool
     */
    public function isNotPaused()
    {
        return ! $this->isPaused();
    }

    /**
     * Tells if the request is a GET request
     *
     * @return bool
     */
    public function isGetRequest()
    {
        return mb_strtoupper($this->method) == 'GET';
    }

    /**
     * Processes the RequestInsurance instance
     *
     * @return $this
     *
     * @throws MethodNotAllowedForRequestInsurance
     */
    public function process()
    {
        Log::debug(sprintf('Processing request with id: [%d]', $this->id));

        // Prepare headers by json decoding them and flatten them
        // to an array of header strings
        // Create the request instance, set its options and send it
        $response = HttpRequest::create()
            ->setUrl($this->url)
            ->setMethod($this->method)
            ->setHeaders($this->headers)
            ->setPayload($this->payload)
            ->send();

        // Update the quest with the latest response
        $this->response_body = $response->getBody();
        $this->response_code = $response->getCode();
        $this->response_headers = $response->getHeaders();

        // Create a log for the request to we track of all attempts
        $this->logs()->create([
            'response_body'    => $response->getBody(),
            'response_code'    => $response->getCode(),
            'response_headers' => $response->getHeaders(),
        ]);

        if ($response->shouldNotBeRetried()) {
            $this->paused_at = Carbon::now();
        }

        if ($response->wasSuccessful()) {
            $this->completed_at = Carbon::now();
        }

        if ($this->isNotCompleted()) {
            $this->incrementRetryCount();
            $this->setNextRetryAt();
        }

        $this->save();

        return $this;
    }

    /**
     * Increments the retry count for the request
     *
     * @return $this
     */
    public function incrementRetryCount()
    {
        $this->retry_count++;

        return $this;
    }

    /**
     * Sets the next retry_at timestamp
     *
     * @return $this
     */
    public function setNextRetryAt()
    {
        $seconds = pow($this->retry_factor, $this->retry_count);

        if ($seconds > $this->retry_cap) {
            $seconds = $this->retry_cap;
        }

        $this->retry_at = Carbon::now()->addSeconds($seconds);

        return $this;
    }

    /**
     * Tells if the RequestInsurance instance has been completed
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->completed_at !== null;
    }

    /**
     * Syntactic sugar for negating isCompleted()
     *
     * @return bool
     */
    public function isNotCompleted()
    {
        return ! $this->isCompleted();
    }

    /**
     * Relationship with RequestInsuranceLog
     *
     * @return HasMany
     */
    public function logs()
    {
        return $this->hasMany(RequestInsuranceLog::class);
    }
}
