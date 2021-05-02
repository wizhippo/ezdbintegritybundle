<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

use TanoConsulting\DataValidatorBundle\Constraints\DatabaseConstraint;

abstract class eZBinaryBase extends DatabaseConstraint
{
    public const NOT_FOUND_ERROR = '68aed812-ab6f-11eb-bcbc-0242ac130002';
    public const NOT_READABLE_ERROR = '70331030-ab6f-11eb-bcbc-0242ac130002';
    public const NOT_WRITABLE_ERROR = '7855ed5a-ab6f-11eb-bcbc-0242ac130002';
    public const EMPTY_ERROR = '7e0a4e3a-ab6f-11eb-bcbc-0242ac130002';

    public static $errorMessages = [
        self::NOT_FOUND_ERROR => 'The file could not be found.',
        self::NOT_READABLE_ERROR =>  'The file is not readable.',
        self::NOT_WRITABLE_ERROR => 'The file is not writable.',
        self::EMPTY_ERROR => 'An empty file is not allowed.',
    ];
}
