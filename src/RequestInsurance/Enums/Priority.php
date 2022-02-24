<?php

namespace Cego\RequestInsurance\Enums;

abstract class Priority
{
    public const DEFAULT = 9999;

    public const VERY_LOW = 9000;

    public const LOW = 7000;

    public const MEDIUM = 5000;

    public const HIGH = 3000;

    public const VERY_HIGH = 1000;
}
