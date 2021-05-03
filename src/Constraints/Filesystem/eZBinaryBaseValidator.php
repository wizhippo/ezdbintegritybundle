<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use TanoConsulting\DataValidatorBundle\Constraints\Filesystem\FileValidator;

abstract class eZBinaryBaseValidator extends FileValidator
{
    /**
     * @param string|Connection $dsnOrConnection string format: 'mysql://user:secret@localhost/mydb'
     * @return Connection
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getConnection($dsnOrConnection)
    {
        if (is_string($dsnOrConnection)) {
            // lazy connect to the db
            $dsnOrConnection = DriverManager::getConnection(['url' => $dsnOrConnection]);
        }

        return $dsnOrConnection;
    }

    protected function getDescriptionMessage(eZBinaryBase $constraint)
    {
        return $constraint::$descriptionMessage;
    }

    protected function getErrorMessage(eZBinaryBase $constraint)
    {
        return $constraint::$errorMessage;
    }
}
