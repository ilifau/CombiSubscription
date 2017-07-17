<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
require_once('Services/Locator/classes/class.ilLocatorGUI.php');
require_once('Modules/Course/classes/class.ilObjCourseListGUI.php');
require_once('Modules/Group/classes/class.ilObjGroupListGUI.php');
/**
 * Table GUI for registration items
 */
class ilCoSubItemsTableGUI extends ilTable2GUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/**
	 * ilCoSubItemsTableGUI constructor.
	 * @param ilObjCombiSubscriptionGUI $a_parent_gui
	 * @param string                    $a_parent_cmd
	 */
	function __construct($a_parent_gui, $a_parent_cmd)
	{
		global $ilCtrl;
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->ctrl = $ilCtrl;

		$this->setFormAction($this->ctrl->getFormAction($this->parent));
		$this->setRowTemplate("tpl.il_xcos_items_row.html", $this->plugin->getDirectory());
		$this->setEnableNumInfo(false);

		$this->addColumn('', '', '1%', true);
		$this->addColumn($this->lng->txt('sorting_header'), '', '1%');
		$this->addColumn($this->plugin->txt('identifier'));
		$this->addColumn($this->lng->txt('title'));
		$this->addColumn($this->lng->txt('description'));
		if ($this->parent->object->getMethodObject()->hasMinSubscription())
		{
			$this->addColumn($this->plugin->txt('sub_min_short'));
		}
		if ($this->parent->object->getMethodObject()->hasMaxSubscription())
		{
			$this->addColumn($this->plugin->txt('sub_max_short'));
		}
		$this->addColumn($this->plugin->txt('target_object'));
		$this->addColumn('');

		$this->setSelectAllCheckbox('item_ids');
		//$this->addMultiCommand('configureTargets', $this->plugin->txt('configure_targets'));
		$this->addMultiCommand('confirmDeleteItems', $this->plugin->txt('delete_items'));
		$this->addCommandButton('saveSorting',  $this->lng->txt('sorting_save'));
	}

	/**
	 * Prepare the data to be displayed
	 * @param   ilCoSubItem[]   $a_items
	 */
	public function prepareData($a_items)
	{
		$data = array();
		$sort = 10;
		foreach ($a_items as $item)
		{
			$row =  get_object_vars($item);
			$row['sort'] = $sort;
			$data[] = $row;
			$sort += 10;
		}
		$this->setData($data);
	}

	/**
	 * Fill a single data row
	 */
	protected function fillRow($a_set)
	{
		$this->tpl->setCurrentBlock('checkbox');
		$this->tpl->setVariable('ITEM_ID',$a_set['item_id']);
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock('sort');
		$this->tpl->setVariable('ITEM_ID',$a_set['item_id']);
		$this->tpl->setVariable('SORT',$a_set['sort']);
		$this->tpl->parseCurrentBlock();


		$columns = array('identifier', 'title', 'description');
		if ($this->parent->object->getMethodObject()->hasMinSubscription())
		{
			$column[] = 'sub_min';
		}
		if ($this->parent->object->getMethodObject()->hasMaxSubscription())
		{
			$columns[] = 'sub_max';
		}
		foreach ($columns as $key)
		{
			$this->tpl->setCurrentBlock('column');
			$this->tpl->setVariable('CONTENT',$a_set[$key].' ');
			$this->tpl->parseCurrentBlock();
		}

		// target info
		if (!empty($a_set['target_ref_id']))
		{
			$ref_id = $a_set['target_ref_id'];
			$type = ilObject::_lookupType($ref_id, true);
			$props = array();
			if ($type == 'crs')
			{
				$list = new ilObjCourseListGUI();
				$list->initItem($ref_id, ilObject::_lookupObjId($ref_id));
				$props = $list->getProperties();
			}
			elseif ($type == 'grp')
			{
				$list = new ilObjGroupListGUI();
				$list->initItem($ref_id, ilObject::_lookupObjId($ref_id));
				$props = $list->getProperties();
			}

			foreach ($props as $prop)
			{
				$this->tpl->setCurrentBlock('info');
				if ($prop['alert'])
				{
					$this->tpl->setVariable('CLASS', 'il_ItemAlertProperty');
				}
				$this->tpl->setVariable('INFO', ($prop['property'] ? $prop['property']. ': ' : '') . $prop['value']);
				$this->tpl->parseCurrentBlock();
			}

			$locator = new ilLocatorGUI();
			$locator->addContextItems($ref_id);
			$this->tpl->setCurrentBlock('target');
			$this->tpl->setVariable('PATH',  $locator->getHTML());
			$this->tpl->parseCurrentBlock();
		}


		$this->tpl->setCurrentBlock('link');
		$this->ctrl->setParameter($this->parent,'item_id', $a_set['item_id']);
		$this->tpl->setVariable('LINK_URL', $this->ctrl->getLinkTarget($this->parent,'editItem'));
		$this->tpl->setVariable('LINK_TXT', $this->lng->txt('edit'));
		$this->tpl->parseCurrentBlock();
	}
}