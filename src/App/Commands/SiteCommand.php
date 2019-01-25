<?php

namespace App\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class SiteCommand extends Command
{
    protected $params;
    protected $siteName;

    public function __construct(ParameterBag $params)
    {
      $this->params = $params;
      return parent::__construct();
    }

    protected function setSiteName(string $name)
    {
      $this->siteName = $name;
    }

    protected function getSiteName()
    {
      return $this->siteName;
    }

    protected function getRootDir()
    {
      return '/var/www/' . $this->getSiteName();
    }

    protected function getWebroot()
    {
      return $this->getRootDir() . '/webroot';
    }

    protected function getApacheConfigPath()
    {
      return '/etc/apache2/sites-available/' . $this->getSiteName() . '.conf';
    }

    protected function getDbName()
    {
      return $this->getSiteName();
    }

    protected function runProcess(array $args, array $options = [])
    {
        $options += [
          'exception' => TRUE,
          'output' => function ($type, $buffer) {
            echo $buffer;
          },
        ];
        $process = new Process($args);
        $process->run($options['output']);
        if (!$process->isSuccessful() && $options['exception']) {
          throw new ProcessFailedException($process);
        }
        return $process;
    }
}
