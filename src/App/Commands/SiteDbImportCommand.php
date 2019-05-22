<?php

namespace App\App\Commands;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SiteDbImportCommand extends SiteCommand
{
    protected static $defaultName = 'app:dbimport';

    protected function configure()
    {
        $this->setName('app:dbimport')
            ->setDescription('Import db into local site')
            ->setHelp('Import db into local site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site.')
            ->addArgument('dbpath', InputArgument::REQUIRED, 'The path to the sql file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setSiteName($input->getArgument('name'));
        $sqlPath = $input->getArgument('dbpath');
        $sqlFile = pathinfo($sqlPath);
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
        $output->writeln(sprintf('Imported new db to %s', $dbName));
    }

}
