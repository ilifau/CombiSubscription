<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Combined Subscription Import
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilCoSubImport
{
	const TYPE_EXCEL = 'excel';
	const TYPE_CSV = 'csv';

	const MODE_ITEMS = 'items';
	const MODE_REG_BY_ITEM = 'reg_by_item';
	const MODE_REG_BY_PRIO = 'reg_by_prio';
	const MODE_ASS_BY_ITEM = 'ass_by_item';
	const MODE_ASS_BY_COL = 'ass_by_col';
    const MODE_ASS_BY_IDS = 'ass_by_ids';

    /** @var \ILIAS\DI\Container */
    protected \ILIAS\DI\Container $dic;

    /** @var ilLanguage  */
    protected ilLanguage $lng;

	/** @var ilCombiSubscriptionPlugin */
	protected ilCombiSubscriptionPlugin $plugin;

	/**
	 * @var ilObjCombiSubscription
	 */
	protected ilObjCombiSubscription $object;

	/** @var  string Writer Type ('excel' or 'csv') */
	protected string $type;

	/** @var string import mode ('ass_by_item') */
	protected string $mode;

    /** @var string import comment (used for run creation) */
    protected string $comment;

	/** @var string $message */
	protected string $message = '';

	/** @var array [colname, colnname, ...] */
	protected array $columns = [];

	/** @var array [ [colname => value, ...], ... ] */
	protected array $rows = [];

	/** @var  ilCoSubRun */
	protected ilCoSubRun $run;

	/** @var ilCoSubItem[]  (indexed by item_id) */
	protected array $items = [];

    /** @var array ilCoSubUser[]  (indexed by user_id) */
    protected array $users = [];

	/** @var array title => item_id */
	protected array $items_by_title = [];

	/** @var array identifier => item_id */
	protected array $items_by_identifier = [];

	/** @var array login => user_id */
	protected array $users_by_login = [];

	/** @var array login => user_id */
	protected array $new_users_by_login = [];

	/**
	 * Constructor.
	 * @param ilCombiSubscriptionPlugin		$plugin
	 * @param ilObjCombiSubscription		$object
	 * @param string						$mode
	 */
	public function __construct(ilCombiSubscriptionPlugin $plugin, ilObjCombiSubscription $object, string $mode = '', string $comment = '')
	{
		global $DIC;

        $this->dic = $DIC;
        $this->lng = $DIC->language();

		$this->object = $object;
		$this->plugin  = $plugin;
		$this->mode = $mode;
        $this->comment = $comment;

		$this->plugin->includeClass('models/class.ilCoSubRun.php');
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
	}

	/**
	 * Import a data file
	 * @param string $file
	 * @return bool
	 */
	public function ImportFile(string $file): bool
	{
		$this->message = '';
		try
		{
			if (!is_readable($file)) {
				throw new Exception($this->plugin->txt('import_file_not_readable'));
			}


			//  Create a new Reader of the type that has been identified
			$type = IOFactory::identify($file);
			switch ($type)
			{
				case 'Xls':
				case 'Xlsx':
					$reader = IOFactory::createReader($type);
					$reader->setReadDataOnly(true);
					$this->type = self::TYPE_EXCEL;
					break;

				case 'Csv':
					$reader = IOFactory::createReader($type);
					$reader->setDelimiter(';');
					$reader->setEnclosure('"');
					$this->type = self::TYPE_CSV;
					break;

				default:
					throw new Exception($this->plugin->txt('import_error_format'));
			}

			$xls = $reader->load($file);
			$sheet = $xls->getSheet(0);

			// analyze the read data
			switch ($this->mode)
			{
				case self::MODE_ITEMS:
					$this->readData($sheet);
					$this->readItems();
					break;

				case self::MODE_ASS_BY_ITEM:
					$this->readData($sheet);
					$this->readAssignmentsByItems();
					break;

				case self::MODE_ASS_BY_COL:
					$this->readData($sheet);
					$this->readAssignmentsByColumns();
					break;

                case self::MODE_ASS_BY_IDS;
                    $this->readData($sheet);
                    $this->readAssignmentsByIds();
                    break;

				default:
					throw new Exception($this->plugin->txt('import_error_mode'));
			}
		}
		catch (Exception $e)
		{
			$this->message = $e->getMessage();

			// cleanup, if neccessary
			if (isset($this->run) && isset($this->run->run_id))
			{
				ilCoSubAssign::_deleteForObject($this->object->getId(), $this->run->run_id);
				ilCoSubRun::_deleteById($this->run->run_id);

				foreach ($this->new_users_by_login as $login => $user_id)
				{
					ilCoSubChoice::_deleteForObject($this->object->getId(), $user_id);
				}
			}

			return false;
		}

		// copy the imported assignments as current ones if a run has been created
		if (isset($this->run) && isset($this->run->run_id))
		{
			$this->object->copyAssignments($this->run->run_id, 0);
			$this->object->setClassProperty('ilCoSubAssignmentsGUI', 'source_run', $this->run->run_id);
		}

		return true;
	}

	/**
	 * Read the data of the imported sheet
	 * Prepare the column list
	 * Prepare the row data list of arrays (indexed by column names)
	 *
	 * @param Worksheet $sheet
	 * @throws Exception	if columns are not named or unique, or if id column is missing
	 */
	protected function readData(Worksheet $sheet): void
	{

		$data = $sheet->toArray(null, true, false, false);

//        echo "<pre>";
//        print_r($data);
//        exit;

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
				// use column names as
				$this->rows[$row -1][$this->columns[$col]] = $rowdata[$col];
			}
		}
	}


	/**
	 * Read the assignments of users by columns
	 * Column 'ID' column has the username
	 * Columns '1', '2', '3', have the items identifiers or titles
	 */
	public function readAssignmentsByColumns(): void
	{
		if (!in_array('ID', $this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_id_missing'));
		}

		$this->loadItemData();
		$this->loadUserData();
		$assign_columns = array();

		// collect the assignment columns
		foreach($this->columns as $colname)
		{
			if (is_numeric($colname) && floor($colname) == $colname)
			{
				$assign_columns[] = $colname;
			}
		}
		if (empty($assign_columns))
		{
			throw new Exception($this->plugin->txt('import_error_no_ass_col'));
		}
		if (count($assign_columns) > 1 && !$this->object->getMethodObject()->hasMultipleAssignments())
		{
			throw new Exception($this->plugin->txt('import_error_multi_ass_columns'));
		}

		// assign the items
		$this->createRun();
		foreach ($this->rows as $rowdata)
		{
			if (empty($rowdata['ID']))
			{
				// no user row
				continue;
			}

			$added = false;
			$user_id = $this->users_by_login[$rowdata['ID']];
			if (empty($user_id))
			{
				$user_id = $this->adddUserByLogin($rowdata['ID']);
				$added = true;
			}
			if (empty($user_id))
			{
				throw new Exception(sprintf($this->plugin->txt('import_error_user_not_found'), $rowdata['ID']));
			}

			$assignments = array();
			foreach ($assign_columns as $colname)
			{
				$entry = $rowdata[$colname];
				if (!empty($entry))
				{
					$item_id = null;
					if (isset($this->items_by_identifier[$entry]))
					{
						$item_id = $this->items_by_identifier[$entry];
					}
					elseif (isset($this->items_by_title[$entry]))
					{
						$item_id = $this->items_by_title[$entry];
					}

					if (empty($item_id))
					{
						throw new Exception(sprintf($this->plugin->txt('import_error_item_not_found'), $entry));
					}

					$ass = new ilCoSubAssign();
					$ass->obj_id = $this->run->obj_id;
					$ass->run_id = $this->run->run_id;
					$ass->user_id = $user_id;
					$ass->item_id = $item_id;
					$ass->save();

					$assignments[] = $ass;
				}
			}

			if ($added)
			{
				// user is added, so create dummy choices
				$this->createChoicesForAssignments($assignments);
			}
		}
	}

	/**
	 * Read the assignments of users by items
	 * Column 'ID' column has the username
	 * Columns named by item titles or identifiers ar not empty if an item is assigned
	 */
	public function readAssignmentsByItems(): void
	{
		if (!in_array('ID', $this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_id_missing'));
		}

		$this->loadItemData();
		$this->loadUserData();
		$items_by_columns = array();

		// collect the item columns
		foreach($this->columns as $colname)
		{
			$item_id = null;
			if (isset($this->items_by_identifier[$colname]))
			{
				$item_id = $this->items_by_identifier[$colname];
			}
			elseif (isset($this->items_by_title[$colname]))
			{
				$item_id = $this->items_by_title[$colname];
			}

			if (!empty($item_id))
			{
				$items_by_columns[$colname] = $item_id;
			}
		}
		if (count($items_by_columns) < count($this->object->getItems()))
		{
			throw new Exception($this->plugin->txt('import_error_items_missing'));
		}

		// assign the items
		$this->createRun();
		foreach ($this->rows as $rowdata)
		{
			if (empty($rowdata['ID']))
			{
				// no user row
				continue;
			}
			
			$added = false;
			$user_id = $this->users_by_login[$rowdata['ID']];
			if (empty($user_id))
			{
				$user_id = $this->adddUserByLogin($rowdata['ID']);
				$added = true;
			}
			if (empty($user_id))
			{
				throw new Exception(sprintf($this->plugin->txt('import_error_user_not_found'), $rowdata['ID']));
			}

			$assignments = array();
			foreach ($items_by_columns as $colname => $item_id)
			{
				if (!empty($rowdata[$colname]))
				{
					$ass = new ilCoSubAssign();
					$ass->obj_id = $this->run->obj_id;
					$ass->run_id = $this->run->run_id;
					$ass->user_id = $user_id;
					$ass->item_id = $item_id;
					$ass->save();

					$assignments[] = $ass;
				}
			}
			if (count($assignments) > 1 && !$this->object->getMethodObject()->hasMultipleAssignments())
			{
				throw new Exception($this->plugin->txt('import_error_multi_ass_entries'));
			}
		}
	}

    /**
     * Read the assignments from a raw data table having obj_id, user_id and item_id as columns
     * @throws Exception
     */
    public function readAssignmentsByIds(): void
    {
        if (!in_array('obj_id', $this->columns)) {
            throw new Exception($this->plugin->txt('import_error_obj_id_missing'));
        }
        if (!in_array('user_id', $this->columns)) {
            throw new Exception($this->plugin->txt('import_error_user_id_missing'));
        }
        if (!in_array('item_id', $this->columns)) {
            throw new Exception($this->plugin->txt('import_error_item_id_missing'));
        }

        $this->loadItemData();
        $this->loadUserData();

        $assignments = [];
        $assigned = [];

        foreach ($this->rows as $rowdata)
        {
            $obj_id = $rowdata['obj_id'];
            $user_id = $rowdata['user_id'];
            $item_id = $rowdata['item_id'];

            if ($obj_id != $this->object->getId()) {
                throw new Exception(sprintf($this->plugin->txt('import_error_wrong_obj_id'), $obj_id));
            }
            if (!isset($this->users[$user_id])) {
                throw new Exception(sprintf($this->plugin->txt('import_error_wrong_user_id'), $user_id));
            }
            if (!isset($this->items[$item_id])) {
                throw new Exception(sprintf($this->plugin->txt('import_error_wrong_item_id'), $item_id));
            }

            if (isset($assigned[$user_id]) && !$this->object->getMethodObject()->hasMultipleAssignments()) {
                throw new Exception($this->plugin->txt('import_error_multi_ass_entries'));
            }

            $ass = new ilCoSubAssign();
            $ass->obj_id = $obj_id;
            $ass->user_id = $user_id;
            $ass->item_id = $item_id;

            $assignments[] = $ass;
            $assigned[$user_id] = true;
        }

        $this->createRun();
        foreach ($assignments as $ass) {
            $ass->run_id = $this->run->run_id;
            $ass->save();
        }
    }


	public function readItems(): void
	{
		$this->loadItemData();

		if (!in_array('title', $this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_title_missing'));
		}

		$this->plugin->includeClass('models/class.ilCoSubItem.php');

		$categories = array();
		foreach ($this->object->getCategories() as $cat_id => $category)
		{
			$categories[$category->title] = $category->cat_id;
		}

		foreach ($this->rows as $rowdata)
		{
			if (!empty($rowdata['identifier']) && !empty($this->items_by_identifier[$rowdata['identifier']]))
			{
				$item = $this->items[$this->items_by_identifier[$rowdata['identifier']]];
			}
			else
			{
				$item = new ilCoSubItem();
				$item->obj_id = $this->object->getId();
			}

			$item->title = $rowdata['title'];

			if (!empty($rowdata['description']))
			{
				$item->description = $rowdata['description'];
			}

			if (!empty($rowdata['identifier']))
			{
				$item->identifier = $rowdata['identifier'];
			}

			if (!empty($rowdata['category']) && !empty($categories[$rowdata['category']]))
			{
				$item->cat_id = $categories[$rowdata['category']];
			}

			if (!empty($rowdata['sub_min']))
			{
				$item->sub_min = $rowdata['sub_min'];
			}

			if (!empty($rowdata['sub_max']))
			{
				$item->sub_max = $rowdata['sub_max'];
			}

			$item->save();

			if (empty($rowdata['period_start']) || empty ($rowdata['period_end']))
			{
				continue;
			}

			$item->deleteSchedules();

			$this->plugin->includeClass('models/class.ilCoSubSchedule.php');
			$schedule = new ilCoSubSchedule();
			$schedule->obj_id = $item->obj_id;
			$schedule->item_id = $item->item_id;

			if (is_float($rowdata['period_start']))
			{
				$schedule->period_start = $this->excelTimeToUnix($rowdata['period_start']);
			}
			else
			{
				$start = new ilDateTime($rowdata['period_start'], IL_CAL_DATETIME);
				$schedule->period_start = $start->get(IL_CAL_UNIX);
			}

			if (is_float($rowdata['period_end']))
			{
				$schedule->period_end = $this->excelTimeToUnix($rowdata['period_end']);
			}
			else
			{
				$end = new ilDateTime($rowdata['period_end'], IL_CAL_DATETIME);
				$schedule->period_end = $end->get(IL_CAL_UNIX);
			}

			$schedule->save();
		}
	}


	/**
	 * Create the run to which the assignments should be related
	 */
	public function createRun(): void
	{
		global $ilUser;

		$this->run = new ilCoSubRun();
		$this->run->obj_id = $this->object->getId();
		$this->run->run_start = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->method = 'import';
		$this->run->details = (empty($this->comment) ? sprintf($this->plugin->txt('run_details_import'), $ilUser->getFullname()) : $this->comment);
		$this->run->save();
	}

	/**
	 * Create choices for assignments
	 * This is done for users who don't have an assignment
	 * @param ilCoSubAssign[] $a_assignments
	 */
	public function createChoicesForAssignments(ilCoSubAssign $a_assignments): void
	{
		$this->plugin->includeClass('models/class.ilCoSubChoice.php');
		$method = $this->object->getMethodObject();
		$has_mc = $method->hasMultipleChoice();
		$max_prio = count($method->getPriorities()) -1;

		$prio = 0;
		foreach ($a_assignments as $ass)
		{
			$choice = new ilCoSubChoice();
			$choice->item_id = $ass->item_id;
			$choice->user_id = $ass->user_id;
			$choice->obj_id = $ass->obj_id;
			$choice->priority = $prio;
			$choice->save();

			if (!$has_mc)
			{
				$prio++;
			}

			if ($prio >= $max_prio)
			{
				break;
			}
		}
	}

	/**
	 * search for the user id of a user by login
	 * and add it to the list of new users
	 * @param $a_login
	 * @return array|bool
	 */
	public function adddUserByLogin(string $a_login): array|bool
	{
		$user_id = ilObjUser::_lookupId($a_login);
		if (!empty($user_id))
		{
			$this->new_users_by_login[$a_login] = $user_id;
			return $user_id;
		}
		return false;
	}


	/**
	 * Get import errors
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

	/**
	 * Get the assignments run
	 * @return ilCoSubRun
	 */
	public function getRun(): ilCoSubRun
	{
		return $this->run;
	}


	/**
	 * Load the titles and identifiers of items
	 */
	protected function loadItemData(): void
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
	 * Load the logins and names of users
	 */
	protected function loadUserData(): void
	{
        $this->users = $this->object->getUsers();
		$user_ids = array_keys($this->users);
		if (empty($user_ids))
		{
			return;
		}

		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setUserFilter($user_ids);

		$user_query_result = $user_query->query();
		foreach ($user_query_result['set'] as $user)
		{
			$this->users_by_login[$user['login']] = $user['usr_id'];
		}
	}

	/**
	 * Convert an excel time to unix
	 * todo: check if the date conversion is ok
	 *
	 * @param $time
	 * @return int
	 */
	protected function excelTimeToUnix(float|int $time): int
	{
        return Date::excelToTimestamp($time, $this->dic->user()->getTimeZone());

// old implementation
//		$date = (int) $time;
//		$time = round(($time - $date) * 86400) - 3600;
//
//		$date = PHPExcel_Shared_Date::ExcelToPHP($date);
//		$dateTime = new ilDateTime(date('Y-m-d', $date) .' '. date('H:i:s', $time), IL_CAL_DATETIME);
//
//		return $dateTime->get(IL_CAL_UNIX);
	}
}