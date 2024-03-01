<?php
use FAU\Ilias\Helper\CourseConstantsHelper;
/**
 * Target management for combined subscriptions
 * All course/group related functions should go here
 */
class ilCombiSubscriptionTargets
{
    protected \ILIAS\DI\Container $dic;
    protected ilObjCombiSubscription $object;
    protected ilCombiSubscriptionPlugin $plugin;

    /** ilCoSubItem[] (indexed by item_id) */
    protected array $items = [];
    /** [['grouping' => ilObjCourseGrouping, 'conditions' => array], ...] */
    protected ?array $groupings = null;

    public function __construct($a_object, $a_plugin)
    {
        global $DIC;

        $this->dic = $DIC;
        $this->object = $a_object;
        $this->plugin = $a_plugin;

        $this->items = $this->object->getItems();
    }


    /**
     * Check if a target type supports a subscriptionPeriod
    */
    public function hasSubscriptionPeriod(string $a_type): bool
    {
        return in_array($a_type, array('crs', 'grp', 'auto'));
    }

    /**
     * Check if a target type supports minimum subscriptions
     */
    public function hasMinSubscriptions(string $a_type): bool
    {
        return in_array($a_type, array('crs', 'grp'));
    }

    /**
     * Check if a target type supports minimum subscriptions
     */
    public function hasMaxSubscriptions(string $a_type): bool
    {
        return in_array($a_type, array('crs', 'grp', 'sess'));
    }

    /**
     * Check if a target type supports membership limitation groupings
     */
    public static function hasMemLimitGrouping(string $a_type): bool
    {
        return in_array($a_type, array('crs', 'grp'));
    }


    /**
     * Get the form properties for setting the targets config
     * @param string $a_type target object type or 'auto' for auto assignment configuration
     * @return array
     */
    public function getFormProperties(string $a_type, ilCoSubTargetsConfig $a_config): array
    {
        $properties = array();

        $set_type = new ilCheckboxInputGUI($this->plugin->txt('set_sub_type'), 'set_sub_type');
        $set_type->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_type_info_auto' : 'set_sub_type_info'));
        $set_type->setChecked($a_config->set_sub_type);
        $properties[] = $set_type;

        $sub_type = new ilRadioGroupInputGUI($this->plugin->txt('sub_type'), 'sub_type');
        $opt = new ilRadioOption($this->plugin->txt('sub_type_combi'), ilCoSubTargetsConfig::SUB_TYPE_COMBI);
        $sub_type->addOption($opt);
        $opt = new ilRadioOption($this->plugin->txt('sub_type_direct'), ilCoSubTargetsConfig::SUB_TYPE_DIRECT);
        $sub_type->addOption($opt);
        $opt = new ilRadioOption($this->plugin->txt('sub_type_confirm'), ilCoSubTargetsConfig::SUB_TYPE_CONFIRM);
        $sub_type->addOption($opt);
        $opt = new ilRadioOption($this->plugin->txt('sub_type_none'), ilCoSubTargetsConfig::SUB_TYPE_NONE);
        $sub_type->addOption($opt);
        $sub_type->setValue($a_config->sub_type);
        $set_type->addSubItem($sub_type);

        if ($this->hasSubscriptionPeriod($a_type)) {
            $set_sub_period = new ilCheckboxInputGUI($this->plugin->txt('set_sub_period'), 'set_sub_period');
            $set_sub_period->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_period_info_auto' : 'set_sub_period_info'));
            $set_sub_period->setChecked($a_config->set_sub_period);
            $properties[] = $set_sub_period;

            $sub_period = new ilDateDurationInputGUI($this->plugin->txt('sub_period'), "sub_period");
            $sub_period->setShowTime(true);
            $sub_period->setStart(new ilDateTime($a_config->sub_period_start, IL_CAL_UNIX));
            $sub_period->setStartText($this->plugin->txt('sub_period_start'));
            $sub_period->setEnd(new ilDateTime($a_config->sub_period_end, IL_CAL_UNIX));
            $sub_period->setEndText($this->plugin->txt('sub_period_end'));
            $set_sub_period->addSubItem($sub_period);
        }

        if ($this->object->getMethodObject()->hasMinSubscription() && $this->hasMinSubscriptions($a_type)) {
            $set_min = new ilCheckboxInputGUI($this->plugin->txt('set_sub_min'), 'set_sub_min');
            $set_min->setInfo($this->plugin->txt('set_sub_min_info'));
            $set_min->setChecked($a_config->set_sub_min);
            $properties[] = $set_min;

            $sub_min_by = new ilRadioGroupInputGUI($this->plugin->txt('set_sub_min'), 'sub_min_by');
            $sub_min_by_item = new ilRadioOption($this->plugin->txt('set_by_item'), ilCoSubTargetsConfig::SET_BY_ITEM);
            $sub_min_by_input = new ilRadioOption($this->plugin->txt('set_by_input'), ilCoSubTargetsConfig::SET_BY_INPUT);
            $sub_min_by_item->setInfo($this->plugin->txt('set_by_item_info'));
            $sub_min_by_input->setInfo($this->plugin->txt('set_by_input_info'));
            $sub_min_by->addOption($sub_min_by_item);
            $sub_min_by->addOption(($sub_min_by_input));

            $sub_min = new ilNumberInputGUI($this->plugin->txt('sub_min_short'), 'sub_min');
            $sub_min->allowDecimals(false);
            $sub_min->setMinValue(0);
            $sub_min->setSize(4);
            $sub_min_by_input->addSubItem($sub_min);
            $set_min->addSubItem($sub_min_by);
        }

        if ($this->object->getMethodObject()->hasMaxSubscription() && $this->hasMaxSubscriptions($a_type)) {
            $set_max = new ilCheckboxInputGUI($this->plugin->txt('set_sub_max'), 'set_sub_max');
            $set_max->setInfo($this->plugin->txt('set_sub_max_info'));
            $set_max->setChecked($a_config->set_sub_max);
            $properties[] = $set_max;

            $sub_max_by = new ilRadioGroupInputGUI($this->plugin->txt('set_sub_max'), 'sub_max_by');
            $sub_max_by_item = new ilRadioOption($this->plugin->txt('set_by_item'), ilCoSubTargetsConfig::SET_BY_ITEM);
            $sub_max_by_input = new ilRadioOption($this->plugin->txt('set_by_input'), ilCoSubTargetsConfig::SET_BY_INPUT);
            $sub_max_by_item->setInfo($this->plugin->txt('set_by_item_info'));
            $sub_max_by_input->setInfo($this->plugin->txt('set_by_input_info'));
            $sub_max_by->addOption($sub_max_by_item);
            $sub_max_by->addOption(($sub_max_by_input));

            $sub_max = new ilNumberInputGUI($this->plugin->txt('sub_max_short'), 'sub_max');
            $sub_max->allowDecimals(false);
            $sub_max->setmaxValue(0);
            $sub_max->setSize(4);
            $sub_max_by_input->addSubItem($sub_max);
            $set_max->addSubItem($sub_max_by);
        }

        $set_wait = new ilCheckboxInputGUI($this->plugin->txt('set_sub_wait'), 'set_sub_wait');
        $set_wait->setInfo($this->plugin->txt($a_type == 'auto' ? 'set_sub_wait_info_auto' : 'set_sub_wait_info'));
        $set_wait->setChecked($a_config->set_sub_wait);
        $properties[] = $set_wait;

        $sub_wait = new ilRadioGroupInputGUI($this->plugin->txt('sub_wait'), 'sub_wait');
        $sub_wait->setValue($a_config->sub_wait);
        $opt = new ilRadioOption($this->plugin->txt('sub_wait_auto'), ilCoSubTargetsConfig::SUB_WAIT_AUTO);
        $sub_wait->addOption($opt);
        $opt = new ilRadioOption($this->plugin->txt('sub_wait_manu'), ilCoSubTargetsConfig::SUB_WAIT_MANU);
        $sub_wait->addOption($opt);
        $opt = new ilRadioOption($this->plugin->txt('sub_wait_none'), ilCoSubTargetsConfig::SUB_WAIT_NONE);
        $sub_wait->addOption($opt);
        $set_wait->addSubItem($sub_wait);

        return $properties;
    }

