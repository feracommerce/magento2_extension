<?php
/**
 * @author: Sviatoslav Lashkiv
 * @email: ss.lashkiv@gmail.com
 * @team: MageCloud
 */

namespace Fera\Ai\Logger;

/**
 * Class Logger
 * @package Fera\Ai\Logger
 */
class Logger extends \Monolog\Logger
{
    /**
     * Logger constructor.
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(string $name, $handlers = array(), $processors = array())
    {
        parent::__construct($name, $handlers, $processors);
    }
}
