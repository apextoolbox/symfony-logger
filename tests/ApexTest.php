<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\Apex;
use Exception;
use Mockery;
use Psr\Log\LoggerInterface;

class ApexTest extends AbstractTestCase
{
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        Apex::setLogger($this->logger);
    }

    public function testLogException(): void
    {
        $exception = new Exception('Test exception');

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Test exception', Mockery::on(function ($context) use ($exception) {
                return isset($context['exception']) && $context['exception'] === $exception;
            }));

        Apex::logException($exception);

        // Mockery verification counts as assertion
        $this->addToAssertionCount(1);
    }

    public function testLogExceptionWithContext(): void
    {
        $exception = new Exception('Test exception');
        $context = ['user_id' => 123];

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Test exception', Mockery::on(function ($ctx) use ($exception, $context) {
                return isset($ctx['exception'])
                    && $ctx['exception'] === $exception
                    && $ctx['user_id'] === 123;
            }));

        Apex::logException($exception, $context);

        // Mockery verification counts as assertion
        $this->addToAssertionCount(1);
    }
}
