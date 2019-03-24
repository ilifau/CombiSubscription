<?php

/**
 * Registration screen of a combined subscription
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 * @ilCtrl_isCalledBy ilCoSubRegistrationGUI: ilObjCombiSubscriptionGUI
 * @ilCtrl_Calls ilCoSubRegistrationGUI: ilRepositorySearchGUI
 */
class ilCoSubRegistrationGUI extends ilCoSubUserManagementBaseGUI
{
	/** @var string command to show the list of users */
	protected $cmdUserList = 'listRegistrations';

	/** @var ilCoSubCategory[] */
	protected $categories = [];

	/** @var bool registration is disabled */
	protected $disabled = false;

	/** @var ilObjUser ilias_user */
	protected $ilias_user = null;

	/** @var array local_item_id => other_item_id => item */
	protected $conflicts = [];

	/**
	 * Execute a command
	 * note: permissions are already checked in parent gui
	 * @throws ilCtrlException
	 */
	public function executeCommand()
	{
		$next_class = $this->ctrl->getNextClass();
		switch ($next_class)
		{
			case 'ilrepositorysearchgui':
				$this->tabs->setSubTabActive('list_registrations');
				$this->performUserSearch();
				return;
		}

		// get the user that should be treated
		$this->loadIliasUser();
		$this->ctrl->saveParameter($this, 'user_id');

		$this->tabs->setSubTabActive($this->isOwnRegistration() ? 'own_registration' : 'list_registrations');
		$this->categories = $this->object->getCategories();

		$cmd = $this->ctrl->getCmd('editRegistration');
		switch ($cmd)
		{
			case 'listRegistrations':
			case 'editRegistration':
			case 'saveRegistration':
			case 'sendSubscriptionEmail':
			case 'confirmDeleteRegistration':
			case 'deleteRegistration':
			case 'cancelRegistration':
			case 'mailToUsers':
			case 'removeUsers':
			case 'removeUsersConfirmation':
            case 'fillEmptyRegistrations':

			$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}


	/**
	 * Show a list of registered users
	 */
	public function listRegistrations()
	{
		/**
		 * @var ilAccessHandler $ilAccess
		 * @var ilErrorHandling $ilErr
		 */
		global $ilAccess, $ilErr;

		if (!$ilAccess->checkAccess('write', '', $this->object->getRefId()))
		{
			$ilErr->raiseError($this->lng->txt('permission_denied'));
		}

		$this->tabs->activateSubTab('list_registrations');

		$this->plugin->includeClass('guis/class.ilCoSubUsersTableGUI.php');
		$table_gui = new ilCoSubUsersTableGUI($this, 'listRegistrations');
		$table_gui->prepareData();

		$this->showInfo();
		$this->provideUserSearch();

        if ($this->plugin->hasAdminAccess())
        {
            $button = ilLinkButton::getInstance();
            $button->setUrl($this->ctrl->getLinkTarget($this,'fillEmptyRegistrations'));
            $button->setCaption('fill_empty_registrations');
            $this->toolbar->addButtonInstance($button);
        }


		$this->tpl->setContent($table_gui->getHTML());
	}

	/**
	 * Edit the registration of the current user
	 * @param array|null	posted priorities to be set (item_id => priority)
	 */
	public function editRegistration($priorities = null)
	{
		// get the user for checking if it is fixed
		$userObj = $this->object->getUser($this->ilias_user->getId());

		// get conflicts
		$this->plugin->includeClass('class.ilCombiSubscriptionConflicts.php');
		$conflictsObj = new ilCombiSubscriptionConflicts($this->object, $this->plugin);
		$conflicts = $conflictsObj->getExternalConflicts([$userObj->user_id], false);
		if (isset($conflicts[$userObj->user_id]))
		{
			$this->conflicts = $conflicts[$userObj->user_id];
		}

		if ($this->isOwnRegistration())
		{
			// check subscription period
			if ($this->object->isBeforeSubscription())
			{
				ilUtil::sendInfo($this->plugin->txt('subscription_period_not_started'));
				$this->disabled = true;
			}
			elseif ($this->object->isAfterSubscription())
			{
				ilUtil::sendInfo($this->plugin->txt('subscription_period_finished'));
				$this->disabled = true;
			}
			elseif ($userObj->is_fixed)
			{
				ilUtil::sendInfo($this->plugin->txt('subscription_message_user_fixed'));
				$this->disabled = true;
			}
		}

		$saved_priorities = $this->object->getPrioritiesOfUser($this->ilias_user->getId());

		// take the current priorities of the user if none are posted
		if (!isset($priorities))
		{
			// optionally pre-select all items if user has not yet registered
			if (!empty($saved_priorities))
			{
				$priorities = $saved_priorities;
			}
			elseif ($this->object->getPreSelect())
			{
				$priorities = array();
				ilUtil::sendInfo($this->plugin->txt('pre_select_message'));
				foreach ($this->object->getItems() as $item)
				{
					$priorities[$item->item_id] = '0';
				}
			}
			else
			{
				$priorities = array();
			}
		}

		$this->plugin->includeClass('guis/class.ilCoSubFormGUI.php');
		$form = new ilCoSubFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		if (!$this->disabled)
		{
			$form->addCommandButton('saveRegistration', $this->plugin->txt('save_registration'));
			$form->addCommandButton('cancelRegistration', $this->lng->txt('cancel'));
			if ($this->isOwnRegistration() && !empty($saved_priorities))
			{
				$form->addSeparator();
				$form->addCommandButton('confirmDeleteRegistration', $this->plugin->txt('delete_registration'));
			}
		}

		if (empty($this->categories))
		{
			$form->setContent($this->getFlatRegisterHTML($priorities));
		}
		else
		{
			$form->setContent($this->getCatRegisterHTML($priorities));
		}

		$infos = $this->getRegistrationInfos($userObj);
		$this->tpl->setContent($this->getUserInfoHTML() .implode('', $infos) . $form->getHTML());

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
	 * @param ilCoSubUser $userObj
	 * @return array
	 */
	public function getRegistrationInfos($userObj)
	{

		$infos = array();
		if ($this->object->getExplanation())
		{
			$infos[] = $this->pageInfo($this->object->getExplanation());
		}
		else
		{
			$sentences = array();
			$methodObj = $this->object->getMethodObject();

			if ($methodObj->hasEmptyChoice())
			{
				$min = $this->object->getMinChoices();
				if ($min == 1)
				{
					$sentences[] = $this->plugin->txt('min_choice_explanation');
				}
				elseif ($min > 0)
				{
					$sentences[] = sprintf($this->plugin->txt('min_choicess_explanation'),$min);
				}
			}

			if ($methodObj instanceof ilCoSubMethodRandom)
			{
				switch($methodObj->priority_choices)
				{
					case ilCoSubMethodRandom::PRIO_CHOICES_UNIQUE:
						$sentences[] = $methodObj->txt('prio_choices_unique_explanation');
						break;
					case ilCoSubMethodRandom::PRIO_CHOICES_LIMITED:
						$sentences[] = $methodObj->txt('prio_choices_limited_explanation');
						break;
					case ilCoSubMethodRandom::PRIO_CHOICES_FREE:
						$sentences[] = $methodObj->txt('prio_choices_free_explanation');
						break;
				}

				$num = $methodObj->getNumberAssignments();
				if ($num == 1)
				{
					$sentences[] = $this->plugin->txt('one_assignment_explanation');
				}
				elseif($num > 0)
				{
					$sentences[] = sprintf($this->plugin->txt('num_assignments_explanation'), $num);
				}
			}

			if (!empty($sentences))
			{
				$infos[] = $this->pageInfo(implode(' ', $sentences));
			}

			foreach ($this->object->getItems() as $item)
			{
				if ($item->getSchedules())
				{
					$infos[] = $this->pageInfo($this->plugin->txt('conflict_explanation'));
					break;
				}
			}
		}


		// studydata conditions are avaiable
		if ($this->plugin->withStudyCond())
		{
			require_once('Services/Membership/classes/class.ilSubscribersStudyCond.php');

			if (ilSubscribersStudyCond::_hasConditions($this->object->getId()))
			{
				$infos[] = $this->pageInfo(sprintf($this->plugin->txt('studycond_intro'), ilSubscribersStudyCond::_getConditionsText($this->object->getId())));

				if (!ilSubscribersStudyCond::_checkConditions($this->object->getId(), $userObj->user_id))
				{
					ilUtil::sendInfo($this->plugin->txt('studycond_msg_not_fulfilled'));
				}
			}
		}

		return $infos;
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
		$table_gui->setDisabled($this->disabled);
		$table_gui->prepareData(
			$this->object->getItems('selectable'),
			$priorities,
			$this->object->getPriorityCounts(),
			$this->conflicts);

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
		$acc_gui->setActiveHeaderClass('ilCoSubRegAccHeaderActive');
		$acc_gui->head_class_set = true;	// workaround

		$this->plugin->includeClass('guis/class.ilCoSubRegistrationTableGUI.php');

		$items = $this->object->getItemsByCategory('selectable');
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
				$table_gui->setDisabled($this->disabled);
				$table_gui->prepareData(
					$items[$cat_id],
					$priorities,
					$counts,
					$this->conflicts);

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
		$userObj = $this->object->getUser($this->ilias_user->getId());

		// check fixation and subscription period
		if ($this->isOwnRegistration() && ($userObj->is_fixed || $this->object->isBeforeSubscription() || $this->object->isAfterSubscription()))
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
				$choice->user_id = $this->ilias_user->getId();
				$choice->item_id = $item->item_id;
				$choice->priority = $priority;
				$choices[] = $choice;

				$cat_counts[(int) $item->cat_id]++;
				$used_prio[$priority] = true;
			}
		}

		// check for unused priorities if each priority has to be chosen
		if (count($used_prio) <= $max_prio && !$has_ec)
		{
			ilUtil::sendFailure($this->plugin->txt('empty_choice_alert'));
			return $this->editRegistration($posted);
		}

		// check for mimimum choices in categories
		$catmess = array();
		foreach($this->categories as $cat_id => $category)
		{
			if (!empty($category->min_choices) && $cat_counts[$cat_id] < $category->min_choices)
			{
				$catmess[] = sprintf($this->plugin->txt('cat_choose_low_mess'), $category->title);
			}
		}
		if (!empty($catmess))
		{
			ilUtil::sendFailure(implode('<br />', $catmess));
			return $this->editRegistration($posted);
		}

		// finally save the choices
		$userObj->save();
		ilCoSubChoice::_deleteForObject($this->object->getId(), $userObj->user_id);
		foreach ($choices as $choice)
		{
			$choice->save();
		}

		if ($this->isOwnRegistration())
		{
			ilUtil::sendSuccess($this->plugin->txt('msg_registration_saved_own')
				.sprintf('<br /><a class="small" href="%s">%s</a>',
					$this->ctrl->getLinkTarget($this, 'sendSubscriptionEmail'),
					$this->plugin->txt('msg_send_email_confirmation')));
		}
		else
		{
			$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
			$notification = new ilCombiSubscriptionMailNotification();
			$notification->setPlugin($this->plugin);
			$notification->setObject($this->object);
			$notification->sendRegistration($userObj->user_id, true);

			ilUtil::sendSuccess($this->plugin->txt('msg_registration_saved'));
		}
		// don't redirect because this may show the pre-select
		$this->editRegistration($posted);
	}


