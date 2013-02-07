<?php
class Dbi_Field implements Event_SubjectInterface {
	const EVENT_BEFORESET = 'beforeSet';
	private $_type;
	private $_arguments;
	private $_defaultValue;
	private $_allowNull;
	private $_extras;
	private $_eventObservers = array();
	public function __construct($type, $arguments = array(), $defaultValue = '', $allowNull = false, $extras = array()) {
		$this->_type = $type;
		$this->_arguments = $arguments;
		$this->_defaultValue = $defaultValue;
		$this->_allowNull = $allowNull;
		$this->_extras = $extras;
	}
	/**
	 * The field type (e.g., INT, VARCHAR, etc.)
	 * @return string
	 */
	public function type() {
		return $this->_type;
	}
	/**
	 * An array of arguments to be applied to the field type. Typical arguments
	 * include the size of an integer, the length of a text field, or the values
	 * of an enum.
	 * @return array
	 */
	public function arguments() {
		return $this->_arguments;
	}
	/**
	 * The default value to be used for this field when no value is specified
	 * while creating a new record.
	 * @return scalar
	 */
	public function defaultValue() {
		return $this->_defaultValue;
	}
	/**
	 * True if the field is allowed to have a NULL value.
	 * @return boolean
	 */
	public function allowNull() {
		return $this->_allowNull;
	}
	/**
	 * An array of extra information about the field.
	 * @return array
	 */
	public function extras() {
		return $this->_extras;
	}
	/**
	 * Attach an observer to an event.
	 * @param Event_ObserverInterface $observer The observer for the event.
	 * @param string $event The name of the event (see Dbi_Model constants).
	 */
	public function attach($event, Event_ObserverInterface $observer) {
		$this->_eventObservers[$event][] = $observer;
	}
	/**
	 * Detach an observer from an event.
	 * @param Event_ObserverInterface $observer The observer to detach.
	 * @param string $event The name of the event associated with the observer (see Dbi_Model constants).
	 */
	public function detach($event, Event_ObserverInterface $observer) {
		if (isset($this->_eventObservers[$event])) {
			$key = array_search($observer, $this->_eventObservers[$event], true);
			if ($key !== false) {
				array_splice($this->_eventObservers[$event], $key, 1);
			}
		}
	}
	/**
	 * Notify observers that an event has occurred.
	 * @param string $event The name of the event (see Dbi_Model constants).
	 * @param mixed $object The object that the observer will process (for most
	 * Dbi_Model events, the observer will expect this to be the Dbi_Record
	 * being selected, created, updated, or deleted).
	 */
	public function notify($event, $object = null) {
		if (isset($this->_eventObservers[$event])) {
			if (is_null($object)) $object = $this;
			foreach ($this->_eventObservers[$event] as $observer) {
				$observer->update($object);
			}
		}
	}
}
