<?php

/**
 * Managing class for combined subscription categories
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubCategoriesGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubCategoriesGUI extends ilCoSubBaseGUI
{
	/** @var ilObjCombiSubscriptionGUI */
	public ilObjCombiSubscriptionGUI $parent;

	/** @var  ilObjCombiSubscription */
	public ilObjCombiSubscription $object;

	/** @var  ilCombiSubscriptionPlugin */
	public ilCombiSubscriptionPlugin $plugin;

	/** @var  ilCtrl */
	public ilCtrl $ctrl;

	/** @var ilLanguage */
	public ilLanguage $lng;

	/** @var ilPropertyFormGUI */
	protected ilPropertyFormGUI $form;


	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand(): void
	{
		$this->plugin->includeClass('models/class.ilCoSubCategory.php');

		$cmd = $this->ctrl->getCmd('listCategories');
		switch ($cmd)
		{
			case 'listCategories':
			case 'createCategory':
			case 'saveCategory':
			case 'editCategory':
			case 'updateCategory':
			case 'confirmDeleteCategories':
			case 'deleteCategories':
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
	 * List the categories
	 */
	protected function listCategories(): void
	{
		global $ilToolbar;
		/** @var ilToolbarGUI $ilToolbar */
		$ilToolbar->setFormAction($this->ctrl->getFormAction($this));
		$ilToolbar->addFormButton($this->plugin->txt('create_category'), 'createCategory');

		$this->plugin->includeClass('guis/class.ilCoSubCategoriesTableGUI.php');
		$table_gui = new ilCoSubCategoriesTableGUI($this, 'listCategories');
		$table_gui->prepareData($this->object->getCategories());

		$description = $this->plugin->txt('categories_description');
		$this->tpl->setContent($this->pageInfo($description).$table_gui->getHTML());
	}

	/**
	 * Show form to create a new category
	 */
	protected function createCategory(): void
	{
		$this->initCategoryForm('create');
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Save a new category
	 */
	protected function saveCategory(): void
	{
		$this->initCategoryForm('create');
		if ($this->form->checkInput())
		{
			$category = new ilCoSubCategory();
			$this->saveCategoryProperties($category);
			ilUtil::sendSuccess($this->plugin->txt('msg_category_created'), true);
			$this->ctrl->redirect($this, 'listCategories');
		}
		else
		{
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHtml());
		}
	}

	/**
	 * Show form to edit a category
	 */
	protected function editCategory(): void
	{
		$this->ctrl->saveParameter($this, 'cat_id');
		$category = ilCoSubCategory::_getById($_GET['cat_id']);
		$this->initCategoryForm('edit');
		$this->loadCategoryProperties($category);
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Update an existing category
	 */
	protected function updateCategory(): void
	{
		$this->ctrl->saveParameter($this, 'cat_id');
		$this->initCategoryForm('edit');
		if ($this->form->checkInput())
		{
			$category = ilCoSubCategory::_getById($_GET['cat_id']);
			$this->saveCategoryProperties($category);
			ilUtil::sendSuccess($this->plugin->txt('msg_category_updated'), true);
			$this->ctrl->redirect($this, 'listCategories');
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
	protected function confirmDeleteCategories(): void
	{
		if (empty($_POST['cat_ids']))
		{
			ilUtil::sendFailure($this->lng->txt('select_at_least_one_object'), true);
			$this->ctrl->redirect($this,'listICategories');
		}

		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this));
		$conf_gui->setHeaderText($this->plugin->txt('confirm_delete_categories')
			.'<br /><span class="small">'.$this->plugin->txt('confirm_delete_categories_info').'</span>');
		$conf_gui->setConfirm($this->lng->txt('delete'),'deleteCategories');
		$conf_gui->setCancel($this->lng->txt('cancel'), 'listCategories');

		foreach($_POST['cat_ids'] as $cat_id)
		{
			$category = ilCoSubCategory::_getById($cat_id);
			$conf_gui->addItem('cat_ids[]', $cat_id, $category->title);
		}

		$this->tpl->setContent($conf_gui->getHTML());
	}

	/**
	 * Delete confirmed items
	 */
	protected function deleteCategories(): void
	{
		foreach($_POST['cat_ids'] as $cat_id)
		{
			ilCoSubCategory::_deleteById($cat_id);
		}
		ilUtil::sendSuccess($this->plugin->txt(count($_POST['cat_ids']) == 1  ? 'msg_category_deleted' : 'msg_categories_deleted'), true);
		$this->ctrl->redirect($this, 'listCategories');
	}

	/**
	 * Save the sorting
	 */
	protected function saveSorting(): void
	{
		$sort = $_POST['category_sort'];
		asort($sort, SORT_NUMERIC);

		$position = 0;
		foreach($sort as $cat_id => $sort_value)
		{
			$category = ilCoSubCategory::_getById($cat_id);
			$category->sort_position = $position;
			$category->save();
			$position++;
		}

		$this->ctrl->redirect($this, 'listCategories');
	}


	/**
	 * Init the category form
	 * @param string $a_mode    'edit' or 'create'
	 */
	protected function initCategoryForm(string $a_mode = 'edit'): void
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

		// minimum choices
		$mc = new ilNumberInputGUI($this->plugin->txt('min_choices'), 'min_choices');
		$mc->setInfo($this->plugin->txt('min_choices_info'));
		$mc->setDecimals(0);
		$mc->setSize(4);
		$mc->setRequired(false);
		$this->form->addItem($mc);

		// max assignments
		$ma = new ilNumberInputGUI($this->plugin->txt('cat_max_assignments'), 'max_assignments');
		$ma->setInfo($this->plugin->txt('cat_max_assignments_info'));
		$ma->setDecimals(0);
		$ma->setSize(4);
		$ma->setRequired(false);
		$this->form->addItem($ma);


		switch ($a_mode)
		{
			case 'create':
				$this->form->setTitle($this->plugin->txt('create_category'));
				$this->form->addCommandButton('saveCategory', $this->lng->txt('save'));
				break;

			case 'edit':
				$this->form->setTitle($this->plugin->txt('edit_category'));
				$this->form->addCommandButton('updateCategory', $this->lng->txt('save'));
				break;
		}

		$this->form->addCommandButton('listCategories', $this->lng->txt('cancel'));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}


	/**
	 * Load the properties of a category to the form
	 * @param ilCoSubCategory   $a_category
	 */
	protected function loadCategoryProperties(ilCoSubCategory $a_category): void
	{
		$this->form->setValuesByArray(
			array(
				'title' => $a_category->title,
				'description' => $a_category->description,
				'min_choices' => $a_category->min_choices,
				'max_assignments' => $a_category->max_assignments
			)
		);
	}

	/**
	 * Save the properties from the form to a category
	 * @param   ilCoSubCategory   $a_category
	 * @return  boolean       success
	 */
	protected function saveCategoryProperties(ilCoSubCategory $a_category): bool
	{
		$a_category->obj_id = $this->object->getId();
		$a_category->title = $this->form->getInput('title');
		$a_category->description = $this->form->getInput('description');
		$a_category->min_choices = $this->form->getInput('min_choices');
		$a_category->max_assignments = $this->form->getInput('max_assignments');
		return $a_category->save();
	}
}