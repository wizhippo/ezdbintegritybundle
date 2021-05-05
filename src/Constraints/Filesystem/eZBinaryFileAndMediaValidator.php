<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZBinaryFileAndMediaValidator extends eZBinaryBaseValidator
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
     * @param eZBinaryFileAndMedia $constraint
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof eZBinaryFileAndMedia) {
            throw new UnexpectedTypeException($constraint, eZBinaryFileAndMedia::class);
        }

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        /// @todo should we use the complex config processor for this? $this->configProcessor->processComplexSetting('io.root_dir')
        $rootDir = $this->ioConfigProvider->getRootDir() . '/' . $this->configResolver->getParameter('binary_dir');

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $i = 0;
                $violationCount = 0;
                $finder = $this->getFinder($constraint);
                foreach($finder->in($rootDir) as $file) {
                    ++$i;
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    if (!$this->checkFile($filePath, $connection)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation($this->getErrorMessage($constraint), $violationCount, $constraint));
                }
                $this->log(LogLevel::NOTICE, "Found $i binary and media files files on disk. In the database, found " . $violationCount . " of them missing, " . ($i - $violationCount) . " valid");
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $verbose = false;
                if ($this->logger && $this->logger->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $verbose = true;
                }
                $i = 0;
                $violations = [];
                $finder = $this->getFinder($constraint);
                foreach($finder->in($rootDir) as $file) {
                    ++$i;
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    if (!$this->checkFile($filePath, $connection)) {
                        if ($verbose) {
                            $vdata = array_merge(['file' => $filePath], $this->getFileInfo($filePath));
                        } else {
                            $vdata = $filePath;
                        }
                        $violations[] = $vdata;
                    }
                }
                if ($violations) {
                    $this->context->addViolation(new ConstraintViolation($this->getErrorMessage($constraint), $violations, $constraint));
                }
                $this->log(LogLevel::NOTICE, "Found $i binary and media files on disk. In the database, found " . count($violations) . " of them missing, " . ($i - count($violations)) . " valid");
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                $this->context->addViolation(new ConstraintViolation($this->getDescriptionMessage($constraint), null, $constraint));
                break;
        }
    }

    /**
     * @param string $filePath
     * @param Connection $connection
     * @return bool true if file is found in the db
     */
    protected function checkFile($filePath, $connection)
    {
        $parameters = [$this->quoteForLike(basename($filePath), ':'), $this->quoteForLike(basename(dirname($filePath)), ':') . '/%'];

        $query = "SELECT COUNT(*) AS found FROM ezbinaryfile WHERE filename = ? AND mime_type LIKE ? ESCAPE ':'";
        $data = $connection->executeQuery($query, $parameters)->fetchAllAssociative();
        if ($data[0]['found'] == 0) {
            $query = "SELECT COUNT(*) AS found FROM ezmedia WHERE filename = ? AND mime_type LIKE ? ESCAPE ':'";
            $data = $connection->executeQuery($query, $parameters)->fetchAllAssociative();
        }

        /// @todo if no data is found, scan the ezcontentobject_attribute table, just in case

        return ($data[0]['found'] > 0);
    }
}
