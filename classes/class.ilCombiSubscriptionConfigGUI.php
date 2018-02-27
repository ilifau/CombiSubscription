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

	/** @var  ilCombiSubscriptionPlugin */
	protected $plugin;

	/** @var  ilCtrl */
	protected $ctrl;

	/** @var ilTemplate */
	protected $tpl;

	/** @var ilLanguage */
	protected $lng;

	/** @var ilPropertyFormGUI */
	protected $form;



	/**
	 * ilCombiSubscriptionConfigGUI constructor.
	 */
	public function __construct()
	{
		global $ilCtrl, $tpl, $lng;

		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->lng = $lng;
	}

	/**
	 * Handles all commmands, default is "configure"
	 */
	function performCommand($cmd)
	{
		// now available
		$this->plugin = $this->getPluginObject();

		$this->setTabs();

		$next_class = $this->ctrl->getNextClass();
		if (!empty($next_class))
		{
			$this->plugin->includeClass('abstract/class.ilCoSubMethodBaseConfigGUI.php');

			switch ($next_class)
			{
				case 'ilcosubmethodeattsconfiggui':
					$this->setSubTabs('methods','eatts');
					$this->plugin->includeClass('abstract/class.ilCoSubMethodBase.php');
					$this->plugin->includeClass('methods/class.ilCoSubMethodEATTS.php');
					$this->plugin->includeClass('methods/class.ilCoSubMethodEATTSConfigGUI.php');
					$this->ctrl->forwardCommand(new ilCoSubMethodEATTSConfigGUI($this->plugin));
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
				case "updateProperties":
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

		$ilTabs->addTab('basic', $this->plugin->txt('basic_configuration'), $this->ctrl->getLinkTarget($this, 'configure'));
		$ilTabs->addTab('methods', $this->plugin->txt('assignment_methods'), $this->ctrl->getLinkTarget($this, 'methods'));
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
				$ilTabs->addSubTab('eatts', $this->plugin->txt('ilcosubmethodeatts_title'), $this->ctrl->getLinkTargetByClass('ilCoSubMethodEATTSConfigGUI'));
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
		$this->initPropertiesForm();
		$this->loadPropertiesValues();
		$this->tpl->setContent($this->form->getHTML());
	}


	/**
	 * Update the properties
	 */
	protected function updateProperties()
	{
		$this->initPropertiesForm();
		if ($this->form->checkInput())
		{
			$this->savePropertiesValues();
			ilUtil::sendSuccess($this->lng->txt('msg_obj_modified'), true);
			$this->ctrl->redirect($this, 'configure');
		}
		else
		{
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHTML());
		}
	}

	/**
	 * Inot the properties form
	 */
	protected function initPropertiesForm()
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this));
		$this->form->setTitle($this->plugin->txt('plugin_configuration'));

		$ni = new ilNumberInputGUI($this->plugin->txt('out_of_conflict_time'),'out_of_conflict_time');
		$ni->setInfo($this->plugin->txt('out_of_conflict_time_info'));
		$ni->setRequired(true);
		$ni->setSize(10);
		$this->form->addItem($ni);

		$nt = new ilNumberInputGUI($this->plugin->txt('number_of_tries'),'number_of_tries');
		$nt->setInfo($this->plugin->txt('number_of_tries_info'));
		$nt->setRequired(true);
		$nt->setSize(10);
		$this->form->addItem($nt);

		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues()
	{
		$this->form->getItemByPostVar('out_of_conflict_time')->setValue(
			ilCombiSubscriptionPlugin::_getSetting('out_of_conflict_time', 900));

		$this->form->getItemByPostVar('number_of_tries')->setValue(
			ilCombiSubscriptionPlugin::_getSetting('number_of_tries', 5));
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues()
	{
		ilCombiSubscriptionPlugin::_setSetting('out_of_conflict_time', $this->form->getInput('out_of_conflict_time'));
		ilCombiSubscriptionPlugin::_setSetting('number_of_tries', $this->form->getInput('number_of_tries'));
	}

}
?>
