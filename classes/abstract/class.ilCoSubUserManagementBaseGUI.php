<?php

/**
 * Base class for user management functions in registrations or assignments GUIs
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
abstract class ilCoSubUserManagementBaseGUI extends ilCoSubBaseGUI
{
	protected string $cmdUserList = '';

	/**
	 * Redirect to the list of users
	 */
	protected function redirectToUserList(): void
	{
		$this->ctrl->redirect($this, $this->cmdUserList);
	}

	/**
	 * add a user search to the toobar
	 */
	protected function provideUserSearch(): void
	{
		// add member
		ilRepositorySearchGUI::fillAutoCompleteToolbar(
			$this,
			$this->toolbar,
			array(
				'auto_complete_name'	=> $this->lng->txt('user'),
				'submit_name'			=> $this->lng->txt('add')
			)
		);

		$this->toolbar->addSeparator();

		// search users button
		$button = ilLinkButton::getInstance();
		$button->setUrl($this->ctrl->getLinkTargetByClass('ilRepositorySearchGUI','start'));
		$button->setCaption($this->plugin->txt('search_users'), false);
		$this->toolbar->addButtonInstance($button);
	}

	/**
	 * Perform the user search
	 * @throws ilCtrlException
	 */
	protected function performUserSearch(): void
	{
		$rep_search = new ilRepositorySearchGUI();
		$rep_search->setTitle($this->plugin->txt("add_users"));
		$rep_search->setCallback($this,'addUsers');
		$this->ctrl->setReturn($this,$this->cmdUserList);
		$this->ctrl->forwardCommand($rep_search);
		return;

	}

	/**
	 * Add the users to the registration
	 * @param array|int $user_ids
	 * @param string $a_type
	 * @return bool
	 */
	public function addUsers(array $user_ids, string $a_type = ""): bool
	{
		global $DIC;

		if (empty($user_ids[0]))
		{
			$GLOBALS['lng']->loadLanguageModule('search');
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt('search_err_user_not_exist'),true);
			return false;
		}

		$users = $this->object->getUsers();
        $items = $this->object->getItems();

        $preselect = ($this->object->getPreSelect() && $this->object->getMethodObject()->hasMultipleChoice());

		$added = 0;
		foreach ($user_ids as $user_id)
		{
			if (!isset($users[$user_id]))
			{
				$userObj = new ilCoSubUser();
				$userObj->obj_id = $this->object->getId();
				$userObj->user_id = $user_id;
				$userObj->save();
				$added++;

				if ($preselect) {
                    foreach ($items as $item_id => $item) {
                        $choiceObj = new ilCoSubChoice();
                        $choiceObj->obj_id = $this->object->getId();
                        $choiceObj->user_id = $user_id;
                        $choiceObj->item_id = $item_id;
                        $choiceObj->priority = 0;
                        $choiceObj->save();
                    }
                }
			}
		}

		$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('users_added'), true);
		$this->redirectToUserList();
	}

	/**
	 * Show info about date when the assignments were already transferred ure users were notified
	 */
	public function showInfo(): void
	{
		global $DIC;
		$messages = array();
		$transfer_time = $this->object->getClassProperty('ilCoSubAssignmentsGUI', 'transfer_time', 0);
		if ($transfer_time > 0)
		{
			$date = new ilDateTime($transfer_time, IL_CAL_UNIX);
			$messages[] = sprintf($this->plugin->txt('transfer_assignments_time'), ilDatePresentation::formatDate($date));
		}
		$notify_time = $this->object->getClassProperty('ilCoSubAssignmentsGUI', 'notify_time', 0);
		if ($notify_time > 0)
		{
			$date = new ilDateTime($notify_time, IL_CAL_UNIX);
			$messages[] = sprintf($this->plugin->txt('notify_assignments_time'), ilDatePresentation::formatDate($date));
		}

		if (!empty($messages))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('info', implode('<br />', $messages));
		}
	}

	/**
	 * Confirm the fixation of users
	 */
	public function fixUsersConfirmation(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'fixUsers'));
		$conf_gui->setHeaderText($this->plugin->txt('fix_users_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('fix_users'),'fixUsers');
		$conf_gui->setCancel($this->lng->txt('cancel'), $this->cmdUserList);

		foreach ($this->object->getUserDetails($_POST['ids']) as $user_id => $details)
		{
			$conf_gui->addItem('ids[]', $user_id, $details['showname'], ilUtil::getImagePath('icon_usr.svg'));

		}
		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Fix the assignments of selected users
	 */
	public function fixUsers(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		foreach($this->object->getUsers($_POST['ids']) as $user_id => $userObj)
		{
			$userObj->is_fixed = true;
			$userObj->save();
		}

		$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('fix_users_done'), true);
		$this->redirectToUserList();
	}

	/**
	 * Confirm the unfixation of users
	 */
	public function unfixUsersConfirmation(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'unfixUsers'));
		$conf_gui->setHeaderText($this->plugin->txt('unfix_users_confirmation'));
		$conf_gui->setConfirm($this->plugin->txt('unfix_users'),'unfixUsers');
		$conf_gui->setCancel($this->lng->txt('cancel'),$this->cmdUserList);

		foreach ($this->object->getUserDetails($_POST['ids']) as $user_id => $details)
		{
			$conf_gui->addItem('ids[]', $user_id, $details['showname'], ilUtil::getImagePath('icon_usr.svg'));

		}
		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Fix the assignments of selected users
	 */
	public function unfixUsers(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		foreach($this->object->getUsers($_POST['ids']) as $user_id => $userObj)
		{
			$userObj->is_fixed = false;
			$userObj->save();
		}

		$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('unfix_users_done'), true);
		$this->redirectToUserList();
	}


	/**
	 * Send an e-mail to selected users
	 */
	public function mailToUsers(): void
	{
		global $DIC;
		
		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}
		$rcps = array();
		foreach($_POST['ids'] as $usr_id)
		{
			$rcps[] = ilObjUser::_lookupLogin($usr_id);
		}

		ilMailFormCall::setRecipients($rcps);

		$signature = "\n\n" . $this->plugin->txt('mail_signature') . "\n" . ilLink::_getStaticLink($this->object->getRefId());

		$target = ilMailFormCall::getRedirectTarget(
			$this,
			$this->cmdUserList,
			array(),
			array('type' => 'new', 'sig' => rawurlencode(base64_encode($signature))));

		$DIC->ctrl()->redirectToURL($target);
	}


	/**
	 * Confirm the removing of users
	 */
	public function removeUsersConfirmation(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		$conf_gui = new ilConfirmationGUI();
		$conf_gui->setFormAction($this->ctrl->getFormAction($this,'removeUsers'));
		$conf_gui->setHeaderText($this->plugin->txt('remove_users_confirmation')
			.$this->messageDetails($this->plugin->txt('remove_users_confirmation_details')));
		$conf_gui->setConfirm($this->plugin->txt('remove_users'),'removeUsers');
		$conf_gui->setCancel($this->lng->txt('cancel'), $this->cmdUserList);

		foreach ($this->object->getUserDetails($_POST['ids']) as $user_id => $details)
		{
			$conf_gui->addItem('ids[]', $user_id, $details['showname'], ilUtil::getImagePath('icon_usr.svg'));

		}
		$this->tpl->setContent($conf_gui->getHTML());
		$this->showInfo();
	}

	/**
	 * Fix the assignments of selected users
	 */
	public function removeUsers(): void
	{
		global $DIC;

		if (empty($_POST['ids']))
		{
			$DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->lng->txt("no_checkbox"), true);
			$this->redirectToUserList();
		}

		foreach($_POST['ids'] as $user_id)
		{
			ilCoSubUser::_deleteForObject($this->object->getId(), $user_id);
			ilCoSubChoice::_deleteForObject($this->object->getId(), $user_id);
			ilCoSubAssign::_deleteByObjectAndUser($this->object->getId(), $user_id);
		}

		$mail = new ilCombiSubscriptionMailNotification();
		$mail->setObject($this->object);
		$mail->setPlugin($this->plugin);
		$mail->sendRemoval($_POST['ids']);

		$DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->plugin->txt('remove_users_done'), true);
		$this->redirectToUserList();
	}

}