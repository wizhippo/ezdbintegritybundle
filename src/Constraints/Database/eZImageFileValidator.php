<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZImageFileValidator extends eZBinaryBaseValidator
{
    protected $ioConfigProvider;

    public function __construct(IOConfigProvider $ioConfigProvider)
    {
        $this->ioConfigProvider = $ioConfigProvider;
    }

    /**
     * @param string|Connection $value string format: 'mysql://user:secret@localhost/mydb'
     * @param Constraint $constraint
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws UnexpectedTypeException
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof eZImageFile) {
            throw new UnexpectedTypeException($constraint, eZImageFile::class);
        }

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        // Lame, but so far we found no better way...
        $rootDir = preg_replace(':' . $this->ioConfigProvider->getUrlPrefix() . '$:', '', $this->ioConfigProvider->getRootDir());

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $violationCounts = [];
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    $filePath = $rootDir . $data['filepath'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        if (array_key_exists($error, $violationCounts)) {
                            $violationCounts[$error]++;
                        } else {
                            $violationCounts[$error] = 1;
                        }
                    }
                }
                foreach ($violationCounts as $type => $count) {
                    $this->context->addViolation(new ConstraintViolation(eZBinaryBase::$errorMessages[$type], $count, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $violations = [];
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    $filePath = $rootDir . $data['filepath'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        if (array_key_exists($error, $violations)) {
                            $violations[$error][] = $filePath;
                        } else {
                            $violations[$error] = [$filePath];
                        }
                    }
                    foreach ($violations as $type => $paths) {
                        $this->context->addViolation(new ConstraintViolation(eZBinaryBase::$errorMessages[$type], $paths, $constraint));
                    }
                }
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                /// @todo simplify visualization and move this to the constraint itself
                $this->context->addViolation(new ConstraintViolation('Checks missing, unreadable or empty image files', null, $constraint));
                break;
        }
    }

    protected function getQuery()
    {
        return 'SELECT DISTINCT filepath FROM ezimagefile';
    }
}
