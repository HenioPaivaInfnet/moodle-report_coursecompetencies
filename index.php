<?php
// This file is NOT part of Moodle - http://moodle.org/
//
/**
 * This page lets you manage the competency rating for all the students in a course
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
*/

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$export_xls = optional_param('export_xls', false, PARAM_BOOL);

$params = array('id' => $id);
$course = $DB->get_record('course', $params, '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);

$url = new moodle_url('/report/coursecompetencies/index.php', $params);
$PAGE->set_url($url);

$page = new report_coursecompetencies_report($course);
$coursename = format_string($course->fullname, true, array('context' => $context));

if ($export_xls !== 1) {
	$title = get_string('pluginname', 'report_coursecompetencies');

	$PAGE->set_title($title);
	$PAGE->set_heading($coursename);
	$PAGE->set_pagelayout('incourse');
}

// get_renderer must be called after above functions for the layout to render properly
$output = $PAGE->get_renderer('report_coursecompetencies');

if ($export_xls !== 1) {
	echo $output->header();
	echo $output->heading($title, 3);

	$url->param('export_xls', true);
	echo html_writer::div(
		html_writer::link(
			$url,
			get_string('export_xls', 'report_coursecompetencies'),
			array('class' => 'btn btn-primary')
		),
		'btn_container'
	);

	echo $output->render_report($page);
	echo $output->footer();
} else {
	$data = $page->export_for_template($output);
	$data->category_path = $PAGE->category->path;
	$data->course_name = $coursename;

	$export = $page->export_xls($data);
}
