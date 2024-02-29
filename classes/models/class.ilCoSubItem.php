<?php

/**
 * Item of a combined subscription
 */
class ilCoSubItem
{
	public int $item_id;
	public int $obj_id;
	public ?int $cat_id = null;
	public int $target_ref_id = 0;
	public string $identifier = "";
	public string $title = "";
	public string $description = "";
	public int $sort_position;
	public ?int $sub_min = null;
	public ?int $sub_max = null;
	public bool $selectable = true;
    public ?string $import_id = null;
	/** ilCoSubSchedule[] */
	public ?array $schedules = null;
	/** cached info about the period */ 
	protected string $periodInfoCache;
	/** cached link to the object with this item */ 
	protected string $objectLinkCache;
	/** cached title of the object with this item */ 
	protected string $objectTitleCache;


	/**
	 * Get item by id
	 */
	public static function _getById(int $a_id): ?ilCoSubItem
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_items'
			.' WHERE item_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubItem;
			$obj->fillData($row);
			return $obj;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Delete an item by its id
	 */
	public static function _deleteById(int $a_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE item_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get items by parent object id
	 * return ilCoSubItem[]	indexed by item_id
	 */
	public static function _getForObject(int $a_obj_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_items'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' ORDER BY sort_position ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubItem;
			$obj->fillData($row);
			$objects[$obj->item_id] = $obj;
		}
		return $objects;
	}

