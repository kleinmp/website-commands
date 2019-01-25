<?php

namespace App\App\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SiteDeleteCommand extends SiteCommand
{
    protected static $defaultName = 'app:delete';

    protected function configure()
    {
        $this->setName('app:delete')
            ->setDescription('Delete local site')
            ->setHelp('Delete local site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setSiteName($input->getArgument('name'));
        $question = new ConfirmationQuestion(sprintf('Are you sure you want to delete %s?  This will drop all files and databases and cannot be undone [no]: ', $this->getSiteName()), false);
        if (!$this->getHelper('question')->ask($input, $output, $question)) {
          return;
        }

        $this->deleteDirectory($input, $output);
        $this->deleteDb($input, $output);
        $this->deleteServer($input, $output);
    }

    protected function deleteDirectory(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $this->getRootDir();
        if (file_exists($rootDir)) {
          $this->runProcess(['rm', '-rf', $rootDir]);
          $output->writeln(sprintf('Deleted directory %s', $rootDir));
        }
        else {
          $output->writeln(sprintf('Directory %s does not exist.', $rootDir));
        }
    }

    protected function deleteDb(InputInterface $input, OutputInterface $output)
    {
        $dbName = $this->getDbName();
        $process = $this->runProcess(['mysql', '-e', "use $dbName"], ['exception' => FALSE, 'output' => NULL]);
        if ($process->isSuccessful()) {
          $this->runProcess(['mysql', '-e', "drop database $dbName"]);
          $output->writeln(sprintf('Database %s deleted.', $dbName));
        }
        else {
          $output->writeln(sprintf('Database %s does not exist.', $dbName));
        }
    }

    protected function deleteServer(InputInterface $input, OutputInterface $output)
    {
        $apacheConfigFile = $this->getApacheConfigPath();
        if (file_exists($apacheConfigFile)) {
          $this->runProcess(['sudo', 'a2dissite', $this->getSiteName() . '.conf'], ['output' => NULL]);
          $this->runProcess(['sudo', 'service', 'apache2', 'reload']);
          $this->runProcess(['rm', $apacheConfigFile]);
          $output->writeln(sprintf('Deleted apache config %s', $apacheConfigFile));
        }
        else {
          $output->writeln(sprintf('Apache config %s does not exist.', $apacheConfigFile));
        }
    }

}
