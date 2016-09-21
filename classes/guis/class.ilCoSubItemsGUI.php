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

		$next_class = $this->ctrl->getNextClass();
		switch ($next_class)
		{
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

		$this->plugin->includeClass('guis/class.ilCoSubItemsTableGUI.php');
		$table_gui = new ilCoSubItemsTableGUI($this, 'listItems');
		$table_gui->prepareData($this->object->getItems());
		$this->tpl->setContent($table_gui->getHTML());
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
		foreach ($_POST['ref_id'] as $ref_id)
		{
			$item = $this->object->getItemForTarget($ref_id);
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
		$this->initItemForm('create');
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Save a new item
	 */
	protected function saveItem()
	{
		$this->initItemForm('create');
		if ($this->form->checkInput())
		{
			$item = new ilCoSubItem();
			$this->saveItemProperties($item);
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
		$this->initItemForm('edit');
		$this->loadItemProperties($item);
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Update an existing item
	 */
	protected function updateItem()
	{
		$this->ctrl->saveParameter($this, 'item_id');
		$this->initItemForm('edit');
		if ($this->form->checkInput())
		{
			$item = ilCoSubItem::_getById($_GET['item_id']);
			$this->saveItemProperties($item);
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
		$this->ctrl->saveParameter($this, 'item_id');
		$this->initItemForm(empty($_GET['item_id']) ? 'create' : 'edit');

		$input = $this->form->getItemByPostVar('target_ref_id');
		$input->readFromSession();
		$item = $this->object->getItemForTarget($input->	getValue());
		$this->loadItemProperties($item);

		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Init the Item form
	 * @param string $a_mode    'edit' or 'create'
	 */
	protected function initItemForm($a_mode = 'edit')
	{
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		include_once('Services/Form/classes/class.ilRepositorySelectorInputGUI.php');
		$this->form = new ilPropertyFormGUI();

		// target
		$rs = new ilRepositorySelectorInputGUI($this->plugin->txt('target_object'), 'target_ref_id');
		$rs->setClickableTypes($this->plugin->getAvailableTargetTypes());
		$rs->setInfo($this->plugin->txt('target_object_info'));
		$rs->setHeaderMessage($this->plugin->txt('select_target_object'));
		$this->form->addItem($rs);

		// title
		$ti = new ilTextInputGUI($this->plugin->txt('title'), 'title');
		$ti->setRequired(true);
		$this->form->addItem($ti);

		// description
		$ta = new ilTextAreaInputGUI($this->plugin->txt('description'), 'description');
		$this->form->addItem($ta);

		if ($this->object->getMethodObject()->hasMinSubscription())
		{
			// minimum subscriptions
			$sm = new ilNumberInputGUI($this->plugin->txt('sub_min'), 'sub_min');
			$sm->setDecimals(0);
			$sm->setMinValue(0);
			$sm->setSize(4);
			$sm->setRequired(false);
			$this->form->addItem($sm);
		}

		// maximum subscriptions
		$sm = new ilNumberInputGUI($this->plugin->txt('sub_max'), 'sub_max');
		$sm->setDecimals(0);
		$sm->setMinValue(1);
		$sm->setSize(4);
		$sm->setRequired(true);
		$this->form->addItem($sm);

		switch ($a_mode)
		{
			case 'create':
				$this->form->setTitle($this->plugin->txt('create_item'));
				$this->form->addCommandButton('saveItem', $this->lng->txt('save'));
				break;

			case 'edit':
				$this->form->setTitle($this->plugin->txt('edit_item'));
				$this->form->addCommandButton('updateItem', $this->lng->txt('save'));
				break;
		}

		$this->form->addCommandButton('listItems', $this->lng->txt('cancel'));
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
				'title' => $a_item->title,
				'description' => $a_item->description,
				'target_ref_id' => $a_item->target_ref_id,
				'sub_min' => $a_item->sub_min,
				'sub_max' => $a_item->sub_max
			)
		);
	}

	/**
	 * Save the properties from the form to an item
	 * @param   ilCoSubItem   $a_item
	 * @return  boolean       success
	 */
	protected function saveItemProperties($a_item)
	{
		$a_item->obj_id = $this->object->getId();
		$a_item->title = $this->form->getInput('title');
		$a_item->description = $this->form->getInput('description');
		$a_item->target_ref_id = $this->form->getInput('target_ref_id');
		if ($this->object->getMethodObject()->hasMinSubscription())
		{
			$a_item->sub_min = $this->form->getInput('sub_min');
		}
		$a_item->sub_max = $this->form->getInput('sub_max');
		return $a_item->save();
	}
}