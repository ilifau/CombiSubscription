<?php

require_once('Customizing/global/plugins/Services/Repository/RepositoryObject/CombiSubscription/classes/models/class.ilCoSubSchedule.php');

/**
 * Item of a combined subscription
 */
class ilCoSubItem
{
	/** @var  integer */
	public $item_id;

	/** @var  integer */
	public $obj_id;

	/** @var  integer */
	public $cat_id;

	/** @var  integer */
	public $target_ref_id;

	/** @var  string */
	public $identifier;

	/** @var  string */
	public $title;

	/** @var  string */
	public $description;

	/** @var  integer */
	public $sort_position;

	/** @var  integer|null */
	public $sub_min;

	/** @var  integer|null */
	public $sub_max;

	/** @var  bool */
	public $selectable = true;

	/** @var  ilCoSubSchedule[] */
	public $schedules = null;

	/**
	 * Get item by id
	 * @param integer  $a_id
	 * @return ilCoSubItem or null if not exists
	 */
	public static function _getById($a_id)
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
	 * @param integer $a_id
	 */
	public static function _deleteById($a_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE item_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get items by parent object id
	 * @param integer   object id
	 * @return ilCoSubItem[]	indexed by item_id
	 */
	public static function _getForObject($a_obj_id)
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
	 * @param integer object id
	 */
	public static function _deleteForObject($a_obj_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_items WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Check if two items have a period conflict
	 * @param self $item1
	 * @param self $item2
	 * @param int $buffer
	 * @return bool
	 */
	public static function _haveConflict($item1, $item2, $buffer = 3600)
	{
		// no conflict if period is not fully defined
		if (empty($item1->period_start) || empty($item1->period_end) || empty($item2->period_start) || empty($item2->period_end))
		{
			return false;
		}

		// check if start of one item is in the period of the other item
		if (($item1->period_start >= $item2->period_start && $item1->period_start < $item2->period_end + $buffer) ||
			($item2->period_start >= $item1->period_start && $item2->period_start < $item1->period_end + $buffer))
		{
			return true;
		}
		return false;
	}

	/**
	 * Clone the item for a new object
	 * @param int	$a_obj_id
	 * @param array	$a_cat_map (old_cat_id => new_cat_id)
	 * @return self
	 */
	public function saveClone($a_obj_id, $a_cat_map)
	{
		$clone = clone $this;
		$clone->obj_id = $a_obj_id;
		$clone->item_id = null;
		if (!empty($this->cat_id)) {
			$clone->cat_id = $a_cat_map[$this->cat_id];
		}
		$clone->save();
		return $clone;
	}


	/**
	 * Fill the properties with data from an array
	 * @param array $data assoc data
	 */
	protected function fillData($data)
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
	}

	/**
	 * Save an item object
	 * @return  boolean     success
	 */
	public function save()
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
				'selectable' => array('integer', $this->selectable)
			)
		);
		return $rows > 0;
	}

	/**
	 * Get the schedules of the item
	 * @return ilCoSubSchedule[]
	 */
	public function getSchedules()
	{
		if (!isset($this->schedules))
		{
			$this->schedules = ilCoSubSchedule::_getForObject($this->obj_id, $this->item_id);
		}

		return $this->schedules;
	}


	/**
	 * Delete the schedules of the item
	 */
	public function deleteSchedules()
	{
		foreach ($this->getSchedules() as $schedule)
		{
			$schedule->delete();
		}
	}


	/**
	 * Get info about a period
	 * @return string
	 */
	public function getPeriodInfo()
	{
		$info = array();
		foreach($this->getSchedules() as $schedule)
		{
			$info[] = $schedule->getPeriodInfo();
		}
		return implode('; ', $info);
	}
}