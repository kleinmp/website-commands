<?php

namespace App\Site\Commands;

use App\Base\Commands\Command as BaseCommand;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class Command extends BaseCommand
{
    protected $siteName;
    protected $solrSchemaPath;

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
      return $this->params->get('site.www_dir') . $this->getSiteName();
    }

    protected function getWebroot()
    {
      return $this->getRootDir() . '/webroot';
    }

    protected function getApacheConfigPath()
    {
      return $this->params->get('site.apache_conf_dir') . $this->getSiteName() . '.conf';
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
      return is_dir($this->params->get('site.solr_data_path') . $this->getSiteName());
    }
}
