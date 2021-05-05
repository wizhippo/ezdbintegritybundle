<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

/// @todo add fields that allow to skip reporting of aliases / originals / files without an object or version
class eZImageFile extends eZBinaryBase
{
    public static $descriptionMessage = 'Checks missing, unreadable or empty image files';
}
