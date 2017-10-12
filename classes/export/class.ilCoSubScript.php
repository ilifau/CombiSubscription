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
	}

	/**
	 * Import a data file
	 * @param string $file
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
		require_once('Modules/Group/classes/class.ilObjGroup.php');
		require_once('Modules/Session/classes/class.ilObjSession.php');
		require_once('Modules/Exercise/classes/class.ilObjExercise.php');
		require_once('Modules/Exercise/classes/class.ilExAssignment.php');
		require_once('Services/AccessControl/classes/class.ilConditionHandler.php');

		$this->loadItemData();
		$this->checkFtpStructure();

		ilDatePresentation::setUseRelativeDates(false);

		$exercises = array();
		foreach ($this->rows as $r => $rowdata)
		{
			$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];

			/** @var ilObjSession $newSession */
			$origSession = new ilObjSession($rowdata['sess_orig_id'], true);
			$newSession = $origSession->cloneObject($rowdata['group_id']);
			//$newSession->setTitle("Teilnahme ". $rowdata['title']);
			$newSession->setRegistrationType(ilMembershipRegistrationSettings::TYPE_OBJECT);
			$newSession->setRegistrationRefId($this->object->getRefId());
			//$newSession->setRegistrationMaxUsers($rowdata['sub_max']);
			//$newSession->enableRegistrationUserLimit(true);
			//$newSession->enableRegistrationWaitingList(true);
			//$newSession->setWaitingListAutoFill(false);
			$newSession->update();

			ilSessionAppointment::_deleteBySession($newSession->getId());
			$appointment = new ilSessionAppointment();
			$appointment->setSessionId($newSession->getId());
			$appointment->toggleFullTime(false);
			$appointment->setStart(new ilDateTime($this->excelTimeToUnix($rowdata['period_start']), IL_CAL_UNIX));
			$appointment->setEnd(new ilDateTime($this->excelTimeToUnix($rowdata['period_end']), IL_CAL_UNIX));
			$appointment->create();

			$cond = new ilConditionHandler();
			$cond->setTriggerRefId($rowdata['test_id']);
			$cond->setTriggerObjId(ilObject::_lookupObjId($rowdata['test_id']));
			$cond->setTriggerType('tst');
			$cond->setOperator(ilConditionHandler::OPERATOR_PASSED);
			$cond->setTargetRefId($newSession->getRefId());
			$cond->setTargetObjId($newSession->getId());
			$cond->setTargetType('sess');
			$cond->setObligatory(true);
			$cond->setHiddenStatus(false);
			$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
			$cond->storeCondition();

			/** @var ilObjExercise $newExercise */
			if (empty($exercises[$rowdata['group_id']]))
			{
				$origExercise = new ilObjExercise($rowdata['ex_orig_id'], true);
				$newExercise = $origExercise->cloneObject($rowdata['group_id']);
				//$newExercise->setTitle("Abgabe ". $rowdata['title']);
				$newExercise->update();
				/** @var ilExAssignment $assignment */
				foreach (ilExAssignment::getInstancesByExercise($newExercise->getId()) as $assignment)
				{
					$assignment->delete();
				}
				$exercises[$rowdata['group_id']] = $newExercise;

				$cond = new ilConditionHandler();
				$cond->setTriggerRefId($rowdata['test_id']);
				$cond->setTriggerObjId(ilObject::_lookupObjId($rowdata['test_id']));
				$cond->setTriggerType('tst');
				$cond->setOperator(ilConditionHandler::OPERATOR_PASSED);
				$cond->setTargetRefId($newExercise->getRefId());
				$cond->setTargetObjId($newExercise->getId());
				$cond->setTargetType('exc');
				$cond->setObligatory(true);
				$cond->setHiddenStatus(false);
				$cond->setReferenceHandlingType(ilConditionHandler::UNIQUE_CONDITIONS);
				$cond->storeCondition();
			}
			else
			{
				$newExercise = $exercises[$rowdata['group_id']];
			}


			$start = $this->excelTimeToUnix($rowdata['period_start']);
			$end = $this->excelTimeToUnix($rowdata['period_end']);
			$deadline = $this->excelTimeToUnix($rowdata['ex_deadline']);

			/** @var ilExAssignment $ass */
			$ass = current(ilExAssignment::getInstancesByExercise(ilObject::_lookupObjId($rowdata['ex_orig_id'])));
			$ass->setId(null);
			$ass->setExerciseId($newExercise->getId());
			$ass->setType(ilExAssignment::TYPE_UPLOAD_TEAM);
			$ass->setTitle("Versuch ". ilDatePresentation::formatPeriod(new ilDateTime($start, IL_CAL_UNIX), new ilDateTime($end, IL_CAL_UNIX)));
			$ass->setStartTime($start);
			$ass->setDeadline($deadline);
			$ass->setMandatory(false);
			$ass->setTeamTutor(true);
			$ass->save();

			$this->rows[$r]['sess_id'] = $newSession->getRefId();
			$this->rows[$r]['ex_id'] = $newExercise->getRefId();
			$this->rows[$r]['ex_ass_id'] = $newExercise->getRefId();

			$item->target_ref_id = $newSession->getRefId();
			$item->save();
		}
	}

	protected function checkFtpStructure()
	{
		foreach ($this->rows as $r => $rowdata)
		{
			if (empty($rowdata['identifier']) || empty($this->items_by_identifier[$rowdata['identifier']]))
			{
				throw new Exception("Kein Identifier oder Einheit nicht gefunden in Zeile $r");
			}

			$group_id = $rowdata['group_id'];
			if (empty($group_id) || !ilObject::_exists($group_id,true, 'grp') || ilObject::_isInTrash($group_id))
			{
				throw new Exception("Gruppe $group_id nicht gefunden!");
			}

			$test_id = $rowdata['test_id'];
			if (empty($test_id) || !ilObject::_exists($test_id,true, 'tst') || ilObject::_isInTrash($test_id))
			{
				throw new Exception("Test $test_id nicht gefunden!");
			}

			$sess_orig_id = $rowdata['sess_orig_id'];
			if (empty($sess_orig_id) || !ilObject::_exists($sess_orig_id,true, 'sess') || ilObject::_isInTrash($sess_orig_id))
			{
				throw new Exception("Sitzung $sess_orig_id nicht gefunden!");
			}

			$ex_orig_id = $rowdata['ex_orig_id'];
			if (empty($ex_orig_id) || !ilObject::_exists($ex_orig_id,true, 'exc') || ilObject::_isInTrash($ex_orig_id))
			{
				throw new Exception("Übung $ex_orig_id nicht gefunden!");
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
	 * todo: check the ugly workaround
	 *
	 * @param $time
	 * @return int
	 */
	protected function excelTimeToUnix($time)
	{
		global $ilUser;

		$date = (int) $time;
		$time = round(($time - $date) * 86400) - 3600;

		$date = PHPExcel_Shared_Date::ExcelToPHP($date);
		$dateTime = new ilDateTime(date('Y-m-d', $date) .' '. date('H:i:s', $time), IL_CAL_DATETIME);

		return $dateTime->get(IL_CAL_UNIX);
	}
}