    /**
     * Get the inputs from the properties form
     * @param string $a_type target type
     */
    public function getFormInputs(ilPropertyFormGUI $form, string $a_type, ?ilCoSubTargetsConfig $config = null): ilCoSubTargetsConfig
    {
        if (!isset($config)) {
            $config = new ilCoSubTargetsConfig($this->object);
        }

        $config->set_sub_type = (bool)$form->getInput('set_sub_type');
        $config->sub_type = (string)$form->getInput('sub_type');


        if ($this->hasSubscriptionPeriod($a_type)) {
            $config->set_sub_period = (bool)$form->getInput('set_sub_period');

            /** @var ilDateDurationInputGUI $sub_period */
            $sub_period = $form->getItemByPostVar('sub_period');
            $start = $sub_period->getStart();
            $end = $sub_period->getEnd();
            $config->sub_period_start = (int)(isset($start) ? $sub_period->getStart()->get(IL_CAL_UNIX) : null);
            $config->sub_period_end = (int)(isset($end) ? $sub_period->getEnd()->get(IL_CAL_UNIX) : null);
        } else {
            $config->set_sub_period = false;
            $config->sub_period_start = null;
            $config->sub_period_end = null;
        }

        if ($this->object->getMethodObject()->hasMinSubscription() && $this->hasMinSubscriptions($a_type)) {
            $config->set_sub_min = (bool)$form->getInput('set_sub_min');
            $config->sub_min_by = (string)$form->getInput('sub_min_by');
            $config->sub_min = (int)$form->getInput('sub_min');
        } else {
            $config->set_sub_min = false;
        }
        if ($this->object->getMethodObject()->hasMaxSubscription() && $this->hasMaxSubscriptions($a_type)) {
            $config->set_sub_max = (bool)$form->getInput('set_sub_max');
            $config->sub_max_by = (string)$form->getInput('sub_max_by');
            $config->sub_max = (int)$form->getInput('sub_max');
        } else {
            $config->set_sub_max = false;
        }

        $config->set_sub_wait = (bool)$form->getInput('set_sub_wait');
        $config->sub_wait = (string)$form->getInput('sub_wait');

        return $config;
    }

    /**
     * Get a new category for a target reference
     */
    public function getCategoryForTarget(int $a_ref_id): ilCoSubCategory
    {
        $category = new ilCoSubCategory();
        $category->obj_id = $this->object->getId();
        $category->max_assignments = 1;

        switch (ilObject::_lookupType($a_ref_id, true)) {
            case 'crs':
                $course = new ilObjCourse($a_ref_id, true);
                $category->title = $course->getTitle();
                $category->description = $course->getDescription();
                $category->import_id = $course->getImportId();
                break;
        }
        return $category;
    }

