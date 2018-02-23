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

		$description = $this->method->txt('properties_description');
		$this->tpl->setContent($this->pageInfo($description).$this->form->getHTML());

	}

	/**
	 * Update the properties
	 */
	protected function updateProperties()
	{
		$this->initPropertiesForm();
		if ($this->form->checkInput())
		{
			$this->form->setValuesByPost(); // needed for duration input
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

		// selection properties
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->method->txt('selection_properties'));
		$this->form->addItem($sh);

		// number of priorities
		$ni = new ilNumberInputGUI($this->method->txt('number_priorities'), 'number_priorities');
		$ni->setInfo($this->method->txt('number_priorities_info'));
		$ni->setDecimals(0);
		$ni->setSize(2);
		$ni->setMinValue(1);
		$ni->setValue(2);
		$ni->setRequired(true);
		$this->form->addItem($ni);

		// priority_choices
		$pc = new ilRadioGroupInputGUI($this->method->txt('prio_choices'), 'priority_choices');
		$pc->addOption(new ilRadioOption(
			$this->method->txt('prio_choices_free'),
			ilCoSubMethodRandom::PRIO_CHOICES_FREE,
			$this->method->txt('prio_choices_free_info')));
		$pc->addOption(new ilRadioOption(
			$this->method->txt('prio_choices_limited'),
			ilCoSubMethodRandom::PRIO_CHOICES_LIMITED,
			$this->method->txt('prio_choices_limited_info')));
		$pc->addOption(new ilRadioOption(
			$this->method->txt('prio_choices_unique'),
			ilCoSubMethodRandom::PRIO_CHOICES_UNIQUE,
			$this->method->txt('prio_choices_unique_info')));
		$this->form->addItem($pc);

		// calculation proerties
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->method->txt('calculation_properties'));
		$this->form->addItem($sh);

		// number of assignments
		$ni = new ilNumberInputGUI($this->method->txt('number_assignments'), 'number_assignments');
		$ni->setInfo($this->method->txt('number_assignments_info'));
		$ni->setDecimals(0);
		$ni->setSize(2);
		$ni->setMinValue(1);
		$ni->setValue(2);
		$ni->setRequired(true);
		$this->form->addItem($ni);

		// out of conflict time
		$global_seconds = (int) $this->plugin->getOutOfConflictTime();
		$global_minutes = (int) ($global_seconds / 60);

		$di = new ilDurationInputGUI($this->method->txt('out_of_conflict_time'), 'out_of_conflict_time');
		$di->setInfo(sprintf($this->method->txt('out_of_conflict_time_info'), $global_minutes));
		$di->setShowMonths(false);
		$di->setShowDays(false);
		$di->setShowHours(true);
		$di->setShowMinutes(true);
		$di->setShowSeconds(false);
		$this->form->addItem($di);

		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues()
	{
		$this->form->getItemByPostVar('number_priorities')->setValue($this->method->number_priorities);
		$this->form->getItemByPostVar('priority_choices')->setValue($this->method->priority_choices);
		$this->form->getItemByPostVar('number_assignments')->setValue($this->method->number_assignments);

		$seconds = (int) max($this->method->getOutOfConflictTime(), $this->plugin->getOutOfConflictTime());
		$hours = (int) ($seconds / 3600);
		$seconds = $seconds % 3600;
		$minutes = (int) ($seconds / 60);

		$this->form->getItemByPostVar('out_of_conflict_time')->setHours($hours);
		$this->form->getItemByPostVar('out_of_conflict_time')->setMinutes($minutes);
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues()
	{
		$this->method->number_priorities = (int) $this->form->getInput('number_priorities');
		$this->method->priority_choices = (string) $this->form->getInput('priority_choices');
		$this->method->number_assignments = (int) $this->form->getInput('number_assignments');

		/** @var ilDurationInputGUI $di */
		$di = $this->form->getItemByPostVar('out_of_conflict_time');
		$seconds = $di->getHours() * 3600 + $di->getMinutes() * 60;
		$this->method->out_of_conflict_time = max($seconds, $this->plugin->getOutOfConflictTime());

		$this->method->saveProperties();
	}
}