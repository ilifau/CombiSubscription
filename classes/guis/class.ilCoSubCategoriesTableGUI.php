<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
require_once('Services/Locator/classes/class.ilLocatorGUI.php');
/**
 * Table GUI for registration cetegories
 */
class ilCoSubCategoriesTableGUI extends ilTable2GUI
{
	/** @var  ilCtrl */
	protected ilCtrl $ctrl;

	/**
	 * ilCoSubCategoriesTableGUI constructor.
	 * @param ilCoSubCategoriesGUI $a_parent_gui
	 * @param string                    $a_parent_cmd
	 */
	function __construct(ilCoSubCategoriesGUI $a_parent_gui, string $a_parent_cmd)
	{
		global $ilCtrl;
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->ctrl = $ilCtrl;

		$this->setFormAction($this->ctrl->getFormAction($this->parent));
		$this->setRowTemplate("tpl.il_xcos_categories_row.html", $this->plugin->getDirectory());
		$this->setEnableNumInfo(false);

		$this->addColumn('', '', '1%', true);
		$this->addColumn($this->lng->txt('sorting_header'), '', '1%');
		$this->addColumn($this->lng->txt('title'));
		$this->addColumn($this->lng->txt('description'));
		$this->addColumn($this->plugin->txt('min_choices'));
		$this->addColumn($this->plugin->txt('cat_max_assignments'));
		$this->addColumn('');

		$this->setSelectAllCheckbox('cat_ids');
		$this->addMultiCommand('confirmDeleteCategories', $this->plugin->txt('delete_categories'));
		$this->addCommandButton('saveSorting',  $this->lng->txt('sorting_save'));
	}

	/**
	 * Prepare the data to be displayed
	 * @param   ilCoSubCategory[]   $a_categories
	 */
	public function prepareData(array $a_categories): void
	{
		$data = array();
		$sort = 10;
		foreach ($a_categories as $category)
		{
			$row =  get_object_vars($category);
			$row['sort'] = $sort;
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
		$this->tpl->setCurrentBlock('checkbox');
		$this->tpl->setVariable('CAT_ID',$a_set['cat_id']);
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock('sort');
		$this->tpl->setVariable('CAT_ID',$a_set['cat_id']);
		$this->tpl->setVariable('SORT',$a_set['sort']);
		$this->tpl->parseCurrentBlock();

		$columns = array('title', 'description', 'min_choices', 'max_assignments');
		foreach ($columns as $key)
		{
			$this->tpl->setCurrentBlock('column');
			$this->tpl->setVariable('CONTENT',$a_set[$key].' ');
			$this->tpl->parseCurrentBlock();
		}


		$this->tpl->setCurrentBlock('link');
		$this->ctrl->setParameter($this->parent,'cat_id', $a_set['cat_id']);
		$this->tpl->setVariable('LINK_URL', $this->ctrl->getLinkTarget($this->parent,'editCategory'));
		$this->tpl->setVariable('LINK_TXT', $this->lng->txt('edit'));
		$this->tpl->parseCurrentBlock();
	}
}