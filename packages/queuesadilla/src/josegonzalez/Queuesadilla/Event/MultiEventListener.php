<?php

namespace josegonzalez\Queuesadilla\Event;

use League\Event\Listener;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;

abstract class MultiEventListener implements EventListenerInterface, Listener, ListenerSubscriber
{
    abstract public function implementedEvents(): array;

    /**
     * @inheritDoc
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        foreach ($this->implementedEvents() as $event => $method) {
            $acceptor->subscribeTo($event, $this);
        }
    }

    /**
     * @inheritDoc
     */
    public function __invoke(object $event): void
    {
        $this->handle($event);
    }

    /**
     * Route a dispatched event to the mapped handler method.
     *
     * @param object $event Dispatched event
     * @return mixed|null
     */
    public function handle(object $event)
    {
        $events = $this->implementedEvents();
        if (empty($events)) {
            return;
        }

        $name = $event instanceof Event ? $event->getName() : null;
        if ($name === null || !isset($events[$name])) {
            return;
        }

        $handler = $events[$name];
        if (!method_exists($this, $handler)) {
            return;
        }

        return $this->$handler($event);
    }
}
