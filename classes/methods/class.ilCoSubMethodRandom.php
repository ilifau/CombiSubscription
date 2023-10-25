<?php

/**
 * Assignment method using random selection
 */
class ilCoSubMethodRandom extends ilCoSubMethodBase
{
	const PRIO_CHOICES_FREE = 'free';
	const PRIO_CHOICES_LIMITED = 'limited';
	const PRIO_CHOICES_UNIQUE = 'unique';

    //
    // Object and method specific settings
    //

	/** @var int number of selectable priorities */
	public $number_priorities = 2;

	/** @var string mode of selecting priorities */
	public $priority_choices = 'free';

	/** @var int number of items to assign in the calculation */
	public $number_assignments = 1;

    /** @var bool tweak: prefer filling single items instead of equal distribution */
    public $prefer_filled_items = false;
    
	/** @var bool tweak: allow less than sub_min assignments per item */
	public $allow_low_filled_items = false;

	/** @var bool tewak: allow less than number_assignments per user */
    public $allow_low_filled_users = false;

	/** @var bool tweak: calculate as if all items were selected by the users */
    public $assume_all_items_selected = false;

	/** @var bool tweak: calculate as if the maximum per item is limited by the minimum */
    public $assume_sub_min_as_limit = false;
    
    /** @var bool tweak: allow to fill up missing assignments of fixed users */
    public $fill_fixed_users = false;
    
    
    //
    // Calculation basics (unchanged in a calculation run)
    //

	/** @var  ilCoSubRun */
	protected $run;

	/**	@var ilCoSubUser[] (indexed by user_id) */
	protected $users;

	/** @var ilCoSubItem[] (indexed by item_id) */
	protected $items = array();

	/** @var  array 	item_id => item_id[] */
	protected $conflicts;
	
	/** @var array cat_id => (int) limit */
	protected $category_limits = array();

    /** @var  array     user_id => item_id => priority */
    protected $inital_priorities = array();

    /**  @var array	user_id => item_id => true */
    protected $fixed_assignments = array();
    
    //
    // Calculation data (changing in a calculation run)
    //

	/** @var  array    user_id => item_id => priority */
	protected $priorities = array();

	/**  @var array	user_id => item_id => true */
	protected $assignments = array();

	/** @var array  	item_id => count */
	protected $assign_counts_item = array();
	
	/** @var array 		user_id => count */
	protected $assign_counts_user = array();

    /** @var int[] IDs of items that are blocked for further assignments */
    protected $blocked_item_ids = array();


    /**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		parent::__construct($a_object, $a_plugin);

		$this->number_priorities = (int) $this->getProperty('number_priorities','2');
		$this->priority_choices = (string) $this->getProperty('priority_choices','free');
		$this->number_assignments = (int) $this->getProperty('number_assignments','1');
		$this->out_of_conflict_time = (int) $this->getProperty('out_of_conflict_time',
			$this->plugin->getOutOfConflictTime());
		$this->tolerated_conflict_percentage = (int) $this->getProperty('tolerated_conflict_percentage',
			$this->plugin->getToleratedConflictPercentage());
	}


	/**
	 * Save the properties
	 */
	public function saveProperties()
	{
		$this->setProperty('number_priorities', sprintf('%d', $this->number_priorities));
		$this->setProperty('priority_choices', sprintf('%s', (string) $this->priority_choices));
		$this->setProperty('number_assignments', sprintf('%d', (int) $this->number_assignments));
		$this->setProperty('out_of_conflict_time', sprintf('%d', (int) $this->out_of_conflict_time));
		$this->setProperty('tolerated_conflict_percentage', sprintf('%d', (int) $this->tolerated_conflict_percentage));

		if ($this->priority_choices == self::PRIO_CHOICES_UNIQUE)
		{
			$this->object->setMinChoices(0);
			$this->object->update();
		}
		elseif ($this->priority_choices == self::PRIO_CHOICES_LIMITED)
		{
			$this->object->setMinChoices(min($this->object->getMinChoices(), $this->number_priorities));
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
		return ($this->priority_choices == self::PRIO_CHOICES_FREE);
	}


	/**
	 * This method allows a priority not being selected
	 * @return bool
	 */
	public function hasEmptyChoice()
	{
		return ($this->priority_choices != self::PRIO_CHOICES_UNIQUE);
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
	 * This method provides the result of calculateAssignments instantly
	 */
	public function hasInstantResult()
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
	 * Initialize the basics needed for the calculation
     * These will not change within a calculation run
	 */
	protected function initCalculationBasics()
	{
		$this->items = $this->object->getItems();
		$this->conflicts = $this->object->getItemsConflicts();
		$this->category_limits = $this->object->getCategoryLimits();
        if ($this->plugin->hasFauService())
		{
			$this->users = $this->object->getUsersForStudyCond();
            $this->inital_priorities = $this->object->getPrioritiesWithPassedRestrictions();
		}
		else
		{
			$this->users = $this->object->getUsers();
            $this->inital_priorities =  $this->object->getPriorities();
		}

//		log_var($this->items, 'items');
//		log_var($this->conflicts, 'conflicts');
//		log_var($this->category_limits, 'category_limits');
//		log_var($this->initial_priorities, 'initial_priorities');
	}

	/**
	 * Initialize the calculation variables for a new assignment loop
	 */
	protected function initCalculationData()
	{
        $this->priorities = $this->inital_priorities;
		$this->assignments = [];
		$this->assign_counts_item = [];
        $this->assign_counts_user = [];

		foreach ($this->items as $item)
		{
			$this->assign_counts_item[$item->item_id] = 0;
		}

		// count the fixed assignments
		foreach ($this->users as $user_id => $user_obj)
		{
            $this->assign_counts_user[$user_id] = 0;

			if ($user_obj->is_fixed)
			{
				// get the actual assignments (run_id = 0)
				foreach ($this->object->getAssignmentsOfUser($user_id) as $item_id => $assign_id)
				{
					// assign as fixed in the new run
					$this->assignUser($user_id, $item_id, true);
				}
			}
		}
	}

	/**
	 * Assign a user to an item and remove it from the priority lists
	 * @param integer	$a_user_id
	 * @param integer	$a_item_id
     * @param integer	$a_fixed    add this assignment to the list of fixed assignments
	 */
	protected function assignUser($a_user_id, $a_item_id, $a_fixed = false)
	{
		// note the assignments
		$this->assignments[$a_user_id][$a_item_id] = true;
        
        if ($a_fixed) {
            $this->fixed_assignments[$a_user_id][$a_item_id] = true;
        }

		// increase the assignment counters
		$this->assign_counts_user[$a_user_id]++;
		$this->assign_counts_item[$a_item_id]++;

		// remove the item choice from the user
		unset($this->priorities[$a_user_id][$a_item_id]);

		// this user has reached the number of assignments per user
		if ($this->assign_counts_user[$a_user_id] >= $this->number_assignments && !empty($this->priorities[$a_user_id]))
		{
			// then remove user from the list of priorities
			unset($this->priorities[$a_user_id]);
		}
	}

	/**
	 * Save the calculated assignments
	 */
	protected function saveAssignments()
	{
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');

		foreach ($this->assignments as $user_id => $items)
		{
			foreach ($items as $item_id => $assigned)
			{
				$assign = new ilCoSubAssign;
				$assign->obj_id = $this->object->getId();
				$assign->run_id = $this->run->run_id;
				$assign->user_id = $user_id;
				$assign->item_id = $item_id;
				$assign->save();
			}
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

		$this->initCalculationBasics();

		// calculcate in multiple rounds to get rid of low filled items
        // with each round a low filled item is removed from the calculation
        // stop if no further items can be asigned
		for ($round = 1; $round <= count($this->items) - count($this->blocked_item_ids); $round++)
		{
			$this->initCalculationData();
			$this->calculateByUsers();

            // low filled items are sorted by the number of their assignments
            $low_ids = $this->getLowFilledItemIds();
            if ($this->allow_low_filled_items || empty($low_ids))
			{
                // no redistribution needed
				break;
			}

            // give assignments of the lowest filled item free for a redistribution
            $low_ids = [$low_ids[0]];
            $this->removeUnfixedAssignments($low_ids);

            // block the lowest filled item for a reassignment
            $this->blocked_item_ids = array_unique(array_merge($this->blocked_item_ids, $low_ids));
		}

		$this->saveAssignments();

		$details = [];
		if ($this->allow_low_filled_items)
		{
			$details[] = $this->txt('allow_low_filled_items');
		}
		if ($this->allow_low_filled_users)
		{
			$details[] = $this->txt('allow_low_filled_users');
		}
		if ($this->assume_all_items_selected)
		{
			$details[] = $this->txt('assume_all_items_selected');
		}
		if ($this->assume_sub_min_as_limit)
		{
			$details[] = $this->txt('assume_sub_min_as_limit');
		}

		$this->run->details = implode(', ', $details);
		$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->save();

		$this->error = '';
		return true;

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
		foreach ($this->getShuffledUserIds() as $user_id)
		{
			$selected = array();
            if (isset($this->fixed_assignments[$user_id])) {
                foreach ($this->fixed_assignments[$user_id] as $item_id => $assigned) {
                    $selected[$item_id] = $this->items[$item_id];
                }
            }
            
			$available = $this->getSortedItemsForUser($user_id);
			$catlimits = $this->category_limits;

			//log_line("<h2>calculate user $user_id</h2>");

			$selected = $this->getRecursiveItemSelectionForUser($selected, $available, $catlimits);

			if ($this->allow_low_filled_users || count($selected) >= $this->number_assignments)
			{
				foreach ($selected as $item_id => $item)
				{
                    // log_line("assign $user_id to $item_id");
					$this->assignUser($user_id, $item_id);
				}
			}
		}
	}

	/**
	 * Get a randomly sorted list of users
	 */
	protected function getShuffledUserIds()
	{
		$user_ids = array();
		foreach ($this->users as $user_id => $user)
		{
			if (!$user->is_fixed || $this->fill_fixed_users)
			{
				$user_ids[] = $user_id;
			}
		}
		shuffle($user_ids);
		return $user_ids;
	}

	/**
	 *	Get a list of items sorted by some criteria
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
			return [];
		}

		$indexed = [];

		// get a random order of items as default
		if ($this->assume_all_items_selected)
		{
			$item_ids = array_keys($this->items);
		}
		else
		{
			$item_ids = array_keys($this->priorities[$a_user_id]);
		}

		shuffle($item_ids);

		foreach ($item_ids as $random => $item_id)
		{
            if (in_array($item_id, $this->blocked_item_ids)) {
                // don't choose a blocked item for assignment
                continue;
            }

			if (isset($this->priorities[$a_user_id][$item_id]))
			{
				// take the chosen priority
				$priority = $this->priorities[$a_user_id][$item_id];
			}
			elseif ($this->assume_all_items_selected)
			{
				// take one after the weakest priority for the workaround
				$priority = $this->number_priorities;
			}

			if (!empty($this->items[$item_id]))
			{
				$item = $this->items[$item_id];

				// check the item subscription limit
				if ($this->assume_sub_min_as_limit && !empty($item->sub_min))
				{
					$limit = $item->sub_min;
				}
				else
				{
					$limit = $item->sub_max;
				}

				// take only items that are not yet full
				if (empty($limit) || $limit > $this->assign_counts_item[$item_id])
				{
					$key1 = sprintf('%06d', $priority);											//sort first by priority (0 is highest priority)
                    $key2 = $this->prefer_filled_items 
                        ? sprintf('%06d', 999999 - $this->assign_counts_item[$item_id])     // then sort by existing assignments (highest first)
                        : sprintf('%06d', $this->assign_counts_item[$item_id]);                     // or sort by existing assignments (lowest first)
                    
					$key3 = sprintf('%06d', $random);											//then sort by random order
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
	 * Get the item selection for a user recursively
	 *
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
		if (!$this->allow_low_filled_users && count($a_selected) + count($a_available) < $this->number_assignments)
		{
			//log_line('number assignments not reachable (start)');
			return array();
		}
        
        $preferred_selection = array();

		// try all items as next one
		foreach ($a_available as $item_id => $item)
		{
			$selected = $a_selected;
			$available = $a_available;
			$catlimits = $a_catlimits;

            // log_line("available: ". implode(',', array_keys($available)));

            // add item to the selected ones
			$selected[$item_id] = $item;
			unset($available[$item_id]);
            
			//log_line("selected: ". implode(',', array_keys($selected)));
            //log_line("available: ". implode(',', array_keys($available)). " (after selection)");

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
            elseif (count($selected) > count($preferred_selection)) 
            {
                $preferred_selection = $selected;
            }

			// remove this item from the available list of this function
			// it will not be an option for the other items, too
			unset ($a_available[$item_id]);
		}

        
        if ($this->allow_low_filled_users) {
            //log_line('low filled users possible(after)');
            return $preferred_selection;
        }	
        else {
            //log_line('not enough assignments reached (after)');
            return array();
        }
    }

	/**
	 * Get the items with less than minimum assignments
	 * @return int[]
	 */
	protected function getLowFilledItemIds()
	{
		$ids = array();
		foreach ($this->items as $item)
		{
			if (!empty($item->sub_min)
                && !in_array($item->item_id, $this->blocked_item_ids)
                && $this->assign_counts_item[$item->item_id] > 0
                && $this->assign_counts_item[$item->item_id] < $item->sub_min)
			{
                $key = sprintf('#%08d %08d', $this->assign_counts_item[$item->item_id], $item->item_id);
				$ids[$key] = $item->item_id;
			}
		}
        ksort($ids);
        return array_values($ids);
	}


    /**
     * Remove any non-fixed user assignments from an item
     * @param int[] $a_item_ids
     */
    protected function removeUnfixedAssignments($a_item_ids)
    {
        foreach ($this->users as $user_id => $user) {
            foreach ($a_item_ids as $item_id) {
                if (isset($this->assignments[$user_id][$item_id]) 
                    && !isset($this->fixed_assignments[$user_id][$item_id])) {
                    $this->assign_counts_user[$user_id]--;
                    $this->assign_counts_item[$item_id]--;
                }
                unset($this->assignments[$user_id][$item_id]);
            }
        }
    }
}