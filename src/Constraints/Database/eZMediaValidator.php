<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZMediaValidator extends eZBinaryFileValidator
{
    protected static $tableName = 'ezmedia';

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
}
