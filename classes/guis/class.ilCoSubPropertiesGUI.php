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
	public function executeCommand()
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
	protected function editProperties()
	{
		$this->initPropertiesForm();
		$this->loadPropertiesValues();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Update properties
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
	 * Init the form
	 */
	protected function initPropertiesForm()
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
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

		// description
		$ex = new ilTextAreaInputGUI($this->plugin->txt('explanation'), 'explanation');
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
		$this->form->addItem($min_choices);

		// method
		$method = new ilRadioGroupInputGUI($this->plugin->txt('assignment_method'), 'method');
		$method->setRequired(true);
		foreach ($this->object->getAvailableMethods() as $method_obj)
		{
			if (!$method_obj->isActive())
			{
				continue;
			}
			$option = new ilRadioOption($method_obj->getTitle(), $method_obj->getId(), $method_obj->getDescription());
			$option->setDisabled(!$method_obj->isActive());
			$method->addOption($option);
		}
		$this->form->addItem($method);

		$this->form->addCommandButton('updateProperties', $this->plugin->txt('save'));

		$this->form->setTitle($this->plugin->txt('edit_properties'));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}

	/**
	 * Load the properties values in the form
	 */
	protected function loadPropertiesValues()
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
	}

	/**
	 * Save the properties values from the form
	 */
	protected function savePropertiesValues()
	{
		$start = $this->form->getInput('sub_start');
		$end = $this->form->getInput('sub_end');

		$this->object->setTitle($this->form->getInput('title'));
		$this->object->setDescription($this->form->getInput('description'));
		$this->object->setOnline($this->form->getInput('online'));
		$this->object->setExplanation($this->form->getInput('explanation'));
		$this->object->setSubscriptionStart(new ilDateTime($start['date'].' '.$start['time'], IL_CAL_DATETIME));
		$this->object->setSubscriptionEnd(new ilDateTime($end['date'].' '.$end['time'], IL_CAL_DATETIME));
		$this->object->setShowBars($this->form->getInput('show_bars'));
		$this->object->setPreSelect($this->form->getInput('pre_select'));
		$this->object->setMinChoices($this->form->getInput('min_choices'));
		$this->object->setMethod($this->form->getInput('method'));
		$this->object->update();
	}
}