<?php

/**
 * Choice of a user
 */
class ilCoSubChoice
{
	public int $choice_id;
	public int $obj_id;
	public int $user_id;
	public int $item_id;
	public int $priority;
    public ?int $module_id = null;


	/**
	 * Delete a choice by its id
	 */
	public static function _deleteById(int $a_id): void
	{
		global $ilDB;
		$ilDB->manipulate('DELETE FROM rep_robj_xcos_choices WHERE choice_id = ' . $ilDB->quote($a_id,'integer'));
	}

	/**
	 * Delete all choices for a parent object id
	 */
	public static function _deleteForObject(int $a_obj_id, ?int $a_user_id = null)
	{
		global $ilDB;

		$query = 'DELETE FROM rep_robj_xcos_choices'.
			' WHERE obj_id = ' . $ilDB->quote($a_obj_id,'integer');

		if (isset($a_user_id))
		{
			$query .= ' AND user_id = ' . $ilDB->quote($a_user_id, 'integer');
		}

		$ilDB->manipulate($query);
	}


	/**
	 * Get all choices for an object and user as an indexed array
	 * return array       item_id => choice_id
	 */
	public static function _getIdsByItem(int $a_obj_id, int $a_user_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_choices'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' AND user_id = ' . $ilDB->quote($a_user_id,'integer');

		$choices = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$choices[$row['item_id']] = $row['choice_id'];
		}
		return $choices;
	}

	/**
	 * Get the user priorities for an object as an indexed array
	 * return array        user_id => item_id => priority
	 */
	public static function _getPriorities(int $a_obj_id, ?int $a_user_id = null): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_choices'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');

		if (isset($a_user_id))
		{
			$query .= ' AND user_id = ' . $ilDB->quote($a_user_id, 'integer');
		}

		$priorities = array();
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$priorities[$row['user_id']][$row['item_id']] = $row['priority'];
		}
		return $priorities;
	}


	/**
	 * Get the count of priorities for each item
	 * return array    item_id => priority => count
	 */
	public static function _getPriorityCounts(int $a_obj_id): array
	{
		global $ilDB;

		$query = 'SELECT item_id, priority, COUNT(choice_id) choices FROM rep_robj_xcos_choices'.
			' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer').
			' GROUP BY item_id, priority';
		$res = $ilDB->query($query);

		$counts = array();
		while ($row = $ilDB->fetchAssoc($res))
		{
			$counts[$row['item_id']][$row['priority']] = $row['choices'];
		}
		return $counts;
	}


    /**
     * Get all choices for an object as an indexed array
     * return array        choice_id => ilCoSubChoice
     */
    public static function _getForObject(int $a_obj_id): array
    {
        global $ilDB;

        $query = 'SELECT * FROM rep_robj_xcos_choices'
            .' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');
        $result = $ilDB->query($query);

        $choices = array();
        while ($row = $ilDB->fetchAssoc($result))
        {
            $obj = new ilCoSubChoice;
            $obj->fillData($row);
            $choices[$obj->choice_id] = $obj;
        }
        return $choices;
    }

    /**
     * Get the selected module id for a user and items
     * int[] $a_item_ids
     */
    public static function _getModuleId(int $a_obj_id, int $a_user_id, array $a_item_ids): ?int {
        global $ilDB;

        $query = 'SELECT module_id FROM rep_robj_xcos_choices'
            .' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
            .' AND user_id = '. $ilDB->quote($a_user_id,'integer')
            .' AND ' . $ilDB->in('item_id', $a_item_ids,false, 'integer');
        $result = $ilDB->query($query);

        while ($row = $ilDB->fetchAssoc($result)) {
            if (!empty($row['module_id'])) {
                return $row['module_id'];
            }
        }
        return null;
    }


    /**
	 * Fill the properties with data from an array
	 * $data array assoc data
	 */
	protected function fillData(array $data): void
	{
		$this->choice_id = $data['choice_id'];
		$this->obj_id = $data['obj_id'];
		$this->user_id = $data['user_id'];
		$this->item_id = $data['item_id'];
		$this->priority = $data['priority'];
        $this->module_id = $data['module_id'];
	}

	/**
	 * Save a choice object
	 * return  boolean     success
	 */
	public function save(): bool
	{
		global $ilDB;

		if (empty($this->choice_id))
		{
			$this->choice_id = $ilDB->nextId('rep_robj_xcos_choices');
		}
        try {
            $rows = $ilDB->replace('rep_robj_xcos_choices',
                array(
                    'choice_id' => array('integer', $this->choice_id)
                ),
                array(
                    'obj_id' => array('integer', $this->obj_id),
                    'user_id' => array('integer', $this->user_id),
                    'item_id' => array('integer', $this->item_id),
                    'priority' => array('integer', $this->priority),
                    'module_id' => array('integer', $this->module_id),
                )
            );
        }
        catch (Exception $e) {
            return 0;
        }

		return $rows > 0;
	}

    /**
     * Clone the choice for a new object
     * array $a_item_map (old_item_id => new_item_id)
     */
    public function saveClone(int $a_obj_id, array $a_item_map): self
    {
        $clone = clone $this;
        $clone->obj_id = $a_obj_id;
        $clone->choice_id = null;
        if (!empty($this->item_id)) {
            $clone->item_id = $a_item_map[$this->item_id];
        }
        $clone->save();

        return $clone;
    }

}