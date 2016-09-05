<?php

include_once('./Services/Repository/classes/class.ilObjectPluginAccess.php');

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
	/**
	 * @var array   obj_id => row
	 */
	static $status_data = array();

	/**
	* Checks wether a user may invoke a command or not
	* (this method is called by ilAccessHandler::checkAccess)
	*
	* Please do not check any preconditions handled by
	* ilConditionHandler here. Also don't do usual RBAC checks.
	*
	* @param	string		$a_cmd			command (not permission!)
 	* @param	string		$a_permission	permission
	* @param	int			$a_ref_id		reference id
	* @param	int			$a_obj_id		object id
	* @param	int			$a_user_id		user id (if not provided, current user is taken)
	*
	* @return	boolean		true, if everything is ok
	*/
	function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = '')
	{
		global $ilUser, $ilAccess;

		if ($a_user_id == '')
		{
			$a_user_id = $ilUser->getId();
		}

		switch ($a_permission)
		{
			case 'read':
				if (!self::checkOnline($a_obj_id) &&
					!$ilAccess->checkAccessOfUser($a_user_id, 'write', '', $a_ref_id))
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
	static function checkOnline($a_obj_id)
	{
		$rec  = self::getStatusData($a_obj_id);
		return (boolean) $rec['is_online'];
	}

	/**
	 * Get the subscription start
	 */
	static function getSubscriptionStart($a_obj_id)
	{
		$rec  = self::getStatusData($a_obj_id);
		return new ilDateTime($rec['sub_start'],IL_CAL_DATETIME);
	}

	/**
	 * Get the subscription end
	 */
	static function getSubscriptionEnd($a_obj_id)
	{
		$rec  = self::getStatusData($a_obj_id);
		return new ilDateTime($rec['sub_end'],IL_CAL_DATETIME);
	}

	/**
	 * Get the status data
	 * @param   int         object id
	 * @return  array       is_online, sub_start, sub_end
	 */
	private static function getStatusData($a_obj_id)
	{
		global $ilDB;

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
