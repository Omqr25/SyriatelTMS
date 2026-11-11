<?php

namespace App\Types;

class TaskStatus
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public const Pending="pending";
    public const InProgress="inProgress";
    public const Completed="completed";
    public const Cancelled="cancelled";

}
