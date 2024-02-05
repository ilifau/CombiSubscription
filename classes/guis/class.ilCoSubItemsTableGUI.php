<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
require_once('Services/Locator/classes/class.ilLocatorGUI.php');
require_once('Modules/Course/classes/class.ilObjCourseListGUI.php');
require_once('Modules/Group/classes/class.ilObjGroupListGUI.php');
require_once('Modules/Session/classes/class.ilObjSessionListGUI.php');
/**
 * Table GUI for registration items
 */
class ilCoSubItemsTableGUI extends ilTable2GUI
{
    /** @var ilContainer */
    protected ilContainer $dic;
    
	/** @var  ilCtrl */
	protected ilCtrl $ctrl;

	/** @var ilCoSubItemsGUI  */
	protected ilCoSubItemsGUI $parent;

	/** @var ilCombiSubscriptionPlugin  */
	protected ilCombiSubscriptionPlugin $plugin;

	/** @var  ilCoSubCategory[]	indexed by cat_id */
	protected array $categories;

	/** @var ilCombiSubscriptionTargets */
	protected ilCombiSubscriptionTargets $targets;

	/**
	 * ilCoSubItemsTableGUI constructor.
	 * @param ilCoSubItemsGUI 			$a_parent_gui
	 * @param string                    $a_parent_cmd
	 */
	function __construct(ilCoSubItemsGUI $a_parent_gui, string $a_parent_cmd)
	{
		global $DIC;
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->categories = $this->parent->object->getCategories();
        $this->dic = $DIC;
		$this->ctrl = $DIC->ctrl();

		$this->plugin->includeClass('class.ilCombiSubscriptionTargets.php');
		$this->targets = new ilCombiSubscriptionTargets($this->parent->object, $this->plugin);

		$this->setFormAction($this->ctrl->getFormAction($this->parent));
		$this->setRowTemplate("tpl.il_xcos_items_row.html", $this->plugin->getDirectory());
		$this->setEnableNumInfo(false);

		$this->addColumn('', '', '1%', true);
		$this->addColumn($this->lng->txt('sorting_header'), '', '1%');
		$this->addColumn($this->plugin->txt('category'));
		$this->addColumn($this->plugin->txt('identifier'));
		$this->addColumn($this->lng->txt('title'));
		$this->addColumn($this->lng->txt('description'));
		$this->addColumn($this->plugin->txt('period'));
		$this->addColumn($this->plugin->txt('item_selectable'));
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
		$this->addMultiCommand('configureTargets', $this->plugin->txt('configure_targets'));
		$this->addMultiCommand('confirmDeleteItems', $this->plugin->txt('delete_items'));
		$this->addMultiCommand('addGrouping', $this->plugin->txt('add_grouping'));
		$this->addMultiCommand('removeGrouping', $this->plugin->txt('remove_grouping'));
		$this->addCommandButton('saveSorting',  $this->lng->txt('sorting_save'));

		if ($this->plugin->hasAdminAccess())
        {
            $this->addMultiCommand('confirmTransferAssignments', $this->plugin->txt('transfer_assignments'));
        }
	}

	/**
	 * Prepare the data to be displayed
	 * @param   ilCoSubItem[]   $a_items
	 */
	public function prepareData(array $a_items): void
	{
		$data = array();
		$sort = 10;
		foreach ($a_items as $item)
		{
			$row =  get_object_vars($item);
			$row['sort'] = $sort;
			if (!empty($row['cat_id'])) {
				$row['category'] = $this->categories[$row['cat_id']]->title;
			}
			$row['period'] = $item->getPeriodInfo();
			$row['selectable'] = $this->lng->txt($row['selectable'] ? 'yes' : 'no');
			$row['groupings'] = $this->targets->getGroupingsOfItem($item);
			$data[] = $row;
			$sort += 10;
		}
		$this->setData($data);
	}

	/**
	 * Fill a single data row
	 */
	protected function fillRow(array $a_set): void
	{
		global $lng;

		$this->tpl->setCurrentBlock('checkbox');
		$this->tpl->setVariable('ITEM_ID',$a_set['item_id']);
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock('sort');
		$this->tpl->setVariable('ITEM_ID',$a_set['item_id']);
		$this->tpl->setVariable('SORT',$a_set['sort']);
		$this->tpl->parseCurrentBlock();


		$columns = array('category', 'identifier', 'title', 'description', 'period', 'selectable');
		if ($this->parent->object->getMethodObject()->hasMinSubscription())
		{
			$columns[] = 'sub_min';
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
		$ref_id = $a_set['target_ref_id'];
		if (!empty($ref_id)  && ilObject::_exists($ref_id, true) && !ilObject::_isInTrash($ref_id))
		{
			$type = ilObject::_lookupType($ref_id, true);
			$props = array();
			if ($type == 'crs')
			{
				$list = new ilObjCourseListGUI();
				$list->initItem($ref_id, ilObject::_lookupObjId($ref_id), NULL);
				$props = $list->getProperties();
			}
			elseif ($type == 'grp')
			{
				$list = new ilObjGroupListGUI();
				$list->initItem($ref_id, ilObject::_lookupObjId($ref_id), NULL);
				$props = $list->getProperties();
			}
			elseif ($type == 'sess')
			{
				$list = new ilObjSessionListGUI();
				$list->initItem($ref_id, ilObject::_lookupObjId($ref_id), NULL);
				$props = $list->getProperties();
			}

			foreach ((array)$props as $prop)
			{
				$this->tpl->setCurrentBlock('info');
				if ($prop['alert'])
				{
					$this->tpl->setVariable('CLASS', 'il_ItemAlertProperty');
				}
				$this->tpl->setVariable('INFO', ($prop['property'] ? $prop['property']. ': ' : '') . $prop['value']);
				$this->tpl->parseCurrentBlock();
			}

			if (!empty($a_set['groupings']))
			{
				$groupingTitles = array();
				/** @var ilObjCourseGrouping $grouping */
				foreach ($a_set['groupings'] as $grouping)
				{
					$groupingTitles[] = $grouping->getTitle();
				}
				$this->tpl->setCurrentBlock('info');
				$this->tpl->setVariable('INFO', '<em>'. $lng->txt('groupings') . ': ' . implode(', ', $groupingTitles) .'</em>');
				$this->tpl->parseCurrentBlock();

			}
            
            if ($this->plugin->hasFauService() && !empty($a_set['target_ref_id'] && !empty($a_set['import_id']))) {
                $import_id = \FAU\Study\Data\ImportId::fromString($a_set['import_id']);
                $this->tpl->setCurrentBlock('info');
                $this->tpl->setVariable('INFO', 
                    $this->dic->fau()->study()->info()->getDetailsLink($import_id, $ref_id, $this->lng->txt('fau_details_link'))
                );
                $this->tpl->parseCurrentBlock();
                
            }
            
			$locator = new ilLocatorGUI();
			$locator->addContextItems($ref_id);
			$this->tpl->setCurrentBlock('target');
			$this->tpl->setVariable('PATH',  $locator->getHTML());
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			$this->tpl->touchBlock('target');
		}

		$this->tpl->setCurrentBlock('link');
		$this->ctrl->setParameter($this->parent,'item_id', $a_set['item_id']);
		$this->tpl->setVariable('LINK_URL', $this->ctrl->getLinkTarget($this->parent,'editItem'));
		$this->tpl->setVariable('LINK_TXT', $this->lng->txt('edit'));
		$this->tpl->parseCurrentBlock();
	}
}