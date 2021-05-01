<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use Doctrine\DBAL\Connection;
use eZ\Publish\SPI\SiteAccess\ConfigProcessor;
use eZ\Publish\Core\IO\IOConfigProvider;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\Constraints\DatabaseValidator;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;

class eZBinaryFileValidator extends DatabaseValidator
{
    protected $ioConfigProvider;
    protected $configResolver;
    protected $configProcessor;

    public function __construct(IOConfigProvider $ioConfigProvider, ConfigResolverInterface $configResolver)
    {
        $this->ioConfigProvider = $ioConfigProvider;
        $this->configResolver = $configResolver;
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

        /// @todo should we use the complex config processor for this? $this->configProcessor->processComplexSetting('io.root_dir')
        $rootDir = $this->ioConfigProvider->getRootDir() . '/' . $this->configResolver->getParameter('binary_dir');

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $violationCount = 0;
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
                    /// @todo separate violations by type: missing/unreadable/unwriteable/0-size
                    if (!is_readable($filePath) || !is_file($filePath) || !filesize($filePath)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation('Missing or unreadable binary files', $violationCount, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_FETCH:
                foreach($connection->executeQuery($this->getQuery())->fetchAllAssociative() as $data) {
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
                    /// @todo separate violations by type: missing/unreadable/unwriteable/0-size
                    if (!is_readable($filePath) || !is_file($filePath) || !filesize($filePath)) {
                        $this->context->addViolation(new ConstraintViolation($rootDir . $data['filename'], null, $constraint));
                    }
                }
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                /// @todo simplify visualization and move this to the constraint itself
                $this->context->addViolation(new ConstraintViolation('Checks missing or unreadable binary files', null, $constraint));
                break;
        }
    }

    protected function getQuery()
    {
        return 'SELECT DISTINCT filename, mime_type FROM ezbinaryfile';
    }

    protected function getFirstPartOfMimeType($mimeType)
    {
        return substr($mimeType, 0, strpos($mimeType, '/'));
    }
}
