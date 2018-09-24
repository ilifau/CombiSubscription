<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
/**
 * Table GUI for registered users
 */
class ilCoSubUsersTableGUI extends ilTable2GUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/**
	 * List of users (indexed by user_id)
	 * @var ilCoSubUser[];
	 */
	protected $users;


	/**
	 * User priorities
	 * @var array  (user_id => item_id => priority)
	 */
	protected $priorities;

	/**
	 * Run assignments
	 * @var array   (run_id => user_id => item_id => assign_id)
	 */
	protected $assignments;

	/**
	 * ilCoSubItemsTableGUI constructor.
	 * @param ilCoSubAssignmentsGUI     $a_parent_gui
	 * @param string                    $a_parent_cmd
	 */
	function __construct($a_parent_gui, $a_parent_cmd)
	{
		global $ilCtrl;

		$this->setId('il_xcos_ass');
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->object = $a_parent_gui->object;
		$this->ctrl = $ilCtrl;
		$this->setFormAction($this->ctrl->getFormAction($this->parent));
		$this->setRowTemplate('tpl.il_xcos_users_row.html', $this->plugin->getDirectory());

		$this->addColumn('','', 1, true);
		$this->addColumn($this->lng->txt('user'), 'user');
		$this->addColumn($this->lng->txt('login'), 'login');
		$this->addColumn($this->plugin->txt('selected_items'), 'registrations');
		$this->addColumn($this->plugin->txt('fixed'), 'is_fixed');
		$this->addColumn('');

		$this->setDefaultOrderField('user');
		$this->setDefaultOrderDirection('asc');
		$this->setSelectAllCheckbox('id');

		$this->addMultiCommand('mailToUsers', $this->plugin->txt('mail_to_users'));
		$this->addMultiCommand('removeUsersConfirmation', $this->plugin->txt('remove_users'));
	}

	/**
	 * Prepare the data to be displayed
	 */
	public function prepareData()
	{
		/** @var ilAccessHandler  $ilAccess*/
		global $ilAccess;

		$this->users = $this->object->getUsers();
		$this->priorities = $this->object->getPriorities();

		$users_for_studycond = $this->object->getUsersForStudyCond();

		if (empty($this->users))
		{
			$this->setData(array());
			return;
		}

		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setLimit($this->plugin->getUserQueryLimit());
		$user_query->setUserFilter(array_keys($this->users));
		$user_query_result = $user_query->query();


		// prepare only the data that is used for sorting
		// all other data will only be calculated for the shown rows
		foreach ($user_query_result['set'] as $user)
		{
			$user_id = $user['usr_id'];
			$userObj = $this->users[$user_id];

			$row = array(
				'user_id' => $user_id,
				'login' => $user['login'],
				'user' => $user['lastname'] . ', ' . $user['firstname'],
				'is_fixed' => $userObj->is_fixed,
				// performance killer
				//'has_access' => $ilAccess->checkAccessOfUser($user_id, 'read', '', $this->object->getRefId()),
				'no_studycond' => !isset($users_for_studycond[$user_id]),
				'registrations'=> isset($this->priorities[$user_id]) ? count($this->priorities[$user_id]) : 0
			);

			$data[] = $row;
		}

		$this->setMaxCount($user_query_result['cnt']);
		$this->setData($data);
	}

	/**
	 * Fill a single data row
	 *
	 * @param array $a_set [
	 *                  'user_id' => int
	 *                  'user' => string
	 *                  'result' => integer, e.g. SATISFIED_FULL ]
	 */
	protected function fillRow($a_set)
	{
		$this->tpl->setVariable('ID', $a_set['user_id']);
		$this->tpl->setVariable('FIXED', $a_set['is_fixed'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
		$this->tpl->setVariable($a_set['is_fixed'] ? 'LOGIN_FIXED' : 'LOGIN', $a_set['login']);
		$this->tpl->setVariable($a_set['is_fixed'] ? 'USER_FIXED' : 'USER', $a_set['user']);
//		if (!$a_set['has_access'])
//		{
//			$this->tpl->setVariable('NO_ACCESS', $this->lng->txt('permission_denied'));
//		}
		if ($a_set['no_studycond'])
		{
			$this->tpl->setVariable('NO_STUDYCOND', $this->plugin->txt('studycond_not_fulfilled'));
		}
		$this->tpl->setVariable('RESULT_IMAGE', $this->parent->parent->getSatisfactionImageUrl($a_set['result']));
		$this->tpl->setVariable('RESULT_TITLE', $this->parent->parent->getSatisfactionTitle($a_set['result']));
		$this->tpl->setVariable('REGISTRATIONS', $a_set['registrations']);

		$this->tpl->setCurrentBlock('link');
		$this->ctrl->setParameter($this->parent,'user_id', $a_set['user_id']);
		$this->tpl->setVariable('LINK_URL', $this->ctrl->getLinkTarget($this->parent,'editRegistration'));
		$this->tpl->setVariable('LINK_TXT', $this->plugin->txt('edit_registration'));
		$this->tpl->parseCurrentBlock();
	}
}