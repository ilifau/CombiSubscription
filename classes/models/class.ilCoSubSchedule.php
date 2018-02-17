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

	/** @var array  '10:00-12:00' => ['mo', 'tu', ...] */
	public $slots = array();

	/** @var array  [[(int) start, (int) end], ... ] */
	protected $times = array();

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
	 * @return ilCoSubSchedule[]	indexed by schedule_id
	 */
	public static function _getForObject($a_obj_id, $a_item_id = null)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_scheds'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');
		if (isset($a_item_id))
		{
			$query .= ' AND item_id = '. $ilDB->quote($a_item_id,'integer');
		}
		$query 	.= ' ORDER BY period_start ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubSchedule;
			$obj->fillData($row);
			$objects[$obj->schedule_id] = $obj;
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
		$times = @unserialize($data['times']);
		if (is_array($times)) {
			$this->times = $times;
		}
	}

	/**
	 * Save a schedule object
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

		$this->calculateTimes();

		$rows = $ilDB->replace('rep_robj_xcos_scheds',
			array(
				'schedule_id' => array('integer', $this->schedule_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'item_id' => array('integer', $this->item_id),
				'period_start' => array('integer', $this->period_start),
				'period_end' => array('integer', $this->period_end),
				'slots' => array('text', serialize($this->slots)),
				'times' => array('text', serialize($this->times))

			)
		);
		return $rows > 0;
	}

	/**
	 * Delete a schedule object
	 */
	public function delete()
	{
		self::_deleteById($this->schedule_id);
	}

	/**
	 * Get info about a period
	 * @return string
	 */
	public function getPeriodInfo()
	{
		global $lng;

		require_once('Services/Calendar/classes/class.ilDatePresentation.php');

		// no schedule
		if (empty($this->period_start) || empty($this->period_end))
		{
			return '';
		}

		// single schedule
		if (empty($this->slots))
		{
			$start = new ilDateTime($this->period_start, IL_CAL_UNIX);
			$end = new ilDateTime($this->period_end, IL_CAL_UNIX);
			return ilDatePresentation::formatPeriod($start, $end);
		}

		// for debugging: show all single times
		// return $this->getTimesInfo();

		// multiple schedule
		$defZone = ilTimeZone::_getInstance(ilTimeZone::_getDefaultTimeZone());

		$start = self::dayDate($this->period_start, $defZone);
		$end =  self::dayDate($this->period_end, $defZone);
		$period = ilDatePresentation::formatPeriod($start, $end);

		$slotinfo = array();
		foreach($this->slots as $timespan => $weekdays)
		{
			$days = array();
			foreach($weekdays as $day)
			{
				$days[] = $lng->txt('rep_robj_xcos_day_'.$day);
			}
			$slotinfo[] = implode(', ', $days) . ' ' . $timespan;
		}

		return $period . ' (' . implode('; ', $slotinfo) . ')';
	}

	/**
	 * Get a complete list of times calculated from the period and slots
	 */
	public function getTimesInfo()
	{
		require_once('Services/Calendar/classes/class.ilDatePresentation.php');

		$info = array();
		foreach ($this->times as $time)
		{
			$start = new ilDateTime($time[0], IL_CAL_UNIX);
			$end = new ilDateTime($time[1], IL_CAL_UNIX);
			$info[] =  ilDatePresentation::formatPeriod($start, $end);
		}
		return implode("; ", $info);
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
	 * @param array $a_slots
	 * @see ilScheduleInputGUI
	 */
	public function setSlotsFromInput($a_slots)
	{
		$this->slots = $a_slots;
	}


	/**
	 * Calculate the actual times from the defined slots
	 */
	protected function calculateTimes()
	{
		// single schedule
		if (empty($this->slots))
		{
			$this->times = array(array($this->period_start, $this->period_end));
			return;
		}


		// calculate times based on the default timezone
		$defZone = ilTimeZone::_getInstance(ilTimeZone::_getDefaultTimeZone());

		// ensure that start and end date have 00:00:00 in the default timezone
		$start = self::dayStart($this->period_start, $defZone->getIdentifier());
		$end = self::dayStart($this->period_end, $defZone->getIdentifier());

		// save the truncated start and end times
		$this->period_start = $start->get(IL_CAL_UNIX);
		$this->period_end = $end->get(IL_CAL_UNIX);

		$translated_slots = $this->translateSlots();

		$times = array();
		$day = $start;
		while(!ilDateTime::_after($day, $end))
		{
			// get the date info related to the default timezone
			$unix = $day->get(IL_CAL_UNIX);
			$defZone->switchTZ();
			$info = getdate($unix);
			$defZone->restoreTZ();

			foreach ($translated_slots as $trans)
			{
				if (in_array($info['wday'], $trans['wdays']))
				{
					// key for sorting the stored times by start and end
					$key = sprintf('%010d-%010d', $unix + $trans['start'],$unix + $trans['end']);
					$times[$key] = array($unix + $trans['start'], $unix + $trans['end']);
				}
			}
			$day->increment(IL_CAL_DAY, 1);
		}

		ksort($times);
		$this->times = array_values($times);
	}

	/**
	 * Translate the slots for date comparison
	 * Wekedays are numbered alike getdate() function
	 *
	 * @return array ['start' => day_seconds, 'end' => day_seconds, 'wdays => [weekday_number, weekday_number, ...], ...]
	 */
	protected function translateSlots()
	{
		$daymap = array('mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6, 'su' => 0);
		$trans = array();
		foreach ($this->slots as $timespan => $weekdays)
		{
			$start = substr($timespan,0,2) * 3600 + substr($timespan,3,2) * 60;
			$end = substr($timespan,6,2) * 3600 + substr($timespan,9,2) * 60;
			$days = array();
			foreach ($weekdays as $weekday) {
				$days[] = $daymap[$weekday];
			}
			$trans[] = array('start' => $start, 'end' => $end, 'wdays' => $days);
		}
		return $trans;
	}


	/**
	 * Get a date object fot the start of the day, related to a timezone
	 *
	 * @param int $timestamp
	 * @param string $tz timezone identifier
	 * @return ilDateTime
	 */
	public static function dayStart($timestamp, $tz = '')
	{
		$orig = new ilDateTime($timestamp, IL_CAL_UNIX);
		return new ilDateTime($orig->get(IL_CAL_DATE, $tz). ' 00:00:00', IL_CAL_DATETIME, $tz);
	}


	/**
	 * Get a date object that is able to be displayed without for period format
	 * The date without time (in given timezone) is returned to a date object (in UTC)
	 *
	 * @param int $timestamp
	 * @param string $tz timezone identifier
	 * @return ilDate
	 */
	public static function dayDate($timestamp, $tz = '')
	{
		$orig = new ilDateTime($timestamp, IL_CAL_UNIX);
		return new ilDate($orig->get(IL_CAL_DATE, $tz), IL_CAL_DATE);
	}
}