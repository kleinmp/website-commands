<?php

namespace App\Site\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCommand extends Command
{
    protected static $defaultName = 'site:delete';

    protected function configure()
    {
        $this->setName(self::$defaultName)
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
        $this->deleteSolr($input, $output);
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
          $this->runProcess(['sudo', 'systemctl', 'reload', 'apache2']);
          $this->runProcess(['rm', $apacheConfigFile]);
          $output->writeln(sprintf('Deleted apache config %s', $apacheConfigFile));
        }
        else {
          $output->writeln(sprintf('Apache config %s does not exist.', $apacheConfigFile));
        }
    }

    protected function deleteSolr(InputInterface $input, OutputInterface $output)
    {
      if ($this->solrCoreCreated()) {
        $solrPath = $this->params->get('site.solr_path');
        $this->runProcess(['sudo', '-u', 'solr', '--', $solrPath, 'delete', '-c', $this->getDbName()]);
        $output->writeln(sprintf('Solr core %s was deleted.', $this->getDbName()));
      }
      else {
        $output->writeln(sprintf('Solr core %s does not exist.', $this->getDbName()));
      }
    }

}
