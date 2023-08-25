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
 * Mod pulse - List the available template and manage the template Create, Update, Delete actions, sort the order of templates.
 *
 * @package    mod_pulse
 * @copyright  2023 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Require config.
require(__DIR__.'/../../../../config.php');

// Require plugin libraries.
// require_once($CFG->dirroot. '/theme/boost_union/smartmenus/menulib.php');

// Require admin library.
require_once($CFG->libdir.'/adminlib.php');

// Get parameters.
// $courseid = required_param('course', PARAM_INT); // Course id.
$action = optional_param('action', null, PARAM_ALPHAEXT);

$courseid = optional_param('courseid', null, PARAM_INT);
$instanceid = optional_param('instanceid', null, PARAM_INT);

// Find the courseid.
if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
} else {
    $instanceid = required_param('instanceid', PARAM_INT);
    if ($instance = $DB->get_record('pulse_autoinstances', ['id' => $instanceid], '*', MUST_EXIST)) {
        $course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
        $courseid = $course->id;
    }
}

if (!($course = $DB->get_record('course', ['id' => $courseid]))) {
    throw new moodle_exception('coursenotfound', 'core_course');
}

// Page values.
$context = \context_course::instance($courseid);

// Prepare the page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/pulse/automation/instances/list.php', ['courseid' => $courseid]));
$PAGE->set_course($course);

// admin_externalpage_setup('pulseautomation');
// TODO: Capability checks.

// Process actions.
if ($action !== null && confirm_sesskey()) {
    // Every action is based on a template, thus the template ID param has to exist.
    $instanceid = required_param('instanceid', PARAM_INT);

    // Create template instance. Actions are performed in template instance.
    $instance = mod_pulse\automation\instances::create($instanceid);

    $transaction = $DB->start_delegated_transaction();

    // Perform the requested action.
    switch ($action) {
        // Triggered action is delete, then init the deletion of template.
        case 'delete':
            // Delete the template.
            if ($instance->delete_instance()) {
                // Notification to user for template deleted success.
                \core\notification::success(get_string('templatedeleted', 'pulse'));
            }
            break;
        case 'disable':
            // Disable the template visibility.
            $instance->update_status(false);
            break;

        case 'enable':
            // Disable the template visibility.
            $instance->update_status(true);
            break;
    }

    // Allow to update the changes to database.
    $transaction->allow_commit();

    // Redirect to the same page to view the templates list.
    redirect($PAGE->url);
}

$PAGE->add_body_class('mod-pulse-automation-table');

// Further prepare the page.
$PAGE->set_heading(format_string($course->fullname));

$PAGE->navbar->add(get_string('mycourses', 'core'), new moodle_url('/course/index.php'));
$PAGE->navbar->add(format_string($course->shortname), new moodle_url('/admin/category.php', array('category' => 'mod_pulse')));
$PAGE->navbar->add(get_string('autotemplates', 'pulse'), new moodle_url('/mod/pulse/automation/instances/list.php'));

// Build automation templates table.
$table = new mod_pulse\table\auto_instances($context->id);
$table->define_baseurl($PAGE->url);

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('autotemplates', 'pulse'));

// Show smart menus description.
echo get_string('autotemplates_desc', 'pulse');

// Prepare 'Create smart menu' button. // TODO Review.
$createbutton = $OUTPUT->box_start();
$createbutton .= mod_pulse\automation\helper::get_addtemplate_instance($courseid);
$createbutton .= $OUTPUT->box_end();

// If there aren't any smart menus yet.
$countmenus = $DB->count_records('pulse_autotemplates');
if ($countmenus < 1) {
    // Show the table, which, since it is empty, falls back to the
    // "There aren't any smart menus created yet. Please create your first smart menu to get things going." notice.
    $table->out(0, true);

    // And then show the button.
    echo $createbutton;

    // Otherwise.
} else {
    // Show the button.
    echo $createbutton;

    // And then show the table.
    $table->out(10, true);
}

// Finish page output.
echo $OUTPUT->footer();
