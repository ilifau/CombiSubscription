<?php

/**
 * Schedule of a combined subscription
 */
class ilCoSubSchedule
{

	/** @var  integer */
	public $schedule_id;

	/** @var  integer */
	public $obj_id;

	/** @var  integer */
	public $item_id;

	/** @var  integer|null */
	public $period_start;

	/** @var  integer|null */
	public $period_end;

	/** @var array  weekday => [slot => timespan], e.g. 'mo' => [ 0 => '08:00-10:00' ] */
	public $slots = array();


	/**
	 * Get schedule by id
	 * @param integer  $a_id
	 * @return ilCoSubSchedule|null
	 */
	public static function _getById($a_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_scheds'
			.' WHERE schedule_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubSchedule;
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
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_scheds WHERE schedule_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get schedules by parent object id
	 * @param integer   $a_obj_id
	 * @param integer   $a_item_id
	 * @return ilCoSubSchedule[][]	indexed by item_id, schedule_id
	 */
	public static function _getForObject($a_obj_id, $a_item_id = null)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_schedules'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');
		if (isset($a_item_id))
		{
			$query .= ' AND item_id = '. $ilDB->quote($a_item_id,'integer');
		}
		$query 	.= ' ORDER BY sort_position ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubSchedule;
			$obj->fillData($row);
			$objects[$obj->item_id][$obj->schedule_id] = $obj;
		}
		return $objects;
	}

	/**
	 * Delete all schedules for a parent object id
	 * @param integer object id
	 */
	public static function _deleteForObject($a_obj_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_scheds WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Clone the schedule for a new object
	 * @param int	$a_obj_id
	 * @param array	$a_item_map (old_item_id => new_item_id)
	 * @return self
	 */
	public function saveClone($a_obj_id,$a_item_map)
	{
		$clone = clone $this;
		$clone->obj_id = $a_obj_id;
		$clone->schedule_id = null;
		$clone->item_id = $a_item_map[$this->item_id];
		$clone->save();
		return $clone;
	}


	/**
	 * Fill the properties with data from an array
	 * @param array $data assoc data
	 */
	protected function fillData($data)
	{
		$this->schedule_id = $data['schedule_id'];
		$this->obj_id = $data['obj_id'];
		$this->item_id = $data['item_id'];
		$this->period_start = $data['period_start'];
		$this->period_end = $data['period_end'];
		$slots = @unserialize($data['slots']);
		if (is_array($slots)) {
			$this->slots = $slots;
		}
	}

	/**
	 * Save an item object
	 * @return  boolean     success
	 */
	public function save()
	{
		global $ilDB;

		if (empty($this->obj_id) || empty($this->item_id))
		{
			return false;
		}
		if (empty($this->schedule_id))
		{
			$this->schedule_id = $ilDB->nextId('rep_robj_xcos_scheds');
		}
		$rows = $ilDB->replace('rep_robj_xcos_scheds',
			array(
				'schedule_id' => array('integer', $this->schedule_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'item_id' => array('integer', $this->item_id),
				'period_start' => array('integer', $this->period_start),
				'period_end' => array('integer', $this->period_end),
				'slots' => array('text', serialize($this->slots))

			)
		);
		return $rows > 0;
	}

	/**
	 * Get info about a period
	 * @return string
	 */
	public function getPeriodInfo()
	{
		require_once('Services/Calendar/classes/class.ilDatePresentation.php');

		if (empty($this->period_start) || empty($this->period_end))
		{
			return '';
		}

		$start = new ilDateTime($this->period_start, IL_CAL_UNIX);
		$end = new ilDateTime($this->period_end, IL_CAL_UNIX);

		return ilDatePresentation::formatPeriod($start, $end);
	}


	/**
	 * Get the slots for ilScheduleInputGUI
	 * @see ilScheduleInputGUI
	 */
	public function getSlotsForInput()
	{
		return $this->slots;
	}

	/**
	 * Set the slots from ilScheduleInputGUI
	 * @param $a_slots
	 * @see ilScheduleInputGUI
	 */
	public function setSlotsFromInput($a_slots)
	{
		$this->slots = $a_slots;
	}
}