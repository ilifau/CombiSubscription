<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * Combined Subscription Export
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilCoSubExport
{
	const TYPE_EXCEL = 'excel';
	const TYPE_CSV = 'csv';

	const MODE_REG_BY_ITEM = 'reg_by_item';
	const MODE_REG_BY_PRIO = 'reg_by_prio';
	const MODE_ASS_BY_ITEM = 'ass_by_item';
	const MODE_ASS_BY_COL = 'ass_by_col';

    const MODE_RAW_DATA = 'raw_data';
    const MODE_RAW_ITEMS = 'raw_items';
    const MODE_RAW_CHOICES = 'raw_choices';
    const MODE_RAW_SOLUTION = 'raw_solution';
    const MODE_RAW_SETTINGS = 'raw_settings';
    const MODE_RAW_CATEGORIES = 'raw_categories';
    const MODE_RAW_COMFLICTS = 'raw_conflicts';


	protected array $headerStyle = array(
		'font' => array(
			'bold' => true
		),
		'fill' => array(
			'type' => 'solid',
			'color' => array('rgb' => 'DDDDDD'),
		)
	);

	protected array $rowStyles = array(
		0 => array(
			'fill' => array(
				'type' => 'solid',
				'color' => array('rgb' => 'FFFFFF'),
			)),
		1 => array(
			'fill' => array(
				'type' => 'solid',
				'color' => array('rgb' => 'EEEEEE'),
			)),
	);


	/**
	 * @var ilCombiSubscriptionPlugin
	 */
	protected ilCombiSubscriptionPlugin $plugin;

	/**
	 * @var ilObjCombiSubscription
	 */
	protected ilObjCombiSubscription $object;


	/** @var  string Writer Type ('excel' or 'csv') */
	protected string $type;


	/** @var string export mode ('reg_by_item') */
	protected string $mode;


	/** @var ilLanguage $lng */
	protected ilLanguage $lng;

	/**
	 * @var bool user has extended user data access
	 */
	protected bool $extended = false;


	/**
	 * @var bool platform has studydata
	 */
	protected bool $with_studydata = false;


    /**
     * @var bool platform has educations
     */
    protected bool $with_educations = false;


    /**
	 * Constructor.
	 * @param ilCombiSubscriptionPlugin		$plugin
	 * @param ilObjCombiSubscription		$object
	 * @param string						$type
	 * @param string						$mode
	 */
	public function __construct(ilCombiSubscriptionPlugin $plugin, ilObjCombiSubscription $object, string $type = self::TYPE_EXCEL, string $mode = '')
	{
		global $lng;

		$this->object = $object;
		$this->plugin  = $plugin;
		$this->type = $type;
		$this->mode = $mode;
		$this->lng = $lng;

		// check capabilities
		if ($this->plugin->hasUserDataAccess())
		{
			$this->extended = true;

			if ($this->plugin->hasFauService())
			{
				$this->with_studydata = true;
                $this->with_educations = true;
			}
		}
	}


	/**
	 * Build an Excel Export file
	 * @param string	$directory	directory where the file should be creatred
     * @return string   full path of the created file
	 */
	public function buildExportFile(?string $directory = null, ?string $mode = null, ?string $type = null, ?string $delimiter = null, ?string $enclosure = null): string
	{
        $excelObj = new Spreadsheet();
		//$excelObj->setActiveSheetIndex(0);

        $directory = $directory ?? ilUtil::ilTempnam();
        ilUtil::makeDirParents($directory);

		switch($mode ?? $this->mode)
		{
			case self::MODE_REG_BY_ITEM:
				$this->fillRegistrationsByItem($excelObj->getActiveSheet());
                $name = 'registrations';
				break;
			case self::MODE_REG_BY_PRIO:
				$this->fillRegistrationsByPrio($excelObj->getActiveSheet());
                $name = 'registrations';
				break;
			case self::MODE_ASS_BY_ITEM:
				$this->fillAssignmentsByItem($excelObj->getActiveSheet());
                $name = 'assignments';
				break;
            case self::MODE_RAW_ITEMS:
                $this->fillRawItems($excelObj->getActiveSheet());
                $name = 'items';
                $enclosure = '';
                break;
            case self::MODE_RAW_CHOICES:
                $this->fillRawChoices($excelObj->getActiveSheet());
                $name = 'choices';
                $enclosure = '';
                break;
            case self::MODE_RAW_SOLUTION:
                $this->fillRawSolution($excelObj->getActiveSheet());
                $name = 'solution';
                $enclosure = '';
                break;
            case self::MODE_RAW_SETTINGS:
                $this->fillRawSettings($excelObj->getActiveSheet());
                $name = 'settings';
                $enclosure = '';
                break;
            case self::MODE_RAW_CATEGORIES:
                $this->fillRawCategories($excelObj->getActiveSheet());
                $name = 'categories';
                $enclosure = '';
                break;
            case self::MODE_RAW_COMFLICTS:
                $this->fillRawConflicts($excelObj->getActiveSheet());
                $name = 'conflicts';
                $enclosure = '';
                break;

            case self::MODE_RAW_DATA:
                $name = ilUtil::getASCIIFilename($this->object->getTitle()) . '_' . $this->object->getRefId();
                $subdir = $directory . '/' . str_replace(' ', '_', $name);

                ilUtil::makeDirParents($subdir);
                $this->buildExportFile($subdir, self::MODE_RAW_ITEMS);
                $this->buildExportFile($subdir, self::MODE_RAW_CHOICES);
                $this->buildExportFile($subdir, self::MODE_RAW_SOLUTION);
                $this->buildExportFile($subdir, self::MODE_RAW_SETTINGS);
                $this->buildExportFile($subdir, self::MODE_RAW_CATEGORIES);
                $this->buildExportFile($subdir, self::MODE_RAW_COMFLICTS);

                $zipfile = $subdir . '.zip';
                ilUtil::zip($subdir, $zipfile);
                return $zipfile;
		}

		// Save the file
		switch ($type ?? $this->type)
		{
			case self::TYPE_EXCEL:
                $file = $directory . '/' . $name . '.xlsx';
                $writer = IOFactory::createWriter($excelObj, 'Xlsx');
                $writer->save($file);
				break;

			case self::TYPE_CSV:
                $file = $directory . '/' . $name . '.csv';
                /** @var Csv $writer */
                $writer = IOFactory::createWriter($excelObj, 'Csv');
                $writer->setDelimiter($delimiter ?? ';');
                $writer->setEnclosure($enclosure ?? '"');
                $writer->save($file);
                break;
		}
        return $file;
	}


	/**
	 * Fill the sheet with user registrations
	 * Items are columns, the priorities are values
	 * @param Worksheet $worksheet
	 */
	protected function fillRegistrationsByItem(Worksheet $worksheet): void
	{
		// Column definition and header
		$columns = $this->getUserColumns();
		$basecols = count($columns);
		$row2 = array();
		$row3 = array();
		foreach ($this->object->getItems() as $item)
		{
			$columns['item'.$item->item_id] = !empty($item->identifier) ? $item->identifier : $item->title;
			$row2['item'.$item->item_id] = $item->title;
			$row3['item'.$item->item_id] = $item->getPeriodInfo();
		}
		$mapping = $this->fillHeaderRow($worksheet, $columns);
		$this->fillRowData($worksheet, $row2, $mapping, 2);
		$this->fillRowData($worksheet, $row3, $mapping, 3);

		// get the priority names
		$prio_names = $this->object->getMethodObject()->getPriorities();

		// query for users
		$user_query_result = $this->getUserQueryResult();

		$row = 4;
		foreach ($user_query_result['set'] as $user)
		{
			$data = $this->getUserColumnData($user);

			// registrations values
			foreach ($this->object->getPrioritiesOfUser($user['usr_id']) as $item_id => $value)
			{
				$data['item'.$item_id] = $prio_names[$value];
			}

			$this->fillRowData($worksheet, $data, $mapping, $row);
			$row++;
		}

		$worksheet->setTitle($this->plugin->txt('registrations'));
		$worksheet->freezePane('D2');
		$this->adjustSizes($worksheet, range('A',  Coordinate::stringFromColumnIndex($basecols)));
	}

    /**
     * Fill a sheet with raw item data
     * @param $worksheet
     */
    protected function fillRawItems(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'item_id' => 'item_id',
            'sub_min' => 'sub_min',
            'sub_max' => 'sub_max',
            'cat_id' => 'cat_id',
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        $row = 2;
        foreach ($this->object->getItems() as $item) {
            $data = [];
            $data['obj_id'] = $item->obj_id;
            $data['item_id'] = $item->item_id;
            $data['sub_min'] = $item->sub_min;
            $data['sub_max'] = $item->sub_max;
            $data['cat_id'] = $item->cat_id;
            $this->fillRowData($worksheet, $data, $mapping, $row++);
        }
        $worksheet->setTitle('items');
    }

    /**
     * Fill a sheet with raw choices data
     * @param $worksheet
     */
    protected function fillRawChoices(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'user_id' => 'user_id',
            'item_id' => 'item_id',
            'priority' => 'priority'
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        $row = 2;
        foreach ($this->object->getChoices() as $choice) {
            $data = [];
            $data['obj_id'] = $choice->obj_id;
            $data['user_id'] = $choice->user_id;
            $data['item_id'] = $choice->item_id;
            $data['priority'] = $choice->priority;
            $this->fillRowData($worksheet, $data, $mapping, $row++);
        }
        $worksheet->setTitle('choices');
    }


    /**
     * Fill a sheet with raw solution data
     * @param $worksheet
     */
    protected function fillRawSolution(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'user_id' => 'user_id',
            'item_id' => 'item_id'
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        //run_id => user_id => item_id => assign_id
        $assignments = $this->object->getAssignments();

        $row = 2;
        foreach ((array) $assignments[0] as $user_id => $ass) {
            foreach ($ass as $item_id => $assign_id) {
                $data = [];
                $data['obj_id'] = $this->object->getId();
                $data['user_id'] = $user_id;
                $data['item_id'] = $item_id;
                $this->fillRowData($worksheet, $data, $mapping, $row++);
            }
        }

        $worksheet->setTitle('solution');
    }

    /**
     * Fill a sheet with raw solution data
     * @param $worksheet
     */
    protected function fillRawSettings(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'num_priorities' => 'num_priorities',
            'num_assignments' => 'num_assignments'
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        $method = $this->object->getMethodObject();

        $row = 2;
        $data = [];
        $data['obj_id'] = $this->object->getId();
        $data['num_priorities'] = count($method->getPriorities());
        $data['num_assignments'] = $method->getNumberAssignments();
        $this->fillRowData($worksheet, $data, $mapping, $row);
        $worksheet->setTitle('solution');
    }

    /**
     * Fill a sheet with raw item data
     * @param $worksheet
     */
    protected function fillRawCategories(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'cat_id' => 'cat_id',
            'max_assignments' => 'max_assignments',
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        $row = 2;
        foreach ($this->object->getCategories() as $category) {
            $data = [];
            $data['obj_id'] = $category->obj_id;
            $data['cat_id'] = $category->cat_id;
            $data['max_assignments'] = $category->max_assignments;

            $this->fillRowData($worksheet, $data, $mapping, $row++);
        }
        $worksheet->setTitle('categories');
    }


    /**
     * Fill a sheet with raw item data
     * @param $worksheet
     */
    protected function fillRawConflicts(Worksheet $worksheet): void
    {
        $columns = [
            'obj_id' => 'obj_id',
            'item1_id' => 'item1_id',
            'item2_id' => "item2_id"
        ];
        $mapping = $this->fillHeaderRow($worksheet, $columns);

        $row = 2;
        foreach ($this->object->getItemsConflicts() as $item1_id => $items2) {
            foreach ($items2 as $item2_id) {
                $data = [];
                $data['obj_id'] = $this->object->getId();
                $data['item1_id'] = $item1_id;
                $data['item2_id'] = $item2_id;
                $this->fillRowData($worksheet, $data, $mapping, $row++);
            }
        }
        $worksheet->setTitle('conflicts');
    }


    /**
	 * Fill the sheet with assignments
	 * Items are columns, assigned items will have a 1 in the cell
	 * @param Worksheet $worksheet
	 */
	protected function fillAssignmentsByItem(Worksheet $worksheet): void
	{
		// Column definition and header
		$columns = $this->getUserColumns();
		$basecols = count($columns);
		$row2 = array();
		$row3 = array();
		foreach ($this->object->getItems() as $item)
		{
			$columns['item'.$item->item_id] = !empty($item->identifier) ? $item->identifier : $item->title;
			$row2['item'.$item->item_id] = $item->title;
			$row3['item'.$item->item_id] = $item->getPeriodInfo();
		}
		$mapping = $this->fillHeaderRow($worksheet, $columns);
		$this->fillRowData($worksheet, $row2, $mapping, 2);
		$this->fillRowData($worksheet, $row3, $mapping, 3);

		// get the priority names
		$prio_names = $this->object->getMethodObject()->getPriorities();

		// query for users
		$user_query_result = $this->getUserQueryResult();

		$row = 4;
		foreach ($user_query_result['set'] as $user)
		{
			$data = $this->getUserColumnData($user);

			// registrations values
			foreach ($this->object->getAssignmentsOfUser($user['usr_id']) as $item_id => $assign_id)
			{
				$data['item'.$item_id] = 1;
			}

			$this->fillRowData($worksheet, $data, $mapping, $row);
			$row++;
		}

		$worksheet->setTitle($this->plugin->txt('assignments'));
		$worksheet->freezePane('D2');
		$this->adjustSizes($worksheet, range('A',  Coordinate::stringFromColumnIndex($basecols -1)));
	}


	/**
	 * Fill the sheet with user registrations
	 * Priorities are columns, the items are listed as values
	 * @param Worksheet $worksheet
	 */
	protected function fillRegistrationsByPrio(Worksheet $worksheet): void
	{
		// Column definition and header
		$columns = $this->getUserColumns();
		$basecols = count($columns);
		$prio_names = $this->object->getMethodObject()->getPriorities();
		foreach ($prio_names as $index => $name)
		{
			$columns['prio'.$index] = $name;
		}
		$mapping = $this->fillHeaderRow($worksheet, $columns);

		// get the item names
		$item_names = array();
		foreach ($this->object->getItems() as $item)
		{
			$item_names[$item->item_id] = !empty($item->identifier) ? $item->identifier : $item->title;
		}

		// query for users
		$user_query_result = $this->getUserQueryResult();

		$row = 2;
		foreach ($user_query_result['set'] as $user)
		{
			$data = $this->getUserColumnData($user);

			// registrations values
			foreach ($this->object->getPrioritiesOfUser($user['usr_id']) as $item_id => $value)
			{
				$data['prio'.$value] = empty($data['prio'.$value]) ? '' : $data['prio'.$value] . ', ';
				$data['prio'.$value] .= $item_names[$item_id];
			}

			$this->fillRowData($worksheet, $data, $mapping, $row);
			$row++;
		}

		$worksheet->setTitle($this->plugin->txt('registrations'));
		$worksheet->freezePane('D2');
		$this->adjustSizes($worksheet, range('A',  Coordinate::stringFromColumnIndex($basecols -1)));
	}


	/**
	 * Get the definition of the user columns
	 * @return array
	 */
	protected function getUserColumns(): array
	{
		// basic user header
		$columns = array(
			'login' => 'ID',
			'lastname' => $this->lng->txt('lastname'),
			'firstname' => $this->lng->txt('firstname')
		);

		// extended user header
		if ($this->extended)
		{
			$columns = array_merge($columns, array(
				'gender' => $this->lng->txt('gender'),
				'email' => $this->lng->txt('email'),
				'matriculation' => $this->lng->txt('matriculation')
			));

			if ($this->with_studydata)
			{
				$columns['studydata'] =  $this->lng->txt('studydata');
			}

            if ($this->with_educations)
            {
                $columns['educations'] =  $this->lng->txt('educations');
            }
		}

		return $columns;
	}

	/**
	 * Get the result of hte user quers
	 * @see ilUserQuery::query()
	 *
	 * @return array ('cnt', 'set')
	 */
	protected function getUserQueryResult(): array
	{
		$user_ids = array_keys($this->object->getPriorities());
		if (empty($user_ids))
		{
			return array('cnt' => 0, 'set'=> array());
		}

		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setLimit($this->plugin->getUserQueryLimit());
		$user_query->setUserFilter($user_ids);
		$user_query->setAdditionalFields(array('gender','matriculation'));
		$user_query->setOrderField('lastname');

		return $user_query->query();
	}

	/**
	 * Get the data of the user columns for a row
	 *
	 * @param array 	$user 	(single user part of getUserQueryResult())
	 * @return array 	data for the user columns of a row
	 */
	protected function getUserColumnData(array $user): array
	{
        global $DIC;

		$data = array();

		// basic user values
		$data['login'] = $user['login'];
		$data['lastname'] = $user['lastname'];
		$data['firstname'] = $user['firstname'];

		// extended user values
		if ($this->extended)
		{
			$data['gender'] = $user['gender'];
			$data['email'] = $user['email'];
			$data['matriculation'] = $user['matriculation'];

			if ($this->with_studydata)
			{
                $studydata = $DIC->fau()->user()->getStudiesAsText((int) $user['usr_id']);
				if ($this->type == self::TYPE_CSV) {
                    $studydata = $DIC->fau()->tools()->convert()->quoteForExport($studydata);
				}
				$data['studydata'] = $studydata;
			}

            if ($this->with_educations)
            {
                $educations = $DIC->fau()->user()->getEducationsAsText((int) $user['usr_id'], (int) $this->object->getRefId());
                if ($this->type == self::TYPE_CSV) {
                    $educations = $DIC->fau()->tools()->convert()->quoteForExport($educations);
                }
                $data['educations'] = $educations;
            }
		}

		return $data;
	}


	/**
	 * Fill the header Row of a sheet
	 * @param Worksheet	$worksheet
	 * @param array	$columns
	 * @return array	column key => column letter
	 */
	protected function fillHeaderRow(Worksheet $worksheet, array $columns): array
	{
		$col = 1;
		$mapping = array();
		foreach ($columns as $key => $value)
		{
			$letter = Coordinate::stringFromColumnIndex($col++);
			$mapping[$key] = $letter;
			$coordinate = $letter.'1';
			$cell = $worksheet->getCell($coordinate);
			$cell->setValueExplicit($value, DataType::TYPE_STRING);
			$cell->getStyle()->applyFromArray($this->headerStyle);
			$cell->getStyle()->getAlignment()->setWrapText(true);
		}
		return $mapping;
	}

	/**
	 * Fill a row of a sheet with data
	 * @param Worksheet	$worksheet
	 * @param array 				$data		key => value
	 * @param array					$mapping 	key => letter
	 * @param int					$row 		row number
	 */
	protected function fillRowData(Worksheet $worksheet, array $data, array $mapping, int $row): void
	{
		foreach ($data as $key => $value)
		{
			$coordinate = $mapping[$key].(string) $row;
			$cell = $worksheet->getCell($coordinate);
			$cell->setValue($value);
			$cell->getStyle()->getAlignment()->setWrapText(true);
		}
	}

	/**
	 * @param Worksheet	$worksheet
	 */
	protected function adjustSizes(Worksheet $worksheet, ?array $range = null)
	{
		$range = isset($range) ? $range : range('A', $worksheet->getHighestColumn());
		foreach ($range as $columnID)
		{
			$worksheet->getColumnDimension($columnID)->setAutoSize(true);
		}
	}
}