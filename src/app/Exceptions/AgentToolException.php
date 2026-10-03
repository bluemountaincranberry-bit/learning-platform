<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by an AgentTool for bad/invalid arguments or an unmet precondition.
 * Caught by the agent loop and fed back to the model as a tool error result
 * (not a hard failure) so it can retry with corrected arguments.
 */
class AgentToolException extends Exception
{
}
