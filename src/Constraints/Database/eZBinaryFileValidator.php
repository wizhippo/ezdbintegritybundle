<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use Doctrine\DBAL\Connection;
use eZ\Publish\Core\IO\IOConfigProvider;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;
use TanoConsulting\DataValidatorBundle\Constraint;
use TanoConsulting\DataValidatorBundle\ConstraintViolation;
use TanoConsulting\DataValidatorBundle\Context\ExecutionContextInterface;
use TanoConsulting\DataValidatorBundle\Exception\UnexpectedTypeException;

class eZBinaryFileValidator extends eZBinaryBaseValidator
{
    protected $ioConfigProvider;
    protected $configResolver;
    protected static $tableName = 'ezbinaryfile';
    protected static $fileType = 'binary';

    public function __construct(IOConfigProvider $ioConfigProvider, ConfigResolverInterface $configResolver)
    {
        $this->ioConfigProvider = $ioConfigProvider;
        $this->configResolver = $configResolver;
    }

    /**
     * @param string|Connection $value string format: 'mysql://user:secret@localhost/mydb'
     * @param Constraint $constraint
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws UnexpectedTypeException
     */
    public function validate($value, Constraint $constraint)
    {
        $this->checkConstraint($constraint);

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        /// @todo should we use the complex config processor for this? $this->configProcessor->processComplexSetting('io.root_dir')
        $rootDir = $this->ioConfigProvider->getRootDir() . '/' . $this->configResolver->getParameter('binary_dir');

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $i = 0;
                $e = 0;
                $violationCounts = [];
                $stmt = $connection->executeQuery($this->getQuery());
                while (($data = $stmt->fetchAssociative()) !== false) {
                    ++$i;
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        ++$e;
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
                $this->log(LogLevel::NOTICE, "Found $i " . static::$fileType . " files in the database. On disk, found $e of them invalid, " . ($i - $e) . " valid");
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $verbose = false;
                if ($this->logger && $this->logger->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $verbose = true;
                }
                $i = 0;
                $e = 0;
                $violations = [];
                $stmt = $connection->executeQuery($this->getQuery());
                while (($data = $stmt->fetchAssociative()) !== false) {
                    ++$i;
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        ++$e;
                        /// @todo we could save as violation value the data from the db column instead of the full path
                        if ($verbose) {
                            $vdata = ['file' => $filePath, 'objects' => $this->getObjectInfo($connection, $data['filename'], $data['mime_type'])];
                        } else {
                            $vdata = $filePath;
                        }

                        if (array_key_exists($error, $violations)) {
                            $violations[$error][] = $vdata;
                        } else {
                            $violations[$error] = [$vdata];
                        }
                    }
                }
                foreach ($violations as $type => $paths) {
                    $this->context->addViolation(new ConstraintViolation(eZBinaryBase::$errorMessages[$type], $paths, $constraint));
                }
                $this->log(LogLevel::NOTICE, "Found $i " . static::$fileType . " files in the database. On disk, found $e of them invalid, " . ($i - $e) . " valid");
                break;

            case ExecutionContextInterface::MODE_DRY_RUN:
                $this->context->addViolation(new ConstraintViolation($this->getMessage($constraint), null, $constraint));
                break;
        }
    }

    /**
     * @param Constraint $constraint
     * @throws UnexpectedTypeException
     */
    protected function checkConstraint(Constraint $constraint)
    {
        if (!$constraint instanceof eZBinaryFile) {
            throw new UnexpectedTypeException($constraint, eZBinaryFile::class);
        }
    }

    /**
     * @return string
     */
    protected function getQuery()
    {
        return 'SELECT DISTINCT filename, mime_type FROM ' . static::$tableName;
    }

    /**
     * Retrieve object info for a row in the ezbinaryfile table.
     * NB: keep in sync with eZImageFileValidator.
     * @param $connection
     * @param string $fileName
     * @param string $mimeType
     * @return string[][]|null[][]
     */
    protected function getObjectInfo($connection, $fileName, $mimeType)
    {
        // NB: we assume that the data in the db is not _too_ corrupted, and that we have at least attribute and object
        /// @todo decode version status
        /// @todo make query postgresql compatible
        $query = 'SELECT o.id AS id, FROM_UNIXTIME(GREATEST(o.modified, o.published)) AS modified, o.status, v.version, FROM_UNIXTIME(GREATEST(v.modified, v.created)) AS v_modified, group_concat(v.status) AS v_status
FROM ' . static::$tableName . ' b
INNER JOIN ezcontentobject_attribute a ON b.contentobject_attribute_id = a.id AND b.version = a.version
INNER JOIN ezcontentobject o ON o.id = a.contentobject_id
LEFT JOIN ezcontentobject_version v ON v.contentobject_id = a.contentobject_id AND v.version = a.version
WHERE b.filename = ? AND b.mime_type = ?
GROUP BY o.id, v.id
ORDER BY o.id, v.id';
        $stmt = $connection->executeQuery($query, [$fileName, $mimeType]);
        $data = $stmt->fetchAllAssociative();

        if (!$data) {
            return ['id' => null, 'modified' => null, 'status' => null, 'version' => null, 'v_modified' => null, 'v_status' => null];
        }

        return $data;
    }

    /**
     * @param string $mimeType
     * @return string
     */
    protected function getFirstPartOfMimeType($mimeType)
    {
        return substr($mimeType, 0, strpos($mimeType, '/'));
    }
}
