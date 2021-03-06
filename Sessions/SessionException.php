<?php

namespace Wizard\Sessions;

use Wizard\Exception\WizardRuntimeException;

class SessionException extends WizardRuntimeException
{

    function __construct($message, $solution = null)
    {
        parent::__construct($message, $solution, 'SessionException');
    }

}