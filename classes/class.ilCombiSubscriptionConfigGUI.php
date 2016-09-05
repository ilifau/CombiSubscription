<?php

include_once('./Services/Component/classes/class.ilPluginConfigGUI.php');
 
/**
 * Configuration user interface class for combined subscription
 *
 * @author Fred Neumann fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilCombiSubscriptionConfigGUI extends ilPluginConfigGUI
{
	/** @var  ilCtrl */
	protected $ctrl;


	/**
	 * ilCombiSubscriptionConfigGUI constructor.
	 */
	public function __construct()
	{
		global $ilCtrl;
		$this->ctrl = $ilCtrl;
	}

	/**
	 * Handles all commmands, default is "configure"
	 */
	function performCommand($cmd)
	{
		$this->setTabs();

		$next_class = $class ? $class : $this->ctrl->getNextClass();
		if (!empty($next_class))
		{
			$this->plugin_object->includeClass('abstract/class.ilCoSubMethodBaseConfigGUI.php');

			switch ($next_class)
			{
				case 'ilcosubmethodeattsconfiggui':
					$this->setSubTabs('methods','eatts');
					$this->plugin_object->includeClass('abstract/class.ilCoSubMethodBase.php');
					$this->plugin_object->includeClass('methods/class.ilCoSubMethodEATTS.php');
					$this->plugin_object->includeClass('methods/class.ilCoSubMethodEATTSConfigGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubMethodEATTSConfigGUI($this->plugin_object));
					return;

				default:
					// show unknown next class
					$this->tpl->setContent($next_class);
					return;
			}
		}
		else   // no next class
		{
			$this->setSubTabs('basic','');

			$cmd = $cmd ? $cmd : $this->ctrl->getCmd();
			switch ($cmd)
			{
				case "methods":
					$this->ctrl->redirectByClass('ilCoSubMethodEATTSConfigGUI');
					break;

				case "configure":
					$this->$cmd();
					break;

				default:
					// show unknown command
					$this->tpl->setContent($cmd);
					return;
			}
		}
	}


	/**
	 * Set tabs
	 */
	protected function setTabs()
	{
		global $ilTabs;

		$ilTabs->addTab('basic', $this->plugin_object->txt('basic_configuration'), $this->ctrl->getLinkTarget($this, 'configure'));
		$ilTabs->addTab('methods', $this->plugin_object->txt('assignment_methods'), $this->ctrl->getLinkTarget($this, 'methods'));
	}

	/**
	 * Activate a tab and set its sub tabs
	 *
	 * @param string $a_tab     name of the tab (will be activated)
	 * @param string $a_subtab  name of the subtab (will be activated)
	 */
	protected function setSubTabs($a_tab, $a_subtab = '')
	{
		global $ilTabs;

		switch ($a_tab)
		{
			case 'basic':
				break;

			case 'methods':
				$ilTabs->addSubTab('eatts', $this->plugin_object->txt('ilcosubmethodeatts_title'), $this->ctrl->getLinkTargetByClass('ilCoSubMethodEATTSConfigGUI'));
				break;
		}

		if ($a_subtab)
		{
			$ilTabs->activateSubTab($a_subtab);
		}
		$ilTabs->activateTab($a_tab);
	}


	/**
	 * Configure screen
	 */
	function configure()
	{
		global $tpl;

		$pl = $this->getPluginObject();
		ilUtil::sendInfo($pl->txt("nothing_to_configure"), false);
		return;
	}
}
?>
