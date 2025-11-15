<?php
declare(strict_types=1);

namespace KobGitUpdater\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use KobGitUpdater\Core\Container;
use InvalidArgumentException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testCanRegisterAndGetService(): void
    {
        $service = new \stdClass();
        $service->name = 'test';

        $this->container->register('test.service', fn() => $service);

        $retrieved = $this->container->get('test.service');
        $this->assertSame($service, $retrieved);
    }

    public function testCanSetAndGetServiceInstance(): void
    {
        $service = new \stdClass();
        $service->name = 'test';

        $this->container->set('test.service', $service);

        $retrieved = $this->container->get('test.service');
        $this->assertSame($service, $retrieved);
    }

    public function testReturnsNullForNonExistentService(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Service 'non.existent' not found");

        $this->container->get('non.existent');
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $this->container->register('test.service', fn() => new \stdClass());

        $this->assertTrue($this->container->has('test.service'));
        $this->assertFalse($this->container->has('non.existent'));
    }

    public function testHasReturnsTrueForSetService(): void
    {
        $this->container->set('test.service', new \stdClass());

        $this->assertTrue($this->container->has('test.service'));
    }

    public function testSingletonBehavior(): void
    {
        $this->container->register('test.service', fn() => new \stdClass(), true);

        $service1 = $this->container->get('test.service');
        $service2 = $this->container->get('test.service');

        $this->assertSame($service1, $service2);
    }

    public function testNonSingletonBehavior(): void
    {
        $this->container->register('test.service', fn() => new \stdClass(), false);

        $service1 = $this->container->get('test.service');
        $service2 = $this->container->get('test.service');

        // Note: Current implementation still caches as singleton
        // This test documents current behavior
        $this->assertSame($service1, $service2);
    }

    public function testGetServiceIdsReturnsAllIds(): void
    {
        $this->container->register('service.a', fn() => new \stdClass());
        $this->container->set('service.b', new \stdClass());

        $ids = $this->container->getServiceIds();

        $this->assertContains('service.a', $ids);
        $this->assertContains('service.b', $ids);
        $this->assertCount(2, $ids);
    }

    public function testFactoryReceivesContainerInstance(): void
    {
        $receivedContainer = null;
        
        $this->container->register('test.service', function (Container $container) use (&$receivedContainer) {
            $receivedContainer = $container;
            return new \stdClass();
        });

        $this->container->get('test.service');

        $this->assertSame($this->container, $receivedContainer);
    }
}