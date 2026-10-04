<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an AI feature can't produce a result; the message is safe to show to users. */
class AiUnavailableException extends RuntimeException {}
