<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Arquivo contendo a classe que define os dados do relatório.
 *
 * Contém a classe que carrega os dados do relatório e exporta para exibição ou
 * download.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Classe contendo dados para o relatório.
 *
 * Carrega os dados de estudantes, competências e conceitos de um curso para
 * gerar o relatório.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_coursecompetencies_report implements renderable, templatable {

    /** @var context Objeto de contexto do curso. */
    protected $context;
    /** @var course Objeto do curso. */
    protected $course;
    /** @var competency[] Competências associadas ao curso. */
    protected $competencies;
    /**
     * @var stdClass[] Estudantes do curso,
     *                 obtidos por {@link get_enrolled_users}.
     */
    protected $users;

    /**
     * Retorna uma instância do relatório, com propriedades inicializadas.
     *
     * @param stdClass $course Objeto do curso.
     */
    public function __construct($course) {
        $this->course = $course;
        $this->context = context_course::instance($course->id);
    }

    /**
     *
     * Carrega estudantes matriculados e conceitos de competências e exporta
     * os dados para serem utilizados em um template no formato mustache.
     *
     * @param \renderer_base $output Instância de uma classe de
     *                               renderização, usada para obter dados com orientação a objeto.
     * @return stdClass Dados a serem utilizados pelo template.
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $scale = null;

        $course = $this->course;
        $coursecontext = $this->context;

        $extgradescalevalues = array(
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

        $competenciescount = count($data->competencies);

        $data->users = array();
        $currentgroup = groups_get_course_group($course, true);
        $this->users = get_enrolled_users($coursecontext, 'moodle/competency:coursecompetencygradable', $currentgroup);

        foreach ($this->users as $key => $user) {
            $user->picture = $output->user_picture($user, array('visibletoscreenreaders' => false));
            $user->profileurl = (
                new moodle_url('/user/profile.php', array('id' => $user->id, 'course' => $course->id))
            )->out(false);
            $user->fullname = fullname($user);

            $user->competencies = array();
            $usercompetencies = core_competency\api::list_user_competencies_in_course($course->id, $user->id);

            $user->coursepassed = true;
            $user->externalgrade = 0;
            foreach ($usercompetencies as $usercompetencycourse) {
                $competency = null;

                foreach ($data->competencies as $coursecompetency) {
                    if ($coursecompetency->id == $usercompetencycourse->get_competencyid()) {
                        $exporter = new core_competency\external\user_competency_course_exporter(
                            $usercompetencycourse, array('scale' => $scale)
                        );
                        $competency = $exporter->export($output);
                        break;
                    }
                }
                $user->competencies[] = $competency;

                if ($competency->proficiency !== '1') {
                    $user->coursepassed = false;
                } else {
                    $user->externalgrade += $extgradescalevalues[$competency->grade];
                }
            }

            $user->externalgrade /= $competenciescount;

            if ($user->coursepassed === false) {
                $user->externalgrade *= 0.4;
            }

            $user->externalgrade = round($user->externalgrade);

            $data->users[] = $user;
        }
        usort($data->users, function($user1, $user2) {
            return strcmp($user1->fullname, $user2->fullname);
        });

        $data->imgtoggledescription = $output->pix_icon(
            't/collapsed',
            get_string('competency_showdescription', 'report_coursecompetencies')
        );

        return $data;
    }

    /**
     * Exporta dados do relatório no formato Excel.
     *
     * @param stdClass $data Dados de estudantes e competências exportados
     *                       por {@link export_for_template}.
     */
    public function export_xls(stdClass $data) {
        require_once(__DIR__ . '/../../../lib/excellib.class.php');

        global $DB;

        $coursename = $data->coursename;
        $filename = clean_filename("$coursename.xls");
        $competencies = $data->competencies;
        $competenciescount = count($competencies);
        $userscount = count($data->users);

        $firstrow = 1;
        $firstcol = 1;

        $colwidths = array(
            'competency_description' => 100,
            'competency_number' => 4.84,
            'course_result' => 28.6,
            'externalgrade' => 13.3,
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

        $headerfirst = implode(' - ', array(
            $modalidade,
            $programa,
            $classe
        ));
        $headersecond = 'Disciplina: ' . $coursename . ' / Bloco: ' . $bloco;

        $workbook = new MoodleExcelWorkbook($filename);
        $workbook->send($filename);
        $xlssheet = $workbook->add_worksheet(get_string('xls_sheet_name', 'report_coursecompetencies'));

        // Left margin column width.
        $xlssheet->set_column(0, 0, $colwidths['left_margin']);

        // Header.
        $xlssheet->write_string(
            $firstrow,
            $firstcol,
            $headerfirst,
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['border_2202']))
        );
        $xlssheet->merge_cells($firstrow, $firstcol, $firstrow, $firstcol + $competenciescount + 2);
        $col = $firstcol + 1;
        while ($col <= $firstcol + $competenciescount + 2) {
            $xlssheet->write_blank($firstrow, $col++, $workbook->add_format($formats['border_2202']));
        }

        $xlssheet->write_string(
            $firstrow + 1,
            $firstcol,
            $headersecond,
            $workbook->add_format(array_merge($formats['centre_bold'], array('left' => 2)))
        );
        $xlssheet->merge_cells($firstrow + 1, $firstcol, $firstrow + 1, $firstcol + $competenciescount + 2);
        $xlssheet->write_blank($firstrow + 1, $firstcol + $competenciescount + 2, $workbook->add_format(array('right' => 2)));

        // Column titles.
        $xlssheet->write_string(
            $firstrow + 2,
            $firstcol,
            get_string('student', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['student_header_color'], $formats['border_2202']))
        );
        $xlssheet->merge_cells($firstrow + 2, $firstcol, $firstrow + 3, $firstcol);
        $xlssheet->write_blank($firstrow + 3, $firstcol, $workbook->add_format($formats['border_0222']));
        $xlssheet->set_column($firstcol, $firstcol, $colwidths['student']);

        $xlssheet->write_string(
            $firstrow + 2,
            $firstcol + 1,
            get_string('competencies_result', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2)))
        );
        $xlssheet->merge_cells($firstrow + 2, $firstcol + 1, $firstrow + 2, $firstcol + $competenciescount);
        $col = $firstcol + 2;
        while ($col <= $firstcol + $competenciescount) {
            $xlssheet->write_blank($firstrow + 2, $col++, $workbook->add_format(array('border' => 2)));
        }
        $xlssheet->set_column($firstcol + 1, $firstcol + $competenciescount, $colwidths['competency_number']);

        $xlssheet->write_string($firstrow + 2,
            $firstcol + $competenciescount + 1,
            get_string('course_result', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['course_result_header'], array('border' => 2)))
        );
        $xlssheet->merge_cells(
            $firstrow + 2,
            $firstcol + $competenciescount + 1,
            $firstrow + 3,
            $firstcol + $competenciescount + 1
        );
        $xlssheet->write_blank(
            $firstrow + 3,
            $firstcol + $competenciescount + 1,
            $workbook->add_format($formats['border_0222'])
        );
        $xlssheet->set_column(
            $firstcol + $competenciescount + 1,
            $firstcol + $competenciescount + 1,
            $colwidths['course_result']
        );

        $xlssheet->write_string(
            $firstrow + 2,
            $firstcol + $competenciescount + 2,
            get_string('externalgrade', 'report_coursecompetencies'),
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['zebra_even'], array('border' => 2)))
        );
        $xlssheet->merge_cells(
            $firstrow + 2,
            $firstcol + $competenciescount + 2,
            $firstrow + 3,
            $firstcol + $competenciescount + 2
        );
        $xlssheet->write_blank(
            $firstrow + 3,
            $firstcol + $competenciescount + 2,
            $workbook->add_format($formats['border_0222'])
        );
        $xlssheet->set_column(
            $firstcol + $competenciescount + 2,
            $firstcol + $competenciescount + 2,
            $colwidths['externalgrade']
        );

        // Competency numbers.
        $borders = array();
        foreach ($competencies as $index => $competency) {
            if ($index === 0) {
                $borders = $formats['border_2122'];
            } else if ($index === $competenciescount - 1) {
                $borders = $formats['border_2221'];
            } else {
                $borders = $formats['border_2121'];
            }

            $xlssheet->write_number(
                $firstrow + 3,
                $firstcol + $index + 1,
                $competency->idnumber,
                $workbook->add_format(array_merge($formats['zebra_even'], $borders, array('align' => 'centre')))
            );
        }

        // Student rows.
        $borders = array();
        $zebra = array();
        $row = $firstrow;
        foreach ($data->users as $indexuser => $user) {
            $row = $firstrow + $indexuser + 4;

            $borders = ($indexuser === $userscount - 1) ? $formats['border_0222'] : $formats['border_0202'];
            $zebra = ($indexuser % 2 === 0) ? $formats['zebra_even'] : $formats['zebra_odd'];

            $xlssheet->write_string($row, $firstcol, $user->fullname, $workbook->add_format(array_merge($borders, $zebra)));

            $format = null;
            $col = $firstcol;
            foreach ($user->competencies as $index => $competency) {
                $col = $firstcol + $index + 1;
                $format = array_merge($zebra, $formats['centre']);

                if ($indexuser === $userscount - 1) {
                    $format['bottom'] = 2;
                }

                if (isset($competency->grade)) {
                    if ($competency->proficiency === '1') {
                        $format = array_merge($format, $formats['course_result_passed']);
                    } else {
                        $format = array_merge($format, $formats['course_result_failed']);
                    }

                    $xlssheet->write_string($row, $col, $competency->gradename, $workbook->add_format($format));
                } else {
                    $xlssheet->write_blank($row, $col, $workbook->add_format($format));
                }
            }

            $courseresult = ($user->coursepassed === true) ? 'passed' : 'failed';
            $format = array_merge($formats['centre'], $borders, $formats['course_result_' . $courseresult]);

            $xlssheet->write_string($row,
                $col + 1,
                get_string('course_result_' . $courseresult, 'report_coursecompetencies'),
                $format
            );
            $xlssheet->write_number($row, $col + 2, $user->externalgrade, $format);
        }

        $xlssheetcompetencies = $workbook->add_worksheet(get_string('competencies', 'core_competency'));

        // Column widths.
        $xlssheetcompetencies->set_column(0, 0, $colwidths['left_margin']);
        $xlssheetcompetencies->set_column($firstcol, $firstcol, $colwidths['competency_number']);
        $xlssheetcompetencies->set_column($firstcol + 1, $firstcol + 1, $colwidths['competency_description']);

        // Header.
        $xlssheetcompetencies->write_string(
            $firstrow,
            $firstcol,
            $headerfirst,
            $workbook->add_format(array_merge($formats['centre_bold'], $formats['border_2202']))
        );
        $xlssheetcompetencies->merge_cells($firstrow, $firstcol, $firstrow, $firstcol + 1);
        $xlssheetcompetencies->write_blank($firstrow, $firstcol + 1, $workbook->add_format($formats['border_2202']));

        $xlssheetcompetencies->write_string(
            $firstrow + 1,
            $firstcol,
            $headersecond,
            $workbook->add_format(array_merge($formats['centre_bold'], array('left' => 2)))
        );
        $xlssheetcompetencies->merge_cells($firstrow + 1, $firstcol, $firstrow + 1, $firstcol + 1);
        $xlssheetcompetencies->write_blank($firstrow + 1, $firstcol + 1, $workbook->add_format(array('right' => 2)));

        $xlssheetcompetencies->write_string(
            $firstrow + 2,
            $firstcol,
            get_string('competencies', 'core_competency'),
            $workbook->add_format(
                array_merge($formats['centre_bold'], $formats['course_result_header'], array('border' => 2, 'size' => 14))
            )
        );
        $xlssheetcompetencies->merge_cells($firstrow + 2, $firstcol, $firstrow + 2, $firstcol + 1);
        $xlssheetcompetencies->write_blank($firstrow + 2, $firstcol + 1, $workbook->add_format(array('border' => 2)));

        // Competency rows.
        foreach ($competencies as $index => $competency) {
            $format = array();
            if ($index === $competenciescount - 1) {
                $format['bottom'] = 2;
            }

            $xlssheetcompetencies->write_number(
                $firstrow + $index + 3,
                $firstcol,
                $competency->idnumber,
                $workbook->add_format(array_merge($format, array('align' => 'right', 'align' => 'vcentre', 'left' => 2)))
            );
            $xlssheetcompetencies->write_string(
                $firstrow + $index + 3,
                $firstcol + 1,
                $competency->description,
                $workbook->add_format(array_merge($format, array('bold' => 1, 'text_wrap' => true, 'right' => 2)))
            );
        }

        $workbook->close();

        exit;
    }
}
