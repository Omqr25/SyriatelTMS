<?php

namespace App\Types;

class ApplicationStatus
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public const Pending="pending";
    public const UnderReview="underReview";
    public const Accepted="accepted";
    public const Rejected="rejected";
    public const Interviewed="interviewed";
    public const ForInterview="forInterview";
    public const Waiting="waiting";
    public const Withdrawn="withdrawn";
}
