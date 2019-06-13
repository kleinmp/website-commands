<?php

namespace App\Server\Commands;

use App\Base\Commands\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallPhp extends Command
{
    protected $versions = ['5.6', '7.0', '7.1', '7.2'];

    protected function configure()
    {
        $this->setName('server:installphp')
            ->setDescription('Install new version of php.')
            ->setHelp('Install new version of php.')
            ->addArgument('version', InputArgument::REQUIRED, 'The version of php to install.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version');

        // install php-version script
        $phpVersionScriptPath = $this->params->get('server.php_version_script_path');
        if (!file_exists($phpVersionScriptPath)) {
          $this->runProcess(['sudo', 'cp', '../templates/php-version', $phpVersionScriptPath]);
          $this->runProcess(['sudo', 'chmod', '+x', $phpVersionScriptPath]);
        }
        // check version
        if (!in_array($version, $this->versions)) {
          throw new InvalidArgumentException(sprintf('%s is not a valid version of php.  Must be one of ' . implode(', ', $this->versions) . '.', $version));
        }

        // check if installed, install packages
        if (file_exists('/usr/bin/php' . $version)) {
          throw new InvalidArgumentException(sprintf('Php version %s is already installed.', $version));
        }

        $this->installPhpPackages($version, $input, $output);
        // check if fpm installed, install fpm

        /*$sqlFile = pathinfo($sqlPath);
        if (!file_exists($sqlPath)) {
          throw new InvalidArgumentException(sprintf('%s is not an existing file.', $sqlPath));
        }
        if ($sqlFile['extension'] != 'sql') {
          throw new InvalidArgumentException(sprintf('%s is not a valid sql file.', $sqlPath));
        }
        $question = new ConfirmationQuestion(sprintf('Are you sure you want to import a new db into %s?  This will delete the current db. [no]: ', $this->getDbName()), false);
        if (!$this->getHelper('question')->ask($input, $output, $question)) {
          return;
        }

        $dbName = $this->getDbName();
        $this->runProcess(['mysql', '-e', "drop database $dbName"], ['exception' => FALSE]);
        $this->runProcess(['mysql', '-e', "create database $dbName"]);
        $this->runProcess(['mysql', $dbName, '-e', "source $sqlPath"], ['output' => NULL, 'timeout' => NULL]);
        $output->writeln(sprintf('Imported new db to %s', $dbName));*/
    }

    protected function installPhpPackages($version, InputInterface $input, OutputInterface $output)
    {
      $packages = [
        'bcmath',
        'cli',
        'common',
        'curl',
        'dev',
        'fpm',
        'gd',
        'json',
        'mysql',
        'mbstring',
        'opcache',
        'soap',
        'xml',
        'zip',
      ];
      array_walk($packages, function(&$package) use ($version) {
        $package = 'php' . $version . '-' . $package;
      });
      $command = ['sudo', 'apt-get', 'install', '-y', 'php' . $version] + $packages;
      $this->runProcess($command);
    }
}
