<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

class eZImageFile extends eZBinaryBase
{
    public static $descriptionMessage = 'Checks missing, unreadable or empty image files';
}
