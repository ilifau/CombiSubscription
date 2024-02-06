<?php

/**
 * Assignment of a user to an item
 * Note: an empty run_id (database: null, property: 0) stands for the chosen assignment
 */
class ilCoSubAssign
{
	public int $assign_id;
	public int $obj_id;
	public int $run_id;
	public int $user_id;
	public int $item_id;


	/**
	 * Get assignment by id
	 * $a_id  assign id
	 */
	public static function _getById(int $a_id): ? ilCoSubAssign
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
	 */
	public static function _deleteById(int $a_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_ass WHERE assign_id = ' . $ilDB->quote($a_id,'integer'));
	}


	/**
	 * Get all assignments for an object as an indexed array
	 * $a_obj_id      object id
	 * return array        run_id => user_id => item_id => assign_id (run_id = 0 for the chosen assignments)
	 */
	public static function _getForObjectAsArray(int $a_obj_id): array
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
	 * Get all assignment ids for an object and user as an indexed array
	 * return array       item_id => run_id => assign_id (run_id = 0 for the chosen assignments)
	 */
	public static function _getIdsByItemAndRun(int $a_obj_id, int $a_user_id, ?int $a_run_id = null): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_ass'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' AND user_id = ' . $ilDB->quote($a_user_id,'integer');

		if (isset($a_run_id))
		{
		    if ($a_run_id == 0)
            {
                $query .= ' AND run_id IS NULL';
            }
            else
            {
                $query .= ' AND run_id = ' . $ilDB->quote($a_run_id,'integer');
            }
        }

		$assignments = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$run_id = isset($row['run_id']) ? $row['run_id'] : 0;
			$assignments[$row['item_id']][$run_id] = $row['assign_id'];
		}
		return $assignments;
	}


	/**
	 * Delete all assignments for a parent object id
	 * $a_obj_id object id
	 * $a_run_id     run id (optional, 0 is for the selected assignments)
	 * $a_keep_user_ids	list of user_ids for which the assignments shoud be kept
	 */
	public static function _deleteForObject(int $a_obj_id, ?int $a_run_id = null, ?array $a_keep_user_ids = []): void
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
	 */
	public static function _deleteByObjectAndUser(int $a_obj_id, int $a_user_id): void
	{
		global $ilDB;

		$query = 'DELETE FROM rep_robj_xcos_ass'.
			' WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer').
			' AND user_id = ' . $ilDB->quote($a_user_id,'integer');
		$ilDB->manipulate($query);
	}

	/**
	 * Fill the properties with data from an array
	 * $data array assoc data
	 */
	protected function fillData(array $data): void
	{
		$this->assign_id = $data['assign_id'];
		$this->obj_id = $data['obj_id'];
		$this->run_id = empty($data['run_id']) ? 0 : $data['run_id'];
		$this->user_id = $data['user_id'];
		$this->item_id = $data['item_id'];
	}

	/**
	 * Save a choice object
	 * return  boolean     success
	 */
	public function save(): bool
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