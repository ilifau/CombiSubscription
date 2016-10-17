<?php

/**
 * Target management for combined subscriptions
 * All course/group related functions should go here
 */
class ilCombiSubscriptionTargets
{
	/** @var  ilObjCombiSubscription */
	protected $object;

	/** @var ilCombiSubscriptionPlugin  */
	protected $plugin;

	/**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		$this->object = $a_object;
		$this->plugin = $a_plugin;
	}

	/**
	 * Add the assigned users as members to the target objects
	 */
	public function addAssignedUsersAsMembers()
	{
		include_once('./Modules/Group/classes/class.ilGroupParticipants.php');
		include_once('./Modules/Group/classes/class.ilGroupMembershipMailNotification.php');

		include_once('./Modules/Course/classes/class.ilCourseParticipants.php');
		include_once('./Modules/Course/classes/class.ilCourseMembershipMailNotification.php');
		require_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

		include_once('./Services/Membership/classes/class.ilSubscribersLot.php');

		// collect the actions to be done
		$actions = array();
		foreach ($this->object->getItems() as $item)
		{
			if (!empty($item->target_ref_id))
			{
				$actions[] = array(
					'ref_id' => $item->target_ref_id,
					'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
					'type' => ilObject::_lookupType($item->target_ref_id, true),
					'users' => array_keys($this->object->getAssignmentsOfItem($item->item_id))
				);
			}
		}

		// do the actions
		foreach ($actions as $action)
		{
			// get membership limitation conditions
			$conditions = ilObjCourseGrouping::_getGroupingConditions($action['obj_id'], $action['type']);

			switch($action['type'])
			{
				case 'grp':
					$part_obj = ilGroupParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_GRP_MEMBER;
					$notification_type = ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER;
					break;

				case 'crs':
					$part_obj = ilCourseParticipants::_getInstanceByObjId($action['obj_id']);
					$role = IL_CRS_MEMBER;
					$notification_type = $part_obj->NOTIFY_ACCEPT_SUBSCRIBER;
					break;
			}

			foreach ($action['users'] as $user_id)
			{
				// check if user is already member in one of the other groups/course
				if (ilObjCourseGrouping::_findGroupingMembership($user_id, $action['type'], $conditions))
				{
					continue;
				}

				// adding the user also deletes the user from the subscribers and from the waiting list
				$part_obj->add($user_id,$role);
				$part_obj->sendNotification($notification_type, $user_id);

				// take the user from the lot lists of the group/course and of the other groups/courses
				ilSubscribersLot::_removeUser($action['obj_id'], $user_id);
				foreach ($conditions as $condition)
				{
					lSubscribersLot::_removeUser($condition['target_obj_id'], $user_id);
				}
			}
		}
	}

	public function addNonAssignedUsersAsSubscribers()
	{
		include_once('./Modules/Group/classes/class.ilObjGroup.php');
		include_once('./Modules/Group/classes/class.ilGroupWaitingList.php');

		include_once('./Modules/Course/classes/class.ilObjCourse.php');
		include_once('./Modules/Course/classes/class.ilCourseWaitingList.php');
		require_once('./Modules/Course/classes/class.ilObjCourseGrouping.php');

		include_once('./Services/Membership/classes/class.ilSubscribersLot.php');

		// collect the actions to be done
		$actions = array();
		foreach ($this->object->getItems() as $item)
		{
			if (!empty($item->target_ref_id))
			{
				// find users who selected the item
				$users = array();
				foreach ($this->object->getPrioritiesOfItem($item->item_id) as $user_id => $priority)
				{
					// take those that failed to get any assignment
					if (!count($this->object->getAssignmentsOfUser($user_id)))
					{
						$users[] = $user_id;
					}
				}

				$actions[] = array(
					'ref_id' => $item->target_ref_id,
					'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
					'type' => ilObject::_lookupType($item->target_ref_id, true),
					'users' => $users
				);
			}
		}

		// do the actions
		foreach ($actions as $action)
		{
			// get membership limitation conditions
			$conditions = ilObjCourseGrouping::_getGroupingConditions($action['obj_id'], $action['type']);

			switch($action['type'])
			{
				case 'grp':
					$object = new ilObjGroup($action['ref_id'], true);
					if ($object->isWaitingListEnabled())
					{
						$list_obj = new ilGroupWaitingList($action['obj_id']);
					}
					elseif ($object->enabledLotList())
					{
						$list_obj = new ilSubscribersLot($action['obj_id']);
					}
					else
					{
						$list_obj = null;
					}
					break;

				case 'crs':
					$object = new ilObjCourse($action['ref_id'], true);
					if ($object->enabledWaitingList())
					{
						$action['list'] = 'waiting_list';
						$list_obj = new ilCourseWaitingList($action['obj_id']);
					}
					elseif ($object->enabledLotList())
					{
						$action['list'] = 'lot_list';
						$list_obj = new ilSubscribersLot($action['obj_id']);
					}
					else
					{
						$list_obj = null;
					}
					break;
			}

			foreach ($action['users'] as $user_id)
			{
				// check if user is already member in one of the other groups/course
				if (ilObjCourseGrouping::_findGroupingMembership($user_id, $action['type'], $conditions))
				{
					continue;
				}

				if (isset($list_obj))
				{
					$list_obj->addToList($user_id);
				}
			}
		}
	}

	/**
	 * Load the users from the lot lists
	 */
	public function loadLotLists()
	{
		include_once('./Services/Membership/classes/class.ilSubscribersLot.php');
		$this->plugin->includeClass('models/class.ilCoSubChoice.php');

		foreach ($this->object->getItems() as $item)
		{
			if (!empty($item->target_ref_id))
			{
				$lot_list = new ilSubscribersLot(ilObject::_lookupObjId($item->target_ref_id));

				foreach($lot_list->getUserIds() as $user_id)
				{
					$choice = new ilCoSubChoice;
					$choice->obj_id = $this->object->getId();
					$choice->item_id = $item->item_id;
					$choice->user_id = $user_id;
					$choice->priority = 0;
					$choice->save();
				}
			}
		}
	}
}