    /**
     * Get an item for a target reference
     * @param ilCoSubItem $item (an existing item that should be modified)
     */
    public function getItemForTarget(int $a_ref_id, ?ilCoSubItem $item = null): ilCoSubItem
    {
        if (!isset($item)) {
            $item = new ilCoSubItem;
            $item->obj_id = $this->object->getId();
        }
        $item->target_ref_id = $a_ref_id;

        switch (ilObject::_lookupType($a_ref_id, true)) {
            case 'crs':
                $course = new ilObjCourse($a_ref_id, true);
                $item->title = $course->getTitle();
                $item->description = $course->getDescription();
                $item->import_id = $course->getImportId();
                if ($course->isSubscriptionMembershipLimited()) {
                    $item->sub_min = $course->getSubscriptionMinMembers();
                    $item->sub_max = $course->getSubscriptionMaxMembers();
                }
                break;

            case 'grp':
                $group = new ilObjGroup($a_ref_id, true);
                $item->title = $group->getTitle();
                $item->description = $group->getDescription();
                $item->import_id = $group->getImportId();
                if ($group->isMembershipLimited()) {
                    $item->sub_min = $group->getMinMembers();
                    $item->sub_max = $group->getMaxMembers();
                }
                break;

            case 'sess':
                $session = new ilObjSession($a_ref_id, true);
                $item->title = $session->getTitle();
                $item->description = $session->getDescription();
                if ($session->isRegistrationUserLimitEnabled()) {
                    $item->sub_min = $session->getRegistrationMinUsers();
                    $item->sub_max = $session->getRegistrationMaxUsers();
                }
                break;
        }
        return $item;
    }


    /**
     * Synchronize the items from the targets before the assignments are calculated
     * - Course members will be added as users with fixed assignments
     * - Maximum assignments is set to the lower value of the subscription and the target
     */
    public function syncFromTargetsBeforeCalculation(): void
    {
        $users = $this->object->getUsers();
        $assignments = $this->object->getAssignments();

        $this->filterUntrashedTargets();
        foreach ($this->items as $item_id => $item) {
            if (!empty($item->target_ref_id)) {
                $obj_id = ilObject::_lookupObjectId($item->target_ref_id);
                $max = 0;

                switch (ilObject::_lookupType($obj_id, false)) {
                    case 'crs':
                        $info = ilObjCourseAccess::lookupRegistrationInfo($obj_id, $item->target_ref_id);
                        $partObj = ilCourseParticipants::_getInstanceByObjId($obj_id);
                        $max = (int)$info['reg_info_max_members'];
                        break;

                    case 'grp':
                        $info = ilObjGroupAccess::lookupRegistrationInfo($obj_id, $item->target_ref_id);
                        $partObj = ilGroupParticipants::_getInstanceByObjId($obj_id);
                        $max = (int)$info['reg_info_max_members'];
                        break;

                    case 'sess':
                        $sessObj = new ilObjSession($item->target_ref_id, true);
                        $partObj = ilSessionParticipants::_getInstanceByObjId($obj_id);
                        $max = (int)$sessObj->getRegistrationMaxUsers();
                        break;
                }

                // adjust the maximum assignments
                if ($max > 0 && (empty($item->sub_max) || $item->sub_max > $max)) {
                    $item->sub_max = $max;
                    $item->save();
                }

                // add members as fixed assignments
                foreach ($partObj->getMembers() as $user_id) {
                    if (!isset($users[$user_id])) {
                        $subUser = new ilCoSubUser();
                        $subUser->obj_id = $this->object->getId();
                        $subUser->user_id = $user_id;
                        $users[$user_id] = $subUser;
                    }
                    $subUser = $users[$user_id];
                    $subUser->is_fixed = true;
                    $subUser->save();

                    if (!isset($assignments[0][$user_id][$item_id])) {
                        $assObj = new ilCoSubAssign();
                        $assObj->obj_id = $this->object->getId();
                        $assObj->user_id = $user_id;
                        $assObj->item_id = $item_id;
                        $assObj->run_id = 0;
                        $assObj->save();
                        $assignments[0][$user_id][$item_id] = $assObj->assign_id;
                    }
                }
            }
        }

        // re-read users and assignments for a proper calculation
        $this->object->getUsers([], true);
        $this->object->getAssignments(true);
    }

    /**
     * Get a list of unsaved schedules for a target object
     *
     * @return ilCoSubSchedule[]
     */
    public function getSchedulesForTarget(int $a_ref_id): array
    {
        $schedules = array();
        switch (ilObject::_lookupType($a_ref_id, true)) {
            case 'sess':
                $session = new ilObjSession($a_ref_id, true);
                if ($session->getAppointments()) {
                    /** @var ilSessionAppointment $app */
                    $app = $session->getFirstAppointment();
                    $schedule = new ilCoSubSchedule();
                    $schedule->period_start = $app->getStart()->get(IL_CAL_UNIX);
                    $schedule->period_end = $app->getEnd()->get(IL_CAL_UNIX);
                    $schedules[] = $schedule;
                }
                break;
        }

        return $schedules;
    }

