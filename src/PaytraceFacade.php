<?php

namespace Christhompsontldr\Paytrace;

use Illuminate\Support\Facades\Facade;

class PaytraceFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'paytrace';
    }
}