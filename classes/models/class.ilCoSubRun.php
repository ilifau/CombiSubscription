<?php

/**
 * Meta data of a calculation run
 */
class ilCoSubRun
{
	public int $run_id;
	public int $obj_id;
	public ?ilDateTime $run_start;
	public ?ilDateTime $run_end;
	public string $method;
	public string $details = '';


	/**
	 * Get item by id
	 */
	public static function _getById(int $a_id): ?ilCoSubRun
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_runs'
			.' WHERE run_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubRun;
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
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_runs WHERE run_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get items by parent object id
	 * return ilCoSubRun[]
	 */
	public static function _getForObject(int $a_obj_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_runs'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' ORDER BY run_start ASC';

		$objects = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubRun;
			$obj->fillData($row);
			$objects[] = $obj;
		}
		return $objects;
	}

	/**
	 * Delete all items for a parent object id
	 */
	public static function _deleteForObject(int $a_obj_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_runs WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Fill the properties with data from an array
	 */
	protected function fillData(array $data): void
	{
		$this->run_id = $data['run_id'];
		$this->obj_id = $data['obj_id'];
		$this->run_start = $data['run_start'] ? new ilDateTime($data['run_start'],IL_CAL_DATETIME) : null;
		$this->run_end = $data['run_end'] ? new ilDateTime($data['run_end'],IL_CAL_DATETIME) : null;
		$this->method = $data['method'];
		$this->details = $data['details'];
	}

	/**
	 * Save an item object
	 * return  boolean     success
	 */
	public function save(): bool
	{
		global $ilDB;

		if (empty($this->obj_id))
		{
			return false;
		}
		if (empty($this->run_id))
		{
			$this->run_id = $ilDB->nextId('rep_robj_xcos_runs');
		}
		if (empty($this->run_start))
		{
			$this->run_start  = new ilDateTime(time(),IL_CAL_UNIX);
		}

		$rows = $ilDB->replace('rep_robj_xcos_runs',
			array(
				'run_id' => array('integer', $this->run_id)
			),
			array(
				'obj_id' => array('integer', $this->obj_id),
				'run_start' => array('timestamp', $this->run_start->get(IL_CAL_DATETIME)),
				'run_end' => array('timestamp', isset($this->run_end) && $this->run_end ? $this->run_end->get(IL_CAL_DATETIME) : null),
				'method' => array('text', $this->method),
				'details' => array('text', $this->details),
			)
		);
		return $rows > 0;
	}
}