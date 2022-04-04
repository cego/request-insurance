<?php

namespace Cego\RequestInsurance\Models;

use Exception;
use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;

class SaveRetryingModel extends Model
{
    /**
     * Save the model to the database.
     *
     * @param array $options
     *
     * @throws Exception
     *
     * @return bool
     */
    public function save(array $options = []): bool
    {
        $maxTries = 3;

        for ($try = 1; $try <= $maxTries; $try++) {
            try {
                return parent::save($options);
            } catch (Exception $exception) {
                if ($this->shouldNotRetry($try, $maxTries, $exception)) {
                    throw $exception;
                }

                // Sleep 10ms before retrying
                usleep(10000);
            }
        }

        throw new RuntimeException(sprintf('%s: Unexpected state, the save method should either return or throw an exception...', __METHOD__));
    }

    /**
     * Returns true if we should not retry saving
     *
     * @param int $try
     * @param int $maxTries
     * @param Exception $exception
     *
     * @return bool
     */
    protected function shouldNotRetry(int $try, int $maxTries, Exception $exception): bool
    {
        return $this->isLastSaveRetry($try, $maxTries)
            || $this->exceptionIsInstanceOfNonRetryableExceptionTypes($exception);
    }

    /**
     * Returns true if this is the last retry for saving a model
     *
     * @param $try
     * @param $maxTries
     *
     * @return bool
     */
    protected function isLastSaveRetry($try, $maxTries): bool
    {
        return $try == $maxTries;
    }

    /**
     * Returns true if the given exception is an exception that should not be retried
     *
     * @param Exception $exception
     *
     * @return bool
     */
    protected function exceptionIsInstanceOfNonRetryableExceptionTypes(Exception $exception): bool
    {
        return $exception instanceof EmptyPropertyException;
    }
}
