<?php
// This file is NOT part of Moodle - http://moodle.org/
//

/**
 * Public API of the course competencies report.
 *
 * Defines the APIs used by course competencies reports
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_coursecompetencies_extend_navigation_course($navigation, $course, $context) {
	if (!get_config('core_competency', 'enabled')) {
		return;
	}

	if (has_capability('moodle/competency:coursecompetencyview', $context)) {
		$url = new moodle_url('/report/coursecompetencies/index.php', array('id' => $course->id));
		$name = get_string('pluginname', 'report_coursecompetencies');
		$navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
	}
}
