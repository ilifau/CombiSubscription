<?php

include_once './Services/Repository/classes/class.ilObjectPluginListGUI.php';

/**
* ListGUI implementation for combined subscription object plugin. This one
* handles the presentation in container items (categories, courses, ...)
* together with the corresponfing ...Access class.
*
* PLEASE do not create instances of larger classes here. Use the
* ...Access class to get DB data and keep it small.
*
* @author 		Fred Neumann <fred.neumann@fau.de>
* @version      $id$
*/
class ilObjCombiSubscriptionListGUI extends ilObjectPluginListGUI
{
	
	/**
	* Init type
	*/
	function initType()
	{
		$this->setType('xcos');
	}
	
	/**
	* Get name of gui class handling the commands
	*/
	function getGuiClass()
	{
		return 'ilObjCombiSubscriptionGUI';
	}
	
	/**
	* Get commands
	*/
	function initCommands()
	{
		return array
		(
			array(
				'permission' => 'read',
				'cmd' => 'editRegistration',
				'default' => true),
			array(
				'permission' => 'write',
				'cmd' => 'editProperties',
				'txt' => $this->txt('edit'),
				'default' => false),
		);
	}

	/**
	* Get item properties
	*
	* @return	array		array of property arrays:
	*						'alert' (boolean) => display as an alert property (usually in red)
	*						'property' (string) => property name
	*						'value' (string) => property value
	*/
	function getProperties()
	{
		global $lng, $ilUser;

		$props = array();
		
		$this->plugin->includeClass('class.ilObjCombiSubscriptionAccess.php');
		if (!ilObjCombiSubscriptionAccess::checkOnline($this->obj_id))
		{
			$props[] = array('alert' => true, 'property' => $this->txt('status'),
				'value' => $this->txt('offline'));
		}
		else
		{
			$props[] = array('alert' => true, 'property' => $this->txt('subscription_period'),
				'value' => ilDatePresentation::formatPeriod(
					ilObjCombiSubscriptionAccess::getSubscriptionStart($this->obj_id),
					ilObjCombiSubscriptionAccess::getSubscriptionEnd($this->obj_id))
			);
		}

		return $props;
	}
}
?>
