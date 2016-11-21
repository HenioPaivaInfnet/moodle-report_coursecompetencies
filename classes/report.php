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
		$data = new stdClass();
		$scale = null;

		$course = $this->course;
		$coursecontext = $this->context;

		$data->competencies = array();
		$this->competencies = core_competency\course_competency::list_competencies($course->id);
		foreach ($this->competencies as $key => $competency) {
			if (!isset($scale)) {
				$scale = $competency->get_scale();
			}

			$exporter = new core_competency\external\competency_exporter($competency, array('context' => $coursecontext));
			$competencydata = $exporter->export($output);
			$data->competencies[] = $competencydata;
		}
		usort($data->competencies, function($competency1, $competency2) {
			return $competency1->idnumber - $competency2->idnumber;
		});

		$data->users = array();
		$currentgroup = groups_get_course_group($course, true);
		$this->users = get_enrolled_users($coursecontext, 'moodle/competency:coursecompetencygradable', $currentgroup);

		foreach ($this->users as $key => $user) {
			$user->picture = $output->user_picture($user, array('visibletoscreenreaders' => false));
			$user->profileurl = (new moodle_url('/user/profile.php', array('id' => $user->id, 'course' => $course->id)))->out(false);
			$user->fullname = fullname($user);

			$user->competencies = array();
			$usercompetencycourses = core_competency\api::list_user_competencies_in_course($course->id, $user->id);

			foreach ($usercompetencycourses as $usercompetencycourse) {
				$competency = null;
				foreach ($data->competencies as $coursecompetency) {
					if ($coursecompetency->id == $usercompetencycourse->get_competencyid()) {
						$exporter = new core_competency\external\user_competency_course_exporter($usercompetencycourse, array('scale' => $scale));
						$competency = $exporter->export($output);
						break;
					}
				}
				$user->competencies[] = $competency;
			}

			$data->users[] = $user;
		}
		usort($data->users, function($user1, $user2) {
			return strcmp($user1->fullname, $user2->fullname);
		});

		$data->img_toggledescription = $output->pix_icon('t/collapsed', get_string('competency_showdescription', 'report_coursecompetencies'));

		return $data;
	}

	public function export_xls(stdClass $data) {
		require_once(__DIR__ . '/../../../lib/excellib.class.php');

		$coursename = format_string($this->course->fullname, true, array('context' => $this->context));
		$downloadfilename = clean_filename("$coursename.xls");
		$workbook = new MoodleExcelWorkbook($downloadfilename);
		$workbook->send($downloadfilename);
		$myxls = $workbook->add_worksheet(get_string('xls_sheet_name', 'report_coursecompetencies'));

		$workbook->close();

		exit;
	}
}
