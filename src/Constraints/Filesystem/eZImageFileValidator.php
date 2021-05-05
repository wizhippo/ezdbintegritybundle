<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;
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
     * Unlike other FileValidators, we get a DNS as value instead of a path
     * @param string|Connection $value string format: 'mysql://user:secret@localhost/mydb'
     * @param eZImageFile $constraint
     * @throws \Doctrine\DBAL\Driver\Exception
     *
     * @todo add check: all image alias files without original file (maybe use a separate validator) ?
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof eZImageFile) {
            throw new UnexpectedTypeException($constraint, eZImageFile::class);
        }

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        // @todo check if the 'images' part could actually be overridden
        $rootDir = $this->ioConfigProvider->getRootDir() . '/images';
        $prefix = ':^' . preg_replace(':' . $this->ioConfigProvider->getUrlPrefix() . '$:', '', $this->ioConfigProvider->getRootDir()) . ':';
        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $i = 0;
                $violationCount = 0;
                $finder = $this->getFinder($constraint);
                foreach($finder->in($rootDir)->notPath('/_aliases/') as $file) {
                    ++$i;
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    $fileSubPath = preg_replace($prefix, '', $filePath);
                    if (!$this->checkFile($fileSubPath, $connection)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation($this->getErrorMessage($constraint), $violationCount, $constraint));
                }
                $this->log(LogLevel::NOTICE, "Found $i image files on disk. In the database, found " . $violationCount . " of them missing, " . ($i - $violationCount) . " valid");
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $verbose = false;
                if ($this->logger && $this->logger->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $verbose = true;
                }
                $i = 0;
                $violations = [];
                $finder = $this->getFinder($constraint);
                foreach($finder->in($rootDir)->notPath('/_aliases/') as $file) {
                    ++ $i;
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    $fileSubPath = preg_replace($prefix, '', $filePath);
                    if (!$this->checkFile($fileSubPath, $connection)) {
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
                $this->log(LogLevel::NOTICE, "Found $i image files on disk. In the database, found " . count($violations). " of them missing, " . ($i - count($violations)) . " valid");
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                $this->context->addViolation(new ConstraintViolation($this->getDescriptionMessage($constraint), null, $constraint));
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
        $query = "SELECT COUNT(*) AS found FROM ezimagefile WHERE filepath = ?";
        $parameters = [$filePath];
        $data = $connection->executeQuery($query, $parameters)->fetchAllAssociative();
        /// @todo if no data is found, scan the ezcontentobject_attribute table, just in case
        return ($data[0]['found'] > 0);
    }
}
