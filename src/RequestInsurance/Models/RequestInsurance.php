<?php

namespace Cego\RequestInsurance\Models;

use Exception;
use Throwable;
use Carbon\Carbon;
use JsonException;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use GuzzleHttp\TransferStats;
use Cego\RequestInsurance\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Crypt;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\HttpResponse;
use Illuminate\Database\Eloquent\Builder;
use Cego\RequestInsurance\Casts\CarbonUtc;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\RequestInsuranceBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;
use Cego\RequestInsurance\Factories\RequestInsuranceFactory;
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
 * @property string|null $trace_id
 * @property string|array|null $encrypted_fields
 * @property string|array $response_headers
 * @property string $response_body
 * @property int $response_code
 * @property int $retry_count
 * @property int $retry_factor
 * @property int $retry_cap
 * @property Carbon|null $retry_at
 * @property bool $retry_inconsistent
 * @property string $state
 * @property Carbon $state_changed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string | null $timings
 *
 * @method static RequestInsurance create($attributes = [])
 * @method static RequestInsurance|null first(array|string $columns = [])
 */
class RequestInsurance extends SaveRetryingModel
{
    use HasFactory;

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * Variable which marks if the model is currently encrypted.
     *
     * @var bool
     */
    protected bool $isEncrypted = false;

    /**
     * Total time duration for a request insurance before it receives a response.
     * Default is -1 to indicate that the field was never set.
     *
     * @var int
     */
    protected int $totalTime = -1;

