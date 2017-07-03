<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
/**
 * Table GUI for registration items
 */
class ilCoSubRunsTableGUI extends ilTable2GUI
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
		$this->setId('il_xcos_runs');
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->ctrl = $ilCtrl;

		$this->setFormAction($this->ctrl->getFormAction($this->parent));
		$this->setRowTemplate("tpl.il_xcos_runs_row.html", $this->plugin->getDirectory());

		$this->addColumn('', '', '1%', true);
		$this->addColumn($this->plugin->txt('index'),'start');
		$this->addColumn($this->lng->txt('details'), '','30%');
		$this->addColumn($this->plugin->txt('users_assigned'), 'users_assigned');
		$this->addColumn($this->plugin->txt('users_satisfied'), 'users_satisfied');
		$this->addColumn($this->plugin->txt('items_satisfied'), 'items_status');

		$this->addMultiCommand('confirmDeleteRuns', $this->plugin->txt('delete_assignments'));
	}

	/**
	 * Prepare the data to be displayed
	 * @param   ilCoSubRun[]   $a_runs
	 */
	public function prepareData($a_runs)
	{
		$assignments = $this->parent->object->getAssignments();
		$priorities = $this->parent->object->getPriorities();
		$items = $this->parent->object->getItems();

		$data = array();
		foreach ($a_runs as $index => $run)
		{
			$row = get_object_vars($run);
			$row['index'] = $index;
			$row['run_id'] = $run->run_id;
			$row['start'] = $run->run_start->get(IL_CAL_UNIX);
			$row['details'] = $run->details;

			if (!isset($run->run_end))
			{
				$data[] = $row;
				continue;
			}

			/** @var ilCoSubMethodBase $method */
			if ($method = $this->parent->object->getMethodObjectByClass($row['method']))
			{
				$title = isset($method) ? $method->getTitle() : $this->plugin->txt('calculated');
				$duration = $run->run_end->get(IL_CAL_UNIX) - $run->run_start->get(IL_CAL_UNIX);
				$row['details'] = sprintf($this->plugin->txt('method_duration'), $title, $duration) . "\n" . $row['details'];
			}

			$row['all_users'] = count($priorities);
			$row['users_assigned'] = isset($assignments[$run->run_id]) ? count($assignments[$run->run_id]) : 0;

			// user satisfactions
			$row['users_satisfied'] = 0;
			$row['users_satisfied_full'] = 0;
			$row['users_satisfied_medium'] = 0;
			$row['users_satisfied_not'] = 0;
			foreach (array_keys($priorities) as $user_id)
			{
				switch ($this->parent->object->getSatisfaction($user_id, $run->run_id))
				{
					case ilObjCombiSubscription::SATISFIED_FULL:
						$row['users_satisfied']++;
						$row['users_satisfied_full']++;
						break;
					case ilObjCombiSubscription::SATISFIED_MEDIUM:
						$row['users_satisfied']++;
						$row['users_satisfied_medium']++;
						break;
					case ilObjCombiSubscription::SATISFIED_NOT:
						$row['users_satisfied_not']++;
						break;
				}
			}

			// item satisfactions
			$row['all_items'] = count($this->parent->object->getItems());
			$row['items_satisfied'] = 0;
			$row['items_satisfied_not'] = 0;
			$sums = $this->parent->object->getAssignmentsSums($run->run_id);
			foreach($this->parent->object->getItems() as $item)
			{
				if ($sums[$item->item_id] < $item->sub_min || $sums[$item->item_id] > $item->sub_max)
				{
					$row['items_satisfied_not']++;
				}
				else
				{
					$row['items_satisfied']++;
				}
			}

			$data[] = $row;
		}
		$this->setData($data);
	}

	/**
	 * Fill a single data row
	 */
	protected function fillRow($a_set)
	{
		$this->tpl->setVariable('RUN_ID',$a_set['run_id']);

		$this->tpl->setVariable('INDEX', $this->parent->object->getRunLabel($a_set['index'])
			. ': ' . ilDatePresentation::formatDate(new ilDateTime($a_set['start'], IL_CAL_UNIX)));
		$this->tpl->setVariable('DETAILS', nl2br($a_set['details']));

		$this->tpl->setVariable('USERS_ASSIGNED', $a_set['users_assigned']);
		$this->tpl->setVariable('USERS_SATISFIED', $a_set['users_satisfied']);

		if ($a_set['users_satisfied_not'] > 0)
		{
			$this->tpl->setVariable('USERS_SATISFIED_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_NOT));
			$this->tpl->setVariable('USERS_SATISFIED_STATUS', $this->plugin->txt('satisfied_not').': '.$a_set['users_satisfied_not']);

		}
		elseif ($a_set['users_satisfied_medium'] > 0)
		{
			$this->tpl->setVariable('USERS_SATISFIED_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_MEDIUM));
			$this->tpl->setVariable('USERS_SATISFIED_STATUS', $this->plugin->txt('satisfied_medium').': '.$a_set['users_satisfied_medium']);
		}
		else
		{
			$this->tpl->setVariable('USERS_SATISFIED_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_FULL));
			$this->tpl->setVariable('USERS_SATISFIED_STATUS', $this->plugin->txt('satisfied_full').': '.$a_set['users_satisfied_full']);
		}

		$this->tpl->setVariable('ITEMS_SATISFIED', $a_set['items_satisfied']);
		if ($a_set['items_satisfied_not'] > 0)
		{
			$this->tpl->setVariable('ITEMS_SATISFIED_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_NOT));
			$this->tpl->setVariable('ITEMS_SATISFIED_STATUS', $this->plugin->txt('satisfied_not').': '.$a_set['items_satisfied_not']);
		}
		else
		{
			$this->tpl->setVariable('ITEMS_SATISFIED_IMAGE', $this->parent->parent->getSatisfactionImageUrl(ilObjCombiSubscription::SATISFIED_FULL));
			$this->tpl->setVariable('ITEMS_SATISFIED_STATUS', $this->plugin->txt('satisfied').': '.$a_set['items_satisfied']);
		}
	}
}