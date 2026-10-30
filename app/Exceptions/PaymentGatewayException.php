<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The payment gateway could not be reached or refused a request. The message is safe to show to customers.
 */
class PaymentGatewayException extends RuntimeException
{
    //
}
