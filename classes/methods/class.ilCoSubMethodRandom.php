<?php

/**
 * Assignment method using random selection
 */
class ilCoSubMethodRandom extends ilCoSubMethodBase
{
	/** @var  ilCoSubRun */
	protected $run;

	/** @var ilCoSubItem[] | null */
	protected $items = array();

	/** @var  array     user_id => item_id => priority */
	protected $priorities = array();

	/** @var array 		item_id => priority => count */
	protected $priority_counts = array();


	/**
	 * Get the name of the properties GUI class
	 * (overwrite this if no properties GUI is provided)
	 * @return string
	 */
	public function getPropertiesGuiName()
	{
		return '';
	}


	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * @return array    number => name
	 */
	public function getPriorities()
	{
		return array(
			0 => $this->txt('select_preferred'),
			1 => $this->txt('select_alternative'),
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
		return true;
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
		return true;
	}


	/**
	 * Calculate the assignments
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 *
	 * @param ilCoSubRun    $a_run
	 * @return bool         true: calculation is started, false: an error occurred, see getError()
	 */
	public function calculateAssignments($a_run)
	{
		include_once('include/inc.debug.php');

		$this->run = $a_run;
		$this->run->run_start = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->save();

		$this->items = $this->object->getItems();
		$this->priorities = $this->object->getPriorities();
		$this->priority_counts = $this->object->getPriorityCounts();


		for ($priority = 0; $priority <= 1; $priority++)
		{
			foreach ($this->getSortedItemsForPriority($priority) as $item)
			{
				$assigned = 0;
				foreach ($this->getSortedUsersForItemAndPriority($item, $priority) as $user_id)
				{
					if ($assigned >= $item->sub_max)
					{
						break;
					}
					$this->assignUser($user_id, $item->item_id);
					$assigned++;
				}
			}
		}

		$this->run->details = '';
		$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->save();

		$this->error = '';
		return true;
	}


	/**
	 * Get sorted items for a priority
	 * Sorting criteria:
	 * -	first group: items where all choices of the priority can be fulfilled
	 * -	second group: items, where choices can't be fulfilled
	 * -	items in each group by decreasing number of choices in this priority
	 *
	 * @param 	int	$a_priority
	 * @return	ilCoSubItem
	 */
	protected function getSortedItemsForPriority($a_priority)
	{
		$indexed = array();

		$i_count = count($this->items);

		for ($i = 0; $i < $i_count; $i++)
		{
			$item = $this->items[$i];
			$p_count = isset($this->priority_counts[$item->item_id][$a_priority]) ? $this->priority_counts[$item->item_id][$a_priority] : 0;

			// will go into reverse sorting
			$key1 = ($p_count > $item->sub_max ? '0' : '1');	// satisfiable
			$key2 = sprintf('%06d', $p_count);					// number of choices in this priority
			$key3 = sprintf('%06d', $i_count - $i);				// reverse position

			$indexed[$key1.$key2.$key3] = $item;
		}

		krsort($indexed);
		log_var($indexed);
		return array_values($indexed);
	}

	/**
	 * Get sorted choices for an item and a priority
	 * Sorting criteria:
	 * - 	first group: alternative choices of users without any other alternative choice (only prio 1)
	 * -	second group: all other choices or all in prio 1
	 * -	users in each group are sorted randomly
	 *
	 * @param 	ilCoSubItem $a_item
	 * @param	int			$a_priority
	 * @return 	array		user_ids
	 */
	protected function getSortedUsersForItemAndPriority($a_item, $a_priority)
	{
		$first = array();
		$second = array();

		foreach ($this->priorities as $user_id => $item_priorities)
		{
			$p_count = 0;
			$chosen = false;
			foreach ($item_priorities as $item_id => $priority)
			{
				if ($priority == $a_priority)
				{
					$p_count++;
					$chosen = ($item_id == $a_item->item_id);
				}
			}

			if ($chosen)
			{
				if ($a_priority == 1 && $p_count == 1)
				{
					$first[] = $user_id;
				}
				else
				{
					$second[] = $user_id;
				}
			}
		}

		shuffle($first);
		shuffle($second);

		return array_merge($first, $second);
	}

	/**
	 * Assign a user to an item and remove it from the priority lists
	 * @param integer	$a_user_id
	 * @param integer	$a_item_id
	 */
	protected function assignUser($a_user_id, $a_item_id)
	{
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
		$assign = new ilCoSubAssign;
		$assign->obj_id = $this->object->getId();
		$assign->run_id = $this->run->run_id;
		$assign->user_id = $a_user_id;
		$assign->item_id = $a_item_id;
		$assign->save();

		// first update the priority counts from the items
		foreach ($this->priorities[$a_user_id] as $item_id => $priority)
		{
			$this->priority_counts[$item_id][$priority]--;
		}

		// then remove user from the priorities list
		unset($this->priorities[$a_user_id]);
	}
}