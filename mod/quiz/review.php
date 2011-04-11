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
 * This page prints a review of a particular quiz attempt
 *
 * It is used either by the student whose attempts this is, after the attempt,
 * or by a teacher reviewing another's attempt during or afterwards.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$showall = optional_param('showall', 0, PARAM_BOOL);

$url = new moodle_url('/mod/quiz/review.php', array('attempt'=>$attemptid));
if ($page !== 0) {
    $url->param('page', $page);
}
if ($showall !== 0) {
    $url->param('showall', $showall);
}
$PAGE->set_url($url);

$attemptobj = quiz_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
$renderer = $PAGE->get_renderer('mod_quiz');

// Create an object to manage all the other (non-roles) access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$options = $attemptobj->get_display_options(true);

$attemptobj->check_permissions($page, $options, $accessmanager);

// Load the questions and states needed by this page.
$questionids = $attemptobj->get_questionslots($page, $showall);

// Save the flag states, if they are being changed.
if ($options->flags == question_display_options::EDITABLE && optional_param('savingflags', false,
    PARAM_BOOL)) {
    require_sesskey();
    $attemptobj->save_question_flags();
    redirect($attemptobj->review_url(0, $page, $showall));
}

// Log this review.
add_to_log($attemptobj->get_courseid(), 'quiz', 'review', 'review.php?attempt=' .
        $attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());

$renderer->review_header($attemptobj, $accessmanager, $page, $showall);

// Summary table start ============================================================================

// Work out some time-related things.
$attempt = $attemptobj->get_attempt();
$quiz = $attemptobj->get_quiz();

$timetaken = $attemptobj->get_timetaken($attempt, $quiz);
$overtime = $attemptobj->get_overtime($timetaken, $attempt, $quiz);

// Print summary table about the whole attempt.
// First we assemble all the rows that are appopriate to the current situation in
// an array, then later we only output the table if there are any rows to show.
$renderer->review_table($attemptobj, $page, $showall, $attempt, $timetaken, $overtime, $options,
    $quiz);

// Form for saving flags if necessary.
$renderer->review_form($attemptobj, $page, $showall, $options);

// Print all the questions.
$thispage = $attemptobj->get_thispage($page, $showall);
$lastpage = $attemptobj->get_lastpage($page, $showall);
$renderer->review_questions($attemptobj, $thispage, $page, $showall);

$renderer->review_close_form($options);

// Print a link to the next page.
$renderer->review_submit($attemptobj, $accessmanager, $page, $showall);
$renderer->footer();
