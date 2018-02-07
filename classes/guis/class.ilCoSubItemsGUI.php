<?php

/**
 * Managing class for combined subscription items
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubItemsGUI: ilObjCombiSubscriptionGUI
 * @ilCtrl_Calls: ilCoSubItemsGUI: ilPropertyFormGUI
 */
class ilCoSubItemsGUI extends ilCoSubBaseGUI
{
	/** @var ilObjCombiSubscriptionGUI */
	public $parent;

	/** @var  ilObjCombiSubscription */
	public $object;

	/** @var  ilCombiSubscriptionPlugin */
	public $plugin;

	/** @var  ilCtrl */
	public $ctrl;

	/** @var ilTemplate */
	public $tpl;

	/** @var ilLanguage */
	public $lng;

	/** @var ilPropertyFormGUI */
	protected $form;


	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$this->plugin->includeClass('models/class.ilCoSubItem.php');
		$this->plugin->includeClass('models/class.ilCoSubSchedule.php');

		$next_class = $this->ctrl->getNextClass();
		switch ($next_class)
		{
			// items import
			case 'ilcosubitemsimportgui':
				$this->plugin->includeClass('abstract/class.ilCoSubImportBaseGUI.php');
				$this->plugin->includeClass('guis/class.ilCoSubItemsImportGUI.php');
				$this->ctrl->setReturn($this, 'listItems');
				$this->ctrl->forwardCommand(new ilCoSubItemsImportGUI($this->parent));
				return;

			// repository item selection
			case "ilpropertyformgui":
				$this->initItemForm(empty($_GET['item_id']) ? 'create' : 'edit');
				$this->ctrl->saveParameter($this, 'item_id');
				$this->ctrl->setReturn($this, "setTargetObject");
				$this->ctrl->forwardCommand($this->form);
				return;
		}

		$cmd = $this->ctrl->getCmd('listItems');
		switch ($cmd)
		{
			case 'listItems':
			case 'createItem':
			case 'saveItem':
			case 'editItem':
			case 'updateItem':
			case 'confirmDeleteItems':
			case 'deleteItems':
			case 'setTargetObject':
			case 'addRepositoryItems':
			case 'saveRepositoryItems':
			case 'saveSorting':
			case 'configureTargets':
			case 'saveTargetsConfig':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * List the registration items
	 */
	protected function listItems()
	{
		global $ilToolbar;
		/** @var ilToolbarGUI $ilToolbar */
		$ilToolbar->setFormAction($this->ctrl->getFormAction($this));
		$ilToolbar->addFormButton($this->plugin->txt('create_item'), 'createItem');
		$ilToolbar->addSeparator();
		$ilToolbar->addFormButton($this->plugin->txt('add_repository_items'),'addRepositoryItems');

		require_once('Services/UIComponent/Button/classes/class.ilLinkButton.php');
		$button = ilLinkButton::getInstance();
		$button->setCaption($this->plugin->txt('import_items'), false);
		$button->setUrl($this->ctrl->getLinkTargetByClass('ilCoSubItemsImportGUI', 'showImportForm'));
		$ilToolbar->addButtonInstance($button);

		$this->plugin->includeClass('guis/class.ilCoSubItemsTableGUI.php');
		$table_gui = new ilCoSubItemsTableGUI($this, 'listItems');
		$table_gui->prepareData($this->object->getItems());

		$description = implode(' ', array(
			$this->plugin->txt('items_description'),
			$this->plugin->txt('items_description_targets'),
			$this->plugin->txt('items_description_transfer')));

		$this->tpl->setContent($this->pageInfo($description).$table_gui->getHTML());
	}

	/**
	 * Select multiple repository objects to be added as items in one setp
	 */
	protected function addRepositoryItems()
	{
		require_once('Services/Form/classes/class.ilFormGUI.php');
		$form_gui = new ilFormGUI();
		$form_gui->setFormAction($this->ctrl->getFormAction($this, 'listItems'));
		$form_gui->setKeepOpen(true);
		$html = $form_gui->getHTML();

		require_once('Services/Repository/classes/class.ilRepositorySelectorExplorerGUI.php');
		$selector_gui = new ilRepositorySelectorExplorerGUI($this,'addRepositoryItems',$this, 'saveRepositoryItems');
		$selector_gui->setTypeWhiteList(array_merge(array('root','cat','crs','grp','fold'), $this->plugin->getAvailableTargetTypes()));
		$selector_gui->setClickableTypes($this->plugin->getAvailableTargetTypes());
		$selector_gui->setSelectMode('ref_id', true);
		if ($selector_gui->handleCommand())
		{
			return;
		}
		$html = $html . $selector_gui->getHTML();

		require_once('Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php');
		$toolbar_gui = new ilToolbarGUI();
		$toolbar_gui->addFormButton($this->lng->txt('select'),'saveRepositoryItems');
		$toolbar_gui->addFormButton($this->lng->txt('cancel'),'listItems');
		$toolbar_gui->setOpenFormTag(false);
		$toolbar_gui->setCloseFormTag(true);
		$html = $html . $toolbar_gui->getHTML();

		$this->tpl->setContent($html);
	}

	/**
	 * save the items for the selected repository objects
	 */
	protected function saveRepositoryItems()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);