    /**
     * Add the assigned users as members to the target objects
     * @param array $a_item_ids list if item ids to tread
     * @param ?bool $send_target_emails send notification e-mails for the target objects
     * @return array    list of added user_ids
     */
    public function addAssignedUsersAsMembers(array $a_item_ids = [], ?bool $send_target_emails = null): array
    {
        global $tree;

        if (!isset($send_target_emails)) {
            $config = new ilCoSubTargetsConfig($this->object);
            $config->readFromObject();
            $send_target_emails = $config->send_target_emails;
        }

        $added_users = array();

        // collect the assigning actions to be done
        $actions = array();
        foreach ($this->items as $item) {
            // treat only selected items, if list is given
            if (!empty($a_item_ids) && !in_array($item->item_id, $a_item_ids)) {
                continue;
            }

            if (!empty($item->target_ref_id)) {
                // get the users to be assigned
                $users = array_keys($this->object->getAssignmentsOfItem($item->item_id));
                $module_ids = [];
                foreach ($users as $user_id) {
                    $module_ids[$user_id] = ilCoSubChoice::_getModuleId($this->object->getId(), $user_id, [$item->item_id]);
                }

                // prepare the actions for an object and its parents
                foreach ($tree->getNodePath($item->target_ref_id) as $node) {
                    $ref_id = $node['child'];
                    $obj_id = $node['obj_id'];
                    $type = $node['type'];

                    // index actions by ref_id to treat each object only once
                    // parent objects are added first
                    if (isset($actions[$ref_id])) {
                        $actions[$ref_id]['users'] = array_unique(array_merge($actions[$ref_id]['users'], $users));
                    } else {
                        $actions[$ref_id] = array(
                            'ref_id' => $ref_id,
                            'obj_id' => $obj_id,
                            'type' => $type,
                            'users' => $users,
                            // set module ids only for the item object, not for its parents
                            'module_ids' => $ref_id == $item->target_ref_id ? $module_ids : []
                        );
                    }
                }
            }
        }

        // do the actions
        foreach ($actions as $ref_id => $action) {
            // get membership limitation conditions
            $conditions = self::_getGroupingConditions($action['obj_id'], $action['type']);

            switch ($action['type']) {
                case 'crs':
                    $part_obj = ilCourseParticipants::_getInstanceByObjId($action['obj_id']);
                    $role = IL_CRS_MEMBER;
                    $mail_obj = new ilCourseMembershipMailNotification();
                    $mail_obj->setRefId($ref_id);
                    $mail_obj->setType(ilCourseMembershipMailNotification::TYPE_ADMISSION_MEMBER);
                    $mail_obj->setLangModules(array('crs', 'grp', 'sess'));
                    break;

                case 'grp':
                    $part_obj = ilGroupParticipants::_getInstanceByObjId($action['obj_id']);
                    $role = IL_GRP_MEMBER;
                    $mail_obj = new ilGroupMembershipMailNotification();
                    $mail_obj->setRefId($ref_id);
                    $mail_obj->setType(ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER);
                    $mail_obj->setLangModules(array('crs', 'grp', 'sess'));
                    break;

                case 'sess':
                    $part_obj = ilSessionParticipants::_getInstanceByObjId($action['obj_id']);
                    $role = IL_SESS_MEMBER;
                    $mail_obj = new ilSessionMembershipMailNotification();
                    $mail_obj->setRefId($ref_id);
                    $mail_obj->setType(ilSessionMembershipMailNotification::TYPE_ADMISSION_MEMBER);
                    $mail_obj->setLangModules(array('crs', 'grp', 'sess'));
                    break;

                default:
                    continue 2;    // next action
            }

            $added_members = array();
            foreach ($action['users'] as $user_id) {
                // check if user is already a member (relevant for parent course)
                if ($part_obj->isAssigned($user_id)) {
                    continue;
                }
                // check if user is already member in one of the other groups/course
                if (self::_findGroupingMembership($user_id, $action['type'], $conditions)) {
                    continue;
                }

                // adding the user also deletes the user from the subscribers and from the waiting list
                if ($part_obj instanceof ilSessionParticipants) {
                    $part_obj->register($user_id);
                } elseif (isset($role)) {
                    $part_obj->add($user_id, $role);
                } else {
                    $part_obj->add($user_id);
                }
                if ($this->plugin->hasFauService()) {
                    if (isset($action['module_ids'][$user_id])) {
                        $this->dic->fau()->user()->saveMembership($action['obj_id'], $user_id, $action['module_ids'][$user_id]);
                    }
                }
                $added_members[] = $user_id;
                $added_users[] = $user_id;
            }

            if (!empty($added_members) && $send_target_emails) {
                $mail_obj->setRecipients($added_members);
                $mail_obj->send();
            }
        }

        return array_unique($added_users);
    }

    /**
     * Put the non assigned users on the waiting list of target objects
     * @param array $a_item_ids list if item ids to tread
     * @return array    list of added user_ids
     */
    public function addNonAssignedUsersAsSubscribers(array $a_item_ids = []): array
    {
        $num_assignments = $this->object->getMethodObject()->getNumberAssignments();
        $studycond_passed = $this->object->getUsersForStudyCond();
        $restrictions_passed = $this->object->getPrioritiesWithPassedRestrictions();

        // collect the actions to be done
        $actions = array();
        foreach ($this->items as $item) {
            // treat only selected items, if list is given
            if (!empty($a_item_ids) && !in_array($item->item_id, $a_item_ids)) {
                continue;
            }

            if (!empty($item->target_ref_id)) {
                // find users who selected the item
                $users = array();
                $passed = array();
                $module_ids = array();
                $assigned_users = $this->object->getAssignmentsOfItem($item->item_id);

                foreach ($this->object->getPrioritiesOfItem($item->item_id) as $user_id => $priority) {
                    if (isset($assigned_users[$user_id])) {
                        continue;
                    }

                    $module_ids[$user_id] = ilCoSubChoice::_getModuleId($this->object->getId(), $user_id, [$item->item_id]);
                    if (isset($studycond_passed[$user_id]) && isset($restrictions_passed[$user_id][$item->item_id])) {
                        $passed[$user_id] = true;
                    }

                    // take those that do not have enough assignments
                    if (count($this->object->getAssignmentsOfUser($user_id)) < $num_assignments) {
                        $users[] = $user_id;
                    }
                }

                $actions[] = array(
                    'ref_id' => $item->target_ref_id,
                    'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
                    'type' => ilObject::_lookupType($item->target_ref_id, true),
                    'users' => $users,
                    'passed' => $passed,
                    'module_ids' => $module_ids
                );

                if ($this->plugin->hasFauService()) {
                    if ($parent_ref_id = $this->dic->fau()->ilias()->objects()->findParentIliasCourse($item->target_ref_id)) {
                        $actions[] = array(
                            'ref_id' => $parent_ref_id,
                            'obj_id' => ilObject::_lookupObjId($parent_ref_id),
                            'type' => ilObject::_lookupType($parent_ref_id, true),
                            'users' => $users,
                            'passed' => $passed,
                            'module_ids' => $module_ids
                        );
                    }
                }
            }
        }

        return $this->addSubscribersByActions($actions);
    }

