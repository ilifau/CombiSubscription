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
     * Get conflicting assigned items from other combi subscriptions
     *
     * @param   array   $a_user_ids list of user_ids to treat (or empty for all object users)
     * @param   bool    $a_only_assigned check only the assigned items of the users
     * @return array 	conflicts  user_id => local_item_id => other_item_id => item
     */
    public function getExternalConflicts($a_user_ids = array(), $a_only_assigned = false)
    {
        $conflicts = [];

        // loop over users in this object
        foreach (array_keys($this->object->getUsers($a_user_ids)) as $user_id)
        {
            //
            // Step 1: get the local items to check
            //
            $localItems = [];

			$assignments = $a_only_assigned ? $this->object->getAssignmentsOfUser($user_id) : [];
			/** @var ilCoSubItem $item */
			foreach ($this->itemCache[$this->object->getId()] as $item_id => $item)
			{
				if (!empty($item->getSchedules() && (!$a_only_assigned || isset($assignments[$item_id]))))
				{
					$localItems[$item_id] = $item;
				}
			}

            // nothing to compare for the user
            if (empty($localItems))
            {
                continue;
            }


            //
            // Step 2: loop over other combi subscriptions for the user
            //

            /** @var ilCoSubUser $subUser */
            foreach (ilCoSubUser::_getForUser($user_id) as $subUser)
            {
                // same object can be ignored
                if ($subUser->obj_id == $this->object->getId())
                {
                    continue;
                }

                $assign_ids = ilCoSubAssign::_getIdsByItemAndRun($subUser->obj_id, $subUser->user_id, 0);

                /** @var  ilCoSubItem $otherItem */
                foreach ($this->getItems($subUser->obj_id) as $other_item_id => $otherItem)
                {
                    // item can be ignored
                    if ((!isset($assign_ids[$other_item_id])) || empty($otherItem->getSchedules()))
                    {
                        continue;
                    }

                    /** @var  ilCoSubItem $localItem */
                    foreach ($localItems as $local_item_id => $localItem)
                    {
                        if (!isset($this->conflictCache[$local_item_id][$other_item_id]))
                        {
                            $this->conflictCache[$local_item_id][$other_item_id] = ilCoSubItem::_haveConflict($localItem, $otherItem,
								min($this->getBuffer($localItem->obj_id), $this->getBuffer($otherItem->obj_id)),
								min($this->getTolerance($localItem->obj_id), $this->getTolerance($otherItem->obj_id)));
                        }

                        if ($this->conflictCache[$local_item_id][$other_item_id])
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
	 * get items of an object (cached)
	 * @param int $obj_id
	 * @return array
	 */
    public function getItems($obj_id)
	{
		if (!isset($this->itemCache[$obj_id]))
		{
			$this->itemCache[$obj_id] = ilCoSubItem::_getForObject($obj_id);
		}

		return $this->itemCache[$obj_id];
	}

	/**
	 * Get the conflict buffer of an object
	 * @param $obj_id
	 * @return integer
	 */
	public function getBuffer($obj_id)
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
	 * Get the conflict tolerance of an object
	 * @param $obj_id
	 * @return integer
	 */
    public function getTolerance($obj_id)
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