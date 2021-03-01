<?php

namespace TanoConsulting\eZDBIntegrityBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class eZDBIntegrityBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
