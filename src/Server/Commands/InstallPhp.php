<?php

namespace App\Server\Commands;

use App\Base\Commands\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig_Environment;

class InstallPhp extends Command
{
    private $twig;

    public function __construct(ParameterBag $params, Twig_Environment $twig)
    {
      $this->twig = $twig;
      return parent::__construct($params);
    }

    protected function configure()
    {
        $this->setName('server:install-php')
            ->setDescription('Install new version of php.')
            ->setHelp('Install new version of php.')
            ->addArgument('version', InputArgument::REQUIRED, 'The version of php to install.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version');

        $this->installPhpVersionScript($input, $output);
        $this->installPhpPackages($version, $input, $output);
        $this->setupPhpFpm($version, $input, $output);
        $output->writeln(sprintf('Installed php version %s', $version));
    }


    protected function installPhpVersionScript(InputInterface $input, OutputInterface $output)
    {
        $phpVersionScriptPath = $this->params->get('server.php_version_script_path');
        if (!file_exists($phpVersionScriptPath)) {
          $output->writeln('Installed php-version script.');
          $this->runProcess(['ls', __DIR__ . '/../../../templates/php-version']);
          $this->runProcess(['sudo', 'cp', __DIR__ . '/../../../templates/php-version', $phpVersionScriptPath]);
          $this->runProcess(['sudo', 'chmod', '+x', $phpVersionScriptPath]);
        }
    }

    protected function installPhpPackages($version, InputInterface $input, OutputInterface $output)
    {
        $versions = $this->params->get('server.php_allowed_versions');
        if (!in_array($version, $versions)) {
          throw new InvalidArgumentException(sprintf('%s is not a valid version of php.  Must be one of ' . implode(', ', $versions) . '.', $version));
        }

        if (file_exists('/usr/bin/php' . $version)) {
          //throw new InvalidArgumentException(sprintf('Php version %s is already installed.', $version));
        }

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
        $packages += [
          'php-igbinary',
          'php-redis',
        ];
        $command = ['sudo', 'apt-get', 'install', '-y', 'php' . $version] + $packages;
        $this->runProcess($command);
        $output->writeln(sprintf('Installed php version %s packages: %s', $version, implode(', ', $packages)));
    }


    protected function setupPhpFpm($version, InputInterface $input, OutputInterface $output)
    {
        $this->runProcess(['sudo', 'a2dismod', 'mpm_prefork'], ['exception' => FALSE]);
        $this->runProcess(['sudo', 'a2dismod', 'php' . $version], ['exception' => FALSE]);

        $question = new Question('Please enter the port on which to run php-fpm [9001]: ', 9001);
        $port = $this->getHelper('question')->ask($input, $output, $question);
        if (!ctype_digit($port)) {
          throw new InvalidArgumentException(sprintf('%s is not a valid port number.', $port));
        }

        $template = $this->twig->load('php_fpm_pool.conf.twig');
        $fpmPoolContents = $template->render(['port' => $port]);
        file_put_contents('/tmp/www.conf', $fpmPoolContents);
        $this->runProcess(['sudo', 'cp', '/tmp/www.conf', '/etc/php/' . $version . '/fpm/pool.d/www.conf']);
        $this->runProcess(['rm', '/tmp/www.conf']);
        $this->runProcess(['sudo', 'systemctl', 'restart', 'php' . $version . '-fpm.service']);
        $output->writeln(sprintf('Setup php%s-fpm service to run on port %d', $version, $port));
    }
}
