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
 * Essay question definition class.
 *
 * @package qtype_essay
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Represents an essay question.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_essay_question extends question_with_responses {
    public function make_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class('manualgraded');
        return new qim_manualgraded($qa);
    }

    public function get_expected_data() {
        return array('answer' => PARAM_CLEANHTML);
    }

    public function is_complete_response(array $response) {
        return !empty($response['answer']);
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return (empty($prevresponse['answer']) && empty($newresponse['answer'])) ||
                (!empty($prevresponse['answer']) && !empty($newresponse['answer']) &&
                $prevresponse['answer'] == $newresponse['answer']);
    }
}
