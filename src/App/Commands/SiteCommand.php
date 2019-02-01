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
    protected $solrSchemaPath;

    public function __construct(ParameterBag $params)
    {
      $this->params = $params;
      return parent::__construct();
    }

    protected function setSiteName(string $name)
    {
      $this->siteName = $name;
    }

    protected function setSolrSchemaPath(string $path)
    {
      $this->solrSchemaPath = $path;
    }

    protected function getSiteName()
    {
      return $this->siteName;
    }

    protected function getRootDir()
    {
      return $this->params->get('app.www_dir') . $this->getSiteName();
    }

    protected function getWebroot()
    {
      return $this->getRootDir() . '/webroot';
    }

    protected function getApacheConfigPath()
    {
      return $this->params->get('app.apache_conf_dir') . $this->getSiteName() . '.conf';
    }

    protected function getDbName()
    {
      return $this->getSiteName();
    }

    protected function getSolrSchemaPath()
    {
      return $this->solrSchemaPath;
    }

    protected function solrCoreCreated()
    {
      return is_dir($this->params->get('app.solr_data_path') . $this->getSiteName());
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
