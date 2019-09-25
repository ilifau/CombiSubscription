<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
/**
 * Table GUI for registration items
 */
class ilCoSubAssignmentsTableGUI extends ilTable2GUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/**
	 * List of item ids
	 * @var integer[]
	 */
	protected $item_ids = array();

	/**
	 * List of run_ids
	 * @var array   label => run_id
	 */
	protected $run_ids = array();

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
		$this->setRowTemplate('tpl.il_xcos_assignments_row.html', $this->plugin->getDirectory());

		$this->addColumn('','', 1, true);
		$this->addColumn($this->lng->txt('user'), 'user');
		$this->addColumn($this->plugin->txt('satisfaction'), 'result');
		$this->addColumn($this->plugin->txt('assignments'), 'assignments');

		$this->setDefaultOrderField('user');
		$this->setDefaultOrderDirection('asc');
		$this->setSelectAllCheckbox('id');

		$this->addMultiCommand('mailToUsers', $this->plugin->txt('mail_to_users'));
		$this->addMultiCommand('fixUsersConfirmation', $this->plugin->txt('fix_users'));
		$this->addMultiCommand('unfixUsersConfirmation', $this->plugin->txt('unfix_users'));
		$this->addMultiCommand('removeUsersConfirmation', $this->plugin->txt('remove_users'));
		$this->addCommandButton('saveAssignments', $this->plugin->txt('save_assignments'));
		$this->addCommandButton('saveAssignmentsAsRun', $this->plugin->txt('save_assignments_as_run'));
	}

	/**
	 * Prepare the data to be displayed
	 */
	public function prepareData()
	{
		/** @var ilAccessHandler  $ilAccess*/
		global $ilAccess;

		$sums = $this->object->getAssignmentsSums();

		// create the item column headers
		foreach ($this->object->getItems() as $index => $item)
		{
			$this->item_ids[$index] = $item->item_id;
			if (isset($item->sub_min) && isset($item->sub_max))
			{
				$limit = sprintf($this->plugin->txt('sub_limit_from_to'), $item->sub_min, $item->sub_max);
			}
			elseif (isset($item->sub_min))
			{
				$limit = sprintf($this->plugin->txt('sub_limit_from'), $item->sub_min);
			}
			elseif (isset($item->sub_max))
			{
				$limit = sprintf($this->plugin->txt('sub_limit_to'),$item->sub_max);
			}
			$sum = $sums[$item->item_id];

			$tpl = $this->plugin->getTemplate('/default/tpl.il_xcos_assignments_header.html');
			$tpl->setVariable('TITLE', isset($item->identifier) ? $item->identifier : $item->title);

			$tpl->setVariable('LIMIT', $limit);
			$tpl->setVariable('SUM_LABEL', $this->plugin->txt('item_assignment_sum_label'));
			$tpl->setVariable('SUM', $sum);

			if ($sum == 0)
			{
				$tpl->setVariable('SUM_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_EMPTY));
				$tpl->setVariable('SUM_STATUS', $this->plugin->txt('not_assigned'));
			}
			elseif (isset($item->sub_min) && $sum < $item->sub_min)
			{
				$tpl->setVariable('SUM_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_NOT));
				$tpl->setVariable('SUM_STATUS', $this->plugin->txt('sub_min_not_reached'));
			}
			elseif (isset($item->sub_max) && $sum > $item->sub_max)
			{
				$tpl->setVariable('SUM_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_NOT));
				$tpl->setVariable('SUM_STATUS', $this->plugin->txt('sub_max_exceeded'));
			}
			else
			{
				$tpl->setVariable('SUM_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_FULL));
				$tpl->setVariable('SUM_STATUS', $this->plugin->txt('sub_limits_satisfied'));
			}

			$this->addColumn($tpl->get(), 'priority'. $item->item_id);
		}

		if (!$this->object->getMethodObject()->hasMultipleAssignments())
		{
			$this->addColumn($this->plugin->txt('not_assigned'));
		}

		foreach ($this->object->getRunsFinished() as $index => $run)
		{
			$this->run_ids[$this->object->getRunLabel($index)] = $run->run_id;
		}

		$this->users = $this->object->getUsers();
		$this->priorities = $this->object->getPriorities();
		$this->assignments = $this->object->getAssignments();

		$users_for_studycond = $this->object->getUsersForStudyCond(false);

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
				'user' => $user['lastname'] . ', ' . $user['firstname'],
				'result' => $this->object->getUserSatisfaction($user_id, 0),
				'assignments' => 0,
				'is_fixed' => $userObj->is_fixed,
				// performance killer
				//'has_access' => $ilAccess->checkAccessOfUser($user_id, 'read', '', $this->object->getRefId()),
				'no_studycond' => !isset($users_for_studycond[$user_id])
			);

			foreach ($this->item_ids as $item_id)
			{
				$row['priority'.$item_id] = isset($this->priorities[$user_id][$item_id]) ? $this->priorities[$user_id][$item_id] : 999999999;
				$row['assigned'.$item_id] = isset($this->assignments[0][$user_id][$item_id]);
				if ($row['assigned'.$item_id]) {
					$row['assignments']++;
				}
			}
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
		$user_id = $a_set['user_id'];
		$multiple_assignments = $this->object->getMethodObject()->hasMultipleAssignments();

		$assigned = false;
		$assigned_runs = array();
		foreach ($this->item_ids as $item_id)
		{
			$this->tpl->setCurrentBlock('item');

			// priority background
			if (isset($a_set['priority'.$item_id]))
			{
				$color = $this->object->getMethodObject()->getPriorityBackgroundColor($a_set['priority'.$item_id]);
				$this->tpl->setVariable('PRIO_COLOR', 'background-color:'.$color.';');
			}

			// button
			$this->tpl->setVariable('USER_ID', $user_id);
			$this->tpl->setVariable('ITEM_ID', $item_id);
			$this->tpl->setVariable('TYPE', $multiple_assignments ? 'checkbox' : 'radio');
			$this->tpl->setVariable('CHECKED', $a_set['item'.$item_id] ? 'checked="checked"' : '');
			if ($a_set['assigned'.$item_id])
			{
				$this->tpl->setVariable('CHECKED', 'checked="checked"');
				$assigned = true;
			}
			if ($a_set['is_fixed'])
			{
				$this->tpl->setVariable('DISABLED', 'disabled="true"');
			}

			// run list
			$runs = array();
			foreach ($this->run_ids as $label => $run_id)
			{
				if (isset($this->assignments[$run_id][$user_id][$item_id]))
				{
					$runs[] = $label;
					$assigned_runs[$label] = true;
				}
			}
			$this->tpl->setVariable('RUNS', implode(' ', $runs));
			$this->tpl->parseCurrentBlock();
		}

		// 'not assigned' column
		if (!$multiple_assignments)
		{
			$this->tpl->setCurrentBlock('not_assigned');
			$this->tpl->setVariable('USER_ID', $user_id);
			if (!$assigned)
			{
				$this->tpl->setVariable('CHECKED', 'checked="checked"');
			}
			if ($a_set['is_fixed'])
			{
				$this->tpl->setVariable('DISABLED', 'disabled="true"');
			}

			$not_assigned_runs = array_diff(array_keys($this->run_ids), array_keys($assigned_runs));
			$this->tpl->setVariable('RUNS', implode(' ', $not_assigned_runs));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable('ID', $a_set['user_id']);

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
		$this->tpl->setVariable('ASSIGNMENTS', $a_set['assignments']);
	}
}