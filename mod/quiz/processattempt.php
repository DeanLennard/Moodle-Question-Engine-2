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
 * This page deals with processing responses during an attempt at a quiz.
 *
 * People will normally arrive here from a form submission on attempt.php or
 * summary.php, and once the responses are processed, they will be redirected to
 * attempt.php or summary.php.
 *
 * This code used to be near the top of attempt.php, if you are looking for CVS history.
 *
 * @package mod_quiz
 * @copyright 2009 Tim Hunt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/// Remember the current time as the time any responses were submitted
/// (so as to make sure students don't get penalized for slow processing on this page)
$timenow = time();

/// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$nextpage = optional_param('nextpage', 0, PARAM_INT);
$submittedquestionids = required_param('questionids', PARAM_SEQUENCE);
$finishattempt = optional_param('finishattempt', 0, PARAM_BOOL);
$timeup = optional_param('timeup', 0, PARAM_BOOL); // True if form was submitted by timer.

$attemptobj = new quiz_attempt($attemptid);

/// Because IE is buggy (see http://www.peterbe.com/plog/button-tag-in-IE) we cannot
/// do the quiz navigation buttons as <button type="submit" name="page" value="N">Caption</button>.
/// Instead we have to do them as <input type="submit" name="gotopageN" value="Caption"/> -
/// at lest that seemed like the least horrible work-around to me. Therefore, we need to
/// intercept gotopageN parameters here, and adjust $pgae accordingly.
if (optional_param('gotosummary', false, PARAM_BOOL)) {
    $nextpage = -1;
} else {
    $numpagesinquiz = $attemptobj->get_num_pages();
    for ($i = 0; $i < $numpagesinquiz; $i++) {
        if (optional_param('gotopage' . $i, false, PARAM_BOOL)) {
            $nextpage = $i;
            break;
        }
    }
}

/// Set $nexturl now. It will be updated if a particular question was sumbitted in
/// adaptive mode.
if ($nextpage == -1) {
    $nexturl = $attemptobj->summary_url();
} else {
    $nexturl = $attemptobj->attempt_url(0, $nextpage);
}

/// We treat automatically closed attempts just like normally closed attempts
if ($timeup) {
    $finishattempt = 1;
}

/// Check login.
require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
confirm_sesskey();

/// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    quiz_error($attemptobj->get_quizobj(), 'notyourattempt');
}

/// Check capabilites.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}

/// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    quiz_error($attemptobj->get_quizobj(), 'attemptalreadyclosed');
}

/// Don't log - we will end with a redirect to a page that is logged.

/// Load those questions we need, and just the submitted states for now.
// $attemptobj->load_questions($questionids);
//if (!empty($submittedquestionids)) {
//    $attemptobj->load_question_states($submittedquestionids);
//}

/// Process the responses //////////////////////////////////////////////////////
if (!$finishattempt) {
    $attemptobj->process_all_actions($timenow);
    redirect($nexturl);
}

/// Finish the attempt (if we get this far) ////////////////////////////////////

/// Log the end of this attempt.
add_to_log($attemptobj->get_courseid(), 'quiz', 'close attempt',
        'review.php?attempt=' . $attemptobj->get_attemptid(),
        $attemptobj->get_quizid(), $attemptobj->get_cmid());

/// Update the quiz attempt record.
$attemptobj->finish_attempt($timenow);

/// Clear the password check flag in the session.
$accessmanager = $attemptobj->get_access_manager($timenow);
$accessmanager->clear_password_access();

/// Send the user to the review page.
redirect($attemptobj->review_url());
