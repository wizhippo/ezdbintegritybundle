<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use Psr\Log\LoggerInterface;
use TanoConsulting\DataValidatorBundle\Constraints\DatabaseValidator;

abstract class eZBinaryBaseValidator extends DatabaseValidator
{
    /** @var LoggerInterface $output */
    protected $logger;

    /**
     * Checks validity of (content-attached) files
     * @param string $filePath
     * @return false|string false if all is ok, or an error code
     * @todo should we test for is_writeable instead of is_readable ?
     */
    protected function checkFile($filePath)
    {
        if (!is_file($filePath)) {
            return eZBinaryBase::NOT_FOUND_ERROR;
        }
        if (!is_readable($filePath)) {
            return eZBinaryBase::NOT_READABLE_ERROR;
        }
        if (!filesize($filePath)) {
            return eZBinaryBase::EMPTY_ERROR;
        }

        return false;
    }

    protected function getMessage(eZBinaryBase $constraint)
    {
        return $constraint::$descriptionMessage;
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
}
