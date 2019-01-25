<?php

namespace App\App\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig_Environment;

class SiteSetupCommand extends SiteCommand
{
    protected static $defaultName = 'app:setup';
    private $twig;

    public function __construct(ParameterBag $params, Twig_Environment $twig)
    {
      $this->twig = $twig;
      return parent::__construct($params);
    }

    protected function configure()
    {
        $this->setName('app:setup')
            ->setDescription('Set up a local site')
            ->setHelp('Sets up a local site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the site.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setSiteName($input->getArgument('name'));
        $this->setupDirectory($input, $output);
        $this->setupDb($input, $output);
        $this->setupServer($input, $output);
        $this->setupDrupal($input, $output);
    }

    protected function setupDirectory(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $this->getRootDir();
        $webrootLinkPath = $this->getWebroot();
        if (!file_exists($rootDir)) {
          $this->runProcess(['mkdir', '-p', $rootDir]);
          $output->writeln(sprintf('Created directory %s', $rootDir));
        }
        else {
          $output->writeln(sprintf('Directory %s already exists.', $rootDir));
        }

        if (!file_exists($rootDir . '/code')) {
          $question = new Question('Please enter the git repository (Leave empty to avoid cloning) [NULL]: ', NULL);
          if ($repo = $this->getHelper('question')->ask($input, $output, $question)) {
            $this->runProcess(['git', 'clone', $repo, $rootDir . '/code']);
            $output->writeln(sprintf('Cloned repo to %s', $rootDir . '/code'));
          }
        }
        else {
          $output->writeln(sprintf('Repo already exists at %s', $rootDir . '/code'));
        }

        if (!file_exists($webrootLinkPath) && !is_link($webrootLinkPath)) {
          $question = new Question('Please enter the relative path to the webroot [code]: ', 'code');
          $webrootPath = $this->getHelper('question')->ask($input, $output, $question);
          $webrootFullPath = $rootDir . '/' . $webrootPath;
          $this->runProcess(['ln', '-s', $webrootFullPath, $webrootLinkPath]);
          $output->writeln(sprintf('Created symlink %s', $webrootLinkPath));
        }
        else {
          $output->writeln(sprintf('Symlink %s already exists.', $webrootLinkPath));
        }
    }

    protected function setupDb(InputInterface $input, OutputInterface $output)
    {
        $name = $this->getDbName();
        $process = $this->runProcess(['mysql', '-e', "use $name"], ['exception' => FALSE, 'output' => NULL]);
        if (!$process->isSuccessful()) {
          $this->runProcess(['mysql', '-e', "create database $name"]);
          $output->writeln(sprintf('Database %s created.', $name));
        }
        else {
          $output->writeln(sprintf('Database %s already exists.', $name));
        }
    }

    protected function setupServer(InputInterface $input, OutputInterface $output)
    {
        $apacheConfigFile = $this->getApacheConfigPath();
        $webrootLinkPath = $this->getWebroot();
        $name = $this->getSiteName();

        if (!file_exists($apacheConfigFile)) {
          $domainSuffix = $this->params->get('app.domain_suffix');
          $question = new Question('Please enter the php-fpm port on which to run the site [9001]: ', 9001);
          $port = $this->getHelper('question')->ask($input, $output, $question);
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

    protected function setupDrupal(InputInterface $input, OutputInterface $output)
    {
        $webrootLinkPath = $this->getWebroot();
        $name = $this->getSiteName();
        $settingsPath = $webrootLinkPath . '/sites/default';
        if (file_exists($settingsPath . '/default.settings.php')) {
          $siteSettings = file_exists($settingsPath . '/site-settings.php');
          if (!file_exists($settingsPath . '/settings.php')) {

          }
          else if (!file_exists($settingsPath . '/settings.local.php')) {

          }
        }
    }

}