    /**
     * Put the assigned users on the waiting list of target objects (workaround)
     * @param array $a_item_ids list if item ids to tread
     * @return array    list of added user_ids
     */
    public function addAssignedUsersAsSubscribers(array $a_item_ids = []): array
    {
        // collect the actions to be done
        $actions = array();
        foreach ($this->items as $item) {
            // treat only selected items, if list is given
            if (!empty($a_item_ids) && !in_array($item->item_id, $a_item_ids)) {
                continue;
            }

            if (!empty($item->target_ref_id)) {
                // find users who selected the item
                $users = array();
                $module_ids = array();
                foreach ($this->object->getAssignmentsOfItem($item->item_id) as $user_id => $assign_id) {
                    $module_ids[$user_id] = ilCoSubChoice::_getModuleId($this->object->getId(), $user_id, [$item->item_id]);
                    $users[] = $user_id;
                }

                $actions[] = array(
                    'ref_id' => $item->target_ref_id,
                    'obj_id' => ilObject::_lookupObjId($item->target_ref_id),
                    'type' => ilObject::_lookupType($item->target_ref_id, true),
                    'users' => $users,
                    'passed' => $users,         // assigned users should have passed the conditions and restrictions
                    'module_ids' => $module_ids
                );

                if ($this->plugin->hasFauService()) {
                    if ($parent_ref_id = $this->dic->fau()->ilias()->objects()->findParentIliasCourse(
                        $item->target_ref_id
                    )) {
                        $actions[] = array(
                            'ref_id' => $parent_ref_id,
                            'obj_id' => ilObject::_lookupObjId($parent_ref_id),
                            'type' => ilObject::_lookupType($parent_ref_id, true),
                            'users' => $users,
                            'passed' => $users,  // assigned users should have passed the conditions and restrictions
                            'module_ids' => $module_ids
                        );
                    }
                }
            }
        }

        return $this->addSubscribersByActions($actions);
    }


    /**
     * Put users on the waiting list of target objects
     * @param array     [[ref_id => int, obj_id => int, type => string, users => int[] ], ...]
     * @return array    list of added user_ids
     */
    protected function addSubscribersByActions(array $actions): array
    {
        $added_users = array();

        // do the actions
        foreach ($actions as $action) {
            // get membership limitation conditions
            $conditions = self::_getGroupingConditions($action['obj_id'], $action['type']);

            switch ($action['type']) {
                case 'grp':
                    $object = new ilObjGroup($action['ref_id'], true);
                    if ($this->plugin->hasFauService() && $object->isParallelGroup()) {
                        // check waiting list setting of parent course for parllel groups
                        $parent_ref_id = $this->dic->fau()->ilias()->objects()->findParentIliasCourse((int)$action['ref_id']);
                        $parent = new ilObjCourse($parent_ref_id, true);
                        $list_obj = $parent->enabledWaitingList() ? new ilGroupWaitingList($action['obj_id']) : null;
                    }
                    else {
                        $list_obj = $object->isWaitingListEnabled() ? new ilGroupWaitingList($action['obj_id']) : null;
                    }
                    break;

                case 'crs':
                    $object = new ilObjCourse($action['ref_id'], true);
                    $list_obj = $object->enabledWaitingList() ? new ilCourseWaitingList($action['obj_id']) : null;
                    break;

                case 'sess':
                    $object = new ilObjSession($action['ref_id'], true);
                    $list_obj = $object->isRegistrationWaitingListEnabled() ? new ilSessionWaitingList($action['obj_id']) : null;
                    break;
            }

            foreach ($action['users'] as $user_id) {
                // check if user is already member in one of the other groups/course
                if (self::_findGroupingMembership($user_id, $action['type'], $conditions)) {
                    continue;
                }

                if (isset($list_obj)) {
                    if ($this->plugin->hasFauService()) {
                        if (!isset($action['passed']) || isset($action['passed'][$user_id])) {
                            $to_confirm = ilWaitingList::REQUEST_NOT_TO_CONFIRM;
                        }
                        else {
                            $to_confirm = ilWaitingList::REQUEST_TO_CONFIRM;
                        }
                        $list_obj->addToList($user_id, '', $to_confirm);
                        if (!empty($action['module_ids'][$user_id])) {
                            $list_obj->updateModuleId($user_id, $action['module_ids'][$user_id]);
                        }
                    }
                    else {
                        $list_obj->addToList($user_id);
                    }

                    $added_users[] = $user_id;
                }
            }
        }

        return array_unique($added_users);
    }

