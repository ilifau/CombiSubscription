<?php

/**
 * Assignment method using random selection
 */
class ilCoSubMethodRandom extends ilCoSubMethodBase
{
	# region class variables

	/** @var int number of selectable priorities */
	public $number_priorities = 2;

	/** @var bool force once selection per priority */
	public $one_per_priority = false;

	/** @var int number of items to assign in the calculation */
	public $number_assignments = 1;



	/** @var  ilCoSubRun */
	protected $run;

	/** @var ilCoSubItem[] (indexed by item_id) */
	protected $items = array();

	/** @var  array 	item_id => item_id[] */
	protected $conflicts;
	
	/** @var array cat_id => (int) limit */
	protected $category_limits = array();

	/** @var  array     user_id => item_id => priority */
	protected $priorities = array();

	/** @var array 		item_id => priority => count */
	protected $priority_counts_item = array();

	/** @var array  	item_id => count */
	protected $assign_counts_item = array();
	
	/** @var array 		user_id => count */
	protected $assign_counts_user = array();

	# endregion

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
		return true;
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
	 * Initialize the data for calculating assignments
	 */
	protected function initCalculationData()
	{
		$this->items = $this->object->getItems();
		$this->conflicts = $this->object->getItemsConflicts();
		$this->category_limits = $this->object->getCategoryLimits();
		$this->priorities = $this->object->getPriorities();
		$this->priority_counts_item = $this->object->getPriorityCounts();
		$this->assign_counts_item = array();
		foreach ($this->items as $item)
		{
			$this->assign_counts_item[$item->item_id] = 0;
		}
		$this->assign_counts_user = array();
		foreach (array_keys($this->priorities) as $user_id)
		{
			$this->assign_counts_user[$user_id] = 0;
		}
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

		$this->initCalculationData();

		$this->calculateByUsers();

		$this->run->details = '';
		$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->save();

		$this->error = '';
		return true;

	}

	/**
	 * Calculate the assignments primary by items
	 * - support multiple assignments
	 * - don't support category limits
	 * - don't support period conflicts
	 * - may leave partially assigned users
	 */
	protected function calculateByItems()
	{
		for ($priority = 0; $priority < $this->number_priorities; $priority++)
		{
			foreach ($this->getSortedItemsForPriority($priority) as $item)
			{
				foreach ($this->getSortedUsersForItemAndPriority($item, $priority) as $user_id)
				{
					// respect maximum assignments per item
					if (isset($item->sub_max) && $this->assign_counts_item[$item->item_id] >= $item->sub_max)
					{
						break;
					}

					$this->assignUser($user_id, $item->item_id);
				}
			}
		}
	}


	/**
	 * Get sorted items for a priority
	 * Sorting criteria:
	 * -	first group: items where all choices of the priority can be fulfilled
	 * -	second group: items, where choices can't be fulfilled
	 * items in each group by decreasing number of choices in this priority
	 *
	 * @param 	int	$a_priority
	 * @return	ilCoSubItem[]
	 */
	protected function getSortedItemsForPriority($a_priority)
	{
		$indexed = array();
		$position = count($this->items);
		foreach($this->items as $item_id => $item)
		{
			$p_count = (int) $this->priority_counts_item[$item_id][$a_priority];

			// will go into reverse sorting
			$key1 = ((isset($item->sub_max) && $p_count > $item->sub_max) ? '0' : '1');		// satisfiable
			$key2 = sprintf('%06d', $p_count);										// number of choices in this priority
			$key3 = sprintf('%06d', $position);										// reverse position

			$indexed[$key1.$key2.$key3] = $item;
			$position--;
		}

		krsort($indexed);
		return array_values($indexed);
	}

	/**
	 * Get sorted users for an item and a priority
	 * Sorting criteria:
	 * - 	first group: users without any other alternative choice in this priority (only lower priorities)
	 * -	second group: all other users
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
		$users = array();

		foreach ($this->priorities as $user_id => $item_priorities)
		{
			$choices = 0;		// all choices of the user for the given priority
			$chosen = false;	// the given item is chosen
			foreach ($item_priorities as $item_id => $priority)
			{
				if ($priority == $a_priority)
				{
					$choices++;
					if ($item_id == $a_item->item_id)
					{
						$chosen = true;
					}
				}
			}

			if ($chosen)
			{
				$users[] = $user_id;

				// users with only one choice in this priority
				if ($a_priority > 0 && $choices == 1)
				{
					$first[] = $user_id;
				}
				// all other users
				// all users in (highest) priority 0
				else
				{
					$second[] = $user_id;
				}
			}
		}

		shuffle($first);
		shuffle($second);

		$users = array_merge($first, $second);
		return $users;
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

		// increase the assignment counters
		$this->assign_counts_user[$a_user_id]++;
		$this->assign_counts_item[$a_item_id]++;

		// decrease the priority count for the chosen item
		// remove the item choice from the user
		 $priority = $this->priorities[$a_user_id][$a_item_id];
		 $this->priority_counts_item[$a_item_id][$priority]--;
		 unset($this->priorities[$a_user_id][$a_item_id]);

		// this user has reached the number of assignments per user
		if ($this->assign_counts_user[$a_user_id] >= $this->number_assignments && !empty($this->priorities[$a_user_id]))
		{
			// decrease the priority counts for remaining items chosen by the user
			foreach ($this->priorities[$a_user_id] as $item_id => $priority)
			{
				$this->priority_counts_item[$item_id][$priority]--;
			}

			// then remove user from the list of priorities
			unset($this->priorities[$a_user_id]);
		}
	}

	/**
	 * Calculate the assignments primary by users
	 * - support multiple assignments
	 * - support category limits
	 * - support period conflicts
	 * - leaves only fully or not assigned users
	 */
	protected function calculateByUsers()
	{
		for ($priority = 0; $priority < $this->number_priorities; $priority++)
		{
			foreach ($this->getSortedUsers() as $user_id)
			{
				$selected = array();
				$available = $this->getSortedItemsForUser($user_id);
				$catlimits = $this->category_limits;

				//log_line("<h2>calculate user $user_id</h2>");

				$selected = $this->getRecursiveItemSelectionForUser($selected, $available, $catlimits);

				if (count($selected) >= $this->number_assignments)
				{
					foreach ($selected as $item_id => $item)
					{
						$this->assignUser($user_id, $item_id);
					}
				}
			}
		}
	}

