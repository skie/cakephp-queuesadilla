<?php
declare(strict_types=1);

namespace Josegonzalez\CakeQueuesadilla\Test\TestCase\Queue;

use BadMethodCallException;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Josegonzalez\CakeQueuesadilla\Queue\Queue;
use josegonzalez\Queuesadilla\Engine\MysqlEngine;
use RuntimeException;
use stdClass;

/**
 * QueueTest class
 */
class QueueTest extends TestCase
{
    /**
     * setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Log::reset();
        Queue::reset();
    }

    /**
     * teardown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Log::reset();
        Queue::reset();
    }

    /**
     * test all the errors from failed logger imports
     *
     * @return void
     */
    public function testImportingQueueEngineFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Queue::setConfig('fail', []);
        Queue::engine('fail');
    }

    /**
     * test config() with valid key name
     *
     * @return void
     */
    public function testValidKeyName(): void
    {
        Log::setConfig('stdout', ['engine' => 'File']);
        Queue::setConfig('valid', [
            'url' => 'mysql://username:password@localhost:80/database',
        ]);
        $engine = Queue::engine('valid');
        $this->assertInstanceOf(MysqlEngine::class, $engine);
    }

    /**
     * test that loggers have to implement the correct interface.
     *
     * @return void
     */
    public function testNotImplementingInterface(): void
    {
        $this->expectException(RuntimeException::class);

        Queue::setConfig('fail', ['engine' => stdClass::class]);
        Queue::engine('fail');
    }

    /**
     * explicit tests for drop()
     *
     * @return void
     */
    public function testDrop(): void
    {
        Queue::setConfig('default', [
            'url' => 'mysql://username:password@localhost:80/database',
        ]);
        $result = Queue::configured();
        $this->assertContains('default', $result);

        $this->assertTrue(Queue::drop('default'), 'Should be dropped');
        $this->assertFalse(Queue::drop('default'), 'Already gone');

        $result = Queue::configured();
        $this->assertNotContains('default', $result);
    }

    /**
     * Ensure you cannot reconfigure a log adapter.
     *
     * @return void
     */
    public function testConfigErrorOnReconfigure(): void
    {
        $this->expectException(BadMethodCallException::class);

        Queue::setConfig('tests', ['url' => 'mysql://username:password@localhost:80/database']);
        Queue::setConfig('tests', ['url' => 'null://']);
    }

    /**
     * Ensure Queue::push dispatches Queuesadilla.job.pushed via afterEnqueue bridge.
     *
     * @return void
     */
    public function testPushDispatchesJobPushedEvent(): void
    {
        Queue::setConfig('memory', [
            'url' => 'memory://',
        ]);

        /** @var \Cake\Event\Event|null $received */
        $received = null;
        \Cake\Event\EventManager::instance()->on(
            Queue::JOB_PUSHED,
            function (\Cake\Event\Event $event) use (&$received): void {
                $received = $event;
            },
        );

        $ok = Queue::push('strlen', ['hello'], ['config' => 'memory', 'queue' => 'demo']);
        $this->assertTrue($ok);
        $this->assertInstanceOf(\Cake\Event\Event::class, $received);
        $this->assertTrue((bool)$received->getData('success'));
        $this->assertSame('memory', $received->getData('config'));
        $item = $received->getData('item');
        $this->assertIsArray($item);
        $this->assertSame('demo', $item['queue']);
        $this->assertSame('strlen', $item['class']);
    }

    /**
     * Ensure Queue resets correctly
     *
     * @return void
     */
    public function testReset(): void
    {
        Queue::setConfig('test', [
            'url' => 'null://',
        ]);

        $registry = Queue::registry();
        $engine = Queue::engine('test');
        $queue = Queue::queue('test');

        Queue::reset();

        Queue::setConfig('test', [
            'url' => 'null://',
        ]);
        $newRegistry = Queue::registry();
        $newEngine = Queue::engine('test');
        $newQueue = Queue::queue('test');
        $this->assertNotSame($registry, $newRegistry, 'After reset the registry references the old object');
        $this->assertNotSame($engine, $newEngine, 'After reset the engine references the old object');
        $this->assertNotSame($queue, $newQueue, 'After reset the queue references the old object');
    }
}
