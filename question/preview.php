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
 * This page displays a preview of a question
 *
 * The preview uses the option settings from the activity within which the question
 * is previewed or the default settings if no activity is specified. The question session
 * information is stored in the session as an array of subsequent states rather
 * than in the database.
 *
 * @package core
 * @subpackage questionbank
 * @copyright Alex Smith {@link http://maths.york.ac.uk/serving_maths} and
 *      numerous contributors.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/previewlib.php');
require_js('yui_dom-event');
require_js($CFG->httpswwwroot . '/question/preview.js');

// Get and validate question id.
$id = required_param('id', PARAM_INT); // Question id
$question = question_engine::load_question($id);
require_login();
question_require_capability_on($question, 'use');
if (!$category = get_record("question_categories", "id", $question->category)) {
    print_error('unknownquestioncategory', 'question', $question->category);
}

// Get and validate display options.
$displayoptions = new question_display_options();
$displayoptions->set_review_options($CFG->quiz_review); // Quiz-specific, but a sensible source of defaults.
$displayoptions->markdp = optional_param('markdp', $CFG->quiz_decimalpoints, PARAM_INT);
$displayoptions->feedback = optional_param('feedback', question_display_options::VISIBLE, PARAM_INT);
$displayoptions->generalfeedback = optional_param('generalfeedback', question_display_options::VISIBLE, PARAM_INT);
$displayoptions->correctresponse = optional_param('correctresponse', question_display_options::VISIBLE, PARAM_INT);
$displayoptions->marks = optional_param('marks', question_display_options::VISIBLE, PARAM_INT);
$displayoptions->history = optional_param('history', question_display_options::HIDDEN, PARAM_INT);
$displayoptions->flags = question_display_options::HIDDEN;
$displayoptions->manualcomment = question_display_options::VISIBLE;


// Get and validate exitsing preview, or start a new one.
$previewid = optional_param('previewid', 0, PARAM_ALPHANUM);
if ($previewid) {
    if (!isset($SESSION->question_previews[$previewid])) {
        print_error('notyourpreview', 'question');
    }
    $quba = question_engine::load_questions_usage_by_activity($previewid);
    $qnumber = $quba->get_first_question_number();
    $usedquestion = $quba->get_question($qnumber);
    if ($usedquestion->id != $question->id) {
        print_error('questionidmismatch', 'question');
    }
    $question = $usedquestion;

} else {
    $model = optional_param('model', 'deferredfeedback', PARAM_FORMAT);
    $maxmark = optional_param('maxmark', $question->defaultmark, PARAM_NUMBER);

    $quba = question_engine::make_questions_usage_by_activity('core_question_preview',
            get_context_instance_by_id($category->contextid));
    $quba->set_preferred_interaction_model($model);
    $qnumber = $quba->add_question($question, $maxmark);
    $quba->start_all_questions();
    question_engine::save_questions_usage_by_activity($quba);

    $SESSION->question_previews[$quba->get_id()] = true;
}

// Prepare a URL that is used in various places.
$actionurl = $CFG->wwwroot . '/question/preview.php?id=' . $question->id . '&amp;previewid=' . $quba->get_id();

// Create the settings form, and initialise the fields.
$optionsform = new preview_options_form($actionurl);
$currentoptions = clone($displayoptions);
$currentoptions->model = $quba->get_preferred_interaction_model();
$currentoptions->maxmark = $quba->get_question_max_mark($qnumber);
$optionsform->set_data($currentoptions);

// Process change of settings, if that was requested.
if ($newoptions = $optionsform->get_submitted_data()) {
    restart_preview($previewid, $question->id, $newoptions->model,
            $newoptions->maxmark, $newoptions);
}

// Process any actions from the buttons at the bottom of the form.
if (data_submitted() && confirm_sesskey()) {
    if (optional_param('restart', false, PARAM_BOOL)) {
        restart_preview($previewid, $question->id, $quba->get_preferred_interaction_model(),
                $quba->get_question_max_mark($qnumber), $displayoptions);

    } else if (optional_param('fill', null, PARAM_BOOL)) {
        $correctresponse = $quba->get_correct_response($qnumber);
        $quba->process_action($qnumber, $correctresponse);
        question_engine::save_questions_usage_by_activity($quba);
        redirect($actionurl);

    } else if (optional_param('finish', null, PARAM_BOOL)) {
        $quba->process_all_actions();
        $quba->finish_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        redirect($actionurl);

    } else {
        $quba->process_all_actions();
        question_engine::save_questions_usage_by_activity($quba);
        redirect($actionurl);
    }
}

if ($question->length) {
    $displaynumber = '1';
} else {
    $displaynumber = 'i';
}
$restartdisabled = '';
$finishdisabled = '';
$filldisabled = '';
if (question_state::is_finished($quba->get_question_state($qnumber))) {
    $finishdisabled = ' disabled="disabled"';
    $filldisabled = ' disabled="disabled"';
}
if (!$previewid) {
    $restartdisabled = ' disabled="disabled"';
}
// Output
$title = get_string('previewquestion', 'question', format_string($question->name));
$headtags = implode("\n", $quba->render_question_head_html($qnumber));
print_header($title, '', '', '', $headtags);
print_heading($title);

// Start the question form.
echo '<form method="post" action="' . $actionurl .
        '" enctype="multipart/form-data" id="responseform">', "\n";
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />', "\n";
echo '<input type="hidden" name="qnumbers" value="' . $qnumber . '" />', "\n";

// Output the question.
echo $quba->render_question($qnumber, $displayoptions, $displaynumber);

echo '<p class="notifytiny">' . get_string('interactionmodelbeingused', 'question',
        question_engine::get_interaction_model_name($quba->get_question_attempt($qnumber)->
        get_interaction_model_name())) . '</p>';
// Finish the question form.
echo '<div id="previewcontrols" class="controls">';
echo '<input type="submit" name="restart"' . $restartdisabled .
        ' value="' . get_string('restart', 'question') . '" />', "\n";
echo '<input type="submit" name="fill"' . $filldisabled .
        ' value="' . get_string('fillincorrect', 'question') . '" />', "\n";
echo '<input type="submit" name="finish"' . $finishdisabled .
        ' value="' . get_string('submitandfinish', 'question') . '" />', "\n";
echo '</div>';
echo '<script type="text/javascript">question_preview_close_button("' .
        get_string('closepreview', 'question') . '", "previewcontrols");</script>', "\n";
echo '</form>';

// Display the settings form.
$optionsform->display();

// Finish output.
use_html_editor();
print_footer('empty');