	/**
	 * Send an email with the subscriotion info
	 */
	public function sendSubscriptionEmail()
	{
		$this->plugin->includeClass('class.ilCombiSubscriptionMailNotification.php');
		$notification = new ilCombiSubscriptionMailNotification();
		$notification->setPlugin($this->plugin);
		$notification->setObject($this->object);
		$notification->sendRegistration($this->ilias_user->getId());
		$this->parent->returnToContainer();
	}


	/**
	 * Show the confirmation message for deleting the registration
	 */
	public function confirmDeleteRegistration()
	{
		require_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$gui = new ilConfirmationGUI();
		$gui->setFormAction($this->ctrl->getFormAction($this));
		$gui->setHeaderText($this->plugin->txt('delete_registration_question'));
		$gui->setConfirm($this->plugin->txt('delete_registration'),'deleteRegistration');
		$gui->setCancel($this->lng->txt('cancel'),'editRegistration');
		$this->tpl->setContent($this->getUserInfoHTML() . $gui->getHTML());
	}

	/**
	 * Delete the whole registration
	 */
	public function deleteRegistration()
	{
		$this->plugin->includeClass('models/class.ilCoSubChoice.php');
		$this->plugin->includeClass('models/class.ilCoSubUser.php');
		ilCoSubChoice::_deleteForObject($this->object->getId(), $this->ilias_user->getId());
		ilCoSubUser::_deleteForObject($this->object->getId(), $this->ilias_user->getId());
		ilUtil::sendSuccess($this->plugin->txt('registration_deleted'), true);
		if ($this->isOwnRegistration())
		{
			$this->parent->returnToContainer();
		}
		else
		{
			$this->ctrl->redirect($this, 'listRegistrations');
		}
	}

