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

// global $DB;

$id = required_param('id', PARAM_INT);

$params = array('id' => $id);
$course = $DB->get_record('course', $params, '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);

$url = new moodle_url('/report/coursecompetencies/index.php', $params);

$title = get_string('pluginname', 'report_coursecompetencies');
$coursename = format_string($course->fullname, true, array('context' => $context));

$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($coursename);
$PAGE->set_pagelayout('incourse');

$output = $PAGE->get_renderer('report_coursecompetencies');

echo $output->header();
echo $output->heading($title, 3);

$page = new report_coursecompetencies_report($course);
echo $output->render_report($page);

/*
$consulta = $DB->get_records_sql('
	select
		cm.id,
		cm.course,
		compfwk.id frameworkid,
		modalidade.name modalidade,
		escola.name escola,
		programa.name programa,
		classe.name classe,
		bloco.name bloco,
		disciplina.fullname disciplina,
		asg.name avaliacao,
		scale.name escala,
		COUNT(distinct crscomp.id) competencias_curso,
		COUNT(distinct cmcomp.id) competencias_modulo,
		COUNT(distinct comps_fwk.id) competencias_fwk,
		COUNT(distinct comptpl.templateid) templates
	from mdl_course_modules cm
		join mdl_course disciplina on disciplina.id = cm.course
		join mdl_course_categories bloco on bloco.id = disciplina.category
		join mdl_course_categories classe on classe.id = bloco.parent
		join mdl_course_categories programa on programa.id = classe.parent
		join mdl_course_categories escola on escola.id = programa.parent
		join mdl_course_categories modalidade on modalidade.id = escola.parent
		join mdl_modules m on m.id = cm.module
		join mdl_assign asg on asg.id = cm.instance
		left join mdl_competency_coursecomp crscomp on crscomp.courseid = cm.course
		left join mdl_competency comp on comp.id = crscomp.competencyid
		left join mdl_competency_framework compfwk on compfwk.id = comp.competencyframeworkid
		left join mdl_competency comps_fwk on comps_fwk.competencyframeworkid = compfwk.id
		left join mdl_scale scale on scale.id = compfwk.scaleid
		left join mdl_competency_modulecomp cmcomp on cmcomp.cmid = cm.id
			and cmcomp.competencyid = comp.id
		left join mdl_competency_templatecomp comptpl on comptpl.competencyid = comp.id
	where m.name = "assign"
		and cm.id in (' .
	implode(',', $avaliacoes) .
		')
	group by cm.id
	order by modalidade,
		escola,
		programa,
		classe,
		bloco,
		disciplina,
		avaliacao
');

echo html_writer::tag('h3', get_string('consistencycheck_competencieswithoutenoughrubrics', 'local_autocompetencygrade'));

$table = new html_table();
$table->head = array(
	'#',
	get_string('pluginname', 'mod_assign'),
	get_string('pluginname', 'report_competency'),
	get_string('consistencycheck_numrubrics', 'local_autocompetencygrade')
);
$table->data = $competencias_poucas_rubricas;

if (!empty($table->data)) {
	echo html_writer::table($table);
} else {
	echo html_writer::tag('p', get_string('consistencycheck_noresult', 'local_autocompetencygrade'), array('class' => 'alert alert-success'));
}
//*/

echo $output->footer();
