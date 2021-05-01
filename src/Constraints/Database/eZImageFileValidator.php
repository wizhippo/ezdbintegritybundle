<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\Constraints\DatabaseValidator;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;

class eZImageFileValidator extends DatabaseValidator
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
     */
    public function validate($value, Constraint $constraint)
    {
        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        // Lame, but so far we found no better way...
        $rootDir = preg_replace(':' . $this->ioConfigProvider->getUrlPrefix() . '$:', '', $this->ioConfigProvider->getRootDir());

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $violationCount = 0;
                /// @todo test: can we spot existing but unreadbale files ?
                /// @todo should we test for is_writeable instead of is_writeable ?
                /// @todo separate violations by type: missing/unreadable/unwriteable/0-size
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    $filePath = $rootDir . $data['filepath'];
                    if (!is_readable($filePath) || !is_file($filePath) || !filesize($filePath)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation('Missing or unreadable or empty image files', $violationCount, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_FETCH:
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    /// @todo separate violations by type: missing/unreadable/unwriteable/0-size
                    $filePath = $rootDir . $data['filepath'];
                    if (!is_readable($filePath) || !is_file($filePath) || !filesize($filePath)) {
                        $this->context->addViolation(new ConstraintViolation($rootDir . $data['filepath'], null, $constraint));
                    }
                }
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                /// @todo simplify visualization and move this to the constraint itself
                $this->context->addViolation(new ConstraintViolation('Checks missing or unreadable image files', null, $constraint));
                break;
        }
    }

    protected function getQuery()
    {
        return 'SELECT DISTINCT filepath FROM ezimagefile';
    }
}
