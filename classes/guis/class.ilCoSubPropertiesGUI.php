<?php

/**
 * Edit basic properties
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubPropertiesGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubPropertiesGUI extends ilCoSubBaseGUI
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
	 * Edit poperties
	 */
	protected function editProperties(): void
	{
		$this->initPropertiesForm();
		$this->loadPropertiesValues();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Update properties
	 */
	protected function updateProperties(): void
	{
		global $DIC;
		
		$this->initPropertiesForm();
		if ($this->form->checkInput())
		{
			$this->savePropertiesValues();
			$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
			$this->ctrl->redirect($this, 'editProperties');
		}
		else
		{
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHTML());
		}
	}

	/**
	 * Init the form
	 */
	protected function initPropertiesForm(): void
	{
		$this->form = new ilPropertyFormGUI();

		// title
		$ti = new ilTextInputGUI($this->plugin->txt('title'), 'title');
		$ti->setRequired(true);
		$this->form->addItem($ti);

		// description
		$ta = new ilTextAreaInputGUI($this->plugin->txt('description'), 'description');
		$this->form->addItem($ta);

		// online
		$cb = new ilCheckboxInputGUI($this->lng->txt('online'), 'online');
		$this->form->addItem($cb);

		// explanation
		$ex = new ilTextAreaInputGUI($this->plugin->txt('explanation'), 'explanation');
		$ex->setInfo($this->plugin->txt('explanation_info'));
		$this->form->addItem($ex);

		// subscription start
		$start = new ilDateTimeInputGUI($this->plugin->txt('sub_start'),'sub_start');
		$start->setRequired(true);
		$start->setShowTime(true);
		$this->form->addItem($start);

		// subscription end
		$end = new ilDateTimeInputGUI($this->plugin->txt('sub_end'),'sub_end');
		$end->setRequired(true);
		$end->setShowTime(true);
		$this->form->addItem($end);

		// show bars
		$show_bars = new ilCheckboxInputGUI($this->plugin->txt('show_bars'), 'show_bars');
		$show_bars->setInfo($this->plugin->txt('show_bars_info'));
		$show_bars->setChecked(true);
		$this->form->addItem($show_bars);

		// pre select
		$pre_select = new ilCheckboxInputGUI($this->plugin->txt('pre_select'), 'pre_select');
		$pre_select->setInfo($this->plugin->txt('pre_select_info'));
		$pre_select->setChecked(true);
		$this->form->addItem($pre_select);

		// minimum choices
		$min_choices = new ilNumberInputGUI($this->plugin->txt('min_choices'), 'min_choices');
		$min_choices->setRequired(true);
		$min_choices->setInfo($this->plugin->txt('min_choices_info'));
		$min_choices->setSize(3);
		$min_choices->setDecimals(0);
		$min_choices->setValue(0);
        $min_choices->setMinValue(0);
		$this->form->addItem($min_choices);

		// method
		$methods = array();
		foreach ($this->object->getAvailableMethods() as $method_obj)
		{
			if ($method_obj->isActive() || $method_obj->getId() == $this->object->getMethod())
			{
				$methods[] = $method_obj;
			}
		}
		if (count($methods) > 1)
		{
			$method = new ilRadioGroupInputGUI($this->plugin->txt('assignment_method'), 'method');
			$method->setRequired(true);
			foreach ($methods as $method_obj)
			{
				$option = new ilRadioOption($method_obj->getTitle(), $method_obj->getId(), $method_obj->getDescription());
				$option->setDisabled(!$method_obj->isActive());
				$method->addOption($option);
			}
		}
		else
		{
			$method = new ilHiddenInputGUI('method');
		}
		$this->form->addItem($method);

        $targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
        $config = new ilCoSubTargetsConfig($this->object);
        $config->readFromObject();

        if ($this->plugin->withCronJob() && $this->object->getMethodObject()->hasInstantResult())
		{
			// auto process
			$auto = new ilCheckboxInputGUI($this->plugin->txt('auto_process'), 'auto_process');
			$auto->setInfo($this->plugin->txt('auto_process_info'));

			foreach ($targets->getFormProperties('auto', $config) as $property)
			{
				$auto->addSubItem($property);
			}
			$this->form->addItem($auto);

			// last process
			if (is_object($this->object->getLastProcess()))
			{
				$last = new ilNonEditableValueGUI($this->plugin->txt('last_process'), 'last_process');
				$last->setValue(ilDatePresentation::formatDate($this->object->getLastProcess()));
				$this->form->addItem($last);
			}
		}

		$emails = $auto = new ilCheckboxInputGUI($this->plugin->txt('send_target_emails'), 'send_target_emails');
        $emails->setInfo($this->plugin->txt('send_target_emails_info'));
        $emails->setChecked($config->send_target_emails);
        $this->form->addItem($emails);

		$this->form->addCommandButton('updateProperties', $this->plugin->txt('save'));

		$this->form->setTitle($this->plugin->txt('edit_properties'));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}

	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues(): void
	{
		$this->form->getItemByPostVar('title')->setValue($this->object->getTitle());
		$this->form->getItemByPostVar('description')->setValue($this->object->getDescription());
		$this->form->getItemByPostVar('online')->setChecked($this->object->getOnline());
		$this->form->getItemByPostVar('explanation')->setValue($this->object->getExplanation());
		$this->form->getItemByPostVar('sub_start')->setDate($this->object->getSubscriptionStart());
		$this->form->getItemByPostVar('sub_end')->setDate($this->object->getSubscriptionEnd());
		$this->form->getItemByPostVar('show_bars')->setChecked($this->object->getShowBars());
		$this->form->getItemByPostVar('pre_select')->setChecked($this->object->getPreSelect());
		$this->form->getItemByPostVar('min_choices')->setValue($this->object->getMinChoices());
		$this->form->getItemByPostVar('method')->setValue($this->object->getMethod());
		if ($this->plugin->withCronJob() && $this->object->getMethodObject()->hasInstantResult())
		{
			$this->form->getItemByPostVar('auto_process')->setChecked($this->object->getAutoProcess());
		}
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues(): void
	{
		/** @var ilDateTimeInputGUI $start */
		$start = $this->form->getItemByPostVar('sub_start');

		/** @var ilDateTimeInputGUI $end */
		$end = $this->form->getItemByPostVar('sub_end');

		$this->object->setTitle($this->form->getInput('title'));
		$this->object->setDescription($this->form->getInput('description'));
		$this->object->setOnline($this->form->getInput('online'));
		$this->object->setExplanation($this->form->getInput('explanation'));
		$this->object->setSubscriptionStart($start->getDate());
		$this->object->setSubscriptionEnd($end->getDate());
		$this->object->setShowBars($this->form->getInput('show_bars'));
		$this->object->setPreSelect($this->form->getInput('pre_select'));
		$this->object->setMinChoices($this->form->getInput('min_choices'));
		$this->object->setMethod($this->form->getInput('method'));

        $targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
        $config = new ilCoSubTargetsConfig($this->object);
        $config->readFromObject();

        if ($this->plugin->withCronJob() && $this->object->getMethodObject()->hasInstantResult())
		{
			$this->object->setAutoProcess($this->form->getInput('auto_process'));
            $config = $targets->getFormInputs($this->form, 'auto', $config);
		}

		$config->send_target_emails = $this->form->getInput('send_target_emails');
        $config->saveInObject();
		$this->object->update();
	}
}