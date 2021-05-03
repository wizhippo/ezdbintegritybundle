<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem;

class eZBinaryFileAndMedia extends eZBinaryBase
{
    public static $descriptionMessage = 'Checks binary and media files missing from the db';
    public static $errorMessage = 'Binary or media files missing from the db';
}
