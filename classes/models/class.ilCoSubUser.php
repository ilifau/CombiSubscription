<?php

/**
 * Registered User
 */
class ilCoSubUser
{
	public int $obj_id;
	public int $user_id;
	public bool $is_fixed = false;


	/**
	 * Get user by id
	 */
public static function _getById(int $a_obj_id, int $a_user_id): ?ilCoSubUser
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_users'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer')
			.' AND user_id = '. $ilDB->quote($a_user_id,'integer');

		$res = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($res))
		{
			$obj = new ilCoSubUser;
			$obj->fillData($row);
			return $obj;
		}
		else
		{
			return null;
		}
	}


	/**
	 * Get all users for an object as an indexed array
	 * return array        user_id => ilCoSubUser
	 */
	public static function _getForObject(int $a_obj_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_users'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');

		$result = $ilDB->query($query);

		$users = array();
		while ($row = $ilDB->fetchAssoc($result))
		{
			$obj = new ilCoSubUser;
			$obj->fillData($row);
			$users[$obj->user_id] = $obj;
		}
		return $users;
	}

	/**
	 * Get all users for an user as an indexed array
	 * return ilCoSubUser[]    (indexed by obj_id)
	 */
	public static function _getForUser(int $a_user_id): array
	{
		global $ilDB;

		$query = 'SELECT * FROM rep_robj_xcos_users'
			.' WHERE user_id = '. $ilDB->quote($a_user_id,'integer');

		$result = $ilDB->query($query);

		$users = array();
		while ($row = $ilDB->fetchAssoc($result))
		{
			$obj = new ilCoSubUser;
			$obj->fillData($row);
			$users[$obj->obj_id] = $obj;
		}
		return $users;
	}


	/**
	 * Delete all users for an object
	 */
	public static function _deleteForObject(int $a_obj_id, ?int $a_user_id = null): void
	{
		global $ilDB;

		$query = 'DELETE FROM rep_robj_xcos_users'
			.' WHERE obj_id = '. $ilDB->quote($a_obj_id,'integer');

		if (isset($a_user_id))
		{
			$query .= ' AND user_id = ' . $ilDB->quote($a_user_id, 'integer');
		}

		$ilDB->manipulate($query);
	}


	/**
	 * Fill the properties with data from an array
	 */
	protected function fillData(array $data): void
	{
		$this->obj_id = $data['obj_id'];
		$this->user_id = $data['user_id'];
		$this->is_fixed = (bool) $data['is_fixed'];
	}


	/**
	 * Save the user data
	 * return  boolean     success
	 */
	public function save(): bool
	{
		global $ilDB;

		$rows = $ilDB->replace('rep_robj_xcos_users',
			array(
				'obj_id' => array('integer', $this->obj_id),
				'user_id' => array('integer', $this->user_id),
			),
			array(
				'is_fixed' => array('integer', $this->is_fixed),
			)
		);
		return $rows > 0;
	}


    /**
     * Clone the user for a new object
     */
    public function saveClone(int $a_obj_id): self
    {
        $clone = clone $this;
        $clone->obj_id = $a_obj_id;
        $clone->is_fixed = false;
        $clone->save();

        return $clone;
    }
}