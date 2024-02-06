<?php

/**
 * Schedule of a combined subscription
 */
class ilCoSubSchedule
{
	const MAX_TIMES = 200;

	public int $schedule_id;
	public int $obj_id;
	public int $item_id;
	public ?int $period_start;
	public ?int $period_end;
	/** array  '10:00-12:00' => ['mo', 'tu', ...] */ 
	public array $slots = [];

	/**
	 * Calculated times from the period and slots
	 * 200 times can be stored per schedule
	 * @var array  [[(int) start, (int) end], ... ]
	 */
	protected array $times = [];

	/**
	 * Get schedule by id
	 */
	public static function _getById(int $a_id): ?ilCoSubSchedule
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
	 */
	public static function _deleteById(int $a_id): void
	{
        // don't delete an unsaved schedule from campo
        if ($a_id < 0) {
            return;
        }
        
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_scheds WHERE schedule_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get schedules by parent object id and item id
     * This gets the schedules thar are directny
     * 
	 * int   $a_obj_id   object id of the combined subscription
	 * int   $a_item_id  item id if the assignment item
	 * return ilCoSubSchedule[]	indexed by schedule_id
	 */
	public static function _getForObjectAndItem(int $a_obj_id, int $a_item_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_scheds'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
            .' AND item_id = '. $ilDB->quote($a_item_id,'integer');

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
     * Get the schedules for a campo course
     * They are derived from the course's individual dates and are not separately saved
     * Their ids are the negative ids of their individual date to distinct them from the saved schedules of an item
     *
     * int   $a_course_id    id of the campo course
     * int   $a_obj_id       object id of the combined subscription
     * int   $a_item_id      item id if the assignment item
     * return ilCoSubSchedule[]	indexed by schedule_id
     */
    public static function _getForCampoCourse(int $a_course_id, int $a_obj_id, int $a_item_id): array 
    {
        global $DIC;
        
        $schedules = [];
        foreach ($DIC->fau()->study()->repo()->getIndividualDatesOfCourse((int) $a_course_id) as $date) {
            if ($date->getCancelled()) {
                continue;
            }
            
            $schedule = new ilCoSubSchedule();
            $schedule->schedule_id = - $date->getIndividualDatesId(); // negative ID to distinct from the saved schedules
            $schedule->obj_id = (int) $a_obj_id;
            $schedule->item_id = (int) $a_item_id;
            $schedule->period_start = $DIC->fau()->tools()->convert()->dbTimestampToUnix($date->getDate() . ' ' . $date->getStarttime());
            $schedule->period_end = $DIC->fau()->tools()->convert()->dbTimestampToUnix($date->getDate() . ' ' . $date->getEndtime());
            $schedule->slots = [];
            $schedule->calculateTimes(); // just takes period start and end because slots are empty

            $schedules[$schedule->schedule_id] = $schedule;
        }
        return $schedules;
    }

	/**
	 * Delete all schedules for a parent object id
	 */
	public static function _deleteForObject(int $a_obj_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_scheds WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Clone the schedule for a new object
	 * array	$a_item_map (old_item_id => new_item_id)
	 */
	public function saveClone(int $a_obj_id, array $a_item_map): self
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
     * array $data assoc data
	 */
	protected function fillData(array $data): void
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
		$this->times = self::_timesFromString($data['times']);
	}

	/**
	 * Save a schedule object
	 * return  boolean     success
	 */
	public function save(): bool
	{
		global $ilDB;

		if (empty($this->obj_id) || empty($this->item_id))
		{
			return false;
		}
        // don't save a virtual schedule from campo
        if ($this->schedule_id < 0) {
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
				'times' => array('text', self::_timesToString($this->times))
			)
		);
		return $rows > 0;
	}

	/**
	 * Delete a schedule object
	 */
	public function delete(): void
	{
		self::_deleteById($this->schedule_id);
	}

	/**
	 * Get info about a period
	 */
	public function getPeriodInfo(): string
	{
		global $lng;

		// no schedule
		if (empty($this->period_start) || empty($this->period_end))
		{
			return '';
		}

		// for debugging: show all single times
		//return $this->getTimesInfo();

		// single schedule
		if (empty($this->slots))
		{
			$start = new ilDateTime($this->period_start, IL_CAL_UNIX);
			$end = new ilDateTime($this->period_end, IL_CAL_UNIX);
			return ilDatePresentation::formatPeriod($start, $end);
		}

		// multiple schedule
		$defZone = ilTimeZone::_getInstance(ilTimeZone::_getDefaultTimeZone());

		$start = self::_dayDate($this->period_start, $defZone);
		$end =  self::_dayDate($this->period_end, $defZone);
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
	public function getTimesInfo(): string
	{

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
	 * Get the number of calculated times
	 */
	public function getTimesCount(): int
	{
		return count($this->times);
	}

	/**
	 * Get the sum of times of this schedule
	 * return int	sum in seconds
	 */
	public function getSumOfTimes(): int
	{
		$sum = 0;
		foreach ($this->times as $time)
		{
			$sum += $time[1] - $time[0];
		}
		return $sum;
	}

	/**
	 * Get the slots for ilScheduleInputGUI
	 * @see ilScheduleInputGUI
	 */
	public function getSlotsForInput(): array
	{
		return $this->slots;
	}

	/**
	 * Set the slots from ilScheduleInputGUI
	 * @see ilScheduleInputGUI
	 */
	public function setSlotsFromInput(array $a_slots): void
	{
		$this->slots = $a_slots;
	}


	/**
	 * Calculate the actual times from the defined slots
	 */
	protected function calculateTimes(): void
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
		$start = self::_dayStart($this->period_start, $defZone->getIdentifier());
		$end = self::_dayStart($this->period_end, $defZone->getIdentifier());

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
	 * return array ['start' => day_seconds, 'end' => day_seconds, 'wdays => [weekday_number, weekday_number, ...], ...]
	 */
	protected function translateSlots(): array
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
	 * string $tz timezone identifier
	 * return ilDateTime
	 */
	public static function _dayStart(int $timestamp, string $tz = ''): ilDateTime
	{
		$orig = new ilDateTime($timestamp, IL_CAL_UNIX);
		return new ilDateTime($orig->get(IL_CAL_DATE, $tz). ' 00:00:00', IL_CAL_DATETIME, $tz);
	}


	/**
	 * Get a date object that is able to be displayed without for period format
	 * The date without time (in given timezone) is returned to a date object (in UTC)
	 *
	 * @param string $tz timezone identifier
	 * @return ilDate
	 */
	public static function _dayDate(int $timestamp, string $tz = ''): ilDate
	{
		$orig = new ilDateTime($timestamp, IL_CAL_UNIX);
		return new ilDate($orig->get(IL_CAL_DATE, $tz), IL_CAL_DATE);
	}

	/**
	 * Get the sum of conflicting time between two schedules
	 * int $buffer		needed free time between appointments in seconds
	 * int				conflicting time in seconds
	 */
	public static function _getConflictTime(self $schedule1, self $schedule2, int $buffer = 0): int
	{
		$conflict = 0;

		foreach ($schedule1->times as $time1)
		{
			foreach ($schedule2->times as $time2)
			{
				// start of time2 is in period of time1 plus buffer
				if ($time2[0] >= $time1[0] && $time2[0] < $time1[1] + $buffer)
				{
					// add overlapping time, assuming end of time1 is later by buffer
					$conflict += $time1[1] + $buffer - $time2[0];
				}
				// start of time1 is in period of time2 plus buffer
				elseif ($time1[0] >= $time2[0] && $time1[0] < $time2[1] + $buffer)
				{
					// add overlapping time, assuming end of time2 is later by buffer
					$conflict += $time2[1] + $buffer - $time1[0];
				}
			}
		}

		return $conflict;
	}

	/**
	 * Get a string representation of times
	 * (one timespan needs 20 characters)
	 */
	public static function _timesToString(array $times): string
	{
		$string = '';
		foreach ($times as $time)
		{
			$string .= substr(sprintf('%010d',$time[0]). sprintf('%010d',$time[1]), 0, 20);
		}

		return $string;
	}

	/**
	 * Get the times from a string representation
	 */
	public static function _timesFromString(string $string): array
	{
		if (empty($string)) {
			return array();
		}

		$times = array();
		foreach (str_split($string, 20) as $chunk)
		{
			$times[] = array(
				(int) substr($chunk, 0, 10),
				(int) substr($chunk, 10, 10)
			);
		}

		return $times;
	}
}