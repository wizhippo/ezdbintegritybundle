<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZMediaValidator extends eZBinaryFileValidator
{
    /**
     * @param Constraint $constraint
     * @throws UnexpectedTypeException
     */
    protected function checkConstraint(Constraint $constraint)
    {
        if (!$constraint instanceof eZMedia) {
            throw new UnexpectedTypeException($constraint, eZMedia::class);
        }
    }

    /**
     * @return string
     */
    protected function getQuery()
    {
        return 'SELECT DISTINCT filename, mime_type FROM ezmedia';
    }
}
