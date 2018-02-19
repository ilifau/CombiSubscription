<?php

/**
 * Registered User
 */
class ilCoSubUser
{
	/** @var  integer */
	public $obj_id;

	/** @var  integer */
	public $user_id;

	/** @var  bool */
	public $is_fixed = false;


	/**
	 * Get user by id
	 * @param integer  $a_obj_id
	 * @param integer  $a_user_id
	 * @return ilCoSubUser | null
	 */
	public static function _getById($a_obj_id, $a_user_id)
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
	 * @param integer       	$a_obj_id
	 * @return array        user_id => ilCoSubUser
	 */
	public static function _getForObject($a_obj_id)
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
	 * @param integer       	$a_user_id
	 * @return array        obj_id => ilCoSubUser
	 */
	public static function _getForUser($a_user_id)
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
	 * @param integer $a_obj_id
	 * @param integer|null      $a_user_id (optional)
	 */
	public static function _deleteForObject($a_obj_id, $a_user_id = null)
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
	 * @param array  $data
	 */
	protected function fillData($data)
	{
		$this->obj_id = $data['obj_id'];
		$this->user_id = $data['user_id'];
		$this->is_fixed = (bool) $data['is_fixed'];
	}


	/**
	 * Save the user data
	 * @return  boolean     success
	 */
	public function save()
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
}