<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

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

	const MODE_REG_BY_ITEM = 'reg_by_item';
	const MODE_REG_BY_PRIO = 'reg_by_prio';
	const MODE_ASS_BY_ITEM = 'ass_by_item';
	const MODE_ASS_BY_COL = 'ass_by_col';


	/**
	 * @var ilExtendedTestStatisticsPlugin
	 */
	protected $plugin;

	/**
	 * @var ilExtendedTestStatistics
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

	/** @var array title => item_id */
	protected $items_by_title = array();

	/** @var array identifier => item_id */
	protected $items_by_identifier = array();

	/** @var array login => user_id */
	protected $users_by_login = array();

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

		$this->plugin->includeClass('models/class.ilCoSubRun.php');
		$this->plugin->includeClass('models/class.ilCoSubAssign.php');
	}

	/**
	 * Import a data file
	 * @param string $file
	 * @return bool
	 */
	public function ImportFile($file)
	{
		$this->message = '';
		try
		{
			if (!is_readable($file)) {
				throw new Exception($this->plugin->txt('import_file_not_readable'));
			}


			//  Create a new Reader of the type that has been identified
			require_once $this->plugin->getDirectory(). '/lib/PHPExcel-1.8/Classes/PHPExcel.php';
			$type = PHPExcel_IOFactory::identify($file);
			switch ($type)
			{
				case 'Excel5':
				case 'Excel2007':
				case 'Excel2003XML':
					/** @var PHPExcel_Reader_Excel2007 $reader */
					$reader = PHPExcel_IOFactory::createReader($type);
					$reader->setReadDataOnly(true);
					break;

				case 'CSV':
					/** @var PHPExcel_Reader_CSV $reader */
					$reader = PHPExcel_IOFactory::createReader($type);
					$reader->setDelimiter(';');
					$reader->setEnclosure('"');
					break;

				default:
					throw new Exception($this->plugin->txt('import_error_format'));
			}

			/** @var PHPExcel $xls */
			$xls = $reader->load($file);
			$sheet = $xls->getSheet(0);

			// analyze the read data
			switch ($this->mode)
			{
				case self::MODE_ASS_BY_ITEM:
					$this->readData($sheet);
					$this->readAssignmentsByItems();
					break;

				case self::MODE_ASS_BY_COL:
					$this->readData($sheet);
					$this->readAssignmentsByColumns();
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
			}

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
		$data = $sheet->toArray(null, false, false, false);
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
		if (!in_array('ID', $this->columns))
		{
			throw new Exception($this->plugin->txt('import_error_id_missing'));
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
	public function readAssignmentsByColumns()
	{
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
			$user_id = $this->users_by_login[$rowdata['ID']];
			if (empty($user_id))
			{
				throw new Exception(sprintf($this->plugin->txt('import_error_user_not_found'), $rowdata['ID']));
			}

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
				}
			}
		}
	}

	/**
	 * Read the assignments of users by items
	 * Column 'ID' column has the username
	 * Columns named by item titles or identifiers ar not empty if an item is assigned
	 */
	public function readAssignmentsByItems()
	{
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
			$user_id = $this->users_by_login[$rowdata['ID']];
			if (empty($user_id))
			{
				throw new Exception(sprintf($this->plugin->txt('import_error_user_not_found'), $rowdata['ID']));
			}

			$ass_count = 0;
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

					$ass_count++;
				}
			}
			if ($ass_count > 1 && !$this->object->getMethodObject()->hasMultipleAssignments())
			{
				throw new Exception($this->plugin->txt('import_error_multi_ass_entries'));
			}
		}
	}

	/**
	 * Create the run to which the assignments should be related
	 */
	public function createRun()
	{
		global $ilUser;

		$this->run = new ilCoSubRun();
		$this->run->obj_id = $this->object->getId();
		$this->run->run_start = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
		$this->run->method = 'import';
		$this->run->details = sprintf($this->plugin->txt('run_details_import'), $ilUser->getFullname());
		$this->run->save();
	}


	/**
	 * Get import errors
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * Get the assignments run
	 * @return ilCoSubRun
	 */
	public function getRun()
	{
		return $this->run;
	}


	/**
	 * Load the titles and identifiers of items
	 */
	protected function loadItemData()
	{
		foreach ($this->object->getItems() as $item)
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
	protected function loadUserData()
	{
		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setUserFilter(array_keys($this->object->getPriorities()));
		$user_query->setLimit(0);

		$user_query_result = $user_query->query();
		foreach ($user_query_result['set'] as $user)
		{
			$this->users_by_login[$user['login']] = $user['usr_id'];
		}
	}
}