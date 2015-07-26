<?php

namespace React;

use Clue\React\Stdio\Stdio;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

class ReactOutput extends Output
{
    /**
     * @var Stdio
     */
    private $stdio;

    public function __construct(
      Stdio $stdio,
      $verbosity = self::VERBOSITY_NORMAL,
      $decorated = false,
      OutputFormatterInterface $formatter = null
    ) {
        parent::__construct($verbosity, $decorated, $formatter);
        $this->stdio = $stdio;
    }

    protected function doWrite($message, $newline)
    {
        $newline ? $this->stdio->writeln($message) : $this->stdio->write($message);
    }
}

