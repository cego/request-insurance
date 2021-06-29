<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\Component;

class HttpCode extends Component
{
    /**
     * Holds the http status code
     *
     * @var int $code
     */
    public $httpCode;

    /**
     * HttpStatusCode constructor.
     *
     * @param int $httpCode
     */
    public function __construct($httpCode)
    {
        $this->httpCode = $httpCode;
    }

    public function badgeColor()
    {
        if ($this->httpCode >= 100 && $this->httpCode < 200) {
            return 'secondary';
        }

        if ($this->httpCode >= 200 && $this->httpCode < 300) {
            return 'success';
        }

        if ($this->httpCode >= 300 && $this->httpCode < 400) {
            return 'primary';
        }

        if ($this->httpCode >= 400 && $this->httpCode < 500) {
            return 'danger';
        }

        if ($this->httpCode >= 500) {
            return 'warning';
        }
    }

    public function render()
    {
        return view('request-insurance::components.http-code');
    }
}
