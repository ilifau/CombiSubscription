<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

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
	const MODE_ASS_BY_PRIO = 'ass_by_prio';


	protected $headerStyle = array(
		'font' => array(
			'bold' => true
		),
		'fill' => array(
			'type' => 'solid',
			'color' => array('rgb' => 'DDDDDD'),
		)
	);

	protected $rowStyles = array(
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
	 * @var ilExtendedTestStatisticsPlugin
	 */
	protected $plugin;

	/**
	 * @var ilExtendedTestStatistics
	 */
	protected $object;


	/** @var  string Writer Type ('excel' or 'csv') */
	protected $type;


	/** @var string export mode ('reg_by_item') */
	protected $mode;


	/** @var ilLanguage $lng */
	protected $lng;

	/**
	 * @var bool user has extended user data access
	 */
	protected $extended = false;


	/**
	 * @var bool platform has studydata
	 */
	protected $with_studydata = false;

	/**
	 * Constructor.
	 * @param ilCombiSubscriptionPlugin		$plugin
	 * @param ilObjCombiSubscription		$object
	 * @param string						$type
	 * @param string						$mode
	 */
	public function __construct($plugin, $object, $type = self::TYPE_EXCEL, $mode = '')
	{
		global $lng;

		$this->object = $object;
		$this->plugin  = $plugin;
		$this->type = $type;
		$this->mode = $mode;
		$this->lng = $lng;

		// check capabilities
		if ($this->object->hasExtendedUserData())
		{
			$this->extended = true;

			if ($this->object->hasStudyData())
			{
				require_once('Services/StudyData/classes/class.ilStudyData.php');
				$this->with_studydata = true;
			}
		}

	}


	/**
	 * Build an Excel Export file
	 * @param string	$path	full path of the file to create
	 */
	public function buildExportFile($path)
	{
		//Creating Files with Charts using PHPExcel
		require_once $this->plugin->getDirectory(). '/lib/PHPExcel-1.8/Classes/PHPExcel.php';
		$excelObj = new PHPExcel();


		switch($this->mode)
		{
			case self::MODE_REG_BY_ITEM:
				$this->fillRegistrationsByItem($excelObj->getActiveSheet());
				break;
		}

		$excelObj->setActiveSheetIndex(0);

		// Save the file
		ilUtil::makeDirParents(dirname($path));
		switch ($this->type)
		{
			case self::TYPE_EXCEL:
				/** @var PHPExcel_Writer_Excel2007 $writerObj */
				$writerObj = PHPExcel_IOFactory::createWriter($excelObj, 'Excel2007');
				$writerObj->save($path);
				break;
			case self::TYPE_CSV:
				/** @var PHPExcel_Writer_CSV $writerObj */
				$writerObj = PHPExcel_IOFactory::createWriter($excelObj, 'CSV');
				$writerObj->setDelimiter(';');
				$writerObj->setEnclosure('"');
				$writerObj->save($path);
		}
	}


	/**
	 * Fill the sheet with user registrations
	 * Items are columns, the priorities are values
	 * @param PHPExcel_Worksheet $worksheet
	 */
	protected function fillRegistrationsByItem($worksheet)
	{
		$mapping = array();

		// basic user header
		$columns = array(
			'lastname' => $this->lng->txt('lastname'),
			'firstname' => $this->lng->txt('firstname'),
			'login' => $this->lng->txt('login')
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
		}

		$basecols = count($columns);

		// registrations header
		foreach ($this->object->getItems() as $item)
		{
			$columns['item'.$item->item_id] = $item->title;
		}


		$col = 0;
		foreach ($columns as $key => $value)
		{
			$letter = PHPExcel_Cell::stringFromColumnIndex($col++);
			$mapping[$key] = $letter;
			$coordinate = $letter.'1';
			$cell = $worksheet->getCell($coordinate);
			$cell->setValueExplicit($value, PHPExcel_Cell_DataType::TYPE_STRING);
			$cell->getStyle()->applyFromArray($this->headerStyle);
			$cell->getStyle()->getAlignment()->setWrapText(true);
		}


		// query for users
		include_once("Services/User/classes/class.ilUserQuery.php");
		$user_query = new ilUserQuery();
		$user_query->setUserFilter(array_keys($this->object->getPriorities()));
		$user_query->setAdditionalFields(array('gender','matriculation'));
		$user_query->setLimit(0);
		$user_query->setOrderField('lastname');
		$user_query_result = $user_query->query();

		$prio_names = $this->object->getMethodObject()->getPriorities();

		$row = 2;
		foreach ($user_query_result['set'] as $user)
		{

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
					$studydata = ilStudyData::_getStudyDataText($user['usr_id']);

					if ($this->type == self::TYPE_CSV)
					{
						$studydata = str_replace('"','',$studydata);
						$studydata = str_replace("'",'',$studydata);
						$studydata = str_replace("'",'',$studydata);
						$studydata = str_replace(",",' ',$studydata);
						$studydata = str_replace(";",' ',$studydata);
						$studydata = str_replace("\n",' / ',$studydata);
					}

					$data['studydata'] = $studydata;
				}
			}

			// registrations values
			foreach ($this->object->getPrioritiesOfUser($user['usr_id']) as $item_id => $value)
			{
				$data['item'.$item_id] = $prio_names[$value];
			}

			foreach ($data as $key => $value)
			{
				$coordinate = $mapping[$key].(string) $row;
				$cell = $worksheet->getCell($coordinate);
				$cell->setValue($value);
				$cell->getStyle()->getAlignment()->setWrapText(true);
			}

			$row++;
		}

		$worksheet->setTitle($this->lng->txt('registrations'));
		$worksheet->freezePane('D2');
		$this->adjustSizes($worksheet, range('A',  PHPExcel_Cell::stringFromColumnIndex($basecols -1)));
	}



	/**
	 * @param PHPExcel_Worksheet	$worksheet
	 */
	protected function adjustSizes($worksheet, $range = null)
	{
		$range = isset($range) ? $range : range('A', $worksheet->getHighestColumn());
		foreach ($range as $columnID)
		{
			$worksheet->getColumnDimension($columnID)->setAutoSize(true);
		}
	}
}