    /**
     * Read the list of groupings for the item targets
     */
    public function getGroupingData(): array
    {
        if (!isset($this->groupings)) {
            $this->groupings = array();
            foreach ($this->object->getItems() as $item) {
                if (isset($item->target_ref_id)) {
                    $obj_id = ilObject::_lookupObjId($item->target_ref_id);
                    foreach (ilObjCourseGrouping::_getGroupings($obj_id) as $grouping_id) {
                        $grouping = new ilObjCourseGrouping($grouping_id);
                        $conditions = $grouping->getAssignedItems();
                        $this->groupings[] = array('grouping' => $grouping, 'conditions' => $conditions);
                    }
                }
            }
        }
        return $this->groupings;
    }

    /**
     * Get the groupings of an item
     * @return ilObjCourseGrouping[]
     */
    public function getGroupingsOfItem(ilCoSubItem $a_item): array
    {
        if (!isset($a_item->target_ref_id)) {
            return array();
        }
        $groupings = array();
        foreach ($this->getGroupingData() as $groupingData) {
            foreach ($groupingData['conditions'] as $condition) {
                if ($condition['target_ref_id'] == $a_item->target_ref_id) {
                    $groupings[] = $groupingData['grouping'];
                }
            }
        }
        return $groupings;
    }

    /**
     * Add a grouping for the items
     */
    public function addGrouping(): void
    {
        $grouping = new ilObjCourseGrouping();
        $ref_ids = $this->getTargetRefIds();
        if (empty($ref_ids)) {
            return;
        }

        $ref_id = $ref_ids[0];
        $obj_id = ilObject::_lookupObjId($ref_id);

        $grouping->setContainerRefId($ref_id);
        $grouping->setContainerObjId($obj_id);
        $grouping->setContainerType($this->getCommonType());
        $grouping->setTitle($this->object->getTitle());
        $grouping->setUniqueField('login');
        $grouping->create($ref_id, $obj_id);

        foreach ($ref_ids as $ref_id) {
            $obj_id = ilObject::_lookupObjId($ref_id);
            $grouping->assign($ref_id, $obj_id);
        }
    }

    /**
     * Remove a course grouping from the items
     */
    public function removeGrouping(): void
    {
        foreach ($this->items as $item) {
            foreach ($this->getGroupingsOfItem($item) as $grouping) {
                $grouping->deassign($item->target_ref_id, ilObject::_lookupObjId($item->target_ref_id));
            }
        }

        foreach ($this->getGroupingData() as $data) {
            /** @var ilObjCourseGrouping $grouping */
            $grouping = $data['grouping'];
            if ($grouping->getCountAssignedItems() < 2) {
                $grouping->delete();
            }
        }
    }

    /**
     * Get grouping conditions of a container object
     *
     * @return    array   assoc: grouping conditions
     */
    static function _getGroupingConditions(int $a_obj_id, string $a_type): array
    {
        global $tree;

        if (!self::hasMemLimitGrouping($a_type)) {
            return array();
        }

        static $cached_conditions;
        if (isset($cached_conditions[$a_obj_id])) {
            return $cached_conditions[$a_obj_id];
        }

        $ref_id = current(ilObject::_getAllReferences($a_obj_id));
        $trigger_ids = array();
        $conditions = array();

        foreach (ilConditionHandler::_getPersistedConditionsOfTarget($ref_id, $a_obj_id, $a_type) as $condition) {
            if ($condition['operator'] == 'not_member') {
                $trigger_ids[] = $condition['trigger_obj_id'];
            }
        }
        foreach ($trigger_ids as $trigger_id) {
            foreach (ilConditionHandler::_getPersistedConditionsOfTrigger('crsg', $trigger_id) as $condition) {
                // Handle deleted items
                if (!$tree->isDeleted($condition['target_ref_id'])
                    and $condition['operator'] == 'not_member') {
                    $conditions[$condition['target_ref_id']] = $condition;
                }
            }
        }

        $cached_conditions[$a_obj_id] = array_values($conditions);
        return $cached_conditions[$a_obj_id];
    }


    /**
     * Check the grouping conditions for a user
     *
     * @param string $type 'grp' or 'crs'
     * @return   string     obj_id
     */
    static function _findGroupingMembership(int $user_id, string $type, array $conditions): string
    {
        foreach ($conditions as $condition) {
            if ($type == 'crs') {
                $members = ilCourseParticipants::_getInstanceByObjId($condition['target_obj_id']);
                if ($members->isGroupingMember($user_id, $condition['value'])) {
                    return $condition['target_obj_id'];
                }
            } elseif ($type == 'grp') {
                $members = ilGroupParticipants::_getInstanceByObjId($condition['target_obj_id']);
                if ($members->isGroupingMember($user_id, $condition['value'])) {
                    return $condition['target_obj_id'];
                }
            }
        }
        return false;
    }

    /**
     * Check if items with targets exist
     */
    public function targetsExist(): bool
    {
        $ref_ids = $this->getTargetRefIds();
        return !empty($ref_ids);
    }

