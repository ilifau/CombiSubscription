<?php

/**
 * Properties for random calculation
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubMethodRandomPropertiesGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubMethodRandomPropertiesGUI extends ilCoSubBaseGUI
{
	/** @var ilCoSubMethodRandom */
	protected $method;

	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$this->method = $this->object->getMethodObject();

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
	 * Edit the properties
	 */
	protected function editProperties()
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
	protected function initPropertiesForm()
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this));
		$this->form->setTitle($this->method->txt('selection_properties'));

		// number of priorities
		$ni = new ilNumberInputGUI($this->method->txt('number_priorities'), 'number_priorities');
		$ni->setInfo($this->method->txt('number_priorities_info'));
		$ni->setDecimals(0);
		$ni->setSize(2);
		$ni->setMinValue(1);
		$ni->setValue(2);
		$ni->setRequired(true);
		$this->form->addItem($ni);

		// one per priority
		$ci = new ilCheckboxInputGUI($this->method->txt('one_per_priority'), 'one_per_priority');
		$ci->setInfo($this->method->txt('one_per_priority_info'));
		$this->form->addItem($ci);

		// number of assignments
		$ni = new ilNumberInputGUI($this->method->txt('number_assignments'), 'number_assignments');
		$ni->setInfo($this->method->txt('number_assignments_info'));
		$ni->setDecimals(0);
		$ni->setSize(2);
		$ni->setMinValue(1);
		$ni->setValue(2);
		$ni->setRequired(true);
		$this->form->addItem($ni);


		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues()
	{
		$this->form->getItemByPostVar('number_priorities')->setValue($this->method->number_priorities);
		$this->form->getItemByPostVar('one_per_priority')->setChecked($this->method->one_per_priority);
		$this->form->getItemByPostVar('number_assignments')->setValue($this->method->number_assignments);
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues()
	{
		$this->method->number_priorities = (int) $this->form->getInput('number_priorities');
		$this->method->one_per_priority = (bool) $this->form->getInput('one_per_priority');
		$this->method->number_assignments = (int) $this->form->getInput('number_assignments');
		$this->method->saveProperties();
	}
}