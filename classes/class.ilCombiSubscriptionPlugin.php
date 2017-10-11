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

		$ilDB->dropTable('rep_robj_xcos_data');
		$ilDB->dropTable('rep_robj_xcos_items');
		$ilDB->dropTable('rep_robj_xcos_choices');
		$ilDB->dropTable('rep_robj_xcos_runs');
		$ilDB->dropTable('rep_robj_xcos_ass');
		$ilDB->dropTable('rep_robj_xcos_prop');
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
}
?>
