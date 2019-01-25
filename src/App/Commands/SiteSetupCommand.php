<?php

namespace App\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
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
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site.')
            ->addArgument('webroot', InputArgument::REQUIRED, 'The path to the webroot.')
            ->addArgument('fpm-port', InputArgument::OPTIONAL, 'The path to the webroot.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $webrootPath = $input->getArgument('webroot');
        $port = $input->getArgument('fpm-port') ? $input->getArgument('fpm-port') : 9001;
        $domainSuffix = $this->params->get('app.domain_suffix');
        $rootDir = '/var/www/' . $name;
        $webrootFullPath = $rootDir . '/' . $webrootPath;
        $webrootLinkPath = $rootDir . '/webroot';
        $apacheConfigFile = '/etc/apache2/sites-available/' . $name . '.conf';

        if (!file_exists($rootDir)) {
          $this->runProcess(['mkdir', '-p', $rootDir]);
          $output->writeln(sprintf('Created directory %s', $rootDir));
        }
        else {
          $output->writeln(sprintf('Directory %s already exists.', $rootDir));
        }

        if (!file_exists($webrootLinkPath) && !is_link($webrootLinkPath)) {
          $this->runProcess(['ln', '-s', $webrootFullPath, $webrootLinkPath]);
          $output->writeln(sprintf('Created symlink %s', $webrootLink_path));
        }
        else {
          $output->writeln(sprintf('Symlink %s already exists.', $webrootLinkPath));
        }

        $process = $this->runProcess(['mysql', '-e', "use $name"], FALSE);
        if (!$process->isSuccessful()) {
          $this->runProcess(['mysql', '-e', "create database $name"]);
          $output->writeln(sprintf('Database %s created.', $name));
        }
        else {
          $output->writeln(sprintf('Database %s already exists.', $name));
        }

        if (!file_exists($apacheConfigFile)) {
					$template = $this->twig->load('apache.conf.twig');
					$apacheConfigContents = $template->render([
						'webroot' => $webrootLinkPath,
						'domain' => $name . '.' . $domainSuffix,
						'port' => $port,
					]);
          file_put_contents($apacheConfigFile, $apacheConfigContents);
          $this->runProcess(['sudo', 'a2ensite', $name . '.conf']);
          $this->runProcess(['sudo', 'service', 'apache2', 'reload']);
          $output->writeln(sprintf('Created apache config %s', $apacheConfigFile));
        }
        else {
          $output->writeln(sprintf('Apache config %s already exists.', $apacheConfigFile));
        }
        $output->writeln(sprintf('The site is available at %s', $name . '.' . $domainSuffix));

    }

    protected function runProcess(array $args, $throwException = TRUE)
    {
        $process = new Process($args);
				$process->run();
        if (!$process->isSuccessful() && $throwException) {
          throw new ProcessFailedException($process);
        }
        return $process;
    }
}