    /**
     * Check if all existing targets are writable
     */
    public function targetsWritable(): bool
    {
        /** @var ilAccessHandler $ilAccess */
        global $ilAccess;

        foreach ($this->getTargetRefIds() as $ref_id) {
            if (!$ilAccess->checkAccess('write', '', $ref_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * get the common type of the targets
     * @return string|null        type or null if they have different types
     */
    public function getCommonType(): ?string
    {
        $type = null;
        foreach ($this->getTargetRefIds() as $ref_id) {
            $newtype = ilObject::_lookupType($ref_id, true);
            if (empty($type)) {
                $type = $newtype;
            } elseif ($type != $newtype) {
                return null;
            }
        }
        return $type;
    }

    /**
     * Get the ref_ids of all targets
     * @return int[]
     */
    public function getTargetRefIds(): array
    {
        $ref_ids = array();
        foreach ($this->items as $item) {
            if (!empty($item->target_ref_id)) {
                $ref_ids[] = $item->target_ref_id;
            }
        }
        return $ref_ids;
    }

    /**
     * Set the items by an array of item_ids \
     * $a_item_ids int[]
     */
    public function setItemsByIds(array $a_item_ids): void
    {
        $this->items = array();
        foreach ($this->object->getItems() as $item) {
            if (in_array($item->item_id, $a_item_ids)) {
                $this->items[$item->item_id] = $item;
            }
        }
    }

    /**
     * Set the items to be treated
     * - $a_item ilCoSubItem[]
     */
    public function setItems(array $a_items): void
    {
        $this->items = array();
        foreach ($a_items as $item) {
            $this->items[$item->item_id] = $item;
        }
    }

    /**
     * Restrict the list of items to those with writable targets
     */
    public function filterWritableTargets(): void
    {
        /** @var ilAccessHandler $ilAccess */
        global $ilAccess;

        foreach ($this->items as $item_id => $item) {
            if (!$ilAccess->checkAccess('write', '', $item->target_ref_id)) {
                unset($this->items[$item_id]);
            }
        }
    }

    /**
     * Restrict the list of items to existing, untrashed targets
     */
    public function filterUntrashedTargets(): void
    {
        foreach ($this->items as $item_id => $item) {
            if (!ilObject::_exists($item->target_ref_id, true) || ilObject::_isInTrash($item->target_ref_id)) {
                unset($this->items[$item_id]);
            }
        }
    }

    /**
     * Apply the default configuration settings to the target objects
     * This is done when new target objects are connected
     * - the subscription type is set to the combined subscription
     * - the subscription period is set to the period of the combined subscription
     */
    public function applyDefaultTargetsConfig(): bool
    {
        $config = new ilCoSubTargetsConfig($this->object);
        $config->set_sub_type = true;
        $config->sub_type = ilCoSubTargetsConfig::SUB_TYPE_COMBI;

        $config->set_sub_period = true;
        $config->sub_period_start = $this->object->getSubscriptionStart()->get(IL_CAL_UNIX);
        $config->sub_period_end = $this->object->getSubscriptionEnd()->get(IL_CAL_UNIX);

        try {
            $this->applyTargetsConfig($config);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }


    /**
     * Apply configuration settings to the target objects
     * @throws Exception
     */
    public function applyTargetsConfig(ilCoSubTargetsConfig $config): void
    {
        $targets = array();
        $parents = array();

        foreach ($this->items as $item) {
            if (!empty($item->target_ref_id)) {
                $target = ilObjectFactory::getInstanceByRefId($item->target_ref_id, false);
                if (!is_object($target)) {
                    throw new Exception(sprintf($this->plugin->txt('target_object_not_found'), $item->title));
                }
                if (!in_array($target->getType(), $this->plugin->getAvailableTargetTypes())) {
                    throw new Exception(sprintf($this->plugin->txt('target_object_wrong_type'), $item->title));
                }

                $targets[$item->item_id] = $target;

                // get the parent course for parallel groups
                if ($this->plugin->hasFauService() && $target instanceof ilObjGroup) {
                    if ($target->isParallelGroup()) {
                        $parent_ref_id = $this->dic->fau()->ilias()->objects()->findParentIliasCourse($target->getRefId());
                        $parents[$item->item_id] = ilObjectFactory::getInstanceByRefId($parent_ref_id, false);
                    }
                }
            }
        }

        foreach ($targets as $item_id => $target) {
            $item = $this->items[$item_id];

            if ($target instanceof ilObjCourse) {
                $this->applyTargetCourseConfig($target, $config, $item);
            }
            elseif ($target instanceof ilObjGroup) {
                if (isset($parents[$item_id]) && $parents[$item_id] instanceof ilObjCourse) {
                    // group is a parallel group within a counrse
                    // split the configuration settings for parent course and group
                    $course_config = clone $config;
                    $course_config->set_sub_max = false;
                    $course_config->set_sub_min = false;
                    $this->applyTargetCourseConfig($parents[$item_id], $course_config, $item);

                    $group_config = clone $config;
                    $group_config->set_sub_period = false;
                    $group_config->set_sub_type = false;
                    $group_config->set_sub_wait = false;
                    $this->applyTargetGroupConfig($target, $group_config, $item);
                }
                else {
                    $this->applyTargetGroupConfig($target, $config, $item);
                }
            }
            elseif ($target instanceof ilObjSession) {
                $this->applyTargetSessionConfig($target, $config, $item);
            }
        }
    }

    /**
     * Apply a configuration to a target course
     */
    protected function applyTargetCourseConfig(ilObjCourse $target, ilCoSubTargetsConfig $config, ilCoSubItem $item): void
    {
        if ($config->set_sub_type) {
            switch ($config->sub_type) {
                case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
                    $target->setSubscriptionType(CourseConstantsHelper::IL_CRS_SUBSCRIPTION_OBJECT);
                    $target->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_LIMITED);
                    $target->setSubscriptionRefId($this->object->getRefId());
                    $target->setSubscriptionStart($this->object->getSubscriptionStart()->get(IL_CAL_UNIX));
                    $target->setSubscriptionEnd($this->object->getSubscriptionEnd()->get(IL_CAL_UNIX));

                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
                    $target->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_CONFIRMATION);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
                    $target->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_NONE:
                    $target->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_DEACTIVATED);
                    break;
            }
        }

        if ($config->set_sub_period) {
            $target->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_LIMITED);
            $target->setSubscriptionStart($config->sub_period_start);
            $target->setSubscriptionEnd($config->sub_period_end);
        }

        if ($config->set_sub_min) {
            $target->enableSubscriptionMembershipLimitation(true);
            if ($config->sub_min_by == ilCoSubTargetsConfig::SET_BY_ITEM) {
                $target->setSubscriptionMinMembers($item->sub_min);
            } elseif ($config->sub_min_by == ilCoSubTargetsConfig::SET_BY_INPUT) {
                $target->setSubscriptionMinMembers($config->sub_min);
                $item->sub_min = $config->sub_min;
                $item->save();
            }
        }
        if ($config->set_sub_max) {
            $target->enableSubscriptionMembershipLimitation(true);
            if ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_ITEM) {
                $target->setSubscriptionMaxMembers($item->sub_max);
            } elseif ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_INPUT) {
                $target->setSubscriptionMaxMembers($config->sub_max);
                $item->sub_max = $config->sub_max;
                $item->save();
            }
        }

