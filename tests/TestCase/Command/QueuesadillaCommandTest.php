<?php
declare(strict_types=1);

namespace Josegonzalez\CakeQueuesadilla\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Josegonzalez\CakeQueuesadilla\Command\QueuesadillaCommand;
use Josegonzalez\CakeQueuesadilla\Queue\Queue;
use josegonzalez\Queuesadilla\Engine\Base as BaseEngine;
use josegonzalez\Queuesadilla\Engine\MysqlEngine;
use josegonzalez\Queuesadilla\Engine\NullEngine;
use josegonzalez\Queuesadilla\Worker\Base as BaseWorker;
use josegonzalez\Queuesadilla\Worker\SequentialWorker;
use josegonzalez\Queuesadilla\Worker\TestWorker;
use Psr\Log\NullLogger;

/**
 * QueuesadillaCommand test.
 */
class QueuesadillaCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected QueuesadillaCommand $command;

    /**
     * setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configApplication(
            'Josegonzalez\CakeQueuesadilla\Test\App\Application',
            [ROOT . DS . 'tests'],
        );
        $this->command = new QueuesadillaCommand();
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
     * Test that the worker is an instance of the correct object
     *
     * @return void
     */
    public function testGetEngine(): void
    {
        Log::setConfig('stdout', ['engine' => 'File']);
        Queue::setConfig('default', [
            'url' => 'mysql://username:password@localhost:80/database',
        ]);
        $logger = new NullLogger();
        $args = new Arguments([], ['config' => 'default'], ['config', 'queue', 'logger', 'worker']);
        $engine = $this->command->getEngine($args, $logger);
        $this->assertInstanceOf(MysqlEngine::class, $engine);
    }

    /**
     * Test that the worker is an instance of the correct object
     *
     * @return void
     */
    public function testGetWorker(): void
    {
        $logger = new NullLogger();
        $engine = new NullEngine();
        $args = new Arguments([], ['worker' => 'Sequential'], ['config', 'queue', 'logger', 'worker']);
        $worker = $this->command->getWorker($args, $engine, $logger);
        $this->assertInstanceOf(SequentialWorker::class, $worker);

        $args = new Arguments([], ['worker' => 'Test'], ['config', 'queue', 'logger', 'worker']);
        $worker = $this->command->getWorker($args, $engine, $logger);
        $this->assertInstanceOf(TestWorker::class, $worker);
    }

    /**
     * Test that the option parser is shaped right.
     *
     * @return void
     */
    public function testBuildOptionParser(): void
    {
        $parser = $this->command->buildOptionParser(new ConsoleOptionParser());
        $commands = $parser->options();
        $this->assertArrayHasKey('queue', $commands);
        $this->assertArrayHasKey('logger', $commands);
        $this->assertArrayHasKey('worker', $commands);
    }

    /**
     * Test that the queuesadilla command executes successfully.
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        Queue::setConfig('default', [
            'url' => 'memory://',
            'maxRuntime' => 1,
        ]);

        $this->exec('queuesadilla --worker Test');
        $this->assertExitSuccess();
    }

    /**
     * Test that execute dispatches Queuesadilla.worker.created before work().
     *
     * @return void
     */
    public function testExecuteDispatchesWorkerCreatedEvent(): void
    {
        /** @var \Cake\Event\Event|null $received */
        $received = null;
        EventManager::instance()->on(
            QueuesadillaCommand::WORKER_CREATED,
            function (Event $event) use (&$received): void {
                $received = $event;
            },
        );

        Queue::setConfig('default', [
            'url' => 'memory://',
            'maxRuntime' => 1,
        ]);

        $this->exec('queuesadilla --worker Test');
        $this->assertExitSuccess();
        $this->assertInstanceOf(Event::class, $received);
        $this->assertInstanceOf(BaseWorker::class, $received->getData('worker'));
        $this->assertInstanceOf(BaseEngine::class, $received->getData('engine'));
        $this->assertInstanceOf(Arguments::class, $received->getData('args'));
    }
}
