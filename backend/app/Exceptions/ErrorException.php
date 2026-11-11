<?php

namespace App\Exceptions;

use App\Helpers\ApiCode;
use Exception;
use Throwable;

class ErrorException extends Exception
{
   public $message;
    public function __construct(
        string $message = null,
        public int $errorCode = ApiCode::INTERNAL_SERVER_ERROR,
        public mixed $data = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?? __('messages.error.server_error'),
            $errorCode,
            $previous
        );
    }
}
