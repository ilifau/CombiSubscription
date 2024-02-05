<?php

/**
 * Properties for EATTS calculation
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubMethodEATTSConfigGUI: ilCombiSubscriptionConfigGUI
 */
class ilCoSubMethodEATTSConfigGUI extends ilCoSubMethodBaseConfigGUI
{
	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand(): void
	{
		$cmd = $this->ctrl->getCmd('editProperties');
		switch ($cmd)
		{
			case 'editProperties':
			case 'updateProperties':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}

	/**
	 * Get the classname of the assigned method
	 * @return string
	 */
	public function getMethodId(): string
	{
		return 'ilCoSubMethodEATTS';
	}

	/**
	 * Edit the properties
	 */
	protected function editProperties(): void
	{
		$this->initPropertiesForm();
		$this->loadPropertiesValues();
		$this->tpl->setContent($this->form->getHTML());

	}

	/**
	 * Update the properties
	 */
	protected function updateProperties(): void
	{
		$this->initPropertiesForm();
		if ($this->form->checkInput())
		{
			$this->savePropertiesValues();
			ilUtil::sendSuccess($this->lng->txt('msg_obj_modified'), true);
			$this->ctrl->redirect($this, 'editProperties');
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
	protected function initPropertiesForm(): void
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this));
		$this->form->setTitle($this->txt('title'));

		$ti = new ilTextInputGUI($this->txt('server_url'),'server_url');
		$ti->setRequired(true);
		$ti->setSize(20);
		$this->form->addItem($ti);

		$ti = new ilTextInputGUI($this->txt('license_url'),'license_url');
		$ti->setRequired(true);
		$ti->setSize(20);
		$this->form->addItem($ti);

		$ti = new ilTextInputGUI($this->txt('license'),'license');
		$ti->setRequired(true);
		$ti->setSize(10);
		$this->form->addItem($ti);

		$si = new ilSelectInputGUI($this->txt('log_level'),'log_level');
		$ti->setRequired(true);
		$si->setOptions(array('info' => $this->txt('log_level_info')));
		$this->form->addItem($si);

		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues(): void
	{
		/** @var ilCoSubMethodBase $method */
		$method = $this->getMethodId();

		$this->form->getItemByPostVar('server_url')->setValue($method::_getSetting('server_url'));
		$this->form->getItemByPostVar('license_url')->setValue($method::_getSetting('license_url'));
		$this->form->getItemByPostVar('license')->setValue($method::_getSetting('license'));
		$this->form->getItemByPostVar('log_level')->setValue($method::_getSetting('log_level','info'));
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues(): void
	{
		/** @var ilCoSubMethodBase $method */
		$method = $this->getMethodId();

		$method::_setSetting('server_url', $this->form->getInput('server_url'));
		$method::_setSetting('license_url', $this->form->getInput('license_url'));
		$method::_setSetting('license', $this->form->getInput('license'));
		$method::_setSetting('log_level', $this->form->getInput('log_level'));
	}
}