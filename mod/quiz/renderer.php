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
 * Back-end code for handling data about quizzes and the current user's attempt.
 *
 * There are classes for loading all the information about a quiz and attempts,
 * and for displaying the navigation panel.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  2008 onwards Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_renderer extends plugin_renderer_base {
    /**
     * Displays the header of the page and relevent header information.
     *
     * @param object $attemptobj an object containing an instance of the attempt
     * @param object $accessmanager an object containing an instance of the quiz_access_manager
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     */
    public function review_header($attemptobj, $accessmanager, $page, $showall) {
        global $PAGE, $OUTPUT;
        // Work out appropriate title and whether blocks should be shown
        if ($attemptobj->is_preview_user() && $attemptobj->is_own_attempt()) {
            $strreviewtitle = get_string('reviewofpreview', 'quiz');
            navigation_node::override_active_url($attemptobj->start_attempt_url());
        } else {
            $strreviewtitle = get_string('reviewofattempt', 'quiz',
                $attemptobj->get_attempt_number());
            if (empty($attemptobj->get_quiz()->showblocks) && !$attemptobj->is_preview_user()) {
                $PAGE->blocks->show_only_fake_blocks();
            }
        }

        //Display the quiz navigation block
        $this->review_navigation($attemptobj, $page, $showall);

        // Print the page header
        $headtags = $attemptobj->get_html_head_contributions($page, $showall);
        if ($accessmanager->securewindow_required($attemptobj->is_preview_user())) {
            $accessmanager->setup_secure_page($attemptobj->get_course()->shortname.': '.
                format_string($attemptobj->get_quiz_name()), $headtags);
        } else if ($accessmanager->safebrowser_required($attemptobj->is_preview_user())) {
            $PAGE->set_title($attemptobj->get_course()->shortname . ': '.
                format_string($attemptobj->get_quiz_name()));
            $PAGE->set_heading($attemptobj->get_course()->fullname);
            $PAGE->set_cacheable(false);
            echo $OUTPUT->header();
        } else {
            $PAGE->navbar->add($strreviewtitle);
            $PAGE->set_title(format_string($attemptobj->get_quiz_name()));
            $PAGE->set_heading($attemptobj->get_course()->fullname);
            echo $OUTPUT->header();
        }
    }

    /**
     * Displays the quiz navigation block.
     *
     * @param object $attemptobj an object containing an instance of the attempt
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     */
    public function review_navigation($attemptobj, $page, $showall) {
        global $PAGE;
        //Arrange for the navigation to be displayed.
        $navbc = $attemptobj->get_navigation_panel('quiz_review_nav_panel', $page, $showall);
        $firstregion = reset($PAGE->blocks->get_regions());
        $PAGE->blocks->add_fake_block($navbc, $firstregion);
    }

    /**
     * Displays the data about this attempt.
     *
     * @param object $attemptobj an object containing an instance of the attempt
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     * @param object $attempt an object containing the current attempt.
     * @param int $timetaken a timestamp of the time taken.
     * @param int $overtime a timestamp of the overdue time.
     * @param object $options an object containing the render options for that user on that page.
     * @param object $quiz an object containing the current quiz.
     */
    public function review_table($attemptobj, $page, $showall, $attempt, $timetaken, $overtime,
        $options, $quiz) {
        global $CFG, $USER, $DB, $OUTPUT;
        // Print summary table about the whole attempt.
        // First we assemble all the rows that are appopriate to the current situation in
        // an array, then later we only output the table if there are any rows to show.
        $rows = array();
        if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {
            // If showuserpicture is true, the picture is shown elsewhere, so don't repeat it.
            $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
            $rows[] = $this->review_user_picture($attemptobj, $student);
        }
        if ($attemptobj->has_capability('mod/quiz:viewreports')) {
            $this->review_table_attemptlist($attemptobj, $page, $showall);
        }

        if ($page == 0) {
            // Timing information.
            $rows[] = $this->review_table_time($attempt);
            if ($attempt->timefinish) {
                $rows[] = $this->review_table_finished($attempt, $timetaken);
            }
            if (!empty($overtime)) {
                $rows[] = $this->review_table_overtime($overtime);
            }

            // Show marks (if the user is allowed to see marks at the moment).
            $grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
            if ($options->marks >= question_display_options::MARK_AND_MAX &&
                quiz_has_grades($quiz)) {

                if (!$attempt->timefinish) {
                    $rows[] = $this->review_table_inprogress();

                } else if (is_null($grade)) {
                    $rows[] = $this->review_table_nograde($quiz, $grade);

                } else {
                    // Show raw marks only if they are different from the grade
                    // (like on the view page).
                    if ($quiz->grade != $quiz->sumgrades) {
                        $a = new stdClass();
                        $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
                        $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
                        $rows[] = $this->review_table_raw_grade($a);
                    }

                    // Now the scaled grade.
                    $a = new stdClass();
                    $a->grade = '<b>' . quiz_format_grade($quiz, $grade) . '</b>';
                    $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                    if ($quiz->grade != 100) {
                        $a->percent = '<b>'.round($attempt->sumgrades * 100 / $quiz->sumgrades, 0).
                            '</b>';
                        $formattedgrade = get_string('outofpercent', 'quiz', $a);
                    } else {
                        $formattedgrade = get_string('outof', 'quiz', $a);
                    }
                    $rows[] = $this->review_table_scaled_grade($formattedgrade);
                }
            }

            // Feedback if there is any, and the user is allowed to see it now.
            $feedback = $attemptobj->get_overall_feedback($grade);
            if ($options->overallfeedback && $feedback) {
                $rows[] = $this->review_table_feedback($feedback);
            }
        }

        // Now output the summary table, if there are any rows to be shown.
        if (!empty($rows)) {
            $table = $this->review_display_table($rows);
        }
        // Summary table end ======================================================================
        return $table;
    }

    /**
     * Returns the row with the users picture.
     *
     * @param object $attemptobj an object containing an instance of the attempt.
     * @param object $student an object contining the student information.
     * go to.
     */
    public function review_user_picture($attemptobj, $student) {
        $picture = $OUTPUT->user_picture($student, array('courseid'=>$attemptobj->get_courseid()));
        $row ='<tr><th scope="row" class="cell">'.$picture.'</th><td class="cell"><a href="'.
            $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course='.
            $attemptobj->get_courseid().'">'.fullname($student, true).'</a></td></tr>';
        return $row;
    }

    /**
     * Returns the row containing the attempt list.
     *
     * @param object $attemptobj an object containing an instance of the attempt
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     */
    public function review_table_attemptlist($attemptobj, $page, $showall) {
        $attemptlist = $attemptobj->links_to_other_attempts($attemptobj->review_url(0, $page,
            $showall));
        if ($attemptlist) {
            $rows[] = '<tr><th scope="row" class="cell">'.get_string('attempts', 'quiz').
                '</th><td class="cell">'.$attemptlist.'</td></tr>';
        }
    }

    /**
     * Retuns the row containg the start time.
     *
     * @param object $attempt an object containing the current attempt.
     */
    public function review_table_time($attempt) {
        $row = '<tr><th scope="row" class="cell">'.get_string('startedon', 'quiz').
            '</th><td class="cell">'.userdate($attempt->timestart).'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg the finish time and time taken.
     *
     * @param object $attempt an object containing the current attempt.
     * @param int $timetaken time stamp of the time taken.
     */
    public function review_table_finished($attempt, $timetaken) {
        $row = '<tr><th scope="row" class="cell">'.get_string('completedon', 'quiz').
            '</th><td class="cell">'.userdate($attempt->timefinish).'</td></tr>';
        $row .= '<tr><th scope="row" class="cell">'.get_string('timetaken', 'quiz').
            '</th><td class="cell">'.$timetaken.'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg the over time.
     *
     * @param int $overtime time stamp of the over time.
     */
    public function review_table_overtime($overtime) {
        $row = '<tr><th scope="row" class="cell">'.get_string('overdue', 'quiz').
            '</th><td class="cell">'.$overtime.'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg still in progress.
     */
    public function review_table_inprogress() {
        $row = '<tr><th scope="row" class="cell">'.get_string('grade').'</th><td class="cell">'.
            get_string('attemptstillinprogress', 'quiz').'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg the not graded.
     *
     * @param object $quiz an object containing the current quiz.
     * @param float|string $grade contains the grade.
     */
    public function review_table_nograde($quiz, $grade) {
        $row = '<tr><th scope="row" class="cell">'.get_string('grade').'</th><td class="cell">'.
            quiz_format_grade($quiz, $grade).'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg the raw grade.
     *
     * @param object $a an object containing the raw grade.
     */
    public function review_table_raw_grade($a) {
        $row = '<tr><th scope="row" class="cell">'.get_string('marks', 'quiz').
            '</th><td class="cell">'.get_string('outofshort', 'quiz', $a).'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg the scaled grade.
     *
     * @param string $formattedgrade contains the grade.
     */
    public function review_table_scaled_grade($formattedgrade) {
        $row = '<tr><th scope="row" class="cell">'.get_string('grade').'</th><td class="cell">'.
            $formattedgrade.'</td></tr>';
        return $row;
    }

    /**
     * Retuns the row containg any feedback.
     *
     * @param object $feedback contains any feed back the student is allowed to see.
     */
    public function review_table_feedback($feedback) {
        $row = '<tr><th scope="row" class="cell">'.get_string('feedback', 'quiz').
            '</th><td class="cell">'.$feedback.'</td></tr>';
        return $row;
    }

    /**
     * Display the table containg all the rows of data.
     *
     * @param array $rows an array containing all teh rows for the table.
     */
    public function review_display_table($rows) {
        $table = '<table class="generaltable generalbox quizreviewsummary"><tbody>'. "\n";
        $table .= implode("\n", $rows);
        $table .= "\n</tbody></table>\n";
        echo $table;
    }

    /**
     * Opens & displays the review form. 
     *
     * @param object $attemptobj an object containing an instance of the attempt
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     * @param object $options an object containing the render options for that user on that page.
     */
    public function review_form($attemptobj, $page, $showall, $options) {
        // Form for saving flags if necessary.
        if ($options->flags == question_display_options::EDITABLE) {
            echo '<form action="' . $attemptobj->review_url(0, $page, $showall) .
                    '" method="post" class="questionflagsaveform"><div>';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        }
    }

    /**
     * Opens & displays the review form. 
     *
     * @param object $attemptobj an object containing an instance of the attempt.
     * @param string $thispage a string containing data about this page.
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param int $showall 0 gets all pages, 1 gets the current page.
     */
    public function review_questions($attemptobj, $thispage, $page, $showall) {
        foreach ($attemptobj->get_slots($thispage) as $slot) {
            echo $attemptobj->render_question($slot, true, $attemptobj->review_url($slot, $page,
                $showall));
        }
    }

    /**
     * Closes & displays the review form. 
     *
     * @param object $options an object containing the render options for that user on that page.
     */
    public function review_close_form($options) {
        global $PAGE;
        // Close form if we opened it.
        if ($options->flags == question_display_options::EDITABLE) {
            echo '<div class="submitbtns">'."\n".
                '<input type="submit" class="questionflagsavebutton" name="savingflags" value="'.
                get_string('saveflags', 'question').'" />'."</div>\n"."\n</div></form>\n";
            $PAGE->requires->js_init_call('M.mod_quiz.init_review_form', null, false,
                quiz_get_js_module());
        }
    }

    /**
     * Displays the submit links 
     *
     * @param object $attemptobj an object containing an instance of the attempt.
     * @param object $accessmanager an object containing an instance of the quiz_access_manager
     * @param int $page -1 to look up the page number from the slot, otherwise the page number to
     * go to.
     * @param string $lastpage a string containing data about the last page.
     */
    public function review_submit($attemptobj, $accessmanager, $page, $lastpage) {
        // Print a link to the next page.
        echo '<div class="submitbtns">';
        if ($lastpage) {
            $accessmanager->print_finish_review_link($attemptobj->is_preview_user());
        } else {
            echo link_arrow_right(get_string('next'), $attemptobj->review_url(0, $page + 1));
        }
        echo '</div>';
    }

    /**
     * Displays the footer
     */
    public function footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }
}
