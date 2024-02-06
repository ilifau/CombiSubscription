<?php

/**
* Access/Condition checking for combined subscription object
*
* Please do not create instances of large application classes (like ilObjExample)
* Write small methods within this class to determin the status.
*
* @author 	Fred Neumann <fred.neumann@fau.de>
* @version $Id$
*/
class ilObjCombiSubscriptionAccess extends ilObjectPluginAccess
{
	/** obj_id => row */
	static array $status_data = [];

	/**
	* Checks wether a user may invoke a command or not
	* (this method is called by ilAccessHandler::checkAccess)
	*
	* Please do not check any preconditions handled by
	* ilConditionHandler here. Also don't do usual RBAC checks.

	*/
	function _checkAccess(string $cmd, string $permission, int $ref_id, int $obj_id, ?int $user_id = null): bool
	{
		global $DIC;

		if ($user_id == null)
		{
			$user_id = $DIC->user()->getId();
		}

		switch ($permission)
		{
			case 'read':
				if (!self::checkOnline($obj_id) &&
					!$DIC->access()->checkAccessOfUser($user_id, 'write', '', $ref_id))
				{
					return false;
				}
				break;
		}

		return true;
	}
	
	/**
	* Check online status of example object
	*/
	static function checkOnline(int $a_obj_id): bool
	{
		$rec  = self::getStatusData($a_obj_id);
		return (boolean) $rec['is_online'];
	}

	/**
	 * Get the subscription start
	 */
	static function getSubscriptionStart(int $a_obj_id): ilDateTime
	{
		$rec  = self::getStatusData($a_obj_id);
		return new ilDateTime($rec['sub_start'],IL_CAL_DATETIME);
	}

	/**
	 * Get the subscription end
	 */
	static function getSubscriptionEnd(int $a_obj_id): ilDateTime
	{
		$rec  = self::getStatusData($a_obj_id);
		return new ilDateTime($rec['sub_end'],IL_CAL_DATETIME);
	}

	/**
	 * Get the status data
	 * @param   int         object id
	 * @return  array       is_online, sub_start, sub_end
	 */
	private static function getStatusData(int $a_obj_id): array
	{
		global $DIC;
		$ilDB = $DIC->database();

		if (!isset(self::$status_data[$a_obj_id]))
		{
			self::$status_data[$a_obj_id] = array();
			$set = $ilDB->query("SELECT is_online, sub_start, sub_end FROM rep_robj_xcos_data ".
				" WHERE obj_id = ".$ilDB->quote($a_obj_id, 'integer')
			);
			if ($rec = $ilDB->fetchAssoc($set))
			{
				self::$status_data[$a_obj_id] = $rec;
			}
		}
		return self::$status_data[$a_obj_id];
	}
}

?>