	/**
	 * Cancel the registration
	 */
	public function  cancelRegistration()
	{
		if ($this->isOwnRegistration())
		{
			$this->parent->returnToContainer();
		}
		else
		{
			$this->ctrl->redirect($this, 'listRegistrations');
		}
	}


	public function fillEmptyRegistrations()
    {
        $users = $this->object->getUsers();
        $items = $this->object->getItems();
        $priorities = $this->object->getPriorities();

        foreach ($users as $user_id => $subUser)
        {
            if (empty($priorities[$user_id]))
            {

                foreach ($items as $item_id => $item)
                {
                    $choiceObj = new ilCoSubChoice();
                    $choiceObj->obj_id = $this->object->getId();
                    $choiceObj->user_id = $user_id;
                    $choiceObj->item_id = $item_id;
                    $choiceObj->priority = 0;
                    $choiceObj->save();
                }
            }
        }

        $this->ctrl->redirect($this, 'listRegistrations');
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

	/**
	 * Get the currently treated user object
	 */
	protected function loadIliasUser()
	{
		/**
		 * @var ilObjUser $ilUser
		 * @var ilAccessHandler $ilAccess
		 * @var ilErrorHandling $ilErr
		 */
		global $ilUser, $ilErr, $ilAccess;

		if (!empty($_GET['user_id']))
		{
			if (!$ilAccess->checkAccess('write', '', $this->object->getRefId()))
			{
				$ilErr->raiseError($this->lng->txt('permission_denied'));
			}
			$this->ilias_user = new ilObjUser($_GET['user_id']);
		}
		else
		{
			$this->ilias_user = $ilUser;
		}
	}

	/**
	 * Check if the the registration is down for oneseof
	 */
	protected function isOwnRegistration()
	{
		return empty($_GET['user_id']);
	}

	/**
	 * Get the information about the currently treated user
	 * @return string
	 */
	protected function getUserInfoHTML()
	{
		if ($this->isOwnRegistration())
		{
			return '';
		}
		else
		{
			return '<h3>'.$this->ilias_user->getFullname() . ' ('. $this->ilias_user->getLogin(). ')'.'</h3>';
		}
	}
}