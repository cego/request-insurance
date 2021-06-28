<?php

namespace Cego\RequestInsurance\Models;

use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\HttpResponse;
use Cego\RequestInsurance\Events;
use Cego\RequestInsurance\RequestInsuranceBuilder;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;
use Exception;
use Carbon\Carbon;
use JsonException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
 * @property int|null $timeout_ms
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
 *
 * @method static RequestInsurance create($attributes = [])
 * @method static RequestInsurance|null first(array|string $columns = [])
 */
class RequestInsurance extends SaveRetryingModel
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
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        // Use the one defined in the config, or the whatever is default
        return Config::get('request-insurance.table') ?? parent::getTable();
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     *
     * @throws JsonException
     */
    protected static function booted(): void
    {
        // We need to hook into the saving event to manipulate and verify data before it is stored in the database
        static::saving(function (RequestInsurance $request) {

            // Throw exception if method is not set
            if ( ! $request->method) {
                throw new EmptyPropertyException('method', $request);
            }

            // Throw exception if url is not set
            if ( ! $request->url) {
                throw new EmptyPropertyException('url', $request);
            }

            // We make sure to json encode headers to json if passed as an array
            if (is_array($request->headers)) {
                $request->headers = json_encode($request->headers, JSON_THROW_ON_ERROR);
            }

            // We make sure to json encode payload to json if passed as an array
            if (is_array($request->payload)) {
                $request->payload = json_encode($request->payload, JSON_THROW_ON_ERROR);
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
            if ($request->get('Active') == 'on') {
                $query->orWhere(function (Builder $builder) {
                    return $builder->whereNull('paused_at')
                        ->whereNull('abandoned_at')
                        ->whereNull('completed_at');
                });
            }

            if ($request->get('Completed') == 'on') {
                $query->orWhere(function (Builder $builder) {
                    return $builder->whereNotNull('completed_at');
                });
            }

            if ($request->get('Abandoned') == 'on') {
                $query->orWhere(function (Builder $builder) {
                    return $builder->whereNotNull('abandoned_at');
                });
            }

            if ($request->get('Failed') == 'on') {
                $query->orWhere(function (Builder $builder) {
                    return $builder->whereNotNull('paused_at')
                        ->whereNull('abandoned_at');
                });
            }

            if ($request->get('Locked') == 'on') {
                $query->orWhere(function (Builder $builder) {
                    return $builder->whereNotNull('locked_at');
                });
            }

            return $query;
        });

        if ($request->has('url') && trim($request->get('url'))) {
            $query = $query->where('url', 'like', $request->get('url'));
        }

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

        $this->paused_at = null;
        $this->abandoned_at = Carbon::now();
        $this->retry_at = null;
        $this->completed_at = null;

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

        // To avoid marking successful requests as failed, we add this successful check
        $this->paused_at = $this->wasSuccessful() ? null : Carbon::now();
        $this->retry_at = null;
        $this->abandoned_at = null;
        $this->completed_at = null;

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
        $this->abandoned_at = null;
        $this->completed_at = null;

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

        // Send the request and receive the response
        $response = $this->sendRequest();

        // Update the request with the latest response
        $this->response_body = $response->getBody();
        $this->response_code = $response->getCode();
        $this->response_headers = $response->getHeaders();

        // Create a log for the request to track all attempts
        try {
            $this->logs()->create([
                'response_body'    => $response->getBody(),
                'response_code'    => $response->getCode(),
                'response_headers' => $response->getHeaders(),
            ]);
        } catch (Exception $exception) {
            Log::error(sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString()));
        }

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

        // It happens that a ->save() causes a deadlock problem,
        // since this is not really a logic error, we add this retry logic
        // so the data is persisted.
        // This will most likely in almost all cases catch the problem before an exception is thrown.
        $this->save();

        $this->dispatchPostProcessEvents($response);

        return $this;
    }

    /**
     * Sends the request to the target URL and returns the response
     *
     * @return HttpResponse
     *
     * @throws MethodNotAllowedForRequestInsurance
     */
    protected function sendRequest()
    {
        // Prepare headers by json decoding them and flatten them
        // to an array of header strings
        // Create the request instance, set its options and send it
        $request = HttpRequest::create()
            ->setUrl($this->url)
            ->setMethod($this->method)
            ->setHeaders($this->headers)
            ->setPayload($this->payload);

        // If a custom timeout is set for this specific request
        // then override the default timeout with the chosen timeout
        if ($this->timeout_ms !== null) {
            $request->setTimeoutMs($this->timeout_ms);
        }

        return $request->send();
    }

    /**
     * Dispatches events depending on the request response
     *
     * @param HttpResponse $response
     */
    protected function dispatchPostProcessEvents(HttpResponse $response): void
    {
        if ($response->wasSuccessful()) {
            Events\RequestSuccessful::dispatch($this);
        } else {
            Events\RequestFailed::dispatch($this);
        }

        if ($response->isClientError()) {
            Events\RequestClientError::dispatch($this);
        }

        if ($response->isServerError()) {
            Events\RequestServerError::dispatch($this);
        }
    }

    /**
     * Returns true if the response code was 2XX
     *
     * @return bool
     */
    public function wasSuccessful(): bool
    {
        return 200 <= $this->response_code && $this->response_code < 300;
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
     * Increments the retry count for the request, and updates the retry at field
     *
     * @return $this
     *
     * @throws Exception
     */
    public function retry()
    {
        $this->incrementRetryCount();
        $this->setNextRetryAt();
        $this->paused_at = null;
        $this->abandoned_at = null;
        $this->completed_at = null;

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
     * Returns an empty request insurance builder instance
     *
     * @return RequestInsuranceBuilder
     */
    public static function builder(): RequestInsuranceBuilder
    {
        return RequestInsuranceBuilder::begin();
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
