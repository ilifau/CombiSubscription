<?php

/**
 * Registration screen of a combined subscription
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubRegistrationGUI: ilObjCombiSubscriptionGUI
 */
class ilCoSubRegistrationGUI extends ilCoSubBaseGUI
{
	/** @var ilCoSubCategory[] */
	var $categories = array();



	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 */
	public function executeCommand()
	{
		$this->categories = $this->object->getCategories();

		$cmd = $this->ctrl->getCmd('editRegistration');
		switch ($cmd)
		{
			case 'editRegistration':
			case 'saveRegistration':
			case 'cancelRegistration':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * Edit the registration of the current user
	 * @param array|null	posted priorities to be set (item_id => priority)
	 */
	public function editRegistration($priorities = null)
	{
		global $ilUser;

		// check subscription period
		if ($this->object->isBeforeSubscription())
		{
			ilUtil::sendInfo($this->plugin->txt('subscription_period_not_started'));
		}
		elseif ($this->object->isAfterSubscription())
		{
			ilUtil::sendInfo($this->plugin->txt('subscription_period_finished'));
		}

		$intro = '';
		if ($this->object->getExplanation())
		{
			$intro = $this->pageInfo($this->object->getExplanation());
		}

		// take the current priorities of the user if none are posted
		if (!isset($priorities))
		{
			$priorities = $this->object->getPrioritiesOfUser($ilUser->getId());
		}



		$this->plugin->includeClass('guis/class.ilCoSubFormGUI.php');
		$form = new ilCoSubFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->addCommandButton('saveRegistration', $this->plugin->txt('save_registration'));
		$form->addCommandButton('cancelRegistration', $this->lng->txt('cancel'));

		if (empty($this->categories))
		{
			$form->setContent($this->getFlatRegisterHTML($priorities));
		}
		else
		{
			$form->setContent($this->getCatRegisterHTML($priorities));
		}
		$this->tpl->setContent($intro . $form->getHTML());

		// color coding of priorities
		$this->tpl->addJavaScript($this->plugin->getDirectory().'/js/ilCombiSubscription.js');
		$colors = array();
		foreach ($this->object->getMethodObject()->getPriorities() as $index => $priority)
		{
			$colors[$index] = $this->object->getMethodObject()->getPriorityBackgroundColor($index);
		}
		$this->tpl->addOnLoadCode('il.CombiSubscription.init('.json_encode($colors).')');
	}

	/**
	 * Get the Html code of flat registrations
	 * @param array|null	$priorities priorities to be set (item_id => priority)
	 * @return string
	 */
	protected function getFlatRegisterHTML($priorities)
	{
		$this->plugin->includeClass('guis/class.ilCoSubRegistrationTableGUI.php');
		$table_gui = new ilCoSubRegistrationTableGUI($this, 'editRegistration');
		$table_gui->prepareData(
			$this->object->getItems(),
			$priorities,
			$this->object->getPriorityCounts());

		return $table_gui->getHTML();
	}

	/**
	 * Get the Html code of categorized registrations
	 * @param array|null	$priorities priorities to be set (item_id => priority)
	 * @return string
	 */
	protected function getCatRegisterHTML($priorities)
	{
		include_once('Services/Accordion/classes/class.ilAccordionGUI.php');
		$acc_gui = new ilAccordionGUI();
		$acc_gui->setAllowMultiOpened(true);

		$this->plugin->includeClass('guis/class.ilCoSubRegistrationTableGUI.php');

		$items = $this->object->getItemsByCategory();
		$counts = $this->object->getPriorityCounts();

		$empty_cat = new ilCoSubCategory();
		$empty_cat->cat_id = 0;
		$empty_cat->title = $this->plugin->txt('other_items');
		$this->categories[0] = $empty_cat;

		foreach ($this->categories as $cat_id => $category)
		{
			if (!empty($items[$cat_id]))
			{
				$table_gui = new ilCoSubRegistrationTableGUI($this, 'editRegistration');
				$table_gui->prepareData(
					$items[$cat_id],
					$priorities,
					$counts);

				$infos = array();
				if ($category->description) {
					$infos[] = $category->description;
				}
				if ($category->min_choices == 1) {
					$infos[] = $this->plugin->txt('cat_choose_min_one_info');
				}
				elseif ($category->min_choices > 1) {
					$infos[] = sprintf($this->plugin->txt('cat_choose_min_one_info'), $category->min_choices);
				}

				$content = '<div class="ilCoSubRegistrationPart">';
				if (!empty($infos)) {
					$content .= $this->pageInfo(implode('<br />', $infos));
				}
				$content .= $table_gui->getHTML();
				$content .= '</div>';

				$acc_gui->addItem($category->title, $content);
			}
		}

		return $acc_gui->getHTML();
	}


	/**
	 * Save the registration of the current user
	 */
	public function saveRegistration()
	{
		global $ilUser;

		// check subscription period
		if ($this->object->isBeforeSubscription() or $this->object->isAfterSubscription())
		{
			$this->ctrl->redirect($this,'editRegistration');
		}

		$this->plugin->includeClass('models/class.ilCoSubChoice.php');

		$method = $this->object->getMethodObject();
		$min_choices = $this->object->getMinChoices();
		$has_mc = $method->hasMultipleChoice();
		$has_ec = $method->hasEmptyChoice();
		$max_prio = count($method->getPriorities()) - 1;
		$used_prio = array();
		$choices = array();
		$cat_counts = array();

		// get and validate the posted choices
		$posted = $this->getPostedPriorities();
		if (count($posted) > 0 && count($posted) < $min_choices)
		{
			ilUtil::sendFailure(sprintf($this->plugin->txt('min_choices_alert'), $min_choices));
			return $this->editRegistration($posted);
		}

		// create choice objects to be saved
		foreach ($this->object->getItems() as $item)
		{
			$priority = $_POST['priority'][$item->item_id];
			if (is_numeric($priority) && $priority >= 0 && $priority <= $max_prio)
			{
				if (isset($used_prio[$priority]) && !$has_mc)
				{
					ilUtil::sendFailure($this->plugin->txt('multiple_choice_alert'));
					return $this->editRegistration($posted);
				}

				$choice = new ilCoSubChoice();
				$choice->obj_id  = $this->object->getId();
				$choice->user_id = $ilUser->getId();
				$choice->item_id = $item->item_id;
				$choice->priority = $priority;
				$choices[] = $choice;

				$cat_counts[(int) $item->cat_id]++;
				$used_prio[$priority] = true;
			}
		}

		// check for unused priorities if each priority has to chosen
		if (count($used_prio) <= $max_prio && !$has_ec)
		{
			ilUtil::sendFailure($this->plugin->txt('empty_choice_alert'));
			return $this->editRegistration($posted);
		}

		// check for mimimum choices in categories
		$catmess = array();
		foreach($this->categories as $cat_id => $category)
		{
			if ($cat_counts[$cat_id] < $category->min_choices)
			{
				$catmess[] = sprintf($this->plugin->txt('cat_choose_low_mess'), $category->title);
			}
		}
		if (!empty($catmess))
		{
			ilUtil::sendFailure(implode('<br />', $catmess));
			return $this->editRegistration($posted);
		}

		ilCoSubChoice::_deleteForObject($this->object->getId(), $ilUser->getId());
		foreach ($choices as $choice)
		{
			$choice->save();
		}

		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendRegistration($ilUser->getId());

		ilUtil::sendSuccess($this->plugin->txt('msg_registration_saved'), true);
		$this->ctrl->redirect($this,'editRegistration');
	}

	/**
	 * Cancel the registration
	 */
	public function cancelRegistration()
	{
		$this->parent->returnToContainer();
	}


	/**
	 * Get the posted priority slection of a user
	 * The 'not selected' options are filtered out
	 * @return array	item_id => priority
	 */
	protected function getPostedPriorities()
	{
		$priorities = array();
		foreach ((array) $_POST['priority'] as $item_id => $priority)
		{
			if (is_numeric($item_id) && is_numeric($priority))
			{
				$priorities[$item_id] = $priority;
			}
		}
		return $priorities;
	}
}