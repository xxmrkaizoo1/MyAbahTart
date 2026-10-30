<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A live delivery price could not be worked out. The message is safe to show to customers.
 */
class DeliveryQuoteException extends RuntimeException
{
    //
}
