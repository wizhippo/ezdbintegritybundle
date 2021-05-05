<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use TanoConsulting\DataValidatorBundle\Constraints\Filesystem\FileValidator;

abstract class eZBinaryBaseValidator extends FileValidator
{
    /** @var LoggerInterface */
    protected $logger;

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

    protected function quoteForLike($string, $escape = '\\')
    {
        if ($escape === '\\') {
            /// @todo this assumes that we are using mysql, and that NO_BACKSLASH_ESCAPES is off...
            return str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $string);
        } else {
            /// @todo should we still double backslashes ?
            return str_replace(['_', '%'], [$escape . '_', $escape . '%'], $string);
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * @param $filePath
     * @return string[]
     */
    protected function getFileInfo($filePath)
    {
        return ['size' => filesize($filePath), 'modified' => date('Y-m-d H:i:s', filemtime($filePath))];
    }
}
