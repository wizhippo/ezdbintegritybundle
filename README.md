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

Installation
------------

Install via Composer

Getting started
---------------

All this bundle does is to add some cli commands. To get you started, try running:

    php bin/console ezdbintegrity:check:schema --dry-run

    php bin/console ezdbintegrity:check:schema

    php bin/console ezdbintegrity:check:schema --display-data

    php bin/console ezdbintegrity:check:storage

    php bin/console ezdbintegrity:check:storage --check-db-orphans

Still to be done
----------------

- validation of image variation files (ie. check aliases without an original file)
- validation of content fields
- allow users to add constraint definitions for custom tables
- test if checks work with multi-repository setups
- test if checks work with ezdfs setups
- test if this bundle could work with ezplatform 2

DISCLAIMER
----------

!!! DO NOT BLINDLY DELETE ANY DATA IN THE DB WHICH IS REPORTED AS FOREIGN KEY VIOLATION !!!

!!! DO NOT BLINDLY DELETE ANY STORAGE FILE WHICH IS REPORTED AS ORPHAN !!!

We take no responsibility for consequences if you do. You should carefully investigate the reason for such violations.
There is a good chance that the problem lies within this extension and not your data - the FK definitions provided have
been reverse-engineered from existing codebase and databases, and are not cast in stone.
