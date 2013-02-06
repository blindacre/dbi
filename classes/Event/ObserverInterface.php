<?php
interface Event_ObserverInterface {
	/**
	 * Receive notification that an event has occurred (usu. from an
	 * Event_SubjectInterface->notify() call).
	 */
	public function update($subject);
}
