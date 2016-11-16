<?php
// This file is NOT part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die;

/**
 * Class containing data for course competencies report
 *
 * @package    report_coursecompetencies
 * @copyright  2016 Instituto Infnet
 */
class report_coursecompetencies_report implements renderable, templatable {

	/** @var context $context */
	protected $context;
	/** @var course $course */
	protected $course;
	/** @var array $competencies */
	protected $competencies;
	/** @var array $users */
	protected $users;

	/**
	 * Construct this renderable.
	 *
	 * @param int $courseid The course id
	 * @param int $userid The user id
	 */
	public function __construct($course) {
		$this->course = $course;
		$this->context = context_course::instance($course->id);
	}

	/**
	 * Export this data so it can be used as the context for a mustache template.
	 *
	 * @param \renderer_base $output
	 * @return stdClass
	 */
	public function export_for_template(renderer_base $output) {
		global $DB;

		$course = $this->course;
		$coursecontext = $this->context;
		$exporter = new tool_lp\external\course_summary_exporter($course, array('context' => $coursecontext));

		$data = new stdClass();
		$data->course = $exporter->export($output);

		$competencies = core_competency\course_competency::list_competencies($course->id);

		print_object($competencies);

		$currentgroup = groups_get_course_group($course, true);
		$users = get_enrolled_users($coursecontext, 'moodle/competency:coursecompetencygradable', $currentgroup);
		foreach ($users as $user) {
			$exporter = new core_competency\external\user_summary_exporter($user);
			$user = $exporter->export($output);
			$data->users[] = $user;
		}

		//print_object($data);

		/*
		$data->usercompetencies = array();
		$scalecache = array();
		$frameworkcache = array();

		$user = core_user::get_user($this->userid);

		$exporter = new user_summary_exporter($user);
		$data->user = $exporter->export($output);
		$data->usercompetencies = array();
		$coursecompetencies = api::list_course_competencies($this->courseid);
		$usercompetencycourses = api::list_user_competencies_in_course($this->courseid, $user->id);

		foreach ($usercompetencycourses as $usercompetencycourse) {
			$onerow = new stdClass();
			$competency = null;
			foreach ($coursecompetencies as $coursecompetency) {
				if ($coursecompetency['competency']->get_id() == $usercompetencycourse->get_competencyid()) {
					$competency = $coursecompetency['competency'];
					break;
				}
			}
			if (!$competency) {
				continue;
			}
			// Fetch the framework.
			if (!isset($frameworkcache[$competency->get_competencyframeworkid()])) {
				$frameworkcache[$competency->get_competencyframeworkid()] = $competency->get_framework();
			}
			$framework = $frameworkcache[$competency->get_competencyframeworkid()];

			// Fetch the scale.
			$scaleid = $competency->get_scaleid();
			if ($scaleid === null) {
				$scaleid = $framework->get_scaleid();
				if (!isset($scalecache[$scaleid])) {
					$scalecache[$competency->get_scaleid()] = $framework->get_scale();
				}

			} else if (!isset($scalecache[$scaleid])) {
				$scalecache[$competency->get_scaleid()] = $competency->get_scale();
			}
			$scale = $scalecache[$competency->get_scaleid()];

			$exporter = new user_competency_course_exporter($usercompetencycourse, array('scale' => $scale));
			$record = $exporter->export($output);
			$onerow->usercompetencycourse = $record;
			$exporter = new competency_summary_exporter(null, array(
				'competency' => $competency,
				'framework' => $framework,
				'context' => $framework->get_context(),
				'relatedcompetencies' => array(),
				'linkedcourses' => array()
			));
			$onerow->competency = $exporter->export($output);
			array_push($data->usercompetencies, $onerow);
		}
		//*/

		return $data;
	}
}
