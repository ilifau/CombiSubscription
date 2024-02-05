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
	protected ilCoSubMethodRandom $method;

	/**
	 * ilCoSubMethodRandomPropertiesGUI constructor.
	 * @param ilObjCombiSubscriptionGUI $a_parent_gui
	 */
	public function __construct(ilObjCombiSubscriptionGUI $a_parent_gui)
	{
		parent::__construct($a_parent_gui);

		$this->method = $this->object->getMethodObject();
	}

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
	 * Edit the properties
	 */
	protected function editProperties(): void
	{
		$this->initPropertiesForm();
		$this->loadPropertiesValues();

		$description = $this->method->txt('properties_description');
		$this->tpl->setContent($this->pageInfo($description).$this->form->getHTML());

	}

	/**
	 * Update the properties
	 */
	protected function updateProperties(): void
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
	protected function initPropertiesForm(): void
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

		$ni = new ilNumberInputGUI($this->plugin->txt('tolerated_conflict_percentage'),'tolerated_conflict_percentage');
		$ni->setInfo($this->plugin->txt('tolerated_conflict_percentage_info'));
		$ni->setRequired(true);
		$ni->setSize(10);
		$ni->setMinValue(0);
		$ni->setMaxValue(100);
		$ni->allowDecimals(false);
		$this->form->addItem($ni);

		$this->form->addCommandButton('updateProperties', $this->lng->txt('save'));
	}

	/**
	 * Add spcific calculation settings to a properties form
	 * @param ilPropertyFormGUI $form
	 */
	public function addCalculationSettings(ilPropertyFormGUI $form): void
	{
		// workarounds switch
		$work = new ilCheckboxInputGUI($this->plugin->txt('calculation_workarounds'),'');
		$work->setInfo($this->plugin->txt('calculation_workarounds_info'));
		$form->addItem($work);

        // prefer filled items
        $pfil = new ilCheckboxInputGUI($this->method->txt('prefer_filled_items'),'prefer_filled_items');
        $pfil->setInfo($this->method->txt('prefer_filled_items_info'));
        $work->addSubItem($pfil);

        // forced item minimum
        $fmin = new ilCheckboxInputGUI($this->method->txt('forced_item_minimum'),'forced_item_minimum');
        $fmin->setInfo($this->method->txt('forced_item_minimum_info'));
            $fmin_num = new ilNumberInputGUI($this->method->txt('forced_item_minimum_num'), 'forced_item_minimum_num');
            $fmin_num->allowDecimals(false);
            $fmin_num->setMinValue(0);
            $fmin_num->setValue(0);
            $fmin->addSubItem($fmin_num);
        $work->addSubItem($fmin);
        
        // allow low filled users
		$lowu = new ilCheckboxInputGUI($this->method->txt('allow_low_filled_users'),'allow_low_filled_users');
		$lowu->setInfo($this->method->txt('allow_low_filled_users_info'));
		$work->addSubItem($lowu);

		// assume all items selected
		$asa = new ilCheckboxInputGUI($this->method->txt('assume_all_items_selected'),'assume_all_items_selected');
		$asa->setInfo($this->method->txt('assume_all_items_selected_info'));
		$work->addSubItem($asa);

		// assume sub min as limit
		$asm = new ilCheckboxInputGUI($this->method->txt('assume_sub_min_as_limit'),'assume_sub_min_as_limit');
		$asm->setInfo($this->method->txt('assume_sub_min_as_limit_info'));
		$work->addSubItem($asm);

        // fill fixed users
        $ffu = new ilCheckboxInputGUI($this->method->txt('fill_fixed_users'),'fill_fixed_users');
        $ffu->setInfo($this->method->txt('fill_fixed_users_info'));
        $work->addSubItem($ffu);

    }

	/**
	 * Apply the specific settings fom a posted properties form
	 * @param ilPropertyFormGUI $form
	 */
	public function applyCalculationSettings(ilPropertyFormGUI $form): void
	{
        $this->method->prefer_filled_items = (bool) $form->getInput('prefer_filled_items');
        if ($form->getInput('forced_item_minimum')) {
            $this->method->forced_item_minimum = (int) $form->getInput('forced_item_minimum_num');
        }
		$this->method->allow_low_filled_users = (bool) $form->getInput('allow_low_filled_users');
		$this->method->assume_all_items_selected = (bool) $form->getInput('assume_all_items_selected');
		$this->method->assume_sub_min_as_limit = (bool) $form->getInput('assume_sub_min_as_limit');
        $this->method->fill_fixed_users = (bool) $form->getInput('fill_fixed_users');
	}


	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues(): void
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
		$this->form->getItemByPostVar('tolerated_conflict_percentage')->setValue($this->method->getToleratedConflictPercentage());
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues(): void
	{
		$this->method->number_priorities = (int) $this->form->getInput('number_priorities');
		$this->method->priority_choices = (string) $this->form->getInput('priority_choices');
		$this->method->number_assignments = (int) $this->form->getInput('number_assignments');

		/** @var ilDurationInputGUI $di */
		$di = $this->form->getItemByPostVar('out_of_conflict_time');
		$seconds = $di->getHours() * 3600 + $di->getMinutes() * 60;
		$this->method->out_of_conflict_time = max($seconds, $this->plugin->getOutOfConflictTime());

		$this->method->tolerated_conflict_percentage = (int) $this->form->getInput('tolerated_conflict_percentage');

		$this->method->saveProperties();
	}
}