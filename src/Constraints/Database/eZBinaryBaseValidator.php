<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use TanoConsulting\DataValidatorBundle\Constraints\DatabaseValidator;

abstract class eZBinaryBaseValidator extends DatabaseValidator
{
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
}
