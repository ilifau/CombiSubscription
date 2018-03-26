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
				$this->initItemForm(ilCoSubItem::_getById($_GET['item_id']));
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
			case 'addGrouping':
			case 'removeGrouping':
			case 'showConflicts':
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

		$ilToolbar->addFormButton($this->plugin->txt('show_conflicts'),'showConflicts');

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

		$items = array();

		if ($this->checkTargetsWritable($_POST['ref_id'], true))
		{
			foreach ($_POST['ref_id'] as $ref_id)
			{
				$item = $targets->getItemForTarget($ref_id);
				$item->save();
				$items[$item->item_id] = $item;

				foreach($targets->getSchedulesForTarget($ref_id) as $schedule)
				{
					$schedule->obj_id = $this->object->getId();
					$schedule->item_id = $item->item_id;
					$schedule->save();
				}
			}
			$targets->setItems($items);
			$targets->applyDefaultTargetsConfig();

			ilUtil::sendSuccess($this->plugin->txt(count($_POST['item_ids']) == 1  ? 'msg_item_created' : 'msg_items_created'), true);
		}

		$this->ctrl->redirect($this, 'listItems');
	}


	/**
	 * Check if target objects are writable
	 * @param array $a_ref_ids
	 * @param bool $a_redirect		keep message for redirect
	 * @return bool
	 */
	protected function checkTargetsWritable($a_ref_ids = array(), $a_redirect = false)
	{
		/** @var ilAccessHandler $ilAccess */
		global $ilAccess;

		foreach ($a_ref_ids as $ref_id)
		{
			if (!$ilAccess->checkAccess('write','', (int) $ref_id))
			{
				$obj_id = ilObject::_lookupObjId($ref_id);
				$title = ilObject::_lookupTitle($obj_id);

				ilUtil::sendFailure(sprintf($this->plugin->txt('target_object_not_writable'), $title), $a_redirect);
				return false;
			}
		}

		return true;
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
		if ($this->form->checkInput() && $this->checkTargetsWritable(array($this->form->getInput('target_ref_id'))))
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
		if ($this->form->checkInput() && $this->checkTargetsWritable(array($this->form->getInput('target_ref_id'))))
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
	 * Confirm the deletion of items
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

		// init the item form without schedules to read the target ref_id
		$this->initItemForm($item);

		/** @var ilRepositorySelectorInputGUI $target_ref_id */
		$target_input = $this->form->getItemByPostVar('target_ref_id');
		$target_input->readFromSession();
		$target_ref_id = $target_input->getValue();

		// apply values generated from the target object
		$item = $targets->getItemForTarget($target_ref_id, $item);

		// unsaved schedules with values generated from the target object
		$schedules = $targets->getSchedulesForTarget($target_ref_id);

		// re-init the item form to apply the schedules
		$this->initItemForm($item, $schedules);

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
		$i = 0;
		foreach ($a_schedules as $schedule)
		{
			$i++;
			$id = (int) $schedule->schedule_id;

			$hidden_id = new ilHiddenInputGUI('schedule_id_'.$i);
			$hidden_id->setValue($schedule->schedule_id);
			$this->form->addItem($hidden_id);

			$group = new ilRadioGroupInputGUI(
				count($a_schedules) > 1 ? sprintf($this->plugin->txt('schedule_x'),$i) : $this->plugin->txt('schedule'),
					'schedule_' .$i);

			$none = new ilRadioOption($this->plugin->txt('schedule_none'), 'none');
			$group->addOption($none);

			$single = new ilRadioOption($this->plugin->txt('schedule_single'), 'single');
				//single start
				$start = new ilDateTimeInputGUI($this->plugin->txt('period_start'),'period_start_'.$i);
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
				$end = new ilDateTimeInputGUI($this->plugin->txt('period_end'),'period_end_'.$i);
				if (isset($schedule->period_end)) {
					$end->setDate(new ilDateTime($schedule->period_end, IL_CAL_UNIX));
				}
				else {
					$end->setDate(new ilDateTime(date('Y-m-d').' 16:00:00',IL_CAL_DATETIME));
				}
				$end->setRequired(true);
				$end->setShowTime(true);
				$end->setInfo($this->plugin->txt('schedule_input_exact'));
				$single->addSubItem($end);

			$group->addOption($single);

			$multi = new ilRadioOption($this->plugin->txt('schedule_multi'), 'multi');

				// multi first
				$first = new ilDateTimeInputGUI($this->plugin->txt('period_first'),'period_first_'.$i);
				if (isset($schedule->period_start)) {
					$first->setDate(ilCoSubSchedule::_dayDate($schedule->period_start));
				}
				else {
					$first->setDate(new ilDate(date('Y-m-d'),IL_CAL_DATE));
				}
				$first->setRequired(true);
				$first->setShowTime(false);
				$multi->addSubItem($first);

				// multi last
				$last = new ilDateTimeInputGUI($this->plugin->txt('period_last'),'period_last_'.$i);
				if (isset($schedule->period_start)) {
					$last->setDate(ilCoSubSchedule::_dayDate($schedule->period_end));
				}
				else {
					$last->setDate(new ilDate(date('Y-m-d'),IL_CAL_DATE));
				}
				$last->setRequired(true);
				$last->setShowTime(false);
				$multi->addSubItem($last);


				$slots = new ilScheduleInputGUI($this->plugin->txt("period_slots"), "slots_".$i);
				$slots->setRequired(true);
				$slots->setValue($schedule->getSlotsForInput());
				require_once('Services/Calendar/classes/class.ilTimeZone.php');
				require_once('Services/Calendar/classes/class.ilCalendarUtil.php');
				$tz = ilTimeZone::_getDefaultTimeZone();
				$tzlist = ilCalendarUtil::_getShortTimeZoneList();
				$slots->setInfo(sprintf($this->plugin->txt('slot_input_timezone'), $tzlist[$tz])
				.' '.$this->plugin->txt('schedule_input_exact'));

				$multi->addSubItem($slots);

			$group->addOption($multi);

			if (empty($schedule->period_start)) {
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
		$count = new ilHiddenInputGUI('schedules_count');
		$count->setValue($i);
		$this->form->addItem($count);

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
	 * @var string $a_type	target object type or 'auto' for auto assignment configuration
	 * @var int[]	$a_item_ids		posted item_ids that should be kept for next post
	 */
	protected function initTargetsForm($a_type, $a_item_ids = array())
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');

		$this->form = new ilPropertyFormGUI();
		$this->form->setTitle($this->plugin->txt('configure_targets_'.$a_type));

		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$config =  new ilCoSubTargetsConfig($this->object);
		$config->readFromSession();
		foreach ($targets->getFormProperties($a_type, $config) as $property)
		{
			$this->form->addItem($property);
		}
		foreach ($a_item_ids as $item_id)
		{
			$hi = new ilHiddenInputGUI('item_ids[]');
			$hi->setValue((int) $item_id);
			$this->form->addItem($hi);
		}

		$this->form->addCommandButton('saveTargetsConfig', $this->plugin->txt('save_target_config'));
		$this->form->addCommandButton('listItems', $this->lng->txt('cancel'));

		$this->ctrl->setParameter($this, 'type', $a_type);
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}


	/**
	 * Save the properties from the form to an item
	 * @param   ilCoSubItem   $a_item
	 */
	protected function saveItemProperties($a_item)
	{
		$old_target_ref_id = $a_item->target_ref_id;

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
		$a_item->save();


		if (!empty($a_item->target_ref_id) && $a_item->target_ref_id != $old_target_ref_id)
		{
			$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
			$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
			$targets->setItems(array($a_item));
			$targets->applyDefaultTargetsConfig();
		}
	}

	/**
	 * Save the properties from the form to the schedules
	 * @param ilCoSubItem $a_item
	 * @param ilCoSubSchedule[] $a_schedules (indexed by schedule_id)
	 * @return  boolean       success
	 */
	protected function saveSchedulesProperties($a_item, $a_schedules = array())
	{
		include_once "Modules/BookingManager/classes/class.ilScheduleInputGUI.php";

		$count = (int) $_POST['schedules_count'];

		// save the posted schedules
		for ($i = 1; $i <= $count; $i++)
		{
			$schedule_id = (int) $_POST['schedule_id_'.$i];
			if (isset($a_schedules[$schedule_id]))
			{
				$schedule = $a_schedules[$schedule_id];
			}
			else
			{
				$schedule = new ilCoSubSchedule();
				$schedule->item_id = $a_item->item_id;
				$schedule->obj_id = $a_item->obj_id;
			}

			$schedule->period_start = null;
			$schedule->period_end = null;
			$schedule->slots = array();

			switch ((string) $_POST['schedule_'.$i] )
			{
				case 'single':
					$start = $this->form->getItemByPostVar('period_start_'.$i);
					$end = $this->form->getItemByPostVar('period_end_'.$i);

					$schedule->period_start = $start->getDate()->get(IL_CAL_UNIX);
					$schedule->period_end = $end->getDate()->get(IL_CAL_UNIX);
					$schedule->save();

					unset($a_schedules[$schedule_id]);			// prevent from being deleted at the end
					break;

				case 'multi':
					$first = $this->form->getInput('period_first_'.$i);
					$last = $this->form->getInput('period_last_'.$i);

					// set times to 00:00 of entered day in server time zone
					$start = new ilDateTime($first['date']. ' 00:00:00', IL_CAL_DATETIME);
					$end = new ilDateTime($last['date']. ' 00:00:00', IL_CAL_DATETIME);

					$schedule->period_start = $start->get(IL_CAL_UNIX);
					$schedule->period_end = $end->get(IL_CAL_UNIX);
					$schedule->setSlotsFromInput(ilScheduleInputGUI::getPostData('slots_'.$i));
					$schedule->save();

					if ($schedule->getTimesCount() > ilCoSubSchedule::MAX_TIMES) {
						ilUtil::sendInfo(sprintf($this->plugin->txt('message_too_many_schedule_times'),
							$schedule->getPeriodInfo(), $schedule->getTimesCount(), ilCoSubSchedule::MAX_TIMES), true);
					}
					unset($a_schedules[$schedule_id]);			// prevent from being deleted at the end
					break;
			}
		}

		// delete the old schedules are not longer selected in the form
		foreach ($a_schedules as $schedule)
		{
			$schedule->delete();
		}

		return true;
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

		$this->initTargetsForm($type, $_POST['item_ids']);
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Commonly save the targets config
	 */
	protected function saveTargetsConfig()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$targets = new ilCombiSubscriptionTargets($this->object, $this->plugin);
		$targets->setItemsByIds($_POST['item_ids']);
		if (!$targets->targetsWritable())
		{
			ilUtil::sendFailure($this->plugin->txt('targets_not_writable'), true);
			$this->ctrl->redirect($this, 'listItems');
		}

		// get the posted configuration
		$type = $_GET['type'];
		$this->initTargetsForm($type);
		$this->form->checkInput();
		$config = $targets->getFormInputs($this->form, $type);
		$config->saveInSession();

		try
		{
			$targets->applyTargetsConfig($config);
		}
		catch (Exception $e)
		{
			ilUtil::sendFailure($this->plugin->txt('target_config_failed').'<br />'. $e->getMessage(), true);
			$this->ctrl->redirect($this, 'listItems');
		}

		ilUtil::sendSuccess($this->plugin->txt('target_config_saved'), true);
		$this->ctrl->redirect($this, 'listItems');
	}

	/**
	 * add a grouping to the targets
	 */
	protected function addGrouping()
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

		$targets->addGrouping();
		$this->ctrl->redirect($this, 'listItems');
	}

	/**
	 * remove grouping of targets
	 */
	protected function removeGrouping()
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

		$targets->removeGrouping();
		$this->ctrl->redirect($this, 'listItems');

	}

	/**
	 * Show a list of item conflicts
	 */
	protected function showConflicts()
	{
		$conflicts = $this->object->getItemsConflicts();
		$items = $this->object->getItems();

		$buffer = max($this->object->getMethodObject()->getOutOfConflictTime(), $this->plugin->getOutOfConflictTime());
		$tolerance = $this->object->getMethodObject()->getToleratedConflictPercentage();

		$html = '';
		foreach ($conflicts as $item_id => $conflict_items)
		{
			$item = $items[$item_id];
			$conflict_html = '';
			foreach ($conflict_items as $conflict_id)
			{
				if ($conflict_id != $item_id)
				{
					$conflict_item = $items[$conflict_id];
					if (ilCoSubItem::_haveConflict($item, $conflict_item, $buffer, $tolerance))
					{
						$conflict_html .= $conflict_item->getPeriodInfo().': '.$conflict_item->title.'<br />';
					}
				}
			}

			if (!empty($conflict_html))
			{
				$html .= '<p><strong>'.$item->getPeriodInfo().': '.$item->title.'</strong><br />' . $conflict_html . '</p>';
			}
		}

		if (empty($html))
		{
			$html = $this->plugin->txt('no_conflicts_found');
		}

		ilUtil::sendInfo($html, false);
		$this->listItems();
	}

}