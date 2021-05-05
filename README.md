eZ DB Integrity Bundle for eZPlatform 3 / Ibexa DXP
===================================================

This bundle is the 'eZPlatform 3 port' of the eZDBIntegrity extension for eZPublish/eZPlatform 1 and 2.

Goals
-----

Allow checking integrity of data in the eZPlatform database:
- foreign keys
- generic data integrity rules which can not expressed as foreign key
- content fields values according to their content type field definition (not yet implemented)

Allow checking integrity of the eZPlatform storage files (images, media and binary files from content).

Requirements
------------

eZPlatform 3, running on MySQL/MariaDB

Installation
------------

Install via Composer: `composer require "tanoconsulting/ezdbintegritybundle:1.0.0-beta2" "tanoconsulting/datavalidatorbundle >=1.0.0-BETA1"`

Getting started
---------------

All this bundle does is to add some cli commands. To get you started, try running:

    php bin/console ezdbintegrity:check:schema --dry-run

    php bin/console ezdbintegrity:check:schema

    php bin/console ezdbintegrity:check:schema --display-data

    php bin/console ezdbintegrity:check:storage

    php bin/console ezdbintegrity:check:storage --check-db-orphans

    php bin/console ezdbintegrity:check:storage --check-db-orphans --display-data

All of the commands do print out more information about what is going on, and more details about the violations found,
when they are run with the `-v` option.

Tips
----

- To avoid excessive memory usage from large queries, when running Symfony in "debug mode", such as commonly for "dev" envs,
  add the `--no-debug` option to your commands. If possible, use a non-debug Symfony env.

- If you still get an 'allowed memory size' fatal error, run the commands with `php -d memory_limit=-1`.

- The best way to troubleshoot "missing images" is to identify only those missing image files which correspond to a currently
  published content version, and which are not auto-generated aliases.
  In the same way, for missing binary files, it is useful to identify only those hich correspond to a currently published
  content version.

  This can be achieved by filtering the list of all missing files using the following command:

        php bin/console ezdbintegrity:check:storage --check-db-orphans --display-data -v | grep -v '"v_status":null' | grep -v '"alias":true'

- If you have lots of images and variations, it might be worth pruning all empty directories from the image storage folder
  from time to time, esp. before executing a backup or if your disk is running out of inodes.
  A quick one-liner to find all empty subdirectories of the current directory is:

        find . -type d -empty

  and to remove them:

        find . -type d -empty -delete

Still to be done
----------------

- validation of content fields data
- allow users to add constraint definitions for their own custom tables
- test if checks work with multi-repository setups
- test if checks work with ezdfs setups
- test if this bundle could work with ezplatform 2
- improvements in output formatting and ability to run a subset of checks
- improvements to storage checks:
  - look harder for candidate replacement files available on disk for missing images (eg. look for {$file}_reference.jpg)
  - validation of image variation files (ie. check existing aliases without an original file)

DISCLAIMER
----------

!!! DO NOT BLINDLY DELETE ANY DATA IN THE DB WHICH IS REPORTED AS FOREIGN KEY VIOLATION !!!

!!! DO NOT BLINDLY DELETE ANY STORAGE FILE WHICH IS REPORTED AS ORPHAN !!!

We take no responsibility for consequences if you do. You should carefully investigate the reason for such violations.
There is a good chance that the problem lies within this extension and not your data - the FK definitions provided have
been reverse-engineered from existing codebase and databases, and are not cast in stone.
