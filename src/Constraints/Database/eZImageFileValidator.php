<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

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

        // Lame, but so far we found no better way...
        $rootDir = preg_replace(':' . $this->ioConfigProvider->getUrlPrefix() . '$:', '', $this->ioConfigProvider->getRootDir());

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $i = 0;
                $e = 0;
                $violationCounts = [];
                $stmt = $connection->executeQuery($this->getQuery());
                while (($data = $stmt->fetchAssociative()) !== false) {
                    ++$i;
                    $filePath = $rootDir . $data['filepath'];
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
                $this->log(LogLevel::NOTICE, "Found $i image files in the database. On disk, found $e of them invalid, " . ($i - $e) . " valid");
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
                    $filePath = $rootDir . $data['filepath'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        ++$e;
                        /// @todo we could save as violation value the data from the db column instead of the full path
                        if ($verbose) {
                            $vdata = ['file' => $filePath, 'objects' => $this->getObjectInfo($connection, $data['filepath'])];
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
                $this->log(LogLevel::NOTICE, "Found $i image files in the database. On disk, found $e of them invalid, " . ($i - $e) . " valid");
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
        if (!$constraint instanceof eZImageFile) {
            throw new UnexpectedTypeException($constraint, eZImageFile::class);
        }
    }

    protected function getQuery()
    {
        return 'SELECT DISTINCT filepath FROM ezimagefile';
    }

    /**
     * Retrieve object info for a row in the ezimagefile table.
     * NB: keep in sync with eZBinaryFileValidator.
     * @param $connection
     * @param string $fileName
     * @param string $mimeType
     * @return string[][]|null[][]
     */
    protected function getObjectInfo($connection, $filePath)
    {
        // NB: we assume that the data in the db is not _too_ corrupted, and that we have at least attribute and object
        /// @todo make queries postgresql compatible
        $query = 'SELECT o.id AS id, FROM_UNIXTIME(GREATEST(o.modified, o.published)) AS modified, o.status
FROM ezimagefile b
INNER JOIN ezcontentobject_attribute a ON b.contentobject_attribute_id = a.id
INNER JOIN ezcontentobject o ON o.id = a.contentobject_id
WHERE b.filepath = ?
GROUP BY o.id
ORDER BY o.id';
        $stmt = $connection->executeQuery($query, [$filePath]);
        $data = $stmt->fetchAllAssociative();

        if (!$data) {
            return ['id' => null, 'modified' => null, 'status' => null, 'version' => null, 'v_modified' => null, 'v_status' => null, 'alias' => null];
        }

        $data3 = [];
        foreach ($data as $row) {
            // find out which versions have/had this file by inspecting the attributes
            // NB matching filename is not enough, we have to match either ' filename="..." suffix="..." basename="..."' or ' filename="..." suffix="..." dirpath="..."'
            /// @todo decode version status
            /// @todo this parsing of xml via LIKE is way too brittle. Use proper REGEXP matching if on mysql 8.0.4 and later
            $query = "SELECT v.version, FROM_UNIXTIME(GREATEST(v.modified, v.created)) AS v_modified, v.status AS v_status
FROM ezcontentobject_attribute a
INNER JOIN ezcontentobject_version v ON v.contentobject_id = a.contentobject_id AND v.version = a.version
WHERE a.contentobject_id = ? AND a.data_type_string = 'ezimage' AND REPLACE(a.data_text, '\n    ', ' ') LIKE ? ESCAPE ':'
GROUP BY v.id
ORDER BY v.id
";
            $fileName = basename($filePath);
            $suffix = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $dirPath = dirname($filePath);
            $stmt = $connection->executeQuery($query, [
                $row['id'],
                '% suffix="' . $this->quoteForLike($suffix, ':') . '" basename="' . $this->quoteForLike($baseName, ':') . '" dirpath="' . $this->quoteForLike($dirPath, ':'). '"%',
            ]);
            $data2 = $stmt->fetchAllAssociative();

            if (count($data2)) {
                $isAlias = false;
            } else {
                $stmt = $connection->executeQuery($query, [
                    $row['id'],
                    '% filename="' . $this->quoteForLike($fileName, ':') . '" suffix="' . $this->quoteForLike($suffix, ':') . '" dirpath="' . $this->quoteForLike($dirPath, ':') . '"%',
                ]);
                $data2 = $stmt->fetchAllAssociative();
                $isAlias = true;
            }

            if (count($data2)) {
                foreach($data2 as $row2) {
                    $data3[] = array_merge($row, $row2, ['alias' => $isAlias]);
                }
            } else {
                $data3[] = array_merge($row, ['version' => null, 'v_modified' => null, 'v_status' => null, 'alias' => null]);
            }
        }

        return $data3;
    }
}
