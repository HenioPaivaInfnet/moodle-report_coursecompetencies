<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * Renderer class for report_coursecompetencies
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
*/

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer class for course competencies report
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
 */
class report_coursecompetencies_renderer extends plugin_renderer_base {

	public function render_report(report $page) {
		$data = $page->export_for_template($this);
		return parent::render_from_template('report_coursecompetencies/report', $data);
	}
}
