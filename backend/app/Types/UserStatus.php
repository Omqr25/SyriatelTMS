<?php

namespace App\Types;

class UserStatus
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public const Active="active";
    public const Inactive="inactive";
    public const Suspended="suspended";
    public const Completed="completed";
}