		foreach ($_POST['ref_id'] as $ref_id)
		{
			$item = $targets->getItemForTarget($ref_id);
			$item->save();
		}
		ilUtil::sendSuccess($this->plugin->txt(count($_POST['item_ids']) == 1  ? 'msg_item_created' : 'msg_items_created'), true);
		$this->ctrl->redirect($this, 'listItems');
	}


	/**
	 * Show form to create a new item
	 */
	protected function createItem()
	{
		$this->initItemForm();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Save a new item
	 */
	protected function saveItem()
	{
		$this->initItemForm();
		if ($this->form->checkInput())
		{
			$item = new ilCoSubItem();
			$this->saveItemProperties($item);
			$this->saveSchedulesProperties($item);

			ilUtil::sendSuccess($this->plugin->txt('msg_item_created'), true);
			$this->ctrl->redirect($this, 'listItems');
		}
		else
		{
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHtml());
		}
	}

	/**
	 * Show form to edit an item
	 */
	protected function editItem()
	{
		$this->ctrl->saveParameter($this, 'item_id');
		$item = ilCoSubItem::_getById($_GET['item_id']);
		$schedules = ilCoSubSchedule::_getForObject($this->object->getId(), $_GET['item_id']);
		$this->initItemForm($item, $schedules);
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Update an existing item
	 */
	protected function updateItem()
	{
		$this->ctrl->saveParameter($this, 'item_id');
		$item = ilCoSubItem::_getById($_GET['item_id']);
		$schedules = ilCoSubSchedule::_getForObject($this->object->getId(), $_GET['item_id']);
		$this->initItemForm($item, $schedules);
		if ($this->form->checkInput())
		{
			$item = ilCoSubItem::_getById($_GET['item_id']);
			$this->saveItemProperties($item);
			$this->saveSchedulesProperties($item, $schedules);
			ilUtil::sendSuccess($this->plugin->txt('msg_item_updated'), true);
			$this->ctrl->redirect($this, 'listItems');
		}
		else
		{
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHtml());
		}
	}

	/**
	 * Confirm the ddeletion of items
	 */
	protected function confirmDeleteItems()
	{
		if (empty($_POST['item_ids']))
		{
			ilUtil::sendFailure($this->lng->txt('select_at_least_one_object'), true);
			$this->ctrl->redirect($this,'listItems');
		}

		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this));
		$conf_gui->setHeaderText($this->plugin->txt('confirm_delete_items'));
		$conf_gui->setConfirm($this->lng->txt('delete'),'deleteItems');
		$conf_gui->setCancel($this->lng->txt('cancel'), 'listItems');

		foreach($_POST['item_ids'] as $item_id)
		{
			$item = ilCoSubItem::_getById($item_id);
			$conf_gui->addItem('item_ids[]', $item_id, $item->title);
		}

		$this->tpl->setContent($conf_gui->getHTML());
	}

	/**
	 * Delete confirmed items
	 */
	protected function deleteItems()
	{
		foreach($_POST['item_ids'] as $item_id)
		{
			ilCoSubItem::_deleteById($item_id);
		}
		ilUtil::sendSuccess($this->plugin->txt(count($_POST['item_ids']) == 1  ? 'msg_item_deleted' : 'msg_items_deleted'), true);
		$this->ctrl->redirect($this, 'listItems');
	}

	/**
	 * Save the sorting
	 */
	protected function saveSorting()
	{
		$sort = $_POST['item_sort'];
		asort($sort, SORT_NUMERIC);

		$position = 0;
		foreach($sort as $item_id => $sort_value)
		{
			$item = ilCoSubItem::_getById($item_id);
			$item->sort_position = $position;
			$item->save();
			$position++;
		}

		$this->ctrl->redirect($this, 'listItems');
	}


	/**
	 * Set the target object
	 */
	protected function setTargetObject()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);

		$this->ctrl->saveParameter($this, 'item_id');


		// get the existing properties
		if (!empty($_GET['item_id']))
		{
			$item = ilCoSubItem::_getById($_GET['item_id']);
		}
		else
		{
			$item = new ilCoSubItem;
		}

		/** @var ilRepositorySelectorInputGUI $target_ref_id */
		$target_input = $this->form->getItemByPostVar('target_ref_id');
		$target_input->readFromSession();

		$target_ref_id = $target_input->getValue();

		// unsaved item with values generated from the target object
		$item = $targets->getItemForTarget($target_ref_id, $item);
		$schedules = $targets->getSchedulesForTarget($target_ref_id);

		$this->initItemForm($item, $schedules);

		if (!empty($values->title))
		{
			$this->form->getItemByPostVar('title')->setValue($values->title);
		}
		if (!empty($values->description))
		{
			$this->form->getItemByPostVar('description')->setValue($values->description);
		}
		if (!empty($values->sub_min))
		{
			$this->form->getItemByPostVar('sub_min')->setValue($values->sub_min);
		}
		if (!empty($values->sub_max))
		{
			$this->form->getItemByPostVar('sub_max')->setValue($values->sub_max);
		}

		if (!empty($values->period_start) &&  !empty($values->period_end))
		{
			/** @var ilCheckboxInputGUI $period */
			$period = $this->form->getItemByPostVar('period');
			$period->setChecked(true);

			/** @var ilDateTimeInputGUI $start */
			$start = $this->form->getItemByPostVar('period_start');
			$start->setDate(new ilDateTime($values->period_start, IL_CAL_UNIX));

			/** @var ilDateTimeInputGUI $end */
			$end = $this->form->getItemByPostVar('period_end');
			$end->setDate(new ilDateTime($values->period_end, IL_CAL_UNIX));
		}

		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Init the Item form
	 * @param ilCoSubItem $a_item
	 * @param ilCoSubSchedule[] $a_schedules
	 */
	protected function initItemForm($a_item = null, $a_schedules = array())
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		include_once('Services/Form/classes/class.ilRepositorySelectorInputGUI.php');
		$this->form = new ilPropertyFormGUI();

		if (!isset($a_item)) {
			$a_item = new ilCoSubItem;
		}
		// add empty schedule to create a new schedule input
		$a_schedules[] = new ilCoSubSchedule();

		// target
		$rs = new ilRepositorySelectorInputGUI($this->plugin->txt('target_object'), 'target_ref_id');
		$rs->setClickableTypes($this->plugin->getAvailableTargetTypes());
		$rs->setInfo($this->plugin->txt('target_object_info'));
		$rs->setHeaderMessage($this->plugin->txt('select_target_object'));
		$rs->setValue($a_item->target_ref_id);
		$this->form->addItem($rs);

		// title
		$ti = new ilTextInputGUI($this->plugin->txt('title'), 'title');
		$ti->setRequired(true);
		$ti->setValue($a_item->title);
		$this->form->addItem($ti);

		// description
		$ta = new ilTextAreaInputGUI($this->plugin->txt('description'), 'description');
		$ta->setValue($a_item->description);
		$this->form->addItem($ta);

		// identifier
		$ti = new ilTextInputGUI($this->plugin->txt('identifier'), 'identifier');
		$ti->setInfo($this->plugin->txt('identifier_info'));
		$ti->setRequired(false);
		$ti->setValue($a_item->identifier);
		$this->form->addItem($ti);

		// category
		$cat_options = array('0' => $this->plugin->txt('no_category_selected'));
		foreach($this->object->getCategories() as $category)
		{
			$cat_options[$category->cat_id] = $category->title;
		}
		$si = new ilSelectInputGUI($this->plugin->txt('category'), 'cat_id');
		$si->setInfo($this->plugin->txt('category_info'));
		$si->setOptions($cat_options);
		$si->setValue($a_item->cat_id);
		$this->form->addItem($si);

		if ($this->object->getMethodObject()->hasMinSubscription())
		{
			// minimum subscriptions
			$sm = new ilNumberInputGUI($this->plugin->txt('sub_min'), 'sub_min');
			$sm->setInfo($this->object->getMethodObject()->txt('sub_min_info'));
			$sm->setDecimals(0);
			$sm->setSize(4);
			$sm->setRequired(false);
			$sm->setValue($a_item->sub_min);
			$this->form->addItem($sm);
		}

		if ($this->object->getMethodObject()->hasMaxSubscription())
		{
			// maximum subscriptions
			$sm = new ilNumberInputGUI($this->plugin->txt('sub_max'), 'sub_max');
			$sm->setInfo($this->object->getMethodObject()->txt('sub_max_info'));
			$sm->setDecimals(0);
			$sm->setSize(4);
			$sm->setRequired(false);
			$sm->setValue($a_item->sub_max);
			$this->form->addItem($sm);
		}

		// selectable
		$selectable = new ilCheckboxInputGUI($this->plugin->txt('item_selectable'), 'selectable');
		$selectable->setInfo($this->plugin->txt('item_selectable_info'));
		$selectable->setChecked($a_item->selectable);
		$this->form->addItem($selectable);

		// schedules
