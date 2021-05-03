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
        $this->checkConstraint($constraint);

        /** @var Connection $connection */
        $connection = $this->getConnection($value);

        // Lame, but so far we found no better way...
        $rootDir = preg_replace(':' . $this->ioConfigProvider->getUrlPrefix() . '$:', '', $this->ioConfigProvider->getRootDir());

        switch($this->context->getOperatingMode()) {
            case ExecutionContextInterface::MODE_COUNT:
                $violationCounts = [];
                $stmt = $connection->executeQuery($this->getQuery());
//$i = 0;
                while (($data = $stmt->fetchAssociative()) !== false) {
//$i++;
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
//echo "Checked $i image files...\n";
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $violations = [];
                $stmt = $connection->executeQuery($this->getQuery());
//$i = 0;
                while (($data = $stmt->fetchAssociative()) !== false) {
//$i++;
                    $filePath = $rootDir . $data['filepath'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        /// @todo we should probably save as violation value the data from the db column
                        $vdata = $filePath;
                        $extradata = $this->getObjectInfo($connection, $data['filepath']);
                        if ($extradata) {
                            $vdata .= ' ' . json_encode($extradata);
                        } else {
                            /// @todo create a violation of a separate type ?
                            $vdata .= ' ["No corresponding Content Version found"]';
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
//echo "Checked $i image files...\n";
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
     * Retrieve object info for a row in the ezimagefile table: info, version(s), version status(es)
     * @param $connection
     * @param string $fileName
     * @param string $mimeType
     * @return string[][]
     */
    protected function getObjectInfo($connection, $filePath)
    {
        /// @todo make queries postgresql compatible
        $query = 'SELECT o.id AS object_id, o.name, FROM_UNIXTIME(GREATEST(o.modified, o.published)) AS modified, o.status
FROM ezimagefile b
LEFT JOIN ezcontentobject_attribute a ON b.contentobject_attribute_id = a.id
LEFT JOIN ezcontentobject o ON o.id = a.contentobject_id
WHERE b.filepath = ?
GROUP BY o.id';
        $stmt = $connection->executeQuery($query, [$filePath]);
        $data = $stmt->fetchAllAssociative();
        foreach ($data as $id => $row) {
            // find out which versions have/had this file by inspecting the attributes
            // NB matching filename is not enough, we have to match either ' filename="..." suffix="..." basename="..."' or ' filename="..." suffix="..." dirpath="..."'
            /// @todo decode version status
            /// @todo this parsing of xml via LIKE is way too brittle. Use proper REGEXP matching if on mysql 8.0.4 and later
            $query = "SELECT v.version, FROM_UNIXTIME(GREATEST(v.modified, v.created)) AS v_modified, v.status AS v_status
FROM ezcontentobject_attribute a
LEFT JOIN ezcontentobject_version v ON v.contentobject_id = a.contentobject_id AND v.version = a.version
WHERE a.contentobject_id = ? AND a.data_type_string = 'ezimage' AND REPLACE(a.data_text, '\n    ', ' ') LIKE ? ESCAPE ':'
GROUP BY v.id
ORDER BY v.id DESC
";
            $fileName = basename($filePath);
            $suffix = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $dirPath = dirname($filePath);
            $stmt = $connection->executeQuery($query, [
                $row['object_id'],
                '% suffix="' . $this->quoteForLike($suffix, ':') . '" basename="' . $this->quoteForLike($baseName, ':') . '" dirpath="' . $this->quoteForLike($dirPath, ':'). '"%',
            ]);
            $data2 = $stmt->fetchAllAssociative();
            if (count($data2)) {
                $isAlias = false;
            } else {
                $stmt = $connection->executeQuery($query, [
                    $row['object_id'],
                    '% filename="' . $this->quoteForLike($fileName, ':') . '" suffix="' . $this->quoteForLike($suffix, ':') . '" dirpath="' . $this->quoteForLike($dirPath, ':') . '"%',
                ]);
                $data2 = $stmt->fetchAllAssociative();
                $isAlias = true;
            }
            if (count($data2)) {
                /// @todo why always pick the latest version? we should add all of them...
                $data[$id] = array_merge($row, $data2[0]);
                if ($isAlias) {
                    $data[$id]['alias'] = true;
                }
            } else {
                /// @todo make it more evident that no known versions references this file
            }
        }
        return $data;
    }
}
