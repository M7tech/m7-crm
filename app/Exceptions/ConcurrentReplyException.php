<?php

namespace App\Exceptions;

use RuntimeException;

class ConcurrentReplyException extends RuntimeException
{
    public function __construct(public readonly ?string $senderName)
    {
        parent::__construct('A newer reply was sent while this draft was being written.');
    }
}
