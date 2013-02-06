<?php
interface Event_SubjectInterface {
	/**
	 * Attach an observer to an event
	 * @param Event_ObserverInterface $observer The event's observer.
	 * @param string $event The name of the event to observe.
	 */
	public function attach($event, Event_ObserverInterface $observer);
	/**
	 * Detach an observer from an event.
	 * @param Event_ObserverInterface $observer The observer to detach.
	 * @param string $event The name of the event associated with the observer.
	 */
	public function detach($event, Event_ObserverInterface $observer);
	/**
	 * Notify observers of an event that occurred.
	 * @param string $event The name of the event that occurred.
	 * @param mixed $object An object containing data relevant to the event.
	 */
	public function notify($event, $object = null);
}
