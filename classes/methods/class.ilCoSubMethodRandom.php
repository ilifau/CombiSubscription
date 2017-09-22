<?php

/**
 * Assignment method using random selection
 */
class ilCoSubMethodRandom extends ilCoSubMethodBase
{
	# region class variables
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
	
	/** @var array 		user_id => count */
	protected $assign_counts_user = array();


	/** @var int number of selectable priorities */
	public $number_priorities = 2;

	/** @var bool force once selection per priority */
	public $one_per_priority = false;

	/** @var int number of items to assign in the calculation */
	public $number_assignments = 1;

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
	 * Initialize the data for calculating assignments
	 */
	protected function initCalculationData()
	{
		$this->items = $this->object->getItems();
		$this->priorities = $this->object->getPriorities();
		$this->priority_counts = $this->object->getPriorityCounts();
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
	 * items in each group by decreasing number of choices in this priority
	 *
	 * @param 	int	$a_priority
	 * @return	ilCoSubItem[]
	 */
	protected function getSortedItemsForPriority($a_priority)
	{
		$indexed = array();
		$i_count = count($this->items);

		for ($i = 0; $i < $i_count; $i++)
		{
			$item = $this->items[$i];
			$p_count = (int) $this->priority_counts[$item->item_id][$a_priority];

			// will go into reverse sorting
			$key1 = ((isset($item->sub_max) && $p_count > $item->sub_max) ? '0' : '1');		// satisfiable
			$key2 = sprintf('%06d', $p_count);										// number of choices in this priority
			$key3 = sprintf('%06d', $i_count - $i);								// reverse position

			$indexed[$key1.$key2.$key3] = $item;
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
		 $this->priority_counts[$a_item_id][$priority]--;
		 unset($this->priorities[$a_user_id][$a_item_id]);

		// this user has reached the number of assignments per user
		if ($this->assign_counts_user[$a_user_id] >= $this->number_assignments)
		{
			// decrease the priority counts for remaining items chosen by the user
			foreach ($this->priorities[$a_user_id] as $item_id => $priority)
			{
				$this->priority_counts[$item_id][$priority]--;
			}

			// then remove user from the list of priorities
			unset($this->priorities[$a_user_id]);
		}
	}
}