	/**
	 * Get a randomly sorted list of users
	 */
	protected function getSortedUsers()
	{
		$users  = array_keys($this->priorities);
		shuffle($users);
		return $users;
	}

	/**
	 *	Get a list of items sorted by sorted by some criteria
	 *  1) the priority set by the user
	 *  2) already existing assignments (try to fill groups)
	 *
	 * @param	int				$a_user_id
	 * @return	ilCoSubItem[]	item_id => item
	 */
	protected function getSortedItemsForUser($a_user_id)
	{
		if (!is_array($this->priorities[$a_user_id]))
		{
			return array();
		}

		$indexed = array();

		// get a random order of items as default
		$item_ids = array_keys($this->priorities[$a_user_id]);
		shuffle($item_ids);

		foreach ($item_ids as $index => $item_id)
		{
			$priority = $this->priorities[$a_user_id][$item_id];

			if (!empty($this->items[$item_id]))
			{
				$item = $this->items[$item_id];

				// take only items that are not yet full
				if (empty($item->sub_max) || $item->sub_max > $this->assign_counts_item[$item_id])
				{
					$key1 = sprintf('%06d', $priority);											//sort first by priority (0 is highest priority)
					$key2 = sprintf('%06d', 999999 - $this->assign_counts_item[$item_id]);	//then sort by existing assignments (highest first)
					$key3 = sprintf('%06d', $index);												//then sort by random order
					$indexed[$key1.$key2.$key3] = $item_id;
				}
			}
		}
		ksort($indexed);

		$items = array();
		foreach ($indexed as $keys => $item_id)
		{
			$items[$item_id] = $this->items[$item_id];
		}
		return $items;
	}

	/**
	 * @param $a_selected	ilCoSubItem[]	item_id => item		list of already selected items
	 * @param $a_available	ilCoSubItem[]	item_id => item		list of still available items
	 * @param $a_catlimits	int[]		 	item_id => limit	still available selections per category
	 * @return 				ilCoSubItem[]	item_id => item		selected items
	 */
	protected function getRecursiveItemSelectionForUser($a_selected, $a_available, $a_catlimits)
	{
		//log_line('getRecursiveItemSelectionForUser');

		// positive break condition - required number of assignments reached
		if (count($a_selected) >= $this->number_assignments)
		{
			//log_line('number assignments reached (start)');
			return $a_selected;
		}

		// negative break condition - assignment not possible
		if (count($a_selected) + count($a_available) < $this->number_assignments)
		{
			//log_line('number assignments not reachable (start)');
			return array();
		}

		// try all items as next one
		foreach ($a_available as $item_id => $item)
		{
			$selected = $a_selected;
			$available = $a_available;
			$catlimits = $a_catlimits;

			// add item to the selected ones
			$selected[$item_id] = $item;
			unset($available[$item_id]);

			//log_line("selected: ". implode(',', array_keys($selected)));
			//log_line("available: ". implode(',', array_keys($available)));

			// remove conflicting items from the current available list
			foreach ($this->conflicts[$item_id] as $conflict_item_id)
			{
				if (isset($available[$conflict_item_id]))
				{
					unset($available[$conflict_item_id]);
				}
			}

			//log_line("available: ". implode(',', array_keys($available)). " (no conflicts)");

			// decrease and check the category limit of the chosen item
			if (!empty($item->cat_id) && isset($a_catlimits[$item->cat_id]))
			{
				$catlimits[$item->cat_id] --;

				//category has reached its limit
				if ($catlimits[$item->cat_id] <= 0)
				{
					// remove all items of this category from the current available list
					foreach ($available as $item2_id => $item2)
					{
						if ($item2->cat_id == $item->cat_id)
						{
							unset($available[$item2_id]);
						}
					}
				}
			}

			//log_line("available: ". implode(',', array_keys($available)). "(no catlimits)");

			// append recursively calculated selections
			foreach ($this->getRecursiveItemSelectionForUser($selected, $available, $catlimits) as $item2_id => $item2)
			{
				$selected[$item2_id] = $item2;
			}

			// positive break condition - required number of assignments reached
			if (count($selected) >= $this->number_assignments)
			{
				//log_line('number assignments reached (after)');
				return $selected;
			}

			// remove this item from the available list of this function
			// it will not be an option for the other items, too
			unset ($a_available[$item_id]);
		}

		//log_line('no item fits (after)');
		return array();
	}
}