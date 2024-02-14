<?php

/**
* Combined subscription repository object plugin
*
* @author Fred Neumann <fred.neumann@fau.de>
* @version $Id$
*
*/
class ilCombiSubscriptionPlugin extends ilRepositoryObjectPlugin
{
	/** ilSetting[] */
	protected static array $settings;
	protected static self $instance;

	/**
	 * Get the plugin instance
	 */
	public static function getInstance(): self {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	public function getPluginName(): string
	{
		return 'CombiSubscription';
	}

	/**
	 * Get the available target types
	 */
	function getAvailableTargetTypes(): array
	{
		return array('crs','grp','sess');
	}


	protected function uninstallCustom(): void
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
	 */
	public function allowCopy(): bool
	{
		return true;
	}

	/**
	 * Limit for user queries
	 */
	public function getUserQueryLimit(): int
	{
		return 100000;
	}


	/**
	 * Checks if a user has extended access to other user data
	 */
	public function hasUserDataAccess(): bool
	{
		global $DIC;

		static $allowed = null;

		if (!isset($allowed))
		{
			$privacy = ilPrivacySettings::getInstance();
			$allowed = $DIC->rbac()->system()->checkAccess('export_member_data', $privacy->getPrivacySettingsRefId());
		}

		return $allowed;
	}

	/**
	 * Check if the user has administrative access
	 */
	public function hasAdminAccess(): bool
	{
		global $DIC;

		return $DIC->rbac()->system()->checkAccess("visible", SYSTEM_FOLDER_ID);
	}

	/**
	 * Check if the FAU service is available (StudOn only)
	 */
	public function hasFauService(): bool
	{
		global $DIC;
		return $DIC->isDependencyAvailable('fau');
	}
	

	/**
	 * Check if cron job is active
	 */
	public function withCronJob(): bool
	{
		global $DIC;

		if($DIC["component.repository"]->hasPluginId('crnhk'))
			return $DIC["component.repository"]->getPluginByName('CombiSubscriptionCron')->isActive();
		else return false;
	}

	/**
	 * Handle a call by the cron job plugin
	 * @return	int		Number of processed objects
	 * @throws	Exception
	 */
	public function handleCronJob(): int
	{
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
	 */
	public static function _getClassSetting(string $a_class, string $a_key, string $a_default_value = ''): string
	{
		self::_readClassSettings($a_class);
		return self::$settings[$a_class]->get($a_key, $a_default_value);

	}

	/**
	 * Set a global setting for a class (maintained in administration)
	 */
	public static function _setClassSetting(string $a_class, string $a_key, string $a_value): void
	{
		self::_readClassSettings($a_class);
		self::$settings[$a_class]->set($a_key, $a_value, true);
	}


	/**
	 * Read the global settings for a class
	 */
	protected static function _readClassSettings(string $a_class): void
	{
		if (!isset(self::$settings[$a_class]))
		{
			$settings_obj = new ilSetting($a_class);
			self::$settings[$a_class] = new ilSetting($a_class);;
		}
	}

	/**
	 * Get a global setting for this method
	 */
	public static function _getSetting(string $a_key, string $a_default_value = ''): string
	{
		return ilCombiSubscriptionPlugin::_getClassSetting('ilObjCombiSubscription', $a_key, $a_default_value);
	}

	/**
	 * Set a global setting for this method
	 */
	public static function _setSetting(string $a_key, string $a_value): void
	{
		ilCombiSubscriptionPlugin::_setClassSetting('ilObjCombiSubscription', $a_key, $a_value);
	}


	/**
	 * Get the configured time buffer for conflict recognition
	 */
	public function getOutOfConflictTime(): int
	{
		return (int) self::_getSetting('out_of_conflict_time', 900);
	}

	/**
	 * Get the tolerated percentage of schedule time being in conflict with other item
	 */
	public function getToleratedConflictPercentage(): int
	{
		return (int) self::_getSetting('tolerated_conflict_percentage', 20);
	}

	/**
	 * Get the number of calculation tries for the auto assignment
	 */
	public function getNumberOfTries(): int
	{
		return (int) self::_getSetting('number_of_tries', 5);
	}

	/**
	 * Get the number of calculation tries for the auto assignment
	 */
	public function getCloneWithChoices(): bool
	{
		return (bool) self::_getSetting('clone_with_choices', 0);
	}
}
?>
