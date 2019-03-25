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

    /** @var array local_item_id => other_item_id  => conflict (true|false|null) */
    protected $conflictCache = [];

    /** @var array obj_id => (int) buffer */
    protected $bufferCache = [];

    /** @var array obj_id => (int) tolerance */
	protected $toleranceCache = [];

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
     * @param   bool    $a_only_assigned check only the assigned items of current user ans subscription
     * @return array 	conflicts  user_id => local_item_id => other_item_id => item
     */
    public function getExternalConflicts($a_user_ids = array(), $a_only_assigned = false)
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
                // same object can be ignored
                if ($subUser->obj_id == $this->object->getId())
                {
                    continue;
                }

                // final assignments of user in other object
                $assign_ids = ilCoSubAssign::_getIdsByItemAndRun($subUser->obj_id, $subUser->user_id, 0);

                foreach ($this->getItems($subUser->obj_id) as $other_item_id => $otherItem)
                {
					// item without assignments or schedule can be ignored
                    if ((!isset($assign_ids[$other_item_id])) || empty($otherItem->getSchedules()))
                    {
                        continue;
                    }

                    foreach ($localItems as $local_item_id => $localItem)
                    {
                        if ($this->haveConflict($localItem, $otherItem))
                        {
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
		global $DIC;

		if (!isset($this->bufferCache[$obj_id]))
		{
			$sql = "
				SELECT p.value 
				FROM rep_robj_xcos_prop p INNER JOIN rep_robj_xcos_data d ON p.obj_id = d.obj_id AND p.class = d.method 
				WHERE p.property = 'out_of_conflict_time'
				AND d.obj_id = ". $DIC->database()->quote($obj_id, 'integer');

			$result = $DIC->database()->query($sql);
			$row = $DIC->database()->fetchAssoc($result);

			if (isset($row['value']))
			{
				$this->bufferCache[$obj_id] = $row['value'];
			}
			else
			{
				$this->bufferCache[$obj_id] = $this->plugin->getOutOfConflictTime();
			}
		}

		return $this->bufferCache[$obj_id];
	}

	/**
	 * Get the conflict tolerance of an object (cached)
	 * @param $obj_id
	 * @return integer
	 */
	protected function getTolerance($obj_id)
	{
		global $DIC;

		if (!isset($this->bufferCache[$obj_id]))
		{
			$sql = "
				SELECT p.value 
				FROM rep_robj_xcos_prop p INNER JOIN rep_robj_xcos_data d ON p.obj_id = d.obj_id AND p.class = d.method 
				WHERE p.property = 'tolerated_conflict_percentage'
				AND d.obj_id = ". $DIC->database()->quote($obj_id, 'integer');

			$result = $DIC->database()->query($sql);
			$row = $DIC->database()->fetchAssoc($result);

			if (isset($row['value']))
			{
				$this->toleranceCache[$obj_id] = $row['value'];
			}
			else
			{
				$this->toleranceCache[$obj_id] = $this->plugin->getToleratedConflictPercentage();
			}
		}

		return $this->toleranceCache[$obj_id];
	}
}