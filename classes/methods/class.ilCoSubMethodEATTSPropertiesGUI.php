<?php

/**
 * Properties for EATTS calculation
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubMethodEATTSPropertiesGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubMethodEATTSPropertiesGUI extends ilCoSubBaseGUI
{
	/** @var ilCoSubMethodEATTS */
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
		$this->form->setTitle($this->method->txt('calculation_properties'));

		// time limit
		$di = new ilDurationInputGUI($this->method->txt('time_limit'), 'time_limit');
		$di->setShowSeconds(true);
		$di->setRequired(false);
		$this->form->addItem($di);

		// maximum iteration
		$ni = new ilNumberInputGUI($this->method->txt('max_iterations'), 'max_iterations');
		$ni->setDecimals(0);
		$ni->setMinValue(1);
		$ni->setMaxValue(1000000000);
		$ni->setSize(10);
		$ni->setRequired(false);
		$this->form->addItem($ni);

		// priority weight
		$ni = new ilNumberInputGUI($this->method->txt('priority_weight'), 'priority_weight');
		$ni->setDecimals(1);
		$ni->setMinValue(0);
		$ni->setSize(4);
		$ni->setRequired(false);
		$this->form->addItem($ni);

		// minimum subscriptions
		if ($this->method->hasMinSubscription())
		{
			$ni = new ilNumberInputGUI($this->method->txt('sub_min_weight'), 'sub_min_weight');
			$ni->setDecimals(1);
			$ni->setMinValue(0);
			$ni->setSize(4);
			$ni->setRequired(false);
			$this->form->addItem($ni);
		}

		// maximum subscriptions
		if ($this->method->hasMaxSubscription())
		{
			$ni = new ilNumberInputGUI($this->method->txt('sub_max_weight'), 'sub_max_weight');
			$ni->setDecimals(1);
			$ni->setMinValue(0);
			$ni->setSize(4);
			$ni->setRequired(false);
			$this->form->addItem($ni);
		}

		if ($this->method->hasPeerSelection())
		{
			$ni = new ilNumberInputGUI($this->method->txt('peers_weight'), 'peers_weight');
			$ni->setDecimals(1);
			$ni->setMinValue(0);
			$ni->setSize(4);
			$ni->setRequired(false);
			$this->form->addItem($ni);
		}

		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues()
	{
		$hours = floor($this->method->time_limit / 3600);
		$rest = $this->method->time_limit % 3600;
		$minutes = floor($rest / 60);
		$seconds = $rest % 60;

		$this->form->getItemByPostVar('time_limit')->setHours($hours);
		$this->form->getItemByPostVar('time_limit')->setMinutes($minutes);
		$this->form->getItemByPostVar('time_limit')->setSeconds($seconds);
		$this->form->getItemByPostVar('max_iterations')->setValue($this->method->max_iterations);
		$this->form->getItemByPostVar('priority_weight')->setValue($this->method->priority_weight);
		$this->form->getItemByPostVar('sub_max_weight')->setValue($this->method->sub_max_weight);
		if ($this->method->hasMinSubscription())
		{
			$this->form->getItemByPostVar('sub_min_weight')->setValue($this->method->sub_min_weight);
		}
		if ($this->method->hasMaxSubscription())
		{
			$this->form->getItemByPostVar('sub_max_weight')->setValue($this->method->sub_max_weight);
		}
		if ($this->method->hasPeerSelection())
		{
			$this->form->getItemByPostVar('peers_weight')->setValue($this->method->peers_weight);
		}
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues()
	{
		$limit = $this->form->getInput('time_limit');
		$this->method->time_limit = $limit['hh']*3600 + $limit['mm']*60 + $limit['ss'];
		$this->method->max_iterations = $this->form->getInput('max_iterations');
		$this->method->priority_weight = $this->form->getInput('priority_weight');
		$this->method->sub_max_weight = $this->form->getInput('sub_max_weight');
		if ($this->method->hasMinSubscription())
		{
			$this->method->sub_min_weight = $this->form->getInput('sub_min_weight');
		}
		if ($this->method->hasMaxSubscription())
		{
			$this->method->sub_max_weight = $this->form->getInput('sub_max_weight');
		}
		if ($this->method->hasPeerSelection())
		{
			$this->method->peers_weight = $this->form->getInput('peers_weight');
		}
		$this->method->saveProperties();
	}
}