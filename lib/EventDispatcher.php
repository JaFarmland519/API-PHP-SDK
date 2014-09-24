<?php
namespace Trackvia;

/**
 * Generic event dispatcher class.
 * 
 * Bind functions to an object by event name.
 * Then trigger events from that object which then executes binded functions.
 * 
 * @author  otake <chris.oake@trackvia.com>
 */
abstract class EventDispatcher
{
    /**
     * Array to store callbacks for different event names
     * @var array
     */
    protected $events = array();

    /**
     * Attach an event listener.
     * @param  string $event
     * @param  string|array $callback A callback function name that can be passed into call_user_func()
     */
    public function on($event, $callback, $extraParams = array())
    {
        if (!is_callable($callback)) {
            throw new \Exception("Callback argument is not callable. Check you have the right function name.");
        }

        if (!isset($this->events[$event])) {
            // initialize an array for this event name
            $this->events[$event] = array();
        }

        $this->events[$event][] = array($callback, $extraParams);
    }

    /**
     * Trigger a binded event.
     * This will call all binded callback functions for a given event.
     * @param string $event The event name
     * @param array Array of optional data to trigger with the event
     */
    public function trigger($event, $data = array())
    {
        if (isset($this->events[$event])) {
            // loop through each callback for this event
            foreach ($this->events[$event] as $callbackData) {
                call_user_func($callbackData[0], $data, $callbackData[1]);
            }
        }
    }
}