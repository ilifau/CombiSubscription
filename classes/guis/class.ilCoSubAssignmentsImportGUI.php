<?php

/**
 * Class for Excel/CSV import of assignments
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilCoSubAssignmentsImportGUI: ilCoSubAssignmentsGUI
 */
class ilCoSubAssignmentsImportGUI extends ilCoSubImportBaseGUI
{
    protected bool $add_comment = true;

	public function __construct(ilObjCombiSubscriptionGUI $a_parent_gui)
	{
		parent::__construct($a_parent_gui);

		$this->modes = array(
			ilCoSubImport::MODE_ASS_BY_ITEM => array(
				'title' => $this->plugin->txt('import_mode_ass_by_item'),
				'info' => $this->plugin->txt('import_mode_ass_by_item_info'),
				'success' => $this->plugin->txt('import_assignments_finished'),
				'failure' => $this->plugin->txt('import_assignments_failed'),
				'default' => true
			),
			ilCoSubImport::MODE_ASS_BY_COL => array(
				'title' => $this->plugin->txt('import_mode_ass_by_col'),
				'info' => $this->plugin->txt('import_mode_ass_by_col_info'),
				'success' => $this->plugin->txt('import_assignments_finished'),
				'failure' => $this->plugin->txt('import_assignments_failed'),
				'default' => false
			),
            ilCoSubImport::MODE_ASS_BY_IDS => array(
                'title' => $this->plugin->txt('import_mode_ass_by_ids'),
                'info' => $this->plugin->txt('import_mode_ass_by_ids_info'),
                'success' => $this->plugin->txt('import_assignments_finished'),
                'failure' => $this->plugin->txt('import_assignments_failed'),
                'default' => false
            ),
		);
	}
}