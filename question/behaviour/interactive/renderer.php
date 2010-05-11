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
 * Renderer for outputting parts of a question belonging to the interactive
 * behaviour.
 *
 * @package qbehaviour_interactive
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Interactive behaviour renderer.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_interactive_renderer extends qbehaviour_renderer {
    public function get_state_string(question_attempt $qa) {
        // TODO when this is move to the behaviour class, change to use is_try_again_state.
        if ($qa->get_state()->is_active()) {
            $laststep = $qa->get_last_step();
            if ($laststep->has_behaviour_var('submit') && $laststep->has_behaviour_var('_triesleft')) {
                return get_string('notcomplete', 'qbehaviour_interactive');
            } else {
                return get_string('triesremaining', 'qbehaviour_interactive', $qa->get_last_behaviour_var('_triesleft'));
            }
        } else {
            return $qa->get_state()->default_string();
        }
    }

    public function controls(question_attempt $qa, question_display_options $options) {
        return $this->submit_button($qa, $options);
    }

    public function feedback(question_attempt $qa, question_display_options $options) {
        if (!$qa->get_state()->is_active() || !$options->readonly) {
            return '';
        }

        $attributes = array(
            'type' => 'submit',
            'name' => $qa->get_im_field_name('tryagain'),
            'value' => get_string('tryagain', 'qbehaviour_interactive'),
            'class' => 'submit btn',
        );
        if ($options->readonly !== qbehaviour_interactive::READONLY_EXCEPT_TRY_AGAIN) {
            $attributes['disabled'] = 'disabled';
        }
        return html_writer::empty_tag('input', $attributes);
    }
}
