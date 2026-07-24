<?php

namespace josegonzalez\Queuesadilla\Event;

use InvalidArgumentException;
use League\Event\EventDispatcher;
use League\Event\ListenerPriority;
use League\Event\ListenerSubscriber;

trait EventManagerTrait
{

    /**
     * Instance of the League\Event\EventDispatcher this object is using
     * to dispatch inner events.
     *
     * @var \League\Event\EventDispatcher|null
     */
    protected $eventManager = null;

    /**
     * Default class name for new event objects.
     *
     * @var string
     */
    protected $eventClass = '\josegonzalez\Queuesadilla\Event\Event';

    /**
     * Returns the League\Event\EventDispatcher manager instance for this object.
     *
     * You can use this instance to register any new listeners or callbacks to the
     * object events, or create your own events and trigger them at will.
     *
     * @param \League\Event\EventDispatcher|null $eventManager the eventManager to set
     * @return \League\Event\EventDispatcher
     */
    public function eventManager(?EventDispatcher $eventManager = null)
    {
        if ($eventManager !== null) {
            $this->eventManager = $eventManager;
        } elseif (empty($this->eventManager)) {
            $this->eventManager = new EventDispatcher();
        }

        return $this->eventManager;
    }

    /**
     * Wrapper for creating and dispatching events.
     *
     * Returns a dispatched event.
     *
     * @param string $name Name of the event.
     * @param array|null $data Any value you wish to be transported with this event to
     * it can be read by listeners.
     *
     * @param object|null $subject The object that this event applies to
     * ($this by default).
     *
     * @return \josegonzalez\Queuesadilla\Event\Event
     */
    public function dispatchEvent($name, $data = null, $subject = null)
    {
        if ($subject === null) {
            $subject = $this;
        }

        $event = new $this->eventClass($name, $subject, $data);
        $this->eventManager()->dispatch($event);

        return $event;
    }

    /**
     * Attach a named listener, callable, or multi-event subscriber.
     *
     * @param string|\League\Event\ListenerSubscriber|\josegonzalez\Queuesadilla\Event\MultiEventListener $name Event name or subscriber
     * @param callable|object|null $listener Listener callable or object
     * @param array $options Listener options (priority)
     * @return void
     */
    public function attachListener($name, $listener = null, array $options = [])
    {
        if (!$listener) {
            if ($name instanceof ListenerSubscriber) {
                $this->eventManager()->subscribeListenersFrom($name);

                return;
            }
            if ($name instanceof MultiEventListener) {
                foreach ($name->implementedEvents() as $event => $method) {
                    $method;
                    $this->attachListener($event, $name, $options);
                }

                return;
            }
            throw new InvalidArgumentException('Invalid listener for event');
        }
        $options += ['priority' => ListenerPriority::NORMAL];
        $this->eventManager()->subscribeTo($name, $listener, $options['priority']);
    }
}
