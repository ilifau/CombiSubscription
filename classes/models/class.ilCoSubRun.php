<?php

/**
 * Meta data of a calculation run
 */
class ilCoSubRun
{
	/** @var  integer */
	public $run_id;

	/** @var  integer */
	public $obj_id;

	/** @var  ilDateTime */
	public $run_start;

	/** @var  ilDateTime */
	public $run_end;

	/** @var  string */
	public $method;

	/** @var  string */
	public $details;


	/**
	 * Get item by id
	 * @param integer  item id
	 * @return ilCoSubRun or null if not exists
	 */
	public static function _getById($a_id)
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
	 * @param integer item id
	 */
	public static function _deleteById($a_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_runs WHERE run_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Get items by parent object id
	 * @param integer   object id
	 * @return ilCoSubRun[]
	 */
	public static function _getForObject($a_obj_id)
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
	 * @param integer object id
	 */
	public static function _deleteForObject($a_obj_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_runs WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer'));
	}

	/**
	 * Fill the properties with data from an array
	 * @param array assoc data
	 */
	protected function fillData($data)
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
	 * @return  boolean     success
	 */
	public function save()
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
				'run_end' => array('timestamp', $this->run_end ? $this->run_end->get(IL_CAL_DATETIME) : null),
				'method' => array('text', $this->method),
				'details' => array('text', $this->details),
			)
		);
		return $rows > 0;
	}
}