//		$sh = new ilFormSectionHeaderGUI();
//		$sh->setTitle($this->plugin->txt('schedules'));
//		$this->form->addItem($sh);

		include_once "Modules/BookingManager/classes/class.ilScheduleInputGUI.php";
		foreach ($a_schedules as $schedule)
		{
			$id = (int) $schedule->schedule_id;

			$group = new ilRadioGroupInputGUI($this->plugin->txt('schedule'), 'schedule_' .$id);

			$none = new ilRadioOption($this->plugin->txt('schedule_none'), 'none');
			$group->addOption($none);

			$single = new ilRadioOption($this->plugin->txt('schedule_single'), 'single');
				//single start
				$start = new ilDateTimeInputGUI($this->plugin->txt('period_start'),'period_start_'.$id);
				if (isset($schedule->period_start)) {
					$start->setDate(new ilDateTime($schedule->period_start, IL_CAL_UNIX));
				}
				else {
					$start->setDate(new ilDateTime(date('Y-m-d').' 08:00:00',IL_CAL_DATETIME));
				}
				$start->setRequired(true);
				$start->setShowTime(true);
				$single->addSubItem($start);

				// single end
				$end = new ilDateTimeInputGUI($this->plugin->txt('period_end'),'period_end'.$id);
				if (isset($schedule->period_end)) {
					$end->setDate(new ilDateTime($schedule->period_end, IL_CAL_UNIX));
				}
				else {
					$end->setDate(new ilDateTime(date('Y-m-d').' 16:00:00',IL_CAL_DATETIME));
				}
				$end->setRequired(true);
				$end->setShowTime(true);
				$single->addSubItem($end);

			$group->addOption($single);

			$multi = new ilRadioOption($this->plugin->txt('schedule_multi'), 'multi');

				// multi first
				$first = new ilDateTimeInputGUI($this->plugin->txt('period_first'),'period_first_'.$id);
				if (isset($schedule->period_start)) {
					$first->setDate(new ilDateTime($schedule->period_start, IL_CAL_UNIX));
				}
				else {
					$first->setDate(new ilDateTime(date('Y-m-d').' 00:00:00',IL_CAL_DATETIME));
				}
				$first->setRequired(true);
				$first->setShowTime(false);
				$multi->addSubItem($first);

				// multi last
				$last = new ilDateTimeInputGUI($this->plugin->txt('period_last'),'period_last_'.$id);
				if (isset($schedule->period_start)) {
					$last->setDate(new ilDateTime($schedule->period_start, IL_CAL_UNIX));
				}
				else {
					$last->setDate(new ilDateTime(date('Y-m-d').' 23:59:59',IL_CAL_DATETIME));
				}
				$last->setRequired(true);
				$last->setShowTime(false);
				$multi->addSubItem($last);


				$slots = new ilScheduleInputGUI($this->plugin->txt("period_slots"), "slots_".$id);
				$slots->setRequired(true);
				$slots->setValue($schedule->getSlotsForInput());
				$multi->addSubItem($slots);

			$group->addOption($multi);

			if (empty($id)) {
				$group->setValue('none');
			}
			elseif (empty($schedule->slots)) {
				$group->setValue('single');
			}
			else {
				$group->setValue('multi');
			}
			$this->form->addItem($group);
		}

		if (empty($a_item->item_id))
		{
			$this->form->setTitle($this->plugin->txt('create_item'));
			$this->form->addCommandButton('saveItem', $this->lng->txt('save'));
		}
		else
		{
			$this->form->setTitle($this->plugin->txt('edit_item'));
			$this->form->addCommandButton('updateItem', $this->lng->txt('save'));
		}

		$this->form->addCommandButton('listItems', $this->lng->txt('cancel'));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}


	/**
	 * Initialize the form to configure the targets commonly
	 */
	protected function initTargetsForm($a_type)
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);

		$this->form = new ilPropertyFormGUI();
		$this->form->setTitle($this->plugin->txt('configure_targets_'.$a_type));

		$set_type = new ilCheckboxInputGUI($this->plugin->txt('set_sub_type'), 'set_sub_type');
		$set_type->setInfo($this->plugin->txt('set_sub_type_info'));
		$set_type->setChecked($this->object->getPreference('ilCoSubItemsGUI', 'set_sub_type', true));
		$this->form->addItem($set_type);

		$sub_type = new ilRadioGroupInputGUI($this->plugin->txt('sub_type'), 'sub_type');
		$opt = new ilRadioOption($this->plugin->txt('sub_type_combi'), ilCombiSubscriptionTargets::SUB_TYPE_COMBI);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_direct'), ilCombiSubscriptionTargets::SUB_TYPE_DIRECT);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_confirm'), ilCombiSubscriptionTargets::SUB_TYPE_CONFIRM);
		$sub_type->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_type_none'), ilCombiSubscriptionTargets::SUB_TYPE_NONE);
		$sub_type->addOption($opt);
		$sub_type->setValue($this->object->getPreference('ilCoSubItemsGUI', 'sub_type', ilCombiSubscriptionTargets::SUB_TYPE_COMBI));
		$set_type->addSubItem($sub_type);

		if ($targets->hasSubscriptionPeriod($a_type))
		{
			$set_sub_period = new ilCheckboxInputGUI($this->plugin->txt('set_sub_period'), 'set_sub_period');
			$set_sub_period->setInfo($this->plugin->txt('set_sub_period_info'));
			$set_sub_period->setChecked($this->object->getPreference('ilCoSubItemsGUI', 'set_sub_period', false));
			$this->form->addItem($set_sub_period);

			include_once "Services/Form/classes/class.ilDateDurationInputGUI.php";
			$sub_period = new ilDateDurationInputGUI($this->plugin->txt('sub_period'), "sub_period");
			$sub_period->setShowTime(true);
			$sub_period->setStart(new ilDateTime($this->object->getPreference('ilCoSubItemsGUI', 'sub_period_start', time()),IL_CAL_UNIX));
			$sub_period->setStartText($this->plugin->txt('sub_period_start'));
			$sub_period->setEnd(new ilDateTime($this->object->getPreference('ilCoSubItemsGUI', 'sub_period_end', time()),IL_CAL_UNIX));
			$sub_period->setEndText($this->plugin->txt('sub_period_end'));
			$set_sub_period->addSubItem($sub_period);
		}

		if ($this->object->getMethodObject()->hasMinSubscription() && $targets->hasMinSubscriptions($a_type))
		{
			$set_min = new ilCheckboxInputGUI($this->plugin->txt('set_sub_min'), 'set_sub_min');
			$set_min->setInfo($this->plugin->txt('set_sub_min_info'));
			$set_min->setChecked($this->object->getPreference('ilCoSubItemsGUI', 'set_sub_min', true));
			$this->form->addItem($set_min);
		}

		if ($this->object->getMethodObject()->hasMaxSubscription()) {
			$set_max = new ilCheckboxInputGUI($this->plugin->txt('set_sub_max'), 'set_sub_max');
			$set_max->setInfo($this->plugin->txt('set_sub_max_info'));
			$set_max->setChecked($this->object->getPreference('ilCoSubItemsGUI', 'set_sub_max', true));
			$this->form->addItem($set_max);
		}

		$set_wait = new ilCheckboxInputGUI($this->plugin->txt('set_sub_wait'), 'set_sub_wait');
		$set_wait->setInfo($this->plugin->txt('set_sub_wait_info'));
		$set_wait->setChecked($this->object->getPreference('ilCoSubItemsGUI', 'set_sub_wait', true));
		$this->form->addItem($set_wait);

		$sub_wait = new ilRadioGroupInputGUI($this->plugin->txt('sub_wait'), 'sub_wait');
		$sub_wait->setValue($this->object->getPreference('ilCoSubItemsGUI', 'sub_wait', ilCombiSubscriptionTargets::SUB_WAIT_AUTO));
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_auto'), ilCombiSubscriptionTargets::SUB_WAIT_AUTO);
		$sub_wait->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_manu'), ilCombiSubscriptionTargets::SUB_WAIT_MANU);
		$sub_wait->addOption($opt);
		$opt = new ilRadioOption($this->plugin->txt('sub_wait_none'), ilCombiSubscriptionTargets::SUB_WAIT_NONE);
		$sub_wait->addOption($opt);
		$set_wait->addSubItem($sub_wait);

		$this->form->addCommandButton('saveTargetsConfig', $this->plugin->txt('save_target_config'));
		$this->form->addCommandButton('listItems', $this->lng->txt('cancel'));

		$this->ctrl->setParameter($this, 'type', $a_type);
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}



	/**
	 * Load the properties of an item to the form
	 * @param ilCoSubItem   $a_item
	 */
	protected function loadItemProperties($a_item)
	{
		$this->form->setValuesByArray(
			array(
				'identifier' => $a_item->identifier,
				'title' => $a_item->title,
				'description' => $a_item->description,
				'target_ref_id' => $a_item->target_ref_id,
				'cat_id' => $a_item->cat_id,
				'sub_min' => $a_item->sub_min,
				'sub_max' => $a_item->sub_max,
				'selectable' => $a_item->selectable
			)
		);

		/** @var ilCheckboxInputGUI $period */
		$period = $this->form->getItemByPostVar('period');
		if (empty($a_item->period_start) || empty($a_item->period_end))
		{
			$period->setChecked(false);
		}
		else
		{
			$period->setChecked(true);

			/** @var ilDateTimeInputGUI $start */
			$start = $this->form->getItemByPostVar('period_start');
			$start->setDate(new ilDateTime($a_item->period_start, IL_CAL_UNIX));

			/** @var ilDateTimeInputGUI $end */
			$end = $this->form->getItemByPostVar('period_end');
			$end->setDate(new ilDateTime($a_item->period_end, IL_CAL_UNIX));
		}
	}


	/**
	 * Save the properties from the form to an item
	 * @param   ilCoSubItem   $a_item
	 * @return  boolean       success
	 */
	protected function saveItemProperties($a_item)
	{
		$a_item->obj_id = $this->object->getId();
		$a_item->identifier = $this->form->getInput('identifier');
		$a_item->title = $this->form->getInput('title');
		$a_item->description = $this->form->getInput('description');
		$a_item->target_ref_id = $this->form->getInput('target_ref_id');
		$a_item->cat_id = $this->form->getInput('cat_id');
		$a_item->cat_id = empty($a_item->cat_id) ? null : $a_item->cat_id;
		if ($this->object->getMethodObject()->hasMinSubscription())
		{
			$sub_min = $this->form->getInput('sub_min');
			$a_item->sub_min = empty($sub_min) ? null : $sub_min;
		}
		if ($this->object->getMethodObject()->hasMaxSubscription())
		{
			$sub_max = $this->form->getInput('sub_max');
			$a_item->sub_max = empty($sub_max) ? null : $sub_max;
		}
		$a_item->selectable = $this->form->getInput('selectable');

		return $a_item->save();
	}

	/**
	 * Save the properties from the form to the schedules
	 * @param ilCoSubItem $a_item
	 * @param ilCoSubSchedule[] $a_schedules
	 * @return  boolean       success
	 */
	protected function saveSchedulesProperties($a_item, $a_schedules = array())
	{
		// prepare a new schedule to be saved
		$schedule = new ilCoSubSchedule();
		$schedule->obj_id = $this->object->getId();
		$schedule->item_id = $a_item->item_id;
		$a_schedules[] = $schedule;


	}

	/**
	 * Show the form to configure the target objects
	 */
	protected function configureTargets()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');

		if (empty($_POST['item_ids']))
		{
			ilUtil::sendFailure($this->lng->txt('select_at_least_one_object'), true);
			$this->ctrl->redirect($this,'listItems');
		}

		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets->setItemsByIds($_POST['item_ids']);
		if (!$targets->targetsExist())
		{
			ilUtil::sendFailure($this->plugin->txt('targets_not_defined'), true);
			$this->ctrl->redirect($this, 'listItems');
		}
		if (!$targets->targetsWritable())
		{
			ilUtil::sendFailure($this->plugin->txt('targets_not_writable'), true);
			$this->ctrl->redirect($this, 'listItems');
		}

		$type = $targets->getCommonType();
		if (empty($type))
		{
			ilUtil::sendFailure($this->plugin->txt('targets_different_type'), true);
			$this->ctrl->redirect($this, 'listItems');
		}

		$this->initTargetsForm($type);
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Commonly save the targets config
	 */
	protected function saveTargetsConfig()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$type = $_GET['type'];

		$this->initTargetsForm($type);
		$this->form->checkInput();

		$set_sub_type = (bool) $this->form->getInput('set_sub_type');
		$this->object->setPreference('ilCoSubItemsGUI', 'set_sub_type', $set_sub_type);

		$sub_type = (string) $this->form->getInput('sub_type');
		$this->object->setPreference('ilCoSubItemsGUI', 'sub_type', $sub_type);


		if ($targets->hasSubscriptionPeriod($type))
		{
			$set_sub_period = (bool) $this->form->getInput('set_sub_period');
			$this->object->setPreference('ilCoSubItemsGUI', 'set_sub_period', $set_sub_period);

			/** @var ilDateDurationInputGUI $sub_period */
			$sub_period = $this->form->getItemByPostVar('sub_period');
			$sub_period_start = (int) $sub_period->getStart()->get(IL_CAL_UNIX);
			$this->object->setPreference('ilCoSubItemsGUI', 'sub_period_start', $sub_period_start);
			$sub_period_end = (int) $sub_period->getEnd()->get(IL_CAL_UNIX);
			$this->object->setPreference('ilCoSubItemsGUI', 'sub_period_end', $sub_period_end);
		}
		else
		{
			$set_sub_period = false;
			$sub_period_start = null;
			$sub_period_end = null;
		}

		if ($this->object->getMethodObject()->hasMinSubscription() && $targets->hasMinSubscriptions($type))
		{
			$set_sub_min = (bool) $this->form->getInput('set_sub_min');
			$this->object->setPreference('ilCoSubItemsGUI', 'set_sub_min', $set_sub_min);
		}
		else
		{
			$set_sub_min = false;
		}
		if ($this->object->getMethodObject()->hasMaxSubscription())
		{
			$set_sub_max = (bool) $this->form->getInput('set_sub_max');
			$this->object->setPreference('ilCoSubItemsGUI', 'set_sub_max', $set_sub_max);
		}
		else
		{
			$set_sub_max = false;
		}

		$set_sub_wait = (bool) $this->form->getInput('set_sub_wait');
		$this->object->setPreference('ilCoSubItemsGUI', 'set_sub_wait', $set_sub_wait);

		$sub_wait = (string) $this->form->getInput('sub_wait');
		$this->object->setPreference('ilCoSubItemsGUI', 'sub_wait', $sub_wait);

		try
		{
			$targets->setTargetsConfig(
				$set_sub_type ? $sub_type : null,
				$set_sub_period ? $sub_period_start : null,
				$set_sub_period ? $sub_period_end : null,
				$set_sub_min,
				$set_sub_max,
				$set_sub_wait ? $sub_wait : null
			);
		}
		catch (Exception $e)
		{
			ilUtil::sendFailure($this->plugin->txt('target_config_failed').'<br />'. $e->getMessage(), true);
			$this->ctrl->redirect($this, 'listItems');
		}

		ilUtil::sendSuccess($this->plugin->txt('target_config_saved'), true);
		$this->ctrl->redirect($this, 'listItems');
	}
}