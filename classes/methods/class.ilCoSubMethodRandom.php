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

	/** @var array  	item_id => count */
	protected $assign_counts_item = array();
	
	/** @var array 		user_id = count */
	protected $assign_counts_user = array();


	/** @var int number of selectable priorities */
	public $number_priorities = 2;

	/** @var bool force once selection per priority */
	public $one_per_priority = false;

	/** @var int number of items to assign in the calculation */
	public $number_assignments = 1;

	/**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		parent::__construct($a_object, $a_plugin);

		$this->number_priorities = (int) $this->getProperty('number_priorities','2');
		$this->one_per_priority = (bool) $this->getProperty('one_per_priority','0');
		$this->number_assignments = (int) $this->getProperty('number_assignments','1');
	}



	/**
	 * Save the properties
	 */
	public function saveProperties()
	{
		$this->setProperty('number_priorities', sprintf('%d', $this->number_priorities));
		$this->setProperty('one_per_priority', sprintf('%d', (int) $this->one_per_priority));
		$this->setProperty('number_assignments', sprintf('%d', (int) $this->number_assignments));

		if ($this->one_per_priority)
		{
			$this->object->setMinChoices(0);
			$this->object->update();
		}
	}


	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * @return array    number => name
	 */
	public function getPriorities()
	{
		switch ($this->number_priorities)
		{
			case 1:
				return array(
					0 => $this->txt('select_yes')
				);

			case 2:
				return array(
					0 => $this->txt('select_preferred'),
					1 => $this->txt('select_alternative'),
				);

			default:
				$priorities = array();
				for ($i = 0; $i < $this->number_priorities; $i++)
				{
					$priorities[$i] = sprintf($this->txt('select_prio_x'), $i + 1);
				}
				return $priorities;
		}
	}

	/**
	 * Get the text for no selection
	 */
	public function getNotSelected()
	{
		return $this->txt('select_not');
	}


	/**
	 * Get the number of assignments that are done by this method
	 * @return int	(default: 1)
	 */
	public function getNumberAssignments()
	{
		return $this->number_assignments;
	}

	/**
	 * This method allows multiple assignments of items to a user
	 */
	public function hasMultipleAssignments()
	{
		return $this->number_assignments > 1;
	}


	/**
	 * This methods allows multiple selections per priority
	 * @return bool
	 */
	public function hasMultipleChoice()
	{
		return $this->one_per_priority ? false : true;
	}


	/**
	 * This method allows a priority not being selected
	 * @return bool
	 */
	public function hasEmptyChoice()
	{
		return $this->one_per_priority ? false : true;
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
	 * This methods respects maximum subscriptions per assignment
	 * @return bool
	 */
	public function hasMaxSubscription()
	{
		return true;
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
		$this->assign_counts_item = array();
		$this->assign_counts_user = array();

		for ($priority = 0; $priority <= 1; $priority++)
		{
			foreach ($this->getSortedItemsForPriority($priority) as $item)
			{
				if (!isset($this->assign_counts_item[$item->item_id]))
				{
					$this->assign_counts_item[$item->item_id] = 0;
				}

				foreach ($this->getSortedUsersForItemAndPriority($item, $priority) as $user_id)
				{
					if (isset($item->sub_max) && $this->assign_counts_item[$item->item_id] >= $item->sub_max)
					{
						break;
					}
					$this->assignUser($user_id, $item->item_id);
					$this->assign_counts_item[$item->item_id]++;
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
			$key1 = ((isset($item->sub_max) && $p_count > $item->sub_max) ? '0' : '1');	// satisfiable
			$key2 = sprintf('%06d', $p_count);					// number of choices in this priority
			$key3 = sprintf('%06d', $i_count - $i);				// reverse position

			$indexed[$key1.$key2.$key3] = $item;
		}

		krsort($indexed);
		return array_values($indexed);
	}

	/**
	 * Get sorted choices for an item and a priority
	 * Sorting criteria:
	 * - 	first group: alternative choices of users without any other alternative choice (only lower priorities)
	 * -	second group: all other choices or all in prio 0
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
				if ($a_priority > 0 && $p_count == 1)
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

		// maximum assignments per user are reached?
		$this->assign_counts_user[$a_user_id] = ((int) $this->assign_counts_user[$a_user_id]) + 1;
		if ($this->assign_counts_user[$a_user_id] >= $this->number_assignments)
		{
			// then remove user from the priorities list
			unset($this->priorities[$a_user_id]);
		}
	}
}