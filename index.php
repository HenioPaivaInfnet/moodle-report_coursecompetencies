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
 * Página do relatório de competências do curso por estudante.
 *
 * Exibe uma tabela com a lista de estudantes do curso e conceitos de cada
 * competência. Permite alterar os resultados individualmente, incluindo
 * evidência no histórico da competência.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$exportxls = optional_param('export_xls', false, PARAM_BOOL);

$params = array('id' => $id);
$course = $DB->get_record('course', $params, '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);

$url = new moodle_url('/report/coursecompetencies/index.php', $params);
$PAGE->set_url($url);

$page = new report_coursecompetencies_report($course);
$coursename = format_string($course->fullname, true, array('context' => $context));

if ($exportxls !== 1) {
    $title = get_string('pluginname', 'report_coursecompetencies');

    $PAGE->set_title($title);
    $PAGE->set_heading($coursename);
    $PAGE->set_pagelayout('incourse');
}

// The get_renderer function must be called after above functions for the layout to render properly.
$output = $PAGE->get_renderer('report_coursecompetencies');

if ($exportxls !== 1) {
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
    $data->categorypath = $PAGE->category->path;
    $data->coursename = $coursename;

    $export = $page->export_xls();
}
