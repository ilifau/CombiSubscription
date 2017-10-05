<?php

include_once('./Services/Repository/classes/class.ilObjectPluginGUI.php');

/**
 * User Interface class for combined subscription repository object
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilObjCombiSubscriptionGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjCombiSubscriptionGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjCombiSubscriptionGUI extends ilObjectPluginGUI
{
	/** @var ilPropertyFormGUI */
	protected $form = null;

	/** @var ilObjCombiSubscription */
	public $object;

	/** @var  ilCombiSubscriptionPlugin */
	public $plugin;

	/** @var  ilTabsGUI */
	public $tabs_gui;

	/** @var  ilCtrl */
	public $ctrl;


	/**
	* Initialisation
	*/
	protected function afterConstructor()
	{
		// Description is not shown by ilObjectPluginGUI
		if (isset($this->object))
		{
			$this->tpl->setDescription($this->object->getDescription());
			$alerts = array();
			array_push($alerts, array(
				'property' => $this->object->plugin->txt('subscription_period'),
				'value' => ilDatePresentation::formatPeriod($this->object->getSubscriptionStart(), $this->object->getSubscriptionEnd())
			));
			if (!$this->object->getOnline())
			{
				array_push($alerts, array(
					'property' => $this->object->plugin->txt('status'),
					'value' => $this->object->plugin->txt('offline'))
				);
			}

			$this->tpl->setAlertProperties($alerts);
			$this->tpl->addCss($this->object->plugin->getStyleSheetLocation('ilObjCombiSubscription.css'));

		}
	}
	
	/**
	* Get type.
	*/
	final function getType()
	{
		return 'xcos';
	}

	/**
	 * Handles all commmands of this class, centralizes permission checks
	 *
	 * @param	string		$cmd 	command to be executed
	 * @param	string		$class	(optional) class that should handle the command
	 */
	function performCommand($cmd, $class = '')
	{
		$next_class = $class ? $class : $this->ctrl->getNextClass();

		if (!empty($next_class))
		{
			$this->plugin->includeClass('abstract/class.ilCoSubBaseGUI.php');

			switch ($next_class)
			{
				case 'ilcosubpropertiesgui':
					$this->checkPermission('write');
					$this->setSubTabs('settings','properties');
					$this->plugin->includeClass('guis/class.ilCoSubPropertiesGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubPropertiesGUI($this));
					return;

				case 'ilcosubcategoriesgui':
					$this->checkPermission("write");
					$this->checkMethodAvailable();
					$this->setSubTabs('settings', 'categories');
					$this->plugin->includeClass('guis/class.ilCoSubCategoriesGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubCategoriesGUI($this));
					return;

				case 'ilcosubitemsgui':
					$this->checkPermission("write");
					$this->checkMethodAvailable();
					$this->setSubTabs('settings', 'items');
					$this->plugin->includeClass('guis/class.ilCoSubItemsGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubItemsGUI($this));
					return;

				case 'ilcosubregistrationgui':
					$this->checkPermission('read');
					$this->checkMethodAvailable();
					$this->setSubTabs('registration','registration');
					$this->plugin->includeClass('guis/class.ilCoSubRegistrationGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubRegistrationGUI($this));
					return;

//				case 'ilcosubpeersgui':
//					$this->checkPermission('read');
//					$this->checkMethodAvailable();
//					$this->setSubTabs('registration','peers');
//					$this->plugin->includeClass('guis/class.ilCoSubPeersGUI.php');
//					$this->ctrl->forwardCommand(new ilCoSubPeersGUI($this));
//					return;

				case 'ilcosubassignmentsgui':
					$this->checkPermission('write');
					$this->checkMethodAvailable();
					$this->setSubTabs('assignments','assignments');
					$this->plugin->includeClass('guis/class.ilCoSubAssignmentsGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubAssignmentsGUI($this));
					return;

				case 'ilcosubrunsgui':
					$this->checkPermission('write');
					$this->checkMethodAvailable();
					$this->setSubTabs('assignments','runs');
					$this->plugin->includeClass('guis/class.ilCoSubRunsGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubRunsGUI($this));
					return;

				case 'ilcosubexportgui':
					$this->checkPermission('write');
					$this->setSubTabs('assignments','export');
					$this->plugin->includeClass('guis/class.ilCoSubExportGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubExportGUI($this));
					return;

				case 'ilcosubimportgui':
					$this->checkPermission('write');
					$this->setSubTabs('assignments','import');
					$this->plugin->includeClass('guis/class.ilCoSubImportGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubImportGUI($this));
					return;

				default:
					// properties gui of method
					if ($method = $this->object->getMethodObject()
						and $next_class == strtolower($classname = $method->getPropertiesGuiName()))
					{
						$this->checkPermission('write');
						$this->setSubTabs('settings','calc');
						require_once($method->getPropertiesGuiPath());
						$this->ctrl->forwardCommand(new $classname($this));
						return;
					}

					// show unknown next class
					$this->tpl->setContent($next_class);
					return;
			}
		}
		else   // no next class
		{
			$cmd = $cmd ? $cmd : $this->ctrl->getCmd();
			switch ($cmd)
			{
				case 'editProperties':
					$this->performCommand($cmd, 'ilcosubpropertiesgui');
					return;

				case 'editRegistration':
					$this->performCommand($cmd, 'ilcosubregistrationgui');
					return;

				case 'returnToContainer':
					$this->$cmd();
					return;

				default:
					// show unknown command
					$this->tpl->setContent($cmd);
					return;
			}
		}
	}

	/**
	 * Return to the uper object
	 */
	public function returnToContainer()
	{
		global $tree;
		require_once('Services/Link/classes/class.ilLink.php');
		ilUtil::redirect(ilLink::_getLink($tree->getParentId($this->object->getRefId())));
	}


	/**
	* After object has been created -> jump to this command
	*/
	function getAfterCreationCmd()
	{
		return 'editProperties';
	}

	/**
	* Get standard command
	*/
	function getStandardCmd()
	{
		return 'editRegistration';
	}

	/**
	 * Check the availability of an assignment method
	 */
	protected function checkMethodAvailable()
	{
		if (!$this->object->getMethodObject())
		{
			ilUtil::sendFailure($this->plugin->txt('method_not_defined'), true);
			if ($this->checkPermissionBool('write'))
			{
				$this->ctrl->redirect($this,'editProperties');
			}
			else
			{
				$this->returnToContainer();
			}
		}
	}

	/**
	 * Set tabs (called from ilObjectPluginGUI)
	 */
	protected function setTabs()
	{
		// student registration
		if ($this->checkPermissionBool('read', '', $this->getType(), $this->object->getRefId()))
		{
			$this->tabs_gui->addTab('registration', $this->txt('registration'), $this->ctrl->getLinkTarget($this, 'editRegistration'));
		}
		// standard info screen tab
		$this->addInfoTab();

		// assignment management
		if ($this->checkPermissionBool('write', '',  $this->getType(), $this->object->getRefId()))
		{
			$this->tabs_gui->addTab('assignments', $this->plugin->txt('assignment'), $this->ctrl->getLinkTargetByClass('ilCoSubAssignmentsGUI'));
		}

		// object settings
		if ($this->checkPermissionBool('write', '',  $this->getType(), $this->object->getRefId()))
		{
			$this->tabs_gui->addTab('settings', $this->lng->txt('settings'), $this->ctrl->getLinkTarget($this, 'editProperties'));
		}

		// standard permission tab
		$this->addPermissionTab();
	}

	/**
	 * Activate a tab and set its sub tabs
	 *
	 * @param string $a_tab     name of the tab (will be activated)
	 * @param string $a_subtab  name of the subtab (will be activated)
	 */
	protected function setSubTabs($a_tab, $a_subtab = '')
	{
		switch ($a_tab)
		{
			case 'registration':
				$this->tabs_gui->addSubTab('registration', $this->txt('registration'), $this->ctrl->getLinkTarget($this,'editRegistration'));
				//$this->tabs_gui->addSubTab('peers', $this->txt('peers'), $this->ctrl->getLinkTargetByClass('ilCoSubPeersGUI'));
				break;

			case 'settings':
				$this->tabs_gui->addSubTab('properties', $this->txt('properties'), $this->ctrl->getLinkTarget($this,'editProperties'));

				if ($this->object->getMethodObject() && $classname = $this->object->getMethodObject()->getPropertiesGuiName())
				{
					$this->tabs_gui->addSubTab('calc', $this->txt('calc_properties'), $this->ctrl->getLinkTargetByClass($classname));
				}

				$this->tabs_gui->addSubTab('categories', $this->txt('registration_categories'), $this->ctrl->getLinkTargetByClass('ilCoSubCategoriesGUI'));

				$this->tabs_gui->addSubTab('items', $this->txt('registration_items'), $this->ctrl->getLinkTargetByClass('ilCoSubItemsGUI'));
				break;

			case 'assignments':
				$this->tabs_gui->addSubTab('assignments', $this->plugin->txt('current_assignment'), $this->ctrl->getLinkTargetByClass('ilCoSubAssignmentsGUI'));
				$this->tabs_gui->addSubTab('runs', $this->plugin->txt('saved_assignments'), $this->ctrl->getLinkTargetByClass('ilCoSubRunsGUI'));
				$this->tabs_gui->addSubTab('export', $this->plugin->txt('export_data'), $this->ctrl->getLinkTargetByClass('ilCoSubExportGUI'));
				$this->tabs_gui->addSubTab('import', $this->plugin->txt('import_data'), $this->ctrl->getLinkTargetByClass('ilCoSubImportGUI'));
				break;
		}

		if ($a_subtab)
		{
			$this->tabs_gui->activateSubTab($a_subtab);
		}
		$this->tabs_gui->activateTab($a_tab);
	}

	/**
	 * Get the url of a satisfaction image
	 * @param $a_satisfaction
	 * @return string
	 */
	public function getSatisfactionImageUrl($a_satisfaction)
	{
		switch ($a_satisfaction)
		{
			case ilObjCombiSubscription::SATISFIED_FULL:
				return ilUtil::getImagePath('scorm/complete.svg');
			case ilObjCombiSubscription::SATISFIED_MEDIUM:
				return ilUtil::getImagePath('scorm/incomplete.svg');
			case ilObjCombiSubscription::SATISFIED_NOT:
				return ilUtil::getImagePath('scorm/failed.svg');
			default:
				return ilUtil::getImagePath('scorm/not_attempted.svg');
		}
	}

	/**
	 * Get the satisfaction title
	 * @param $a_satisfaction
	 * @return string
	 */
	public function getSatisfactionTitle($a_satisfaction)
	{
		switch ($a_satisfaction)
		{
			case ilObjCombiSubscription::SATISFIED_FULL:
				return $this->plugin->txt('satisfied_full');
			case ilObjCombiSubscription::SATISFIED_MEDIUM:
				return $this->plugin->txt('satisfied_medium');
			case ilObjCombiSubscription::SATISFIED_NOT:
				return $this->plugin->txt('satisfied_not');
			default:
				return $this->plugin->txt('satisfied_unknown');
		}
	}


	/**
	 * Check the unfinished runs if results are available
	 */
	public function checkUnfinishedRuns()
	{
		$success_messages = array();
		$failure_messages = array();

		foreach ($this->object->getRunsUnfinished() as $run)
		{
			if ($this->object->getMethodObject()->checkForResult($run))
			{
				$success_messages[] = $this->plugin->txt('msg_calculation_finished2')
					.' '.ilDatePresentation::formatPeriod($run->run_start, $run->run_end);
			}
			elseif ($this->object->getMethodObject()->getError())
			{
				if ($run->run_end)
				{
					$failure_messages[] = $this->plugin->txt('msg_calculation_failed')
						.' '.ilDatePresentation::formatPeriod($run->run_start, $run->run_end)
						.' '. $this->object->getMethodObject()->getError();
				}
				else
				{
					$failure_messages[] = $this->plugin->txt('msg_check_for_result_failed')
						.' '. $this->object->getMethodObject()->getError();
				}
			}
		}

		if (!empty($success_messages))
		{
			ilUtil::sendSuccess(implode('<br />', $success_messages));
		}

		if (!empty($failure_messages))
		{
			ilUtil::sendFailure(implode('<br />', $failure_messages));
		}

	}

}
