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
 * Arquivo contendo a classe de renderização do relatório.
 *
 * Contém a classe que realiza a renderização do relatório.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Classe de renderização do relatório.
 *
 * Obtém dados do relatório e envia para o template mustache.
 *
 * @package    report_coursecompetencies
 * @copyright  2017 Instituto Infnet {@link http://infnet.edu.br}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_coursecompetencies_renderer extends plugin_renderer_base {

    /**
     * Obtém dados do relatório e envia para o template mustache.
     *
     * @param report $page Página do relatório, com dados para exibição.
     * @return string Código HTML para exibição do relatório.
     */
    public function render_report(report_coursecompetencies_report $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('report_coursecompetencies/report', $data);
    }
}