	/**
	 * Delete all items for a parent object id
	 */
	public static function _deleteForObject(int $a_obj_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Check if two items have a period conflict
	 * int $buffer		needed free time between appointments in seconds
	 * int $tolerance	tolerated percentage of schedule time being in conflict with other item
	 * return bool
	 */
	public static function _haveConflict(self $item1, self $item2, int $buffer = 0, int $tolerance = 0): bool
	{
		$conflict_time = 0;
		$item1_time = $item1->getSumOfTimes();
		$item2_time = $item2->getSumOfTimes();

		// avoid division by zero when calculating relation
		if ($item1_time == 0 || $item2_time == 0)
		{
			// no times, no conflict
			return false;
		}

		foreach ($item1->getSchedules() as $schedule1)
		{
			foreach ($item2->getSchedules() as $schedule2)
			{
				$conflict_time += ilCoSubSchedule::_getConflictTime($schedule1, $schedule2, $buffer);
			}
		}

		// check if conflict share is in tolerance for both items
		if ((100 * $conflict_time / $item1_time) > $tolerance || (100 * $conflict_time / $item2_time) > $tolerance)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Clone the item for a new object
	 * int	$a_obj_id
	 * array	$a_cat_map (old_cat_id => new_cat_id)
	 * return self
	 */
	public function saveClone(int $a_obj_id, array $a_cat_map): self
	{
		$clone = clone $this;
		$clone->obj_id = $a_obj_id;
		$clone->item_id = null;
		if (!empty($this->cat_id)) {
			$clone->cat_id = $a_cat_map[$this->cat_id];
		}
		$clone->save();

		// clone the saved schedules
		foreach (ilCoSubSchedule::_getForObjectAndItem($this->obj_id, $this->item_id) as $schedule) {
			$schedule->saveClone($a_obj_id, array($this->item_id => $clone->item_id));
		}

		return $clone;
	}


	/**
	 * Fill the properties with data from an array
	 * array $data assoc data
	 */
	protected function fillData(array $data): void
	{
		$this->item_id = $data['item_id'];
		$this->obj_id = $data['obj_id'];
		$this->cat_id = $data['cat_id'];
		$this->target_ref_id = $data['target_ref_id'];
		$this->identifier = $data['identifier'];
		$this->title = $data['title'];
		$this->description = $data['description'];
		$this->sort_position = $data['sort_position'];
		$this->sub_min = $data['sub_min'];
		$this->sub_max = $data['sub_max'];
		$this->selectable = (bool) $data['selectable'];
        $this->import_id = $data['import_id'];
	}

	/**
	 * Save an item object
	 * return  boolean     success
	 */
	public function save(): bool
	{
		global $ilDB;

		if (empty($this->obj_id) || empty($this->title))
		{
			return false;
		}
		if (empty($this->item_id))
		{
			$this->item_id = $ilDB->nextId('rep_robj_xcos_items');
		}
		if (!isset($this->sort_position))
		{
			$query = "SELECT MAX(sort_position) pos FROM rep_robj_xcos_items WHERE obj_id= ". $ilDB->quote($this->obj_id,'integer');
			$res = $ilDB->query($query);
			$row = $ilDB->fetchAssoc($res);
			$this->sort_position = (int) $row['pos'] + 1;
		}
		$rows = $ilDB->replace('rep_robj_xcos_items',
			array(
				'item_id' => array('integer', $this->item_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'cat_id' => array('integer', $this->cat_id),
				'target_ref_id' => array('integer', $this->target_ref_id),
				'identifier' => array('text', $this->identifier),
				'title' => array('text', $this->title),
				'description' => array('text', $this->description),
				'sort_position' => array('integer', $this->sort_position),
				'sub_min' => array('integer', $this->sub_min),
				'sub_max' => array('integer', $this->sub_max),
				'selectable' => array('integer', $this->selectable),
                'import_id' => array('string', $this->import_id)
			)
		);
		return $rows > 0;
	}

    /**
     * Get the Campo course id of an item (FAU specific)
     */
    public function getCampoCourseId(): ?int
    {
        if (!empty($this->import_id) && ilCombiSubscriptionPlugin::getInstance()->hasFauService()) {
            $import_id = \FAU\Study\Data\ImportId::fromString($this->import_id);
            if (!empty($import_id->getCourseId())) {
                return $import_id->getCourseId();
            }
        }
        return null;
    }
    
    
	/**
	 * Get the schedules of the item
	 * return ilCoSubSchedule[]
	 */
	public function getSchedules(): array
	{
        // first try to get schedules from the campo course (these should have precedence)
		if (!isset($this->schedules) && !empty($course_id = $this->getCampoCourseId())) {
            $this->schedules = ilCoSubSchedule::_getForCampoCourse($course_id, $this->obj_id, $this->item_id);
		}

        // otherwise read the schedules that are individueally saved
        if (!isset($this->schedules))
        {
            $this->schedules = ilCoSubSchedule::_getForObjectAndItem($this->obj_id, $this->item_id);
        }
        
        return $this->schedules;
	}


	/**
	 * Delete the schedules of the item
	 */
	public function deleteSchedules(): void
	{
		foreach ($this->getSchedules() as $schedule)
		{
			$schedule->delete();
		}
	}


	/**
	 * Get info about a period
	 */
	public function getPeriodInfo(): string
	{
	    if (!isset($this->periodInfoCache)) {

            if (ilCombiSubscriptionPlugin::getInstance()->hasFauService() && !empty($course_id = $this->getCampoCourseId())) {
                global $DIC;
                $info = $DIC->fau()->study()->dates()->getPlannedDatesList($course_id, true);
            }
            else {
                $info = array();
                foreach($this->getSchedules() as $schedule)
                {
                    $info[] = $schedule->getPeriodInfo();
                }
            }

            $this->periodInfoCache =  implode(' | ', $info);
        }
	    return $this->periodInfoCache;
	}

	/**
     * Get the title of the object to which this item belongs
     */
	public function getObjectTitle(): string
    {
        if (!isset($this->objectTitleCache)) {
            $this->objectTitleCache = ilObject::_lookupTitle($this->obj_id);
        }
        return $this->objectTitleCache;
    }

    /**
     * Get the Link to the object to which this item belongs
     */
    public function getObjectLink(): string
    {
        if (!isset($this->objectLinkCache)) {
            foreach (ilObject::_getAllReferences($this->obj_id) as $ref_id) {
                if (!ilObject::_isInTrash($ref_id)) {
                   $this->objectLinkCache = ilLink::_getStaticLink($ref_id, 'xcos');
                    break;
                }
            }
        }
        return $this->objectLinkCache;
    }

	/**
	 * Get the sum of times of this item
	 * return int	sum in seconds
	 */
	public function getSumOfTimes(): int
	{
		$sum = 0;
		foreach ($this->getSchedules() as $schedule)
		{
			$sum += $schedule->getSumOfTimes();
		}
		return $sum;
	}
}