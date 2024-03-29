<?php
/**
 * Conflict management for combined subscriptions
 * All checks and actions regarding item conflicts should go here
 */
class ilCombiSubscriptionConflicts
{
    /** @var  ilObjCombiSubscription */
    protected $object;

    /** @var ilCombiSubscriptionPlugin  */
    protected $plugin;

    /** @var array obj_id => item_id => item) */
    protected $itemCache = [];

	/** @var array obj_id => property_name => (mixed) property */
	protected $propertyCache = [];

    /** @var array local_item_id => other_item_id  => conflict (true|false|null) */
    protected $conflictCache = [];


    /**
     * Constructor
     * @param ilObjCombiSubscription        $a_object
     * @param ilCombiSubscriptionPlugin     $a_plugin
     */
    public function __construct($a_object, $a_plugin)
    {
        $this->object = $a_object;
        $this->plugin = $a_plugin;

        $this->plugin->includeClass('models/class.ilCoSubAssign.php');
        $this->plugin->includeClass('models/class.ilCoSubChoice.php');

        // init the item cache with the local items
        $this->itemCache[$this->object->getId()] = $this->object->getItems();
    }

    /**
     * Get conflicting items from other combi subscriptions
	 * Used to show conflict on the registration or assignment page
	 * This checks only the assigned items of other subscriptions
	 * The parameter $a_only_assigned determines which items of the local subscription are checked
     *
     * @param   array   $a_user_ids list of user_ids to treat (or empty for all object users)
     * @param   bool    $a_only_assigned check only the assigned items of current user and subscription
     * @param   bool    $a_only_assigned check only colficts with external assignments
     * @return array 	conflicts  user_id => local_item_id => other_item_id => item
     */
    public function getConflicts($a_user_ids = array(), $a_only_assigned = false, $a_only_external = false)
    {
        $conflicts = [];

        // loop over users in this object
        foreach (array_keys($this->object->getUsers($a_user_ids)) as $user_id)
        {
			$localItems = $this->getScheduledItemsOfUser($user_id, $a_only_assigned);
			if (empty($localItems))
			{
				continue;
			}

			// participation of user in other subscriptions
            foreach (ilCoSubUser::_getForUser($user_id) as $subUser)
            {
                // same object can be ignored, of only external assignments should be checked
                if ($subUser->obj_id == $this->object->getId() && $a_only_external)
                {
                    continue;
                }

                // final assignments of user in other object
                $assign_ids = ilCoSubAssign::_getIdsByItemAndRun($subUser->obj_id, $subUser->user_id, 0);

                foreach ($this->getItems($subUser->obj_id) as $other_item_id => $otherItem)
                {
					// item without assignments or schedule can be ignored
                    if ((!isset($assign_ids[$other_item_id])) || empty($otherItem->getSchedules())) {
                        continue;
                    }

                    foreach ($localItems as $local_item_id => $localItem)
                    {
                        // no conflict with the same item
                        if ($local_item_id == $other_item_id) {
                            continue;
                        }

                        if ($this->haveConflict($localItem, $otherItem)) {
                            $conflicts[$user_id][$local_item_id][$other_item_id] = $otherItem;
                        }
                    }
                }
            }
        }

        return $conflicts;
    }


	/**
	 * Remove conflicting choices and assignments from other combi subscription
	 * It will remove all which are conflicting with assigned local items
	 * Called when the user is notified or when the assignments are transferred
	 *
	 * @param   array   $a_user_ids list of specific user_ids to treat
	 * @return array 	removed conflicts  user_id => obj_id => item
	 */
	public function removeConflicts($a_user_ids = array())
	{
		/** @var array user_id => obj_id => item  */
		$removed= [];

		// loop over users in this object
		foreach (array_keys($this->object->getUsers($a_user_ids)) as $user_id)
		{
			$localItems = $this->getScheduledItemsOfUser($user_id, true);
			if (empty($localItems))
			{
				continue;
			}

			// participation of user in other subscriptions
			foreach (ilCoSubUser::_getForUser($user_id) as $subUser)
			{
				// same object can be ignored
				// other object with fixed assignment must be ignored
				if ($subUser->obj_id == $this->object->getId() || $subUser->is_fixed)
				{
					continue;
				}

				// choices and stored/final assignments of user in other object
				$choice_ids = ilCoSubChoice::_getIdsByItem($subUser->obj_id, $subUser->user_id);
				$assign_ids = ilCoSubAssign::_getIdsByItemAndRun($subUser->obj_id, $subUser->user_id);

				foreach ($this->getItems($subUser->obj_id) as $other_item_id => $otherItem)
				{
					// item without assignments, choices or schedule can be ignored
					if ((!isset($choice_ids[$other_item_id]) && !isset($assign_ids[$other_item_id])) || empty($otherItem->getSchedules()))
					{
						continue;
					}

					foreach ($localItems as $local_item_id => $localItem)
					{
						if ($this->haveConflict($localItem, $otherItem))
						{
							if (!empty($choice_ids[$other_item_id]))
							{
								ilCoSubChoice::_deleteById($choice_ids[$other_item_id]);
							}

							if (!empty($assign_ids[$other_item_id]))
							{
								foreach ($assign_ids[$other_item_id] as $run => $assign_id)
								{
									ilCoSubAssign::_deleteById($assign_ids[$other_item_id]);
								}
							}

							$removed[$subUser->user_id][$subUser->obj_id][$other_item_id] = $otherItem;
						}
					}
				}
			}
		}

		return $removed;
	}


	/**
	 * get items of an object (cached)
	 * @param int $obj_id
	 * @return ilCosubItem[] (indexed by item_id)
	 */
	protected function getItems($obj_id)
	{
		if (!isset($this->itemCache[$obj_id]))
		{
			$this->itemCache[$obj_id] = ilCoSubItem::_getForObject($obj_id);
		}
		return $this->itemCache[$obj_id];
	}


	/**
	 * Get the local items with schedules that are relevant for a user
	 * @param int $user_id
	 * @param bool $only_assigned get only the assigned items
	 * @return ilCosubItem[] (indexed by item_id)
	 */
	protected function getScheduledItemsOfUser($user_id, $only_assigned = false)
	{
		$localItems = [];
		$assignments = $only_assigned ? $this->object->getAssignmentsOfUser($user_id) : [];
		foreach ($this->getItems($this->object->getId()) as $item_id => $item)
		{
			if ((!$only_assigned || isset($assignments[$item_id])) && !empty($item->getSchedules()))
			{
				$localItems[$item_id] = $item;
			}
		}

		return $localItems;
	}


	/**
	 * Check if two items have a conflict (cached)
	 * @param ilCoSubItem $item1
	 * @param ilCoSubItem $item2
	 * @return bool
	 */
	protected function haveConflict($item1, $item2)
	{
		if (!isset($this->conflictCache[$item1->item_id][$item2->item_id]))
		{
			$conflict = ilCoSubItem::_haveConflict($item1, $item2,
				min($this->getBuffer($item1->obj_id), $this->getBuffer($item2->obj_id)),
				min($this->getTolerance($item1->obj_id), $this->getTolerance($item2->obj_id)));

			$this->conflictCache[$item1->item_id][$item2->item_id] = $conflict;
			$this->conflictCache[$item2->item_id][$item1->item_id] = $conflict;
		}

		return $this->conflictCache[$item1->item_id][$item2->item_id];
	}


	/**
	 * Get the conflict buffer of an object (cached)
	 * @param $obj_id
	 * @return integer
	 */
	protected function getBuffer($obj_id)
	{
		return $this->getMethodProperty($obj_id, 'out_of_conflict_time', $this->plugin->getToleratedConflictPercentage());
	}

	/**
	 * Get the conflict tolerance of an object (cached)
	 * @param $obj_id
	 * @return integer
	 */
	protected function getTolerance($obj_id)
	{
		return $this->getMethodProperty($obj_id, 'tolerated_conflict_percentage', $this->plugin->getToleratedConflictPercentage());
	}

	/**
	 * Get a method property of an object (cached)
	 * @param int $obj_id
	 * @param string $prop_name
	 * @param mixed $default_value
	 * @return integer
	 */
	protected function getMethodProperty($obj_id, $prop_name, $default_value)
	{
		global $DIC;

		if (!isset($this->propertyCache[$obj_id][$prop_name]))
		{
			$sql = "
				SELECT p.value 
				FROM rep_robj_xcos_prop p INNER JOIN rep_robj_xcos_data d ON p.obj_id = d.obj_id AND p.class = d.method 
				WHERE p.property = " . $DIC->database()->quote($prop_name, 'text') . "
				AND d.obj_id = ". $DIC->database()->quote($obj_id, 'integer');

			$result = $DIC->database()->query($sql);
			$row = $DIC->database()->fetchAssoc($result);

			if (isset($row['value']))
			{
				$this->propertyCache[$obj_id][$prop_name] = $row['value'];
			}
			else
			{
				$this->propertyCache[$obj_id][$prop_name] = $default_value;
			}
		}

		return $this->propertyCache[$obj_id][$prop_name];
	}
}