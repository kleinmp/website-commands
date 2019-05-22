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
        $this->setupSolr($input, $output);
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
            $this->runProcess(['git', 'clone', $repo, $rootDir . '/code'], ['timeout' => NULL]);
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
        $domainSuffix = $this->params->get('app.domain_suffix');

        if (!file_exists($apacheConfigFile)) {
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
      $this->setupDrupalSettings($input, $output);
      $this->setupDrupalDirs($input, $output);
    }

    protected function setupDrupalDirs(InputInterface $input, OutputInterface $output)
    {
      $rootDir = $this->getRootDir();
      $webrootLinkPath = $this->getWebroot();
      $settingsPath = $webrootLinkPath . '/sites/default';
      $apacheGroup = $this->params->get('app.apache_group');

      if (!file_exists($settingsPath . '/files')) {
        if (!file_exists($rootDir . '/public_files')) {
          $this->runProcess(['mkdir', $rootDir . '/public_files']);
        }
        $this->runProcess(['sudo', 'chown', ':' . $apacheGroup, $rootDir . '/public_files']);
        $this->runProcess(['ln', '-s', $rootDir . '/public_files', $settingsPath . '/files']);
        $output->writeln(sprintf('Created public files directory at %s.', $rootDir . '/public_files'));
      }
      else {
        $output->writeln('Public files directory already exists.');
      }

      if (!file_exists($rootDir . '/private_files')) {
        $this->runProcess(['mkdir', $rootDir . '/private_files']);
        $this->runProcess(['sudo', 'chown', ':' . $apacheGroup, $rootDir . '/private_files']);
        $output->writeln(sprintf('Created private files directory at %s.', $rootDir . '/private_files'));
      }
      else {
        $output->writeln(sprintf('Private files directory already exists at %s.', $settingsPath . '/private_files'));
      }
    }

    protected function setupDrupalSettings(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $this->getRootDir();
        $webrootLinkPath = $this->getWebroot();
        $name = $this->getSiteName();
        $domainSuffix = $this->params->get('app.domain_suffix');
        $settingsPath = $webrootLinkPath . '/sites/default';
        $settingsFilePath = NULL;

        if (file_exists($settingsPath . '/default.settings.php')) {
          $siteSettings = file_exists($settingsPath . '/site-settings.php') ? "require_once 'site-settings.php';" : NULL;
          $version = $this->getDrupalVersion();
          if ($solrSchemaPath = $this->drupalModulePath('search_api_solr', $version)) {
            $this->setSolrSchemaPath($solrSchemaPath);
          }

          if (!file_exists($settingsPath . '/settings.php')) {
            $settingsFilePath = $settingsPath . '/settings.php';
          }
          else if (!file_exists($settingsPath . '/settings.local.php')) {
            $process = $this->runProcess([
              'grep',
              'settings.local.php',
              $settingsPath . '/settings.php'
            ], ['output' => NULL, 'exception' => FALSE]);
            if ($process->getOutput()) {
              $settingsFilePath = $settingsPath . '/settings.local.php';
            }
          }
          if ($settingsFilePath && $version) {
            $args = [
              'dbname' => $this->getDbName(),
              'username' => $this->params->get('app.db_user'),
              'password' => $this->params->get('app.db_password'),
              'file_private_path' => "'" . $rootDir . "/private_files'",
              'site_settings' => $siteSettings,
              'search_api_solr' => $solrSchemaPath,
              'solr_port' => $this->params->get('app.solr_port'),
              'redis' => $this->drupalModulePath('redis', $version),
            ];

            switch ($version) {
              case 7:
                $args['base_url'] = $name . '.' . $domainSuffix;
                $args['redis'] = str_replace($this->getWebroot() . '/', '', $args['redis']);
                break;
              case 8:
                $args['trusted_host_pattern'] = "'" . str_replace('.', '\.', '^' . $name . '.' . $domainSuffix . '$') . "'";
                $args['config_directories'] = NULL;
                if (is_dir($rootDir . '/code/config')) {
                  $args['config_directories'] = "CONFIG_SYNC_DIRECTORY => dirname(DRUPAL_ROOT) . '/config'";
                  if (is_dir($rootDir . '/code/config/sync')) {
                    $args['config_directories'] = "CONFIG_SYNC_DIRECTORY => dirname(DRUPAL_ROOT) . '/config/sync'";
                  }
                }
                break;
            }

            $template = $this->twig->load("d${version}.settings.php.twig");
            $settingsFileContents = $template->render($args);
            file_put_contents($settingsFilePath, $settingsFileContents);
            $output->writeln(sprintf('Drupal settings file setup at %s.', $settingsFilePath));
          }
        }
        else {
          $output->writeln(sprintf('Drupal settings file already exists at %s.', $settingsFilePath));
        }
    }

    protected function setupSolr($input, $output)
    {
        if (!$this->solrCoreCreated()) {
          if ($schemaBasePath = $this->getSolrSchemaPath()) {
            $solrVersion = $this->params->get('app.solr_version');
            $solrPath = $this->params->get('app.solr_path');
            $schemaPath = $schemaBasePath . '/solr-conf/' . $solrVersion . '.x';
            if (is_dir($schemaPath)) {
              $this->runProcess(['sudo', '-u', 'solr', '--', $solrPath, 'create', '-c', $this->getDbName(), '-d', $schemaPath]);
            }
            $output->writeln(sprintf('Solr core %s was created.', $this->getDbName()));
          }
        }
        else {
          $output->writeln(sprintf('Solr core %s already exists.', $this->getDbName()));
        }
    }

    protected function getDrupalVersion() {
      $webrootLinkPath = $this->getWebroot();
      if (file_exists($webrootLinkPath . '/includes/bootstrap.inc')) {
        $process = $this->runProcess([
          'grep',
          "define('VERSION', '7.",
          $webrootLinkPath . '/includes/bootstrap.inc'
        ], ['output' => NULL, 'exception' => FALSE]);
        if ($process->getOutput()) {
          return 7;
        }
      }
      else if (file_exists($webrootLinkPath . '/core/lib/Drupal.php')) {
        $process = $this->runProcess([
          'grep',
          "const VERSION = '8.",
          $webrootLinkPath . '/core/lib/Drupal.php'
        ], ['output' => NULL, 'exception' => FALSE]);
        if ($process->getOutput()) {
          return 8;
        }
      }
    }

    protected function drupalModulePath($module, $version = NULL)
    {
      if (empty($version)) {
        $version = $this->getDrupalVersion();
      }
      $modulePaths = ['modules/contrib/'];
      if ($version < 8) {
        $modulePaths = ['sites/default/modules/', 'sites/all/modules/', 'sites/default/modules/contrib/', 'sites/all/modules/contrib/'];
      }
      foreach ($modulePaths as $path) {
        if (is_dir($this->getWebroot() . '/' . $path . $module)) {
          return $this->getWebroot() . '/' . $path . $module;
        }
      }
      return NULL;
    }
}
