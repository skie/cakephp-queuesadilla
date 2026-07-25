<?php
declare(strict_types=1);

namespace Josegonzalez\CakeQueuesadilla\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Josegonzalez\CakeQueuesadilla\Queue\Queue;
use josegonzalez\Queuesadilla\Engine\Base as BaseEngine;
use josegonzalez\Queuesadilla\Worker\Base as BaseWorker;
use Psr\Log\LoggerInterface;

class QueuesadillaCommand extends Command
{
    /**
     * Dispatched after the worker is constructed and before work() runs.
     *
     * Listeners receive `worker`, `engine`, and `args` in event data so they can
     * attach league/event listeners to the worker instance.
     */
    public const WORKER_CREATED = 'Queuesadilla.worker.created';

    /**
     * Starts a Queuesadilla worker.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $loggerOption = $args->getOption('logger') ?? 'stdout';
        $logger = Log::engine($loggerOption);
        if (!$logger instanceof LoggerInterface) {
            $logger = Log::engine('debug');
        }
        if (!$logger instanceof LoggerInterface) {
            $io->err('No logger configured.');

            return static::CODE_ERROR;
        }

        $engine = $this->getEngine($args, $logger);
        $worker = $this->getWorker($args, $engine, $logger);
        EventManager::instance()->dispatch(new Event(
            static::WORKER_CREATED,
            $this,
            [
                'worker' => $worker,
                'engine' => $engine,
                'args' => $args,
            ],
        ));
        $worker->work();

        return static::CODE_SUCCESS;
    }

    /**
     * Retrieves a queue engine.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \josegonzalez\Queuesadilla\Engine\Base
     */
    public function getEngine(Arguments $args, LoggerInterface $logger): BaseEngine
    {
        $config = (string)$args->getOption('config');
        $engine = Queue::engine($config);
        assert($engine instanceof BaseEngine);
        $engine->setLogger($logger);
        $queue = $args->getOption('queue');
        if (!empty($queue)) {
            $engine->config('queue', $queue);
        }

        return $engine;
    }

    /**
     * Retrieves a queue worker.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \josegonzalez\Queuesadilla\Engine\Base $engine Engine to run.
     * @param \Psr\Log\LoggerInterface $logger Logger instance.
     * @return \josegonzalez\Queuesadilla\Worker\Base
     */
    public function getWorker(Arguments $args, BaseEngine $engine, LoggerInterface $logger): BaseWorker
    {
        $worker = (string)$args->getOption('worker');
        $workerClass = 'josegonzalez\\Queuesadilla\\Worker\\' . $worker . 'Worker';

        return new $workerClass($engine, $logger, [
            'queue' => $engine->config('queue'),
            'maxRuntime' => $engine->config('maxRuntime'),
            'maxIterations' => $engine->config('maxIterations'),
        ]);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to configure.
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('config', [
            'default' => 'default',
            'help' => 'Name of a queue config to use',
            'short' => 'c',
        ]);
        $parser->addOption('queue', [
            'help' => 'Name of queue to override from loaded config',
            'short' => 'Q',
        ]);
        $parser->addOption('logger', [
            'help' => 'Name of a configured logger',
            'default' => 'stdout',
            'short' => 'l',
        ]);
        $parser->addOption('worker', [
            'choices' => [
                'Sequential',
                'Test',
            ],
            'default' => 'Sequential',
            'help' => 'Name of worker class',
            'short' => 'w',
        ])->setDescription('Runs a Queuesadilla worker.');

        return $parser;
    }
}
