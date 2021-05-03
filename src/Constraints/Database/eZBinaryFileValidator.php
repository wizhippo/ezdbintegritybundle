<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

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
    protected static $tableName = 'ezbinaryfile';

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
                $violationCounts = [];
                $stmt = $connection->executeQuery($this->getQuery());
//$i = 0;
                while (($data = $stmt->fetchAssociative()) !== false) {
//$i++;
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
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
//echo "Checked $i binary files...\n";
                break;

            case ExecutionContextInterface::MODE_FETCH:
                $violations = [];
                $stmt = $connection->executeQuery($this->getQuery());
//$i = 0;
                while (($data = $stmt->fetchAssociative()) !== false) {
//$i++;
                    $filePath = $rootDir . '/' . $this->getFirstPartOfMimeType($data['mime_type']) . '/' . $data['filename'];
                    if (($error = $this->checkFile($filePath)) !== false) {
                        /// @todo we should probably save as violation value the data from the db column
                        $vdata = $filePath;
                        $extradata = $this->getObjectInfo($connection, $data['filename'], $data['mime_type']);
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
//echo "Checked $i binary files...\n";
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
     * Retrieve object info for a row in the ezbinaryfile table: info, version(s), version status(es)
     * @param $connection
     * @param string $fileName
     * @param string $mimeType
     * @return string[][]
     */
    protected function getObjectInfo($connection, $fileName, $mimeType)
    {
        /// @todo decode version status
        /// @todo make query postgresql compatible
        $query = 'SELECT o.id AS object_id, o.name, v.version, FROM_UNIXTIME(GREATEST(v.modified, v.created)) AS v_modified, group_concat(v.status) AS v_status
FROM ' . static::$tableName . ' b
LEFT JOIN ezcontentobject_attribute a ON b.contentobject_attribute_id = a.id AND b.version = a.version
LEFT JOIN ezcontentobject_version v ON v.contentobject_id = a.contentobject_id AND v.version = a.version
LEFT JOIN ezcontentobject o ON o.id = a.contentobject_id
WHERE b.filename = ? AND b.mime_type = ?
GROUP BY o.id, v.id';
        $stmt = $connection->executeQuery($query, [$fileName, $mimeType]);
        $data = $stmt->fetchAllAssociative();
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
