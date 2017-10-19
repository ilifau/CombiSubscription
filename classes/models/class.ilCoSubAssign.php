<?php

/**
 * Assignment of a user to an item
 * Note: an empty run_id (database: null, property: 0) stands for the chosen assignment
 */
class ilCoSubAssign
{
	/** @var  integer */
	public $assign_id;

	/** @var  integer */
	public $obj_id;

	/** @var  integer */
	public $run_id;

	/** @var  integer */
	public $user_id;

	/** @var  integer */
	public $item_id;


	/**
	 * Get assignment by id
	 * @param integer  assign id
	 * @return ilCoSubAssign | null
	 */
	public static function _getById($a_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_ass'
			.' WHERE assign_id = '. $ilDB->quote($a_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubAssign;
			$obj->fillData($row);
			return $obj;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Delete an assignment by its id
	 * @param integer assign_id
	 */
	public static function _deleteById($a_id)
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_ass WHERE assign_id = ' . $ilDB->quote($a_id,'integer'));
	}


	/**
	 * Get all assignments for an object as an indexed array
	 * @param integer       object id
	 * @return array        run_id => user_id => item_id => assign_id (run_id = 0 for the chosen assignments)
	 */
	public static function _getForObjectAsArray($a_obj_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_ass'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');

		$assignments = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$run_id = isset($row['run_id']) ? $row['run_id'] : 0;
			$assignments[$run_id][$row['user_id']][$row['item_id']] = $row['assign_id'];
		}
		return $assignments;
	}


	/**
	 * Delete all assignments for a parent object id
	 * @param integer object id
	 * @param integer|null      run id (optional, 0 is for the selected assignments)
	 * @param integer[]|null	list of user_ids for which the assignments shoud be kept
	 */
	public static function _deleteForObject($a_obj_id, $a_run_id = null, $a_keep_user_ids = array())
	{
		global $ilDB;

		$query = 'DELETE FROM rep_robj_xcos_ass'.
			' WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer');

		if (isset($a_run_id))
		{
			if ($a_run_id == 0)
			{
				$query .= ' AND run_id IS NULL';
			}
			else
			{
				$query .= ' AND run_id = ' . $ilDB->quote($a_run_id, 'integer');
			}
		}

		if (!empty($a_keep_user_ids))
		{
			//negated
			$query .= ' AND '. $ilDB->in('user_id', $a_keep_user_ids, true, 'integer');
		}
		$ilDB->manipulate($query);
	}


	/**
	 * Delete all assignments for a parent object id
	 * @param integer object id
	 * @param integer user id
	 */
	public static function _deleteByObjectAndUser($a_obj_id, $a_user_id)
	{
		global $ilDB;

		$query = 'DELETE FROM rep_robj_xcos_ass'.
			' WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer').
			' AND user_id = ' . $ilDB->quote($a_user_id,'integer');
		$ilDB->manipulate($query);
	}

	/**
	 * Fill the properties with data from an array
	 * @param array assoc data
	 */
	protected function fillData($data)
	{
		$this->assign_id = $data['assign_id'];
		$this->obj_id = $data['obj_id'];
		$this->run_id = empty($data['run_id']) ? 0 : $data['run_id'];
		$this->user_id = $data['user_id'];
		$this->item_id = $data['item_id'];
	}

	/**
	 * Save a choice object
	 * @return  boolean     success
	 */
	public function save()
	{
		global $ilDB;

		if (empty($this->assign_id))
		{
			$this->assign_id = $ilDB->nextId('rep_robj_xcos_ass');
		}
		$rows = $ilDB->replace('rep_robj_xcos_ass',
			array(
				'assign_id' => array('integer', $this->assign_id)
			),
			array(
				'run_id' => array('integer', empty($this->run_id) ? null : $this->run_id),
				'obj_id' => array('integer', $this->obj_id),
				'user_id' => array('integer', $this->user_id),
				'item_id' => array('integer', $this->item_id),
			)
		);
		return $rows > 0;
	}
}