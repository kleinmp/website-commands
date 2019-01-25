<?php

namespace App\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Twig_Environment;

class SiteSetupCommand extends Command
{
    protected static $defaultName = 'app:setup';
    private $params;
    private $twig;

    public function __construct(Twig_Environment $twig, ParameterBag $params) {
      $this->twig = $twig;
      $this->params = $params;
      return parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:setup')
            ->setDescription('Sets up a local site')
            ->setHelp('Sets up a local site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $domainSuffix = $this->params->get('app.domain_suffix');
        $rootDir = '/var/www/' . $name;
        $webrootLinkPath = $rootDir . '/webroot';
        $apacheConfigFile = '/etc/apache2/sites-available/' . $name . '.conf';
        $helper = $this->getHelper('question');

        if (!file_exists($rootDir)) {
          $this->runProcess(['mkdir', '-p', $rootDir]);
          $output->writeln(sprintf('Created directory %s', $rootDir));
        }
        else {
          $output->writeln(sprintf('Directory %s already exists.', $rootDir));
        }

        if (!file_exists($rootDir . '/code')) {
          $question = new Question('Please enter the git repository (Leave empty to avoid cloning) [NULL]: ', NULL);
          if ($repo = $helper->ask($input, $output, $question)) {
            $this->runProcess(['git', 'clone', $repo, $rootDir . '/code']);
            $output->writeln(sprintf('Cloned repo to %s', $rootDir . '/code'));
          }
        }
        else {
          $output->writeln(sprintf('Repo already exists at %s', $rootDir . '/code'));
        }

        if (!file_exists($webrootLinkPath) && !is_link($webrootLinkPath)) {
          $question = new Question('Please enter the relative path to the webroot [code]: ', 'code');
          $webrootPath = $helper->ask($input, $output, $question);
          $webrootFullPath = $rootDir . '/' . $webrootPath;
          $this->runProcess(['ln', '-s', $webrootFullPath, $webrootLinkPath]);
          $output->writeln(sprintf('Created symlink %s', $webrootLinkPath));
        }
        else {
          $output->writeln(sprintf('Symlink %s already exists.', $webrootLinkPath));
        }

        $process = $this->runProcess(['mysql', '-e', "use $name"], ['exception' => FALSE, 'output' => NULL]);
        if (!$process->isSuccessful()) {
          $this->runProcess(['mysql', '-e', "create database $name"]);
          $output->writeln(sprintf('Database %s created.', $name));
        }
        else {
          $output->writeln(sprintf('Database %s already exists.', $name));
        }

        if (!file_exists($apacheConfigFile)) {
          $question = new Question('Please enter the php-fpm port on which to run the site [9001]: ', 9001);
          $port = $helper->ask($input, $output, $question);
          $template = $this->twig->load('apache.conf.twig');
          $apacheConfigContents = $template->render([
            'webroot' => $webrootLinkPath,
            'domain' => $name . '.' . $domainSuffix,
            'port' => $port,
          ]);
          file_put_contents($apacheConfigFile, $apacheConfigContents);
          $this->runProcess(['sudo', 'a2ensite', $name . '.conf'], ['output' => NULL]);
          $this->runProcess(['sudo', 'service', 'apache2', 'reload']);
          $output->writeln(sprintf('Created apache config %s', $apacheConfigFile));
        }
        else {
          $output->writeln(sprintf('Apache config %s already exists.', $apacheConfigFile));
        }
        $output->writeln(sprintf('The site is available at %s', $name . '.' . $domainSuffix));

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
