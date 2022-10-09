<?php

include_once('./Services/Repository/classes/class.ilRepositoryObjectPlugin.php');
 
/**
* Combined subscription repository object plugin
*
* @author Fred Neumann <fred.neumann@fau.de>
* @version $Id$
*
*/
class ilCombiSubscriptionPlugin extends ilRepositoryObjectPlugin
{
	/** @var  ilSetting[]  $settings  */
	protected static $settings;


	function getPluginName()
	{
		return 'CombiSubscription';
	}

	/**
	 * Get the available target types
	 * @return array
	 */
	function getAvailableTargetTypes()
	{
		return array('crs','grp','sess');
	}


	protected function uninstallCustom()
	{
		global $ilDB;

//		$ilDB->dropTable('rep_robj_xcos_ass');
//		$ilDB->dropTable('rep_robj_xcos_cats');
//		$ilDB->dropTable('rep_robj_xcos_choices');
//		$ilDB->dropTable('rep_robj_xcos_data');
//		$ilDB->dropTable('rep_robj_xcos_items');
//		$ilDB->dropTable('rep_robj_xcos_prop');
//		$ilDB->dropTable('rep_robj_xcos_runs');
//		$ilDB->dropTable('rep_robj_xcos_scheds');
//		$ilDB->dropTable('rep_robj_xcos_users');
	}
	/**
	 * decides if this repository plugin can be copied
	 *
	 * @return bool
	 */
	public function allowCopy()
	{
		return true;
	}

	/**
	 * Limit for user queries
	 */
	public function getUserQueryLimit()
	{
		return 100000;
	}


	/**
	 * Checks if a user has extended access to other user data
	 * @return   boolean
	 */
	public function hasUserDataAccess()
	{
		global $DIC;

		static $allowed = null;

		if (!isset($allowed))
		{
			$privacy = ilPrivacySettings::_getInstance();
			$allowed = $DIC->rbac()->system()->checkAccess('export_member_data', $privacy->getPrivacySettingsRefId());
		}

		return $allowed;
	}

	/**
	 * Check if the user has administrative access
	 * @return bool
	 */
	public function hasAdminAccess()
	{
		global $DIC;

		return $DIC->rbac()->system()->checkAccess("visible", SYSTEM_FOLDER_ID);
	}

	/**
	 * Check if the FAU service is available (StudOn only)
	 */
	public function hasFauService() 
	{
		global $DIC;
		return $DIC->isDependencyAvailable('fau');
	}
	

	/**
	 * Check if cron job is active
	 * @return bool
	 */
	public function withCronJob()
	{
		/** @var ilPluginAdmin $ilPluginAdmin */
		global $ilPluginAdmin;

		return $ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', 'CombiSubscriptionCron');
	}

	/**
	 * Handle a call by the cron job plugin
	 * @return	int		Number of processed objects
	 * @throws	Exception
	 */
	public function handleCronJob()
	{
		$this->includeClass('class.ilObjCombiSubscription.php');

		$processed = 0;

		foreach (ilObjCombiSubscription::_getRefIdsForAutoProcess() as $ref_id)
		{
			$subObj = new ilObjCombiSubscription($ref_id);
			if ($subObj->handleAutoProcess())
			{
				$processed++;
			}
		}

		return $processed;
	}

	/**
	 * Get a global setting for a class (maintained in administration)
	 * @param   string  $a_class
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return string	value
	 */
	public static function _getClassSetting($a_class, $a_key, $a_default_value = '')
	{
		self::_readClassSettings($a_class);
		return self::$settings[$a_class]->get($a_key, $a_default_value);

	}

	/**
	 * Set a global setting for a class (maintained in administration)
	 * @param string  $a_class
	 * @param string  $a_key
	 * @param string  $a_value
	 */
	public static function _setClassSetting($a_class, $a_key, $a_value)
	{
		self::_readClassSettings($a_class);
		self::$settings[$a_class]->set($a_key, $a_value, true);
	}


	/**
	 * Read the global settings for a class
	 * @param   string  $a_class
	 */
	protected static function _readClassSettings($a_class)
	{
		if (!isset(self::$settings[$a_class]))
		{
			require_once("Services/Administration/classes/class.ilSetting.php");
			$settings_obj = new ilSetting($a_class);
			self::$settings[$a_class] = new ilSetting($a_class);;
		}
	}

	/**
	 * Get a global setting for this method
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return string	value
	 */
	public static function _getSetting($a_key, $a_default_value = '')
	{
		return ilCombiSubscriptionPlugin::_getClassSetting('ilObjCombiSubscription', $a_key, $a_default_value);
	}

	/**
	 * Set a global setting for this method
	 * @param string  $a_key
	 * @param string  $a_value
	 */
	public static function _setSetting($a_key, $a_value)
	{
		ilCombiSubscriptionPlugin::_setClassSetting('ilObjCombiSubscription', $a_key, $a_value);
	}


	/**
	 * Get the configured time buffer for conflict recognition
	 * @return int
	 */
	public function getOutOfConflictTime()
	{
		return (int) self::_getSetting('out_of_conflict_time', 900);
	}

	/**
	 * Get the tolerated percentage of schedule time being in conflict with other item
	 * @eturn int;
	 */
	public function getToleratedConflictPercentage()
	{
		return (int) self::_getSetting('tolerated_conflict_percentage', 20);
	}

	/**
	 * Get the number of calculation tries for the auto assignment
	 * @return int
	 */
	public function getNumberOfTries()
	{
		return (int) self::_getSetting('number_of_tries', 5);
	}

	/**
	 * Get the number of calculation tries for the auto assignment
	 * @return bool
	 */
	public function getCloneWithChoices()
	{
		return (bool) self::_getSetting('clone_with_choices', 0);
	}
}
?>
