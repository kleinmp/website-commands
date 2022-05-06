# Commands for managing a local Drupal website on Ubuntu.

Setup website (Create directory, checkout git repository if provided, create database, setup solr if in use, setup apache vhost)
./bin/console site:setup [name]

Delete website
./bin/console site:delete [name]

Import new mysql database
./bin/console site:dbimport [db-name] [path-to-file]

Install new version of php
./bin/console server:install-php [version]