    protected $casts = [
        'retry_at'         => CarbonUtc::class,
        'state_changed_at' => CarbonUtc::class,
        'created_at'       => CarbonUtc::class,
        'updated_at'       => CarbonUtc::class,
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
     * Get a fresh timestamp for the model.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function freshTimestamp(): \Illuminate\Support\Carbon
    {
        return Date::now('UTC');
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @throws JsonException
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::retrieved(function (RequestInsurance $request) {
            // A model retrieved from the database will always be encrypted at the time of extraction
            // unless the encrypted fields is equal to null.
            $request->isEncrypted = $request->usesEncryption();

            // And we therefore want to decrypt it, before the user/worker accesses the request
            if ($request->isEncrypted()) {
                $request->decrypt();
            }
        });

        // Set correct created and updated at fields
        static::creating(function (RequestInsurance $request) {
            $request->created_at ??= Carbon::now('UTC');
            $request->updated_at ??= Carbon::now('UTC');

            $request->created_at = $request->created_at->setTimezone('UTC');
            $request->updated_at = $request->updated_at->setTimezone('UTC');
        });

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

            if ($request->state_changed_at === null) {
                $request->state_changed_at = Carbon::now('UTC');
            }

            // In case someone forgets to update the state_changed_at field when updating the state
            if ($request->isDirty('state') && ! $request->isDirty('state_changed_at')) {
                $request->state_changed_at = Carbon::now('UTC');
            }

            /** @var Request $httpRequest */
            $httpRequest = request();

            if ( ! $request->trace_id) {
                if ($httpRequest->hasHeader('X-Request-Trace-Id')) {
                    $request->trace_id = $httpRequest->header('X-Request-Trace-Id');
                } else {
                    // Use cloudflare unique request id if present, or fallback to an uuid.
                    // We use cloudflare so that we can find the origin request that spawned the trace.
                    $request->trace_id = $httpRequest->header('cf-ray', Uuid::uuid6()->toString());
                }
            }

            // Make sure the headers contains the X-Request-Trace-Id header
            $request->headers ??= [];

            if ( ! is_array($request->headers)) {
                $request->headers = json_decode($request->headers, true, 512, JSON_THROW_ON_ERROR);
            }

            $request->headers = array_merge($request->headers, ['X-Request-Trace-Id' => $request->trace_id]);

            $request->encrypted_fields ??= [];

            if ( ! is_array($request->encrypted_fields)) {
                $request->encrypted_fields = json_decode($request->encrypted_fields, true, 512, JSON_THROW_ON_ERROR);
            }

            $encryptedAttributes = array_merge_recursive($request->encrypted_fields, Config::get('request-insurance.fieldsToAutoEncrypt', []));

            foreach ($encryptedAttributes as $outerKey => $encryptedFields) {
                $encryptedAttributes[$outerKey] = array_unique($encryptedFields);
            }

            if (Arr::has($encryptedAttributes, 'headers')) {
                $request->headers = array_merge($request->headers, ['X-Sensitive-Request-Headers-JSON' => json_encode(Arr::get($encryptedAttributes, 'headers'))]);
            }

            if (Arr::has($encryptedAttributes, 'payload')) {
                $request->headers = array_merge($request->headers, ['X-Sensitive-Request-Body-JSON' => json_encode(Arr::get($encryptedAttributes, 'payload'))]);
            }

            $request->encrypted_fields = $encryptedAttributes;

            // Make sure we never save an unencrypted RI to the database
            if ($request->usesEncryption()) {
                $request->encrypt();
            }

            // We make sure to json encode encrypted_fields to json if passed as an array
            if (is_array($request->encrypted_fields)) {
                $request->encrypted_fields = json_encode($request->encrypted_fields, JSON_THROW_ON_ERROR);
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

        static::saved(function (RequestInsurance $request) {
            // After the model have been saved, then decrypt it again locally.
            // It is NOT decrypted in the DB, it is only decrypted so that
            // when the person who created the RI accesses the model instance
            // can read the unencrypted data
            if ($request->usesEncryption()) {
                $request->decrypt();
            }
        });
    }

    /**
     * Encrypts the RI if it has not already been encrypted
     *
     * @return $this
     */
    public function encrypt(): self
    {
        if ( ! $this->usesEncryption() || $this->isEncrypted()) {
            return $this;
        }

        try {
            // Headers are always an array
            $this->headers = json_encode($this->encryptArray($this->getHeadersCastToArray(), $this->getEncryptedHeaders()), JSON_THROW_ON_ERROR);

            // Only process payload if it is an array
            if (is_array($payload = $this->getJsonDecodedPayload())) {
                $this->payload = json_encode($this->encryptArray($payload, $this->getEncryptedPayload()), JSON_THROW_ON_ERROR);
            }

            $this->isEncrypted = true;
        } catch (Exception $exception) {
            Log::error(sprintf('Could not encrypt RI: %s', (string) $exception));
        }

        return $this;
    }

    /**
     * Encrypts the keys of an array that matches the schema
     *
     * @param array $array
     * @param array $schema
     *
     * @return array
     */
    protected function encryptArray(array $array, array $schema): array
    {
        foreach ($schema as $key) {
            if (Arr::has($array, $key)) {
                $plainValue = Arr::get($array, $key);

                Arr::set($array, $key, Crypt::encrypt($plainValue));
            }
        }

        return $array;
    }

    /**
     * Decrypts the RI if it has not already been decrypted
     *
     * @return $this
     */
    public function decrypt(): self
    {
        if ( ! $this->usesEncryption() || $this->isUnencrypted()) {
            return $this;
        }

        try {
            // We reverse the order of the returned array, so that if we encrypt in order A -> B -> C
            // then we also decrypt in the order of C -> B -> A.
            //
            // The reason for this is if there is a bug, which allows the same field to be
            // encrypted multiple times, then it is important that we decrypt
            // in the reverse order.

            // Headers are always an array
            $this->headers = json_encode($this->decryptArray($this->getHeadersCastToArray(), $this->getEncryptedHeaders(true)), JSON_THROW_ON_ERROR);

            // Only process payload if it is an array
            if (is_array($payload = $this->getJsonDecodedPayload())) {
                $this->payload = json_encode($this->decryptArray($payload, $this->getEncryptedPayload(true)), JSON_THROW_ON_ERROR);
            }

            $this->isEncrypted = false;
        } catch (Exception $exception) {
            Log::error(sprintf('Could not decrypt RI: %s', (string) $exception));
        }

        return $this;
    }

    /**
     * Decrypts the keys of an array that matches the schema
     *
     * @param array $array
     * @param array $schema
     *
     * @return array
     */
    protected function decryptArray(array $array, array $schema): array
    {
        foreach ($schema as $key) {
            if (Arr::has($array, $key)) {
                $encryptedValue = Arr::get($array, $key);

                Arr::set($array, $key, Crypt::decrypt($encryptedValue));
            }
        }

        return $array;
    }

    /**
     * Returns a field cast to array. Will throw an error if attribute is not an array or json encoded array.
     *
     * @param string $attribute
     *
     * @throws JsonException
     *
     * @return array
     */
    private function getAttributeCastToArray(string $attribute): array
    {
        if (empty($this->$attribute)) {
            return [];
        }

        return is_array($this->$attribute)
            ? $this->$attribute
            : json_decode($this->$attribute, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns an attribute as an array if it's already an array or a json-encoded array
     * Otherwise just return the attribute
     *
     * @return array|string
     */
    public function getJsonDecodedPayload()
    {
        if (empty($this->payload) || is_array($this->payload)) {
            return $this->payload;
        }

        return json_decode($this->payload, true) ?? $this->payload;
    }

    /**
     * Returns the headers cast to array
     *
     * @throws JsonException
     *
     * @return array
     */
    public function getHeadersCastToArray(): array
    {
        return $this->getAttributeCastToArray('headers');
    }

    /**
     * Returns the flat array of the field which should be encrypted, using the dot notation for nested levels of encryption.
     *
     * @param string $attribute
     * @param bool $reversed
     *
     * @throws JsonException
     *
     * @return array
     */
    protected function getEncryptedAttribute(string $attribute, bool $reversed = false)
    {
        $encryptedAttributes = $this->getAttributeCastToArray('encrypted_fields');

        $encryptedAttribute = $encryptedAttributes[$attribute] ?? [];

        if ($reversed) {
            $encryptedAttribute = array_reverse($encryptedAttribute);
        }

        return $encryptedAttribute;
    }

    /**
     * Returns the flat array of the headers which should be encrypted, using the dot notation for nested levels of encryption.
     *
     * @param bool $reversed
     *
     * @throws JsonException
     *
     * @return array
     */
    protected function getEncryptedHeaders(bool $reversed = false): array
    {
        return $this->getEncryptedAttribute('headers', $reversed);
    }

    /**
     * Returns the flat array of the payload which should be encrypted, using the dot notation for nested levels of encryption.
     *
     * @param bool $reversed
     *
     * @throws JsonException
     *
     * @return array
     */
    protected function getEncryptedPayload(bool $reversed = false): array
    {
        return $this->getEncryptedAttribute('payload', $reversed);
    }

    /**
     * Returns the headers as a json string, with encrypted headers marked as [ ENCRYPTED ].
     * We use this to avoid breaking the interface with long encrypted values.
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getHeadersWithMaskingApplied(): string
    {
        $headers = $this->getHeadersCastToArray();

        $encryptedHeaders = $this->getEncryptedHeaders();

        foreach ($encryptedHeaders as $encryptedHeader) {
            if (Arr::has($headers, $encryptedHeader)) {
                Arr::set($headers, $encryptedHeader, '[ ENCRYPTED ]');
            }
        }

        return json_encode($headers, JSON_THROW_ON_ERROR);
    }

    /**
     * Sets stats field regarding request processing time to the timings field as an array.
     *
     * @param TransferStats $transferStats
     *
     * @return void
     */
    public function setTimings(TransferStats $transferStats): void
    {
        $handlerStats = $transferStats->getHandlerStats();

        $relevantStats = [
            'appconnect_time_us'    => $handlerStats['appconnect_time_us'] ?? -1,
            'connect_time_us'       => $handlerStats['connect_time_us'] ?? -1,
            'namelookup_time_us'    => $handlerStats['namelookup_time_us'] ?? -1,
            'pretransfer_time_us'   => $handlerStats['pretransfer_time_us'] ?? -1,
            'redirect_time_us'      => $handlerStats['redirect_time_us'] ?? -1,
            'starttransfer_time_us' => $handlerStats['starttransfer_time_us'] ?? -1,
            'total_time_us'         => $handlerStats['total_time_us'] ?? -1,
        ];

        $this->timings = json_encode($relevantStats);
    }

    /**
     * Gets the total time duration for a request.
     * Returns -1 if request was not sent / stats not received for some reason.
     *
     * @return int
     */
    public function getTotalTime()
    {
        if ( ! isset($this->timings)) {
            return -1;
        }

        $arrayTimings = json_decode($this->timings, true);
        $timeInMicroSeconds = $arrayTimings['total_time_us'] ?? null;

        if ($timeInMicroSeconds === null) {
            return -1;
        }

        return (int) floor($timeInMicroSeconds / 1000);
    }

    /**
     * Returns the payload as a json string, with encrypted headers marked as [ ENCRYPTED ].
     * We use this to avoid breaking the interface with long encrypted values.
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getPayloadWithMaskingApplied(): string
    {
        $payload = $this->getJsonDecodedPayload();

        // Only process payload if it is an array
        if ( ! is_array($payload)) {
            return $payload;
        }

        $encryptedPayload = $this->getEncryptedPayload();

        foreach ($encryptedPayload as $encryptedPayloadField) {
            if (Arr::has($payload, $encryptedPayloadField)) {
                Arr::set($payload, $encryptedPayloadField, '[ ENCRYPTED ]');
            }
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns true if the mode is currently encrypted
     *
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    /**
     * Returns true if the model is currently unencrypted
     *
     * @return bool
     */
    public function isUnencrypted(): bool
    {
        return ! $this->isEncrypted();
    }

    /**
     * Returns true if this RI is using encryption
     *
     * @return bool
     */
    protected function usesEncryption(): bool
    {
        return $this->encrypted_fields != null;
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
            ->where('state', State::READY)
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc');
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
        $searchedStates = [];

        foreach (State::getAll() as $state) {
            if ($request->get($state) == 'on') {
                $searchedStates[] = $state;
            }
        }

        if ( ! empty($searchedStates)) {
            $query->whereIn('state', $searchedStates);
        }

        if ($request->has('url') && trim($request->get('url'))) {
            $query = $query->where('url', 'like', $request->get('url'));
        }

        if ($request->has('trace_id') && trim($request->get('trace_id'))) {
            $query = $query->where('trace_id', $request->get('trace_id'));
        }

        try {
            if ($request->has('from') && $request->get('from') != null) {
                $from = Carbon::parse($request->get('from'));
                $query = $query->whereDate('created_at', '>=', $from);
                $query = $query->whereTime('created_at', '>=', $from);
            }

            if ($request->has('to') && $request->get('to') != null) {
                $to = Carbon::parse($request->get('to'));
                $query = $query->whereDate('created_at', '<=', $to);
                $query = $query->whereTime('created_at', '<=', $to);
            }
        } catch (Exception $exception) {
            Log::notice('Failed parsing from or to date to Carbon instance in filter');
        }

        return $query;
    }

    /**
     * Gets the shortened version of the payload
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getShortenedPayload(): string
    {
        $payload = $this->getPayloadWithMaskingApplied();

        if (strlen($payload) <= 125) {
            return $payload;
        }

        return sprintf('%s...', substr($payload, 0, 125));
    }

    /**
     * Unstuck the RequestInsurance instance if was left in the PENDING state
     *
     * @throws Exception
     *
     * @return $this
     */
    public function unstuckPending(): RequestInsurance
    {
        if ($this->hasState(State::PENDING)) {
            $this->setState(State::READY);
            $this->save();
        }

        return $this;
    }

    /**
     * Abandons the RequestInsurance instance
     *
     * @throws Exception
     *
     * @return $this
     */
    public function abandon(): RequestInsurance
    {
        $this->setState(State::ABANDONED);

        $this->save();

        return $this;
    }

    /**
     * Returns true if the request insurance has the given state
     *
     * @param string $state
     *
     * @return bool
     */
    public function hasState(string $state): bool
    {
        return $this->state == $state;
    }

    /**
     * Returns true if the RI has any of the given states
     *
     * @param array $states
     *
     * @return bool
     */
    public function hasAnyOfStates(array $states): bool
    {
        foreach ($states as $state) {
            if ($this->hasState($state)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the request insurance does not have the given state
     *
     * @param string $state
     *
     * @return bool
     */
    public function doesNotHaveState(string $state): bool
    {
        return ! $this->hasState($state);
    }

    /**
     * Returns true if the current state is one of the given states
     *
     * @param string ...$states
     *
     * @return bool
     */
    public function inOneOfStates(string ...$states): bool
    {
        return in_array($this->state, $states, true);
    }

    /**
     * Sets the state of the request insurance without saving
     *
     * @param string $state
     *
     * @return void
     */
    public function setState(string $state): void
    {
        if ( ! in_array($state, State::getAll(), true)) {
            throw new \InvalidArgumentException('Invalid state value: ' . $state);
        }

        $this->state = $state;
        $this->state_changed_at = Carbon::now('UTC');

        if ($state != State::WAITING) {
            $this->retry_at = null;
        }
    }

    /**
     * Retries the RI right now, instead of using backoff logic
     *
     * @throws Exception
     *
     * @return $this
     */
    public function retryNow(): RequestInsurance
    {
        if ( ! $this->isRetryable()) {
            return $this;
        }

        $this->setState(State::READY);
        $this->retry_at = null;

        $this->save();

        return $this;
    }

    /**
     * Retries the request insurance at a later time, unless it has exceeded maxRetries in which case the RI enters Failed state
     *
     * @param bool $save
     *
     * @throws Exception
     *
     * @return $this
     */
    public function retryLater(bool $save = true): RequestInsurance
    {
        // If retry_count exceeds maxRetries, the request enters the failed state to avoid an absurd amount of retries
        if ($this->retry_count > Config::get('request-insurance.maximumNumberOfRetries')) {
            $this->setState(State::FAILED);
        } else {
            $this->setState(State::WAITING);
            $this->setNextRetryAt();
        }

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Tells is the request insurance is retryable
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return $this->inOneOfStates(State::FAILED, State::ABANDONED);
    }

    /**
     * Returns the effective timeout
     *
     * @return int
     */
    public function getEffectiveTimeout(): int
    {
        if ($this->timeout_ms === null) {
            return Config::get('request-insurance.timeoutInSeconds', 20);
        }

        return ceil($this->timeout_ms / 1000);
    }

    /**
     * Processes the RequestInsurance instance
     *
     * @param HttpResponse $response
     *
     * @throws Throwable
     *
     * @return void
     */
    public function handleResponse(HttpResponse $response): void
    {
        // Update the request with the latest response
        $this->response_body = $response->getBody();
        $this->response_code = $response->getCode();
        $this->response_headers = $response->getHeaders();

        // Create a log for the request to track all attempts
        try {
            $this->logs()->create([
                'response_body'    => $this->response_body,
                'response_code'    => $this->response_code,
                'response_headers' => $this->response_headers,
                'timings'          => $this->timings,
            ]);
        } catch (Exception $exception) {
            Log::error(sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString()));
        }

        if ($response->isInconsistent()) {
            $response->logInconsistentReason();

            if ($this->retry_inconsistent) {
                $this->retryLater(false);
            } else {
                $this->setState(State::FAILED);
            }
        } elseif ($response->wasSuccessful()) {
            $this->setState(State::COMPLETED);
        // If response code is 408, we take it as a timeout and set the request to retry again later
        } elseif ($response->isRetryable() || ($response->getCode() == 408 && $this->retry_inconsistent)) {
            $this->retryLater(false);
        } else {
            $this->setState(State::FAILED);
        }

        $this->save();

        try {
            $this->dispatchPostProcessEvents($response);
        } catch (Throwable $throwable) {
            Log::error($throwable);

            // If the request would have been retried, but a listener threw an exception
            // then mark the request as FAILED - To force human eyes to look at the request.
            if ($this->hasAnyOfStates([State::WAITING, State::READY]) && $response->wasNotSuccessful()) {
                $this->setState(State::FAILED);
            }
        }
    }

    /**
     * Sends the request to the target URL and returns the response
     *
     * @throws JsonException
     * @throws MethodNotAllowedForRequestInsurance
     *
     * @return HttpResponse
     */
    protected function sendRequest(): HttpResponse
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
            $request->setTimeout(ceil($this->timeout_ms / 1000));
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
        if ($response->isInconsistent()) {
            Events\RequestInconsistent::dispatch($this);

            return;
        }

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
     * Sets the next retry_at timestamp
     *
     * @return $this
     */
    public function setNextRetryAt(): RequestInsurance
    {
        $seconds = pow($this->retry_factor, $this->retry_count);

        if ($seconds > $this->retry_cap) {
            $seconds = $this->retry_cap;
        }

        $this->retry_at = Carbon::now('UTC')->addSeconds($seconds);

        return $this;
    }

    /**
     * Returns an empty request insurance builder instance
     *
     * @return RequestInsuranceBuilder
     */
    public static function getBuilder(): RequestInsuranceBuilder
    {
        return RequestInsuranceBuilder::new();
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

    /**
     * Relationship with RequestInsuranceEdit
     *
     * @return HasMany
     */
    public function edits()
    {
        return $this->hasMany(RequestInsuranceEdit::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return new RequestInsuranceFactory();
    }
}
