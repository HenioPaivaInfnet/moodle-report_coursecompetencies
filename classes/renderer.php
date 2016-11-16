<?php
// This file is NOT part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer class for course competencies report
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
 */
class report_coursecompetencies_renderer extends plugin_renderer_base {

	/**
	 * Defer to template.
	 *
	 * @param report $page
	 * @return string html for the page
	 */
	public function render_report(report_coursecompetencies_report $page) {
		$data = $page->export_for_template($this);
		return $this->render_from_template('report_coursecompetencies/report', $data);
	}
}
