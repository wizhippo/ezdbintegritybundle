<?php

namespace TanoConsulting\eZDBIntegrityBundle\Constraints\Database;

class eZBinaryFile extends eZBinaryBase
{
    public static $descriptionMessage = 'Checks missing, unreadable or empty binary files';
}
