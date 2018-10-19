<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Handler for Combined Subscription Scripts
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilCoSubScript
{
	const TYPE_EXCEL = 'excel';
	const TYPE_CSV = 'csv';

	const MODE_FTP_STRUCTURE = 'ftp_structure';	/** Fertigungstechnisches Praktikum */
	const MODE_FTP_ADJUST = 'ftp_adjust';
	const MODE_FTP_EX_MEM = 'ftp_ex_mem';
	const MODE_FTP_EX_STATUS = 'ftp_ex_status';

	/**
	 * @var ilCombiSubscriptionPlugin
	 */
	protected $plugin;

	/**
	 * @var ilObjCombiSubscription
	 */
	protected $object;

	/** @var  string Writer Type ('excel' or 'csv') */
	protected $type;

	/** @var array  list of mode definition (see constructor) */
	protected $modes;

	/** @var string import mode ('ass_by_item') */
	protected $mode;

	/** @var ilLanguage $lng */
	protected $lng;

	/** @var string $message */
	protected $message = '';

	/** @var array [colname, colnname, ...] */
	protected $columns = array();

	/** @var array [ [colname => value, ...], ... ] */
	protected $rows = array();

	/** @var  ilCoSubRun */
	protected $run;

	/** @var ilCoSubItem[] | null (indexed by item_id) */
	protected $items = array();

	/** @var array title => item_id */
	protected $items_by_title = array();

	/** @var array identifier => item_id */
	protected $items_by_identifier = array();

	/** @var  ilLPObjSettings	 */
	protected $obj_settings;

	/** @var  ilObjectLP */
	protected $obj_lp;

	/** @var array item_id => (int) sum of assignments */
	protected $assignment_sums = array();

	/** @var bool tweak: don't create objects for items without assignments*/
	protected $ignore_unassigned_items = true;

	/** @var  string tweak: owner of created objects (higher precedence as owner_id) */
	protected $owner_login = 'root';
    //protected $owner_login = 'manfred.vogel';
    //protected $owner_login = 'andreas.rohrmoser';

	/** @var  int tweak: owner of created objects */
	protected $owner_id = 6;

	/**
	 * Constructor.
	 * @param ilCombiSubscriptionPlugin		$plugin
	 * @param ilObjCombiSubscription		$object
	 * @param string						$mode
	 */
	public function __construct($plugin, $object, $mode = '')
	{
		global $lng;

		$this->object = $object;
		$this->plugin  = $plugin;
		$this->mode = $mode;
		$this->lng = $lng;

		if (!empty($this->owner_login))
		{
			$this->owner_id = ilObjUser::_lookupId($this->owner_login);
		}

		$this->modes = array(
			ilCoSubScript::MODE_FTP_STRUCTURE => array(
				'title' => 'Struktur für Fertigungstechnisches Praktikum anlegen',
				'info' => 'Legt die Sitzungen und Übungen an, stellt Vorbedingungen und Lernfortschritt ein',
				'success' => 'Die Struktur wurde angelegt.',
				'failure' => 'Die Struktur konnte nicht anglegt werden!',
				'filename' => 'structure.xlsx',
				'default' => true
			),
			ilCoSubScript::MODE_FTP_EX_MEM => array(
				'title' => 'Übungs-Teams für Fertigungstechnisches Praktikum anlegen',
				'info' => 'Trägt die Übungsmitglieder ein und legt die Übungsteams an',
				'success' => 'Die Teams wurden angelegt.',
				'failure' => 'Die Teams konnten nicht anglegt werden!',
				'filename' => 'structure.xlsx',
				'default' => true
			),
//			ilCoSubScript::MODE_FTP_ADJUST => array(
//				'title' => 'Struktur für Fertigungstechnisches Praktikum anpassen',
//				'info' => 'Ersetzt das Antestat durch ein Übungsobjekt mit manuellem Lernfortschritt',
//				'success' => 'Die Struktur wurde angepasst.',
//				'failure' => 'Die Struktur konnte nicht angepasst werden!',
//				'filename' => 'structure.xlsx',
//				'default' => true
//			),
//			ilCoSubScript::MODE_FTP_EX_STATUS => array(
//				'title' => 'Ergebnisse der Antestate für Fertigungstechnisches Praktikum eintragen',
//				'info' => 'Trägt die externen Testergebnisse als Status in den Übungsobjekten der Antestate ein',
//				'success' => 'Die Ergebnisse wurden eingetragen.',
//				'failure' => 'Die Ergebnisse konnten nicht eingetragen werden!',
//				'filename' => 'structure.xlsx',
//				'default' => true
//			),

		);

	}

	/**
	 * Get the list of available modes
	 */
	public function getModes()
	{
		return $this->modes;
	}

	/**
	 * Set the script mode
	 * @param $mode
	 */
	public function setMode($mode)
	{
		$this->mode = $mode;
	}

	/**
	 * Import a data file
	 * @param string $inputFile
	 * @param string $resultFile
	 * @return bool
	 */
	public function ProcessFile($inputFile, $resultFile)
	{
		$this->message = '';
		try
		{
			if (!is_readable($inputFile)) {
				throw new Exception($this->plugin->txt('import_file_not_readable'));
			}


			//  Create a new Reader of the type that has been identified
			require_once $this->plugin->getDirectory(). '/lib/PHPExcel-1.8/Classes/PHPExcel.php';
			$type = PHPExcel_IOFactory::identify($inputFile);
			switch ($type)
			{
				case 'Excel5':
				case 'Excel2007':
				case 'Excel2003XML':
					/** @var PHPExcel_Reader_Excel2007 $reader */
					$reader = PHPExcel_IOFactory::createReader($type);
					$reader->setReadDataOnly(true);
					$this->type = self::TYPE_EXCEL;
					break;

				default:
					throw new Exception($this->plugin->txt('import_error_format'));
			}

			/** @var PHPExcel $xls */
			$xls = $reader->load($inputFile);
			$sheet = $xls->getSheet(0);
			$this->readData($sheet);

			// analyze the read data
			$write = false;
			switch ($this->mode)
			{
				case self::MODE_FTP_STRUCTURE:
					$this->createFtpStructure();
					$write = true;
					break;

				case self::MODE_FTP_ADJUST:
					$this->adjustFtpObjects();
					$write = true;
					break;

				case self::MODE_FTP_EX_MEM:
					$this->createFtpExerciseTeams();
					$write = true;
					break;

				case self::MODE_FTP_EX_STATUS:
					$this->insertFtpExStatus();
					$write = true;
					break;


				default:
					throw new Exception($this->plugin->txt('import_error_mode'));
			}

			if ($write)
			{
				ilUtil::makeDirParents(dirname($resultFile));
				$excelObj = new PHPExcel();
				$excelObj->setActiveSheetIndex(0);
				$this->writeData($excelObj->getActiveSheet());

				/** @var PHPExcel_Writer_Excel2007 $writerObj */
				$writerObj = PHPExcel_IOFactory::createWriter($excelObj, 'Excel2007');
				$writerObj->save($resultFile);
			}
		}
		catch (Exception $e)
		{
			$this->message = $e->getMessage();
			return false;
		}

		return true;
	}

	/**
	 * Read the data of the imprted sheet
	 * Prepare the column list
	 * Prepare the row data list of arrays (indexed by column names)
	 *
	 * @param PHPExcel_Worksheet $sheet
	 * @throws Exception	if columns are not named or unique, or if id column is missing
	 */
	protected function readData($sheet)
	{

		$data = $sheet->toArray(null, true, false, false);

		$this->columns = $data[0];

		if (count($data) < 2)
		{
			throw new Exception($this->plugin->txt('import_error_rows_missing'));
		}
		if (in_array('', $this->columns) || in_array(null, $this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_empty_colname'));
		}
		if (count(array_unique($this->columns)) < count($this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_multiple_colname'));
		}

		for ($row = 1; $row < count($data); $row++)
		{
			$rowdata = $data[$row];
			if (count($rowdata) > count($this->columns))
			{
				throw new Exception($this->plugin->txt('import_error_empty_colname'));
			}
			for ($col = 0; $col < count($rowdata); $col++)
			{
				if (isset($rowdata[$col]))
				{
					// use column names as
					$this->rows[$row -1][$this->columns[$col]] = $rowdata[$col];
				}
			}
		}
	}


	/**
	 * Write the data to a sheet
	 *
	 * @param PHPExcel_Worksheet $sheet
	 * @throws Exception	if columns are not named or unique, or if id column is missing
	 */
	protected function writeData($sheet)
	{
		$data = array();

		$header = array();
		$c = 0;
		foreach ($this->columns as $colname)
		{
			$header[$c] = $colname;
			$c++;
		}
		$data[0] = $header;

		$r = 1;
		foreach ($this->rows as $row)
		{
			$c = 0;
			$rowdata = array();
			foreach ($this->columns as $colname)
			{
				$rowdata[$c] = $row[$colname];
				$c++;
			}
			$data[$r] = $rowdata;
			$r++;
		}

		$sheet->fromArray($data);
	}


	protected function createFtpStructure()
	{
		$this->loadItemData();
		$this->checkFtpStructure();

		ilDatePresentation::setUseRelativeDates(false);

		$group_ids = array();		// group_ref_id => true;
		$session_ids = array();  	// group_ref_id => [session_ref_id => true]
		$exercise_ids = array(); 	// group_ref_id => [exercise_ref_id => true]

		foreach ($this->rows as $r => $rowdata)
		{
			if ($this->ignore_unassigned_items)
			{
				$item_id = $this->items_by_identifier[$rowdata['identifier']];
				if (empty($this->assignment_sums[$item_id]))
				{
					continue;
				}
			}

			// avoid race condition
			$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];
			$ref_id = $item->target_ref_id;
			if (!empty($ref_id)  && ilObject::_exists($ref_id, true) && !ilObject::_isInTrash($ref_id))
			{
				continue;
			}

			$period_start = $this->excelTimeToUnix($rowdata['period_start']);
			$period_end = $this->excelTimeToUnix($rowdata['period_end']);
			$deadline = $this->excelTimeToUnix($rowdata['ex_deadline']);
			$deadline_obj = new ilDateTime($deadline, IL_CAL_UNIX);

			$period_start_obj = new ilDateTime($period_start, IL_CAL_UNIX);
			$period_end_obj = new ilDateTime($period_end, IL_CAL_UNIX);
			$period_duration = ilDatePresentation::formatPeriod($period_start_obj, $period_end_obj);

//			$test_start_obj = new ilDateTime($period_start-(3*24*3600), IL_CAL_UNIX); 	// 3 days before
//			$test_end_obj = new ilDateTime($period_start - 3600, IL_CAL_UNIX);			// 1 hour before
//			$test_duration = ilDatePresentation::formatPeriod($test_start_obj, $test_end_obj);

			/**
			 * Copy Test
			 */
//			$origTest = new ilObjTest($rowdata['test_orig_id'], true);
//			$origTest->setOnline(false);
//			$origTest->saveToDb();

//			$newTest = $origTest->cloneObject($rowdata['group_id']);

			/** @var ilObjTest $newTest */
//			$newTest = new ilObjTest($newTest->getRefId(), true);
//			$newTest->setOnline(true);
//			$newTest->setTitle($rowdata['title']. ' - ' . $newTest->getTitle());
//			$newTest->setDescription($test_duration);
//			$newTest->update();
//			$newTest->setStartingTimeEnabled(true);
//			$newTest->setStartingTime(ilFormat::dateDB2timestamp($test_start_obj->get(IL_CAL_DATETIME)));
//			$newTest->setEndingTimeEnabled(true);
//			$newTest->setEndingTime(ilFormat::dateDB2timestamp($test_end_obj->get(IL_CAL_DATETIME)));
//			$newTest->saveToDb();
//			$newTest->setOwner($this->owner_id);
//			$newTest->updateOwner();


            /**
             * Take existing test
             */
            $newTest = new ilObjTest($rowdata['test_orig_id'], true);

			/**
			 * Copy Exercise
			 */
			/** @var ilObjExercise $newExercise */
			$origExercise = new ilObjExercise($rowdata['ex_orig_id'], true);
			$newExercise = $origExercise->cloneObject($rowdata['group_id']);
			$newExercise->setTitle($rowdata['title']. ' - ' . $newExercise->getTitle());
			$newExercise->setDescription("Abgabe bis: " .ilDatePresentation::formatDate($deadline_obj));
			$newExercise->update();
			$newExercise->setOwner($this->owner_id);
			$newExercise->updateOwner();

			/** @var ilExAssignment $assignment */
			foreach (ilExAssignment::getInstancesByExercise($newExercise->getId()) as $assignment)
			{
				$assignment->delete();
			}
			/** @var ilExAssignment $ass */
			$ass = current(ilExAssignment::getInstancesByExercise(ilObject::_lookupObjId($rowdata['ex_orig_id'])));
			$ass->setId(null);
			$ass->setExerciseId($newExercise->getId());
			$ass->setType(ilExAssignment::TYPE_UPLOAD_TEAM);
			$ass->setStartTime($period_end);
			$ass->setDeadline($deadline);
			$ass->setTeamTutor(true);
			$ass->save();

			/**
			 * Set Test as Precondition for Exercise
			 */
			$cond = new ilConditionHandler();
			$cond->setTriggerRefId($newTest->getRefId());
			$cond->setTriggerObjId($newTest->getId());
			$cond->setTriggerType('tst');
			$cond->setOperator(ilConditionHandler::OPERATOR_PASSED);
			$cond->setTargetRefId($newExercise->getRefId());
			$cond->setTargetObjId($newExercise->getId());
			$cond->setTargetType('exc');
			$cond->setObligatory(true);
			$cond->setHiddenStatus(false);
			$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
			$cond->storeCondition();

			/**
			 * Create Sessison
			 */
			/** @var ilObjSession $newSession */
			$origSession = new ilObjSession($rowdata['sess_orig_id'], true);
			$newSession = $origSession->cloneObject($rowdata['group_id']);
			$newSession->setTitle($rowdata['title']);
			$newSession->setRegistrationType(ilMembershipRegistrationSettings::TYPE_OBJECT);
			$newSession->setRegistrationRefId($this->object->getRefId());
			//$newSession->setRegistrationMaxUsers($rowdata['sub_max']);
			//$newSession->enableRegistrationUserLimit(true);
			//$newSession->enableRegistrationWaitingList(true);
			//$newSession->setWaitingListAutoFill(false);
			$newSession->update();
			$newSession->setOwner($this->owner_id);
			$newSession->updateOwner();

			ilSessionAppointment::_deleteBySession($newSession->getId());
			$appointment = new ilSessionAppointment();
			$appointment->setSessionId($newSession->getId());
			$appointment->toggleFullTime(false);
			$appointment->setStart(new ilDateTime($period_start, IL_CAL_UNIX));
			$appointment->setEnd(new ilDateTime($period_end, IL_CAL_UNIX));
			$appointment->create();

			/**
			 * Set Test as Precondition for Session
			 */
			$cond = new ilConditionHandler();
			$cond->setTriggerRefId($newTest->getRefId());
			$cond->setTriggerObjId($newTest->getId());
			$cond->setTriggerType('tst');
			$cond->setOperator(ilConditionHandler::OPERATOR_PASSED);
			$cond->setTargetRefId($newSession->getRefId());
			$cond->setTargetObjId($newSession->getId());
			$cond->setTargetType('sess');
			$cond->setObligatory(true);
			$cond->setHiddenStatus(false);
			$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
			$cond->storeCondition();

			/**
			 * Assign test and exercise as materials to the session
			 */
			$event_items = new ilEventItems($newSession->getId());
//			$event_items->addItem($newTest->getRefId());
			$event_items->addItem($newExercise->getRefId());
			$event_items->update();

			/**
			 * Assign the session to the item in the combined subscription
			 */
			$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];
			$item->target_ref_id = $newSession->getRefId();
			$item->save();

			/**
			 * Remember what is done
			 */
			$group_ids[$rowdata['group_id']] = true;
			$session_ids[$rowdata['group_id']][$newSession->getRefId()] = true;
			$exercise_ids[$rowdata['group_id']][$newExercise->getRefId()] = true;

			$this->rows[$r]['test_id'] = $newTest->getRefId();
			$this->rows[$r]['sess_id'] = $newSession->getRefId();
			$this->rows[$r]['ex_id'] = $newExercise->getRefId();
			$this->rows[$r]['ex_ass_id'] = $newExercise->getRefId();
		}

		include_once 'include/inc.debug.php';

		// learning progress settings
		foreach (array_keys($group_ids) as $group_ref_id)
		{
			// init lp settings of the group
			$group_obj_id = ilObject::_lookupObjId($group_ref_id);
			$this->obj_settings = new ilLPObjSettings($group_obj_id);
			$this->obj_lp = ilObjectLP::getInstance($group_obj_id);

			// delete an old lp collection
			if ($collection = $this->obj_lp->getCollectionInstance())
			{
				$collection->delete();
			}

			// save the new lp settings of the group
			$this->obj_settings->setMode(ilLPObjSettings::LP_MODE_COLLECTION);
			$this->obj_settings->update(true);

			//important to read the new mode
			$this->obj_lp->resetCaches();

			/** @var ilLPCollectionOfRepositoryObjects $collection */
			if ($collection = $this->obj_lp->getCollectionInstance())
			{
				$collection->activateEntries(array());
				if (!empty($session_ids[$group_ref_id]))
				{
					// take lp of session grouping
					$collection->createNewGrouping(array_keys($session_ids[$group_ref_id]), 1);
				}
				if (!empty($exercise_ids[$group_ref_id]))
				{
					// take lp of exercide grouping
					$collection->createNewGrouping(array_keys($exercise_ids[$group_ref_id]), 1);
				}
			}

			// must be done before refreshing
			$this->obj_lp->resetCaches();

			// refresh learning progress
			ilLPStatusWrapper::_refreshStatus($group_obj_id);
		}
	}

    /**
     * Add the participants to their exercises and build teams
     * @throws Exception
     */
	protected function createFtpExerciseTeams()
	{
		$this->loadItemData();
		$this->checkFtpStructure();

		foreach ($this->rows as $r => $rowdata)
		{
			$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];
			$user_ids = array_keys($this->object->getAssignmentsOfItem($item->item_id));

			if (empty($user_ids))
			{
				continue;
			}

			$sess_ref_id = $item->target_ref_id;
			$sess_obj_id = ilObject::_lookupObjId($sess_ref_id);
			$sessItems = new ilEventItems($sess_obj_id);



			foreach ($sessItems->getItems() as $ref_id)
			{
				// exercise
				if (ilObject::_lookupType($ref_id, true) == "exc")
				{
					$exObj = new ilObjExercise($ref_id, true);
					$exMem = new ilExerciseMembers($exObj);
					/** @var ilExAssignment $exAss */
					$exAss = current(ilExAssignment::getInstancesByExercise($exObj->getId()));

					if (!empty($user_ids))
					{
						$exMem->assignMembers($user_ids);

						$first_user = array_shift($user_ids);
						$team = ilExAssignmentTeam::getInstanceByUserId($exAss->getId(), $first_user, true);
						if(sizeof($user_ids))
						{
							foreach($user_ids as $user_id)
							{
								$team->addTeamMember($user_id);
							}
						}
					}
				}

				// test
//				if (ilObject::_lookupType($ref_id, true) == "tst")
//				{
//					$tstObj = new ilObjTest($ref_id, true);
//
//					$tstObj->setFixedParticipants(1);
//					$tstObj->saveToDb();
//
//					foreach ($user_ids as $user_id)
//					{
//						$tstObj->inviteUser($user_id);
//					}
//				}
			}
		}
	}


	/**
	 * Insert the learning progress to exercises
	 * The excel columss are: group_id, ex_title, matriculation, status
	 * - status will be set for participants of exercises with ex_title in the group object with group_id as ref_id
	 * - users are matched by matriculation numbers
	 * - status can be 'notgraded', 'passed' or 'failed'
	 */
	protected function insertFtpExStatus()
	{
		/** ilTree $tree */
		global $ilDB, $tree;

		require_once('Modules/Exercise/classes/class.ilObjExercise.php');
		require_once('Modules/Exercise/classes/class.ilExerciseMembers.php');
		require_once('Services/Tracking/classes/class.ilLPObjSettings.php');

		$group_id = null;
		$ex_title = null;

		// collect the status by matriculation
		$stat_by_mat = array();
		foreach ($this->rows as $r => $rowdata)
		{
			if (isset($rowdata['matriculation']))
			{
				// take only the first ones
				$group_id = isset($group_id) ? $group_id : $rowdata['group_id'];
				$ex_title = isset($ex_title) ? $ex_title : $rowdata['ex_title'];

				$stat_by_mat[(string) $rowdata['matriculation']] = (string) $rowdata['status'];
			}
		}

		//log_var($stat_by_mat, '$stat_by_mat');

		if(empty($group_id) || empty($ex_title))
		{
			throw new Exception("Gruppe oder Übungstitel nicht angegeben!");
		}

		// collect the status by user id
		$query = "SELECT usr_id, matriculation FROM usr_data WHERE ".
			$ilDB->in('matriculation', array_keys($stat_by_mat), false, 'text');
		$result = $ilDB->query($query);

		$stat_by_usr_id = array();
		while ($user = $ilDB->fetchAssoc($result))
		{
			$stat_by_usr_id[$user['usr_id']] = $stat_by_mat[(string) $user['matriculation']];
		}

		//log_var($stat_by_usr_id, '$stat_by_usr_id');


		// get the relevant exercises in the group
		$gt_data = $tree->getNodeTreeData($group_id);
		$et_data = $tree->getSubTree($gt_data, true, 'exc');

		//log_var($et_data, '$et_data');

		// write the learning progress of the exercise members
		foreach ($et_data as $ex_data)
		{
			if (strpos($ex_data['title'], $ex_title) !== false)
			{
				$exObj = new ilObjExercise($ex_data['child'], true);

				//Set learning progress of exercise
				$this->obj_settings = new ilLPObjSettings($exObj->getId());
				$this->obj_settings->setMode(ilLPObjSettings::LP_MODE_EXERCISE_RETURNED);
				$this->obj_settings->update(true);

				$exMem = new ilExerciseMembers($exObj);
				foreach ($exMem->getMembers() as $user_id)
				{
					$status = $stat_by_usr_id[$user_id];
					if ($status == 'passed' || $status == 'failed')
					{
						ilExerciseMembers::_writeStatus($exObj->getId(), $user_id, $status);
					}
				}
			}
		}
	}

	/**
	 * Replace the 'Antestat' test by an exercise with manual LP setting
	 */
	protected function adjustFtpObjects()
	{
		require_once('Modules/Session/classes/class.ilEventItems.php');
		require_once('Modules/Exercise/classes/class.ilObjExercise.php');
		require_once('Modules/Exercise/classes/class.ilExerciseMembers.php');
		require_once('Modules/Test/classes/class.ilObjTest.php');
		require_once('Services/AccessControl/classes/class.ilConditionHandler.php');
		require_once('Services/Tracking/classes/class.ilLPObjSettings.php');

		$this->loadItemData();
		$this->checkFtpStructure();

		foreach ($this->rows as $r => $rowdata)
		{
			$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];
			$user_ids = array_keys($this->object->getAssignmentsOfItem($item->item_id));

			$sess_ref_id = $item->target_ref_id;
			$sess_obj_id = ilObject::_lookupObjId($sess_ref_id);

			// sessions may have been be deleted due to missing registrations
			if (empty($sess_obj_id) || ilObject::_isInTrash($sess_ref_id))
			{
				continue;
			}

			$sessItems = new ilEventItems($sess_obj_id);
			$cleaned = array();
			$ex_ref_id = null;
			$ex_obj_id = null;
			foreach ($sessItems->getItems() as $ref_id)
			{
				$type = ilObject::_lookupType($ref_id, true);

				// Delete test 'Antestat'
				if ($type == "tst")
				{
					$tstObj = new ilObjTest($ref_id, true);
					$tstObj->delete();
				}
				else
				{
					if ($type == 'exc')
					{
						$ex_ref_id = $ref_id;
						$ex_obj_id = ilObject::_lookupObjectId($ex_ref_id);
					}

					$cleaned[] = $ref_id;
				}
			}
			$sessItems->setItems($cleaned);
			$sessItems->update();

			// Create Exercie 'Antestat'
			$newExercise = new ilObjExercise();
			$newExercise->setTitle($rowdata['identifier'] . ' - Antestat');
			$newExercise->setDescription('Hier werden die Ergebnisse des Antestats eingetragen.');
			$newExercise->setInstruction('Sie müssen diese Übung nicht direkt bearbeiten. In ihr werden die Ergebnisse des Tests eingetragen.');
			$newExercise->create();
			$newExercise->createReference();
			$newExercise->putInTree($rowdata['group_id']);
			$newExercise->setPermissions($rowdata['group_id']);
			$newExercise->setPassMode('man');
			$newExercise->saveData();

			// set the exercise members
			$exMem = new ilExerciseMembers($newExercise);
			if (!empty($user_ids))
			{
				$exMem->assignMembers($user_ids);
			}

			//Set learning progress of exercise
			$this->obj_settings = new ilLPObjSettings($newExercise->getId());
			$this->obj_settings->setMode(ilLPObjSettings::LP_MODE_EXERCISE_RETURNED);
			$this->obj_settings->update(true);

			 //Set Exercise as precondition for session
			$this->cleanupCoditionsOfTarget($sess_ref_id);
			$cond = new ilConditionHandler();
			$cond->setTriggerRefId($newExercise->getRefId());
			$cond->setTriggerObjId($newExercise->getId());
			$cond->setTriggerType('exc');
			$cond->setOperator(ilConditionHandler::OPERATOR_LP);
			$cond->setTargetRefId($sess_ref_id);
			$cond->setTargetObjId($sess_obj_id);
			$cond->setTargetType('sess');
			$cond->setObligatory(true);
			$cond->setHiddenStatus(false);
			$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
			$cond->storeCondition();

			// Set Exercise es precondition for the other exercise
			$this->cleanupCoditionsOfTarget($ex_ref_id);
			$cond = new ilConditionHandler();
			$cond->setTriggerRefId($newExercise->getRefId());
			$cond->setTriggerObjId($newExercise->getId());
			$cond->setTriggerType('exc');
			$cond->setOperator(ilConditionHandler::OPERATOR_LP);
			$cond->setTargetRefId($ex_ref_id);
			$cond->setTargetObjId($ex_obj_id);
			$cond->setTargetType('exc');
			$cond->setObligatory(true);
			$cond->setHiddenStatus(false);
			$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
			$cond->storeCondition();


			// Assign the exercise as session material
			$event_items = new ilEventItems($sess_obj_id);
			$event_items->addItem($newExercise->getRefId());
			$event_items->update();
		}
	}

	/**
	 * Remove the conditions of a target triggered by deleted objects
	 * @param $ref_id
	 */
	protected function cleanupCoditionsOfTarget($ref_id)
	{
		$obj_id = ilObject::_lookupObjectId($ref_id);
		$type = ilObject::_lookupType($obj_id);

		$conditions = ilConditionHandler::_getConditionsOfTarget($ref_id,$obj_id, $type);

		foreach ($conditions as $condition)
		{
			if (!ilObject::_exists($condition['trigger_ref_id']) || ilObject::_isInTrash($condition['trigger_ref_id']))
			{
				ilConditionHandler::deleteCondition($condition['id']);
			}
		}
	}


	protected function checkFtpStructure()
	{
		foreach ($this->rows as $r => $rowdata)
		{
			if (empty($rowdata['identifier']) || empty($this->items_by_identifier[$rowdata['identifier']]))
			{
				throw new Exception("Kein Identifier oder Einheit '".$rowdata['identifier']."'' nicht gefunden in Zeile $r");
			}

			$group_id = $rowdata['group_id'];
			if (empty($group_id) || !ilObject::_exists($group_id,true, 'grp') || ilObject::_isInTrash($group_id))
			{
				throw new Exception("Gruppe $group_id nicht gefunden in Zeile $r!");
			}

			$test_orig_id = $rowdata['test_orig_id'];
			if (empty($test_orig_id) || !ilObject::_exists($test_orig_id,true, 'tst') || ilObject::_isInTrash($test_orig_id))
			{
				throw new Exception("Test $test_orig_id nicht gefunden in Zeile $r!");
			}

			$sess_orig_id = $rowdata['sess_orig_id'];
			if (empty($sess_orig_id) || !ilObject::_exists($sess_orig_id,true, 'sess') || ilObject::_isInTrash($sess_orig_id))
			{
				throw new Exception("Sitzung $sess_orig_id nicht gefunden in Zeile $r!");
			}

			$ex_orig_id = $rowdata['ex_orig_id'];
			if (empty($ex_orig_id) || !ilObject::_exists($ex_orig_id,true, 'exc') || ilObject::_isInTrash($ex_orig_id))
			{
				throw new Exception("Übung $ex_orig_id nicht gefunden in Zeile $r!");
			}
		}
	}



	/**
	 * Get import errors
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * Load the titles and identifiers of items
	 */
	protected function loadItemData()
	{
		$this->items = $this->object->getItems();
		$this->assignment_sums = $this->object->getAssignmentsSums();

		foreach ($this->object->getItems() as $item_id => $item)
		{
			if (!empty($item->identifier))
			{
				$this->items_by_identifier[$item->identifier] = $item->item_id;
			}
			$this->items_by_title[$item->title] = $item->item_id;
		}
	}



	/**
	 * Convert an excel time to unix
	 * todo: check the ugly workaround, especially the -3600
	 *
	 * @param $time
	 * @return int
	 */
	protected function excelTimeToUnix($time)
	{
		if (is_numeric($time))
		{
			$date = (int) $time;
			$time = round(($time - $date) * 86400) - 3600;

			$date = PHPExcel_Shared_Date::ExcelToPHP($date);
			$dateTime = new ilDateTime(date('Y-m-d', $date) .' '. date('H:i:s', $time), IL_CAL_DATETIME);
		}
		else
		{
			$dateTime = new ilDateTime($time, IL_CAL_DATETIME);
		}

		return $dateTime->get(IL_CAL_UNIX);
	}
}