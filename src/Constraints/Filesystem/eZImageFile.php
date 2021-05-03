<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

class eZImageFile extends eZBinaryBase
{
    public static $descriptionMessage = 'Checks image files missing from the db';
    public static $errorMessage = 'Image files missing from the db';
}
