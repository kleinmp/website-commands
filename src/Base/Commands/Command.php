<?php

namespace App\Base\Commands;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class Command extends SymfonyCommand
{
    protected $params;

    public function __construct(ParameterBag $params)
    {
      $this->params = $params;
      return parent::__construct();
    }

    protected function runProcess(array $args, array $options = [])
    {
        $options += [
          'exception' => TRUE,
          'output' => function ($type, $buffer) {
            echo $buffer;
          },
          'cwd' => NULL,
          'input' => NULL,
          'timeout' => 60,
        ];
        $process = new Process($args, $options['cwd'], NULL, $options['input'], $options['timeout']);
        $process->run($options['output']);
        if (!$process->isSuccessful() && $options['exception']) {
          throw new ProcessFailedException($process);
        }
        return $process;
    }
}