        if ($config->set_sub_wait) {
            switch ($config->sub_wait) {
                case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
                    $target->enableWaitingList(true);
                    $target->setWaitingListAutoFill(true);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_MANU:
                    $target->enableWaitingList(true);
                    $target->setWaitingListAutoFill(false);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_NONE:
                    $target->enableWaitingList(false);
                    break;
            }
        }
        $target->update();
    }

    /**
     * Apply a configuration to a target group
     */
    protected function applyTargetGroupConfig(ilObjGroup $target, ilCoSubTargetsConfig $config, ilCoSubItem $item): void
    {
        if ($config->set_sub_type) {
            switch ($config->sub_type) {
                case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
                    $target->setRegistrationType(GRP_REGISTRATION_OBJECT);
                    $target->setRegistrationRefId($this->object->getRefId());
                    $target->setRegistrationStart($this->object->getSubscriptionStart());
                    $target->setRegistrationEnd($this->object->getSubscriptionEnd());

                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
                    $target->setRegistrationType(GRP_REGISTRATION_REQUEST);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
                    $target->setRegistrationType(GRP_REGISTRATION_DIRECT);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_NONE:
                    $target->setRegistrationType(GRP_REGISTRATION_DEACTIVATED);
                    break;
            }
        }

        if ($config->set_sub_period) {
            $target->enableUnlimitedRegistration(false);
            $target->setRegistrationStart(new ilDateTime($config->sub_period_start, IL_CAL_UNIX));
            $target->setRegistrationEnd(new ilDateTime($config->sub_period_end, IL_CAL_UNIX));
        }

        if ($config->set_sub_min) {
            $target->enableMembershipLimitation(true);
            if ($config->sub_min_by == ilCoSubTargetsConfig::SET_BY_ITEM) {
                $target->setMinMembers($item->sub_min);
            } elseif ($config->sub_min_by == ilCoSubTargetsConfig::SET_BY_INPUT) {
                $target->setMinMembers($config->sub_min);
                $item->sub_min = $config->sub_min;
                $item->save();
            }
        }
        if ($config->set_sub_max) {
            $target->enableMembershipLimitation(true);
            if ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_ITEM) {
                $target->setMaxMembers($item->sub_max);
            } elseif ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_INPUT) {
                $target->setMaxMembers($config->sub_max);
                $item->sub_max = $config->sub_max;
                $item->save();
            }
        }

        if ($config->set_sub_wait) {
            switch ($config->sub_wait) {
                case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
                    $target->enableWaitingList(true);
                    $target->setWaitingListAutoFill(true);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_MANU:
                    $target->enableWaitingList(true);
                    $target->setWaitingListAutoFill(false);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_NONE:
                    $target->enableWaitingList(false);
                    break;
            }
        }

        $target->update();
    }

    /**
     * Apply a configuration to a target session
     */
    protected function applyTargetSessionConfig(ilObjSession $target, ilCoSubTargetsConfig $config, ilCoSubItem $item): void
    {
        if ($config->set_sub_type) {
            switch ($config->sub_type) {
                case ilCoSubTargetsConfig::SUB_TYPE_COMBI:
                    $target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_OBJECT);
                    $target->setRegistrationRefId($this->object->getRefId());
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_CONFIRM:
                    $target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_REQUEST);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_DIRECT:
                    $target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_DIRECT);
                    break;
                case ilCoSubTargetsConfig::SUB_TYPE_NONE:
                    $target->setRegistrationType(ilMembershipRegistrationSettings::TYPE_NONE);
                    break;
            }
        }

        if ($config->set_sub_max) {
            $target->enableRegistrationUserLimit(true);
            if ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_ITEM) {
                $target->setRegistrationMaxUsers($item->sub_max);
            } elseif ($config->sub_max_by == ilCoSubTargetsConfig::SET_BY_INPUT) {
                $target->setRegistrationMaxUsers($config->sub_max);
                $item->sub_max = $config->sub_max;
                $item->save();
            }
        }

        if ($config->set_sub_wait) {
            switch ($config->sub_wait) {
                case ilCoSubTargetsConfig::SUB_WAIT_AUTO:
                    $target->enableRegistrationWaitingList(true);
                    $target->setWaitingListAutoFill(true);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_MANU:
                    $target->enableRegistrationWaitingList(true);
                    $target->setWaitingListAutoFill(false);
                    break;
                case ilCoSubTargetsConfig::SUB_WAIT_NONE:
                    $target->enableRegistrationWaitingList(false);
                    $target->setWaitingListAutoFill(false);
                    break;
            }
        }

        $target->update();
    }
}