<?php

namespace Cego\RequestInsurance\Models;

use Exception;
use Carbon\Carbon;
use JsonException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Cego\RequestInsurance\Exceptions\MethodNotAllowedForRequestInsurance;

/**
 * Class RequestInsurance
 *
 * @property int $id
 * @property int $priority
 * @property string $url
 * @property string $method
 * @property string|array $headers
 * @property string $payload
 * @property string|array $response_headers
 * @property string $response_body
 * @property int $response_code
 * @property Carbon|null $completed_at
 * @property Carbon|null $locked_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $abandoned_at
 * @property int $retry_count
 * @property int $retry_factor
 * @property int $retry_cap
 * @property Carbon|null $retry_at
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
     *
     * @throws JsonException
     */
    protected static function booted()
    {
        // We need to hook into the saving event to manipulate data before it is stored in the database
        static::saving(function (RequestInsurance $request) {
            // We make sure to json encode headers to json if passed as an array
            if (is_array($request->headers)) {
                $request->headers = json_encode($request->headers, JSON_THROW_ON_ERROR);
            }

            // We make sure to json encode response headers to json if passed as an array
            if (is_array($request->response_headers)) {
                $request->response_headers = json_encode($request->response_headers, JSON_THROW_ON_ERROR);
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
     * Scopes the query according to the filter set by the request
     *
     * @param Builder $query
     * @param Request $request
     *
     * @return Builder
     */
    public function scopeFilteredByRequest(Builder $query, Request $request)
    {
        $query = $query->where(function () use ($query, $request) {
            if ($request->has('completed')) {
                $query = $query->orWhere('completed_at', '!=', null);
            }

            if ($request->has('paused')) {
                $query = $query->orWhere('paused_at', '!=', null);
            }

            if ($request->has('locked')) {
                $query = $query->orWhere('locked_at', '!=', null);
            }

            if ($request->has('abandoned')) {
                $query = $query->orWhere('abandoned_at', '!=', null);
            }

            return $query;
        });

        try {
            if ($request->has('from') && $request->get('from') != null) {
                $from = Carbon::parse($request->get('from'))->setHours(0)->setMinutes(0)->setSeconds(0);
                $query = $query->whereDate('created_at', '>=', $from);
            }

            if ($request->has('to') && $request->get('to') != null) {
                $to = Carbon::parse($request->get('to'))->setHours(23)->setMinutes(59)->setSeconds(59);
                $query = $query->whereDate('created_at', '<=', $to);
            }
        } catch (Exception $exception) {
            Log::notice('Failed parsing from or to date to Carbon instance in filter');
        }

        return $query;
    }

    /**
     * Gets the a shortened version of the payload
     *
     * @return string
     */
    public function getShortenedPayload()
    {
        if (strlen($this->payload) <= 125) {
            return $this->payload;
        }

        return sprintf('%s...', substr($this->payload, 0, 125));
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
        $this->retry_at = null;

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
     * Syntactic sugar for negating isAbandoned()
     *
     * @return bool
     */
    public function isNotAbandoned()
    {
        return ! $this->isAbandoned();
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
        $this->retry_at = null;

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
        $this->retry_at = Carbon::now();

        if ($this->isAbandoned()) {
            $this->abandoned_at = null;
        }

        $this->save();

        return $this;
    }

    /**
     * Tells is the request insurance is retryable
     *
     * @return bool
     */
    public function isRetryable()
    {
        return $this->isNotCompleted() && ($this->isPaused() || $this->isAbandoned());
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

        if ($response->isNotRetryable()) {
            $this->paused_at = Carbon::now();
        }

        if ($response->wasSuccessful()) {
            $this->completed_at = Carbon::now();
        }

        if ($this->isNotCompleted() && $response->isRetryable()) {
            $this->incrementRetryCount();
            $this->setNextRetryAt();
        }

        $this->save();

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
     * Relationship with RequestInsuranceLog
     *
     * @return HasMany
     */
    public function logs()
    {
        return $this->hasMany(RequestInsuranceLog::class);
    }
}
