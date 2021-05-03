<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

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
                $finder = $this->getFinder($constraint);
                $violationCount = 0;
                foreach($finder->in($rootDir)->notPath('/_aliases/') as $file) {
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    $fileSubPath = preg_replace($prefix, '', $filePath);
                    if (!$this->checkFile($fileSubPath, $connection)) {
                        $violationCount++;
                    }
                }
                if ($violationCount) {
                    $this->context->addViolation(new ConstraintViolation($this->getErrorMessage($constraint), $violationCount, $constraint));
                }
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $finder = $this->getFinder($constraint);
                $violations = [];
                foreach($finder->in($rootDir)->notPath('/_aliases/') as $file) {
                    $filePath = $file->getPath() . '/' . $file->getFilename();
                    $fileSubPath = preg_replace($prefix, '', $filePath);
                    if (!$this->checkFile($fileSubPath, $connection)) {
                        $violations[] = $filePath;
                    }
                }
                if ($violations) {
                    $this->context->addViolation(new ConstraintViolation($this->getErrorMessage($constraint), $violations, $constraint));
                }
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
        return ($data[0]['found'] > 0);
    }
}
