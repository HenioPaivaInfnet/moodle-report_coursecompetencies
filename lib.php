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
 * API pública do relatório de competências por estudante.
 *
 * Define as APIs utilizadas pelo relatório.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Extende o menu de navegação lateral do curso com um item para o relatório.
 *
 * @param navigation_node $navigation O nódulo de navegação do menu onde o item
 * será incluído.
 * @param stdClass $course Objeto do curso referente ao relatório.
 * @param stdClass $context Objeto de contexto do curso.
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
