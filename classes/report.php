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

		$external_grade_scale_values = array(
			'2' => 50,
			'3' => 75,
			'4' => 100
		);

		$data->competencies = array();
		$this->competencies = core_competency\course_competency::list_competencies($course->id);
		foreach ($this->competencies as $key => $competency) {
			if (!isset($scale)) {
				$scale = $competency->get_scale();
			}

			$exporter = new core_competency\external\competency_exporter($competency, array('context' => $coursecontext));
			$competencydata = $exporter->export($output);
			$competencydata->description = format_string($competencydata->description);
			$data->competencies[] = $competencydata;
		}
		usort($data->competencies, function($competency1, $competency2) {
			return $competency1->idnumber - $competency2->idnumber;
		});

		$competencies_count = count($data->competencies);

		$data->users = array();
		$currentgroup = groups_get_course_group($course, true);
		$this->users = get_enrolled_users($coursecontext, 'moodle/competency:coursecompetencygradable', $currentgroup);

		foreach ($this->users as $key => $user) {
			$user->picture = $output->user_picture($user, array('visibletoscreenreaders' => false));
			$user->profileurl = (new moodle_url('/user/profile.php', array('id' => $user->id, 'course' => $course->id)))->out(false);
			$user->fullname = fullname($user);

			$user->competencies = array();
			$usercompetencycourses = core_competency\api::list_user_competencies_in_course($course->id, $user->id);

			$user->course_passed = true;
			$user->external_grade = 0;
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

				if ($competency->proficiency !== '1') {
					$user->course_passed = false;
				} else {
					$user->external_grade += $external_grade_scale_values[$competency->grade];
				}
			}

			$user->external_grade /= $competencies_count;

			if ($user->course_passed === false) {
				$user->external_grade *= 0.4;
			}

			$user->external_grade = round($user->external_grade);

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

		global $DB;

		$course_name = $data->course_name;
		$filename = clean_filename("$course_name.xls");
		$competencies = $data->competencies;
		$competencies_count = count($competencies);
		$users_count = count($data->users);

		$first_row = 1;
		$first_col = 1;

		$col_widths = array(
			'competency_description' => 100,
			'competency_number' => 4.84,
			'course_result' => 28.6,
			'external_grade' => 13.3,
			'left_margin' => 3.4,
			'student' => 40
		);

		$formats = array(
			'border_0222' => array('right' => 2, 'bottom' => 2, 'left' => 2),
			'border_0202' => array('right' => 2, 'left' => 2),
			'border_2121' => array('top' => 2, 'right' => 1, 'bottom' => 2, 'left' => 1),
			'border_2122' => array('top' => 2, 'right' => 1, 'bottom' => 2, 'left' => 2),
			'border_2202' => array('top' => 2, 'right' => 2, 'left' => 2),
			'border_2221' => array('top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 1),
			'centre' => array('align' => 'centre', 'v_align' => 'centre'),
			'centre_bold' => array('align' => 'centre', 'v_align' => 'centre', 'bold' => 1, 'text_wrap' => true),
			'course_result_failed' => array('bg_color' => '#FFA7A7'),
			'course_result_header' => array('bg_color' => '#EAF1DD'),
			'course_result_passed' => array('bg_color' => '#C5E0B3'),
			'student_header_color' => array('bg_color' => '#79C1D5'),
			'zebra_even' => array('bg_color' => '#DAEEF3'),
			'zebra_odd' => array('bg_color' => '#B6DDE8')
		);

		$categories = explode('/', $data->category_path);
		$modalidade = $DB->get_field('course_categories', 'name', array('id' => $categories[1]));
		$programa = $DB->get_field('course_categories', 'name', array('id' => $categories[3]));
		$classe = $DB->get_field('course_categories', 'name', array('id' => $categories[4]));
		$bloco = $DB->get_field('course_categories', 'name', array('id' => $categories[5]));

		$header_first = implode(' - ', array(
			$modalidade,
			$programa,
			$classe
		));
		$header_second = 'Disciplina: ' . $course_name . ' / Bloco: ' . $bloco;

		$workbook = new MoodleExcelWorkbook($filename);
		$workbook->send($filename);
		$xls_sheet = $workbook->add_worksheet(get_string('xls_sheet_name', 'report_coursecompetencies'));

		// Left margin column width
		$xls_sheet->set_column(0, 0, $col_widths['left_margin']);

		// Header
		$xls_sheet->write_string($first_row, $first_col, $header_first, $workbook->add_format(array_merge($formats['centre_bold'], $formats['border_2202'])));
		$xls_sheet->merge_cells($first_row, $first_col, $first_row, $first_col + $competencies_count + 2);
		$col = $first_col + 1;
		while ($col <= $first_col + $competencies_count + 2) {
			$xls_sheet->write_blank($first_row, $col++, $workbook->add_format($formats['border_2202']));
		}

		$xls_sheet->write_string($first_row + 1, $first_col, $header_second, $workbook->add_format(array_merge($formats['centre_bold'], array('left' => 2))));
		$xls_sheet->merge_cells($first_row + 1, $first_col, $first_row + 1, $first_col + $competencies_count + 2);
		$xls_sheet->write_blank($first_row + 1, $first_col + $competencies_count + 2, $workbook->add_format(array('right' => 2)));

		// Column titles
		$xls_sheet->write_string($first_row + 2, $first_col, get_string('student', 'report_coursecompetencies'), $workbook->add_format(array_merge($formats['centre_bold'], $formats['student_header_color'], $formats['border_2202'])));
		$xls_sheet->merge_cells($first_row + 2, $first_col, $first_row + 3, $first_col);
		$xls_sheet->write_blank($first_row + 3, $first_col, $workbook->add_format($formats['border_0222']));
		$xls_sheet->set_column($first_col, $first_col, $col_widths['student']);

		$xls_sheet->write_string($first_row + 2, $first_col + 1, get_string('competencies_result', 'report_coursecompetencies'), $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2))));
		$xls_sheet->merge_cells($first_row + 2, $first_col + 1, $first_row + 2, $first_col + $competencies_count);
		$col = $first_col + 2;
		while ($col <= $first_col + $competencies_count) {
			$xls_sheet->write_blank($first_row + 2, $col++, $workbook->add_format(array('border' => 2)));
		}
		$xls_sheet->set_column($first_col + 1, $first_col + $competencies_count, $col_widths['competency_number']);

		$xls_sheet->write_string($first_row + 2, $first_col + $competencies_count + 1, get_string('course_result', 'report_coursecompetencies'), $workbook->add_format(array_merge($formats['centre_bold'], $formats['course_result_header'], array('border' => 2))));
		$xls_sheet->merge_cells($first_row + 2, $first_col + $competencies_count + 1, $first_row + 3, $first_col + $competencies_count + 1);
		$xls_sheet->write_blank($first_row + 3, $first_col + $competencies_count + 1, $workbook->add_format($formats['border_0222']));
		$xls_sheet->set_column($first_col + $competencies_count + 1, $first_col + $competencies_count + 1, $col_widths['course_result']);

		$xls_sheet->write_string($first_row + 2, $first_col + $competencies_count + 2, get_string('external_grade', 'report_coursecompetencies'), $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2))));
		$xls_sheet->merge_cells($first_row + 2, $first_col + $competencies_count + 2, $first_row + 3, $first_col + $competencies_count + 2);
		$xls_sheet->write_blank($first_row + 3, $first_col + $competencies_count + 2, $workbook->add_format($formats['border_0222']));
		$xls_sheet->set_column($first_col + $competencies_count + 2, $first_col + $competencies_count + 2, $col_widths['external_grade']);

		// Competency numbers
		$borders = array();
		foreach ($competencies as $index => $competency) {
			if ($index === 0) {
				$borders = $formats['border_2122'];
			} else if ($index === $competencies_count - 1) {
				$borders = $formats['border_2221'];
			} else {
				$borders = $formats['border_2121'];
			}

			$xls_sheet->write_number($first_row + 3, $first_col + $index + 1, $competency->idnumber, $workbook->add_format(array_merge($formats['zebra_even'], $borders, array('align' => 'centre'))));
		}

		// Student rows
		$borders = array();
		$zebra = array();
		$row = $first_row;
		foreach ($data->users as $index_user => $user) {
			$row = $first_row + $index_user + 4;

			$borders = ($index_user === $users_count - 1) ? $formats['border_0222'] : $formats['border_0202'];
			$zebra = ($index_user % 2 === 0) ? $formats['zebra_even'] : $formats['zebra_odd'];

			$xls_sheet->write_string($row, $first_col, $user->fullname, $workbook->add_format(array_merge($borders, $zebra)));

			$format = null;
			$col = $first_col;
			foreach ($user->competencies as $index => $competency) {
				$col = $first_col + $index + 1;
				$format = array_merge($zebra, $formats['centre']);

				if ($index_user === $users_count - 1) {
					$format['bottom'] = 2;
				}

				if (isset($competency->grade)) {
					if ($competency->proficiency === '1') {
						$format = array_merge($format, $formats['course_result_passed']);
					} else {
						$format = array_merge($format, $formats['course_result_failed']);
					}

					$xls_sheet->write_string($row, $col, $competency->gradename, $workbook->add_format($format));
				} else  {
					$xls_sheet->write_blank($row, $col, $workbook->add_format($format));
				}
			}

			$course_result = ($user->course_passed === true) ? 'passed' : 'failed';
			$format = array_merge($formats['centre'], $borders, $formats['course_result_' . $course_result]);

			$xls_sheet->write_string($row, $col + 1, get_string('course_result_' . $course_result, 'report_coursecompetencies'), $format);
			$xls_sheet->write_number($row, $col + 2, $user->external_grade, $format);
		}

		$xls_sheet_competencies = $workbook->add_worksheet(get_string('competencies', 'core_competency'));

		// Column widths
		$xls_sheet_competencies->set_column(0, 0, $col_widths['left_margin']);
		$xls_sheet_competencies->set_column($first_col, $first_col, $col_widths['competency_number']);
		$xls_sheet_competencies->set_column($first_col + 1, $first_col + 1, $col_widths['competency_description']);

		// Header
		$xls_sheet_competencies->write_string($first_row, $first_col, $header_first, $workbook->add_format(array_merge($formats['centre_bold'], $formats['border_2202'])));
		$xls_sheet_competencies->merge_cells($first_row, $first_col, $first_row, $first_col + 1);
		$xls_sheet_competencies->write_blank($first_row, $first_col + 1, $workbook->add_format($formats['border_2202']));

		$xls_sheet_competencies->write_string($first_row + 1, $first_col, $header_second, $workbook->add_format(array_merge($formats['centre_bold'], array('left' => 2))));
		$xls_sheet_competencies->merge_cells($first_row + 1, $first_col, $first_row + 1, $first_col + 1);
		$xls_sheet_competencies->write_blank($first_row + 1, $first_col + 1, $workbook->add_format(array('right' => 2)));

		$xls_sheet_competencies->write_string($first_row + 2, $first_col, get_string('competencies', 'core_competency'), $workbook->add_format(array_merge($formats['centre_bold'], $formats['course_result_header'], array('border' => 2, 'size' => 14))));
		$xls_sheet_competencies->merge_cells($first_row + 2, $first_col, $first_row + 2, $first_col + 1);
		$xls_sheet_competencies->write_blank($first_row + 2, $first_col + 1, $workbook->add_format(array('border' => 2)));

		// Competency rows
		foreach ($competencies as $index => $competency) {
			$format = array();
			if ($index === $competencies_count - 1) {
				$format['bottom'] = 2;
			}

			$xls_sheet_competencies->write_number($first_row + $index + 3, $first_col, $competency->idnumber, $workbook->add_format(array_merge($format, array('align' => 'right', 'align' => 'vcentre', 'left' => 2))));
			$xls_sheet_competencies->write_string($first_row + $index + 3, $first_col + 1, $competency->description, $workbook->add_format(array_merge($format, array('bold' => 1, 'text_wrap' => true, 'right' => 2))));
		}

		$workbook->close();

		exit;
	}
}
