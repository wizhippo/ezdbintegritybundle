<?php

namespace TanoConsulting\eZDBIntegrityBundle;

class eZDBIntegrityBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new TaggedServicesCompilerPass());
    }
}
