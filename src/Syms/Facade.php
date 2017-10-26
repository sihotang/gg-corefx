<?php

namespace Syms;

class Facade extends \Illuminate\Support\Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return Syms::class;
    }
}
