<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZBinaryFileValidator extends eZBinaryBaseValidator
{
    protected $ioConfigProvider;
    protected $configResolver;

    public function __construct(IOConfigProvider $ioConfigProvider, ConfigResolverInterface $configResolver)
    {
        $this->ioConfigProvider = $ioConfigProvider;
        $this->configResolver = $configResolver;
    }

    /**
     * Unlike other FileValidators, we get a DNS as value instead of a path
     * @param string|Connection $value string format: 'mysql://user:secret@localhost/mydb'
     * @param eZBinaryFile $constraint
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof eZBinaryFile) {
            throw new UnexpectedTypeException($constraint, eZBinaryFile::class);
        }

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        /// @todo should we use the complex config processor for this? $this->configProcessor->processComplexSetting('io.root_dir')
        $rootDir = $this->ioConfigProvider->getRootDir() . '/' . $this->configResolver->getParameter('binary_dir');

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $finder = $this->getFinder($constraint);
                $violationCount = 0;
                foreach($finder->in($rootDir) as $file) {
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    if (!$this->checkFile($filePath, $connection)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation('Binary files missing from the db', $violationCount, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $finder = $this->getFinder($constraint);
                $violations = [];
                foreach($finder->in($rootDir) as $file) {
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    if (!$this->checkFile($filePath, $connection)) {
                        $violations[] = $filePath;
                    }
                }
                if ($violations) {
                    $this->context->addViolation(new ConstraintViolation('Binary files missing from the db', $violations, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                /// @todo simplify visualization and move this to the constraint itself
                $this->context->addViolation(new ConstraintViolation('Checks binary files missing from the db', null, $constraint));
                break;
        }
    }

    /**
     * @param string $filePath
     * @param Connection $connection
     * @return bool
     */
    protected function checkFile($filePath, $connection)
    {
        $query = "SELECT COUNT(*) AS found FROM ezbinaryfile WHERE filename = ? AND mime_type LIKE ?";
        $parameters = [basename($filePath), basename(dirname($filePath)) . '/%'];
        $data = $connection->executeQuery($query, $parameters)->fetchAllAssociative();
        return ($data[0]['found'] > 0);
    }
}
