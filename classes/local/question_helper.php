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

namespace local_mcpconnector\local;

use core_question\local\bank\question_version_status;
use mod_quiz\quiz_settings;

/**
 * Creates questions in a quiz's own question bank and adds them to the quiz.
 *
 * Follows what the quiz's "Add a new question" flow does: the question is saved by its question
 * type into the quiz's default category, then added at the end of the quiz.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_helper {
    /**
     * Requires the capabilities the quiz editing page and question editor require, and that the quiz can still be edited.
     *
     * @param \cm_info $cm the quiz's course module
     * @return quiz_settings
     * @throws \moodle_exception if the quiz has attempts and its questions can no longer change
     */
    public static function get_editable_quiz(\cm_info $cm): quiz_settings {
        require_capability('mod/quiz:manage', $cm->context);
        require_capability('moodle/question:add', $cm->context);

        $quizobj = quiz_settings::create_for_cmid($cm->id);
        $quizobj->get_structure()->check_can_be_edited();
        return $quizobj;
    }

    /**
     * Returns the form data every question type shares.
     *
     * @param string $name question name
     * @param string $questiontext question text (HTML)
     * @param float $defaultmark
     * @param float $penalty penalty for each incorrect try, as the question type's form sets it
     * @return \stdClass
     * @throws invalid_parameter_exception
     */
    public static function base_form(string $name, string $questiontext, float $defaultmark, float $penalty): \stdClass {
        course_helper::require_not_empty('name', $name);
        course_helper::require_not_empty('questiontext', $questiontext);
        if ($defaultmark < 0) {
            throw new invalid_parameter_exception('defaultmark must not be negative');
        }

        return (object) [
            'name' => $name,
            'questiontext' => self::editor($questiontext),
            'generalfeedback' => self::editor(''),
            'defaultmark' => $defaultmark,
            'penalty' => $penalty,
            'idnumber' => '',
            'status' => question_version_status::QUESTION_STATUS_READY,
        ];
    }

    /**
     * Returns an editor field value as the question forms submit it.
     *
     * @param string $text HTML
     * @return array
     */
    public static function editor(string $text): array {
        return ['text' => $text, 'format' => FORMAT_HTML];
    }

    /**
     * Adds the standard combined feedback fields, with the defaults the question forms show.
     *
     * @param \stdClass $form
     */
    public static function add_combined_feedback(\stdClass $form): void {
        $form->correctfeedback = self::editor(get_string('correctfeedbackdefault', 'question'));
        $form->partiallycorrectfeedback = self::editor(get_string('partiallycorrectfeedbackdefault', 'question'));
        $form->incorrectfeedback = self::editor(get_string('incorrectfeedbackdefault', 'question'));
        $form->shownumcorrect = 1;
    }

    /**
     * Matches a fraction to one of the grade values the question forms offer.
     *
     * The forms only allow these values (100%, 90%, 83.33333%…); a value within 0.005 of one is
     * taken as that value, so 0.33 means 33.33333%.
     *
     * @param float $fraction
     * @param string $field parameter name, for the error message
     * @return float
     * @throws invalid_parameter_exception
     */
    public static function grade_fraction(float $fraction, string $field): float {
        foreach (array_keys(\question_bank::fraction_options_full()) as $option) {
            if (abs($fraction - (float) $option) < 0.005) {
                return (float) $option;
            }
        }
        throw new invalid_parameter_exception(
            "{$field} {$fraction} is not a Moodle grade value (for example 1.0, 0.5, 0.3333333, 0.25, 0 or -0.5)"
        );
    }

    /**
     * Saves a new question in the quiz's default category and adds it at the end of the quiz.
     *
     * @param quiz_settings $quizobj
     * @param string $qtype question type name, e.g. 'multichoice'
     * @param \stdClass $form question form data
     * @return array questionid and slot
     */
    public static function add_to_quiz(quiz_settings $quizobj, string $qtype, \stdClass $form): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $context = $quizobj->get_context();
        $quiz = $quizobj->get_quiz();

        $transaction = $DB->start_delegated_transaction();

        $category = question_get_default_category($context->id, true);
        $form->category = $category->id . ',' . $category->contextid;
        $question = (object) ['qtype' => $qtype, 'category' => $category->id];
        $question = \question_bank::get_qtype($qtype)->save_question($question, $form);

        quiz_require_question_use($question->id);
        quiz_add_quiz_question($question->id, $quiz);
        quiz_delete_previews($quiz);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        $slot = $DB->get_field_sql(
            "SELECT s.slot
               FROM {quiz_slots} s
               JOIN {question_references} qr ON qr.itemid = s.id
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
               JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
              WHERE s.quizid = ? AND qv.questionid = ?",
            [$quiz->id, $question->id],
            MUST_EXIST
        );

        $transaction->allow_commit();

        return [
            'questionid' => (int) $question->id,
            'slot' => (int) $slot,
        ];
    }

    /**
     * Describes the return value shared by the add_*_question functions.
     *
     * @return \core_external\external_single_structure
     */
    public static function returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'questionid' => new \core_external\external_value(PARAM_INT, 'Id of the new question'),
            'slot' => new \core_external\external_value(PARAM_INT, 'Slot number of the question in the quiz'),
        ]);
    }
}
