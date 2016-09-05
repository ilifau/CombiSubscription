<?php

/**
 * Assignment method using the heuristics of Pavel Safre
 * http://publikationen.ub.uni-frankfurt.de/frontdoor/index/index/docId/37015
 */
class ilCoSubMethodSafre extends ilCoSubMethodBase
{
	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * @return array    number => name
	 */
	public function getPriorities()
	{
		return array(
			0 => $this->txt('select_prio1'),
			1 => $this->txt('select_prio2'),
			2 => $this->txt('select_prio3'),
		);
	}

	/**
	 * Get the text for no selection
	 */
	public function getNotSelected()
	{
		return $this->txt('select_not');
	}

	/**
	 * This methods allows multipe selections per oriority
	 * @return bool
	 */
	public function hasMultipleChoice()
	{
		return false;
	}

	/**
	 * This method allows a selection of peers
	 * @return bool
	 */
	public function hasPeerSelection()
	{
		return false;
	}

	/**
	 * This methods respects minimum subscriptions per assignment
	 * @return bool
	 */
	public function hasMinSubscription()
	{
		return false;
	}

	/**
	 * This method is active
	 * @return bool
	 */
	public function isActive()
	{
		return false;
	}


}