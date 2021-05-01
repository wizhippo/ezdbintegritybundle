<?php

namespace TanoConsulting\eZDBIntegrityBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TanoConsultingeZDBIntegrityBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
