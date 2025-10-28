<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\ApexToolboxBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

abstract class AbstractTestCase extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    protected function createKernel(array $configs = []): TestKernel
    {
        return new TestKernel($configs);
    }

    protected function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}

class TestKernel extends Kernel
{
    private array $configs;

    public function __construct(array $configs = [])
    {
        $this->configs = $configs;
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new ApexToolboxBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('apex_toolbox', $this->configs);

            // Set test configuration
            $container->setParameter('apex_toolbox.token', 'test-token');
            $container->setParameter('apex_toolbox.enabled', true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/apex_toolbox_test';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/apex_toolbox_test';
    }
}