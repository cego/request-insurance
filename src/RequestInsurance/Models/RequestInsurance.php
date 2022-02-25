<?php

namespace Cego\RequestInsurance\Models;

use Exception;
use Carbon\Carbon;
use JsonException;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Cego\RequestInsurance\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\HttpResponse;
use Illuminate\Database\Eloquent\Builder;
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
            foreach (['headers', 'payload'] as $attribute) {
                $attributeArray = $this->getAttributeCastToArray($attribute);

                foreach ($this->getEncryptedAttribute($attribute) as $encryptedField) {
                    if (Arr::has($attributeArray, $encryptedField)) {
                        $unencryptedFieldValue = Arr::get($attributeArray, $encryptedField);

                        Arr::set($attributeArray, $encryptedField, Crypt::encrypt($unencryptedFieldValue));
                    }
                }

                $this->$attribute = json_encode($attributeArray, JSON_THROW_ON_ERROR);
            }

            $this->isEncrypted = true;
        } catch (Exception $exception) {
            Log::error(sprintf('Could not encrypt RI: %s', (string) $exception));
        }

        return $this;
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
            foreach (['headers', 'payload'] as $attribute) {
                $fieldArray = $this->getAttributeCastToArray($attribute);

                // We reverse the order of the returned array, so that if we encrypt in order A -> B -> C
                // then we also decrypt in the order of C -> B -> A.
                //
                // The reason for this is if there is a bug, which allows the same field to be
                // encrypted multiple times, then it is important that we decrypt
                // in the reverse order.

                foreach ($this->getEncryptedAttribute($attribute, true) as $encryptedField) {
                    if (Arr::has($fieldArray, $encryptedField)) {
                        $encryptedAttributeValue = Arr::get($fieldArray, $encryptedField);

                        Arr::set($fieldArray, $encryptedField, Crypt::decrypt($encryptedAttributeValue));
                    }
                }

                $this->$attribute = json_encode($fieldArray, JSON_THROW_ON_ERROR);
            }

            $this->isEncrypted = false;
        } catch (Exception $exception) {
            Log::error(sprintf('Could not decrypt RI: %s', (string) $exception));
        }

        return $this;
    }

    /**
     * Returns a field cast to array
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
     * Returns the payload cast to array
     *
     * @throws JsonException
     *
     * @return array
     */
    public function getPayloadCastToArray(): array
    {
        return $this->getAttributeCastToArray('payload');
    }

    /**
     * Returns the flat array of the field which should be encrypted, using the dot notation for nested levels of encryption.
     *
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
     * Returns the field as a json string, with encrypted headers marked as [ ENCRYPTED ].
     * We use this to avoid breaking the interface with long encrypted values.
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getAttributeWithMaskingApplied(string $attribute): string
    {
        $fieldArray = $this->getAttributeCastToArray($attribute);

        $encryptedFieldArray = $this->getEncryptedAttribute($attribute);

        foreach ($encryptedFieldArray as $encryptedField) {
            if (Arr::has($fieldArray, $encryptedField)) {
                Arr::set($fieldArray, $encryptedField, '[ ENCRYPTED ]');
            }
        }

        return json_encode($fieldArray, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the payload as a json string, with encrypted headers marked as [ ENCRYPTED ].
     * We use this to avoid breaking the interface with long encrypted values.
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getHeadersWithMaskingApplied(): string
    {
        return $this->getAttributeWithMaskingApplied('headers');
    }

    /**
     * Returns the headers as a json string, with encrypted headers marked as [ ENCRYPTED ].
     * We use this to avoid breaking the interface with long encrypted values.
     *
     * @throws JsonException
     *
     * @return string
     */
    public function getPayloadWithMaskingApplied(): string
    {
        return $this->getAttributeWithMaskingApplied('payload');
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

        if ($request->has('trace_id') && trim($request->get('trace_id'))) {
            $query = $query->where('trace_id', $request->get('trace_id'));
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
        // To avoid marking successful requests as failed, we add this successful check
        $this->paused_at = $this->wasSuccessful() ? null : Carbon::now();
        $this->retry_at = null;
        $this->abandoned_at = null;
        $this->completed_at = $this->wasSuccessful() ? Carbon::now() : null;

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
     * @throws MethodNotAllowedForRequestInsurance
     *
     * @return $this
     */
    public function process()
    {
        // An event is dispatched before processing begins
        // allowing the application to abandon/complete/paused the requests before processing.
        Events\RequestBeforeProcess::dispatch($this);

        if ($this->isAbandoned() || $this->isCompleted() || $this->isPaused()) {
            return $this;
        }

        // Increment the number of tries as the very first action
        $this->incrementRetryCount();

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
     * @throws MethodNotAllowedForRequestInsurance
     *
     * @return HttpResponse
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
     * @throws Exception
     *
     * @return $this
     */
    public function retry()
    {
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
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return new RequestInsuranceFactory();
    }
}
