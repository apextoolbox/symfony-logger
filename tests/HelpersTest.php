<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\Apex;
use Exception;
use Mockery;
use Psr\Log\LoggerInterface;

class HelpersTest extends AbstractTestCase
{
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        Apex::setLogger($this->logger);
    }

    public function testLogExceptionHelper(): void
    {
        $exception = new Exception('Test exception');

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Test exception', Mockery::on(function ($context) use ($exception) {
                return isset($context['exception']) && $context['exception'] === $exception;
            }));

        logException($exception);

        // Mockery verification counts as assertion
        $this->addToAssertionCount(1);
    }

    public function testLogExceptionHelperWithContext(): void
    {
        $exception = new Exception('Test exception');
        $context = ['order_id' => 456];

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Test exception', Mockery::on(function ($ctx) use ($exception, $context) {
                return isset($ctx['exception'])
                    && $ctx['exception'] === $exception
                    && $ctx['order_id'] === 456;
            }));

        logException($exception, $context);

        // Mockery verification counts as assertion
        $this->addToAssertionCount(1);
    }
}
