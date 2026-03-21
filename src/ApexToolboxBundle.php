<?php

namespace ApexToolbox\SymfonyLogger;

use ApexToolbox\SymfonyLogger\DependencyInjection\ApexToolboxExtension;
use ApexToolbox\SymfonyLogger\DependencyInjection\Compiler\MonologHandlerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApexToolboxBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MonologHandlerPass());
    }

    public function getContainerExtension(): ApexToolboxExtension
    {
        if (null === $this->extension) {
            $this->extension = new ApexToolboxExtension();
        }

        return $this->extension;
    }
}