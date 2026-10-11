# Qualification tracker (`local_qualtracker`)

A Moodle local plugin that gives staff one page to keep track of students in
qualification and funded programs.

**Site administration › Reports › Qualification tracker**
(`/local/qualtracker/index.php`)

- **Overview:** every qualification / funded program, with how many students
  are enrolled, how many have completed it, and how many are still working on it.
- **Expand a program:** each enrolled student, with a status (Completed / In
  progress / Not started), overall progress, and a cell per unit course:
  - ✓ green: unit course completed (hover for the date)
  - % amber: in progress, as the share of tracked activities completed
  - 0% grey: enrolled but not started
  - hatched: not enrolled in that unit
- Search students by name or email, and filter by status, within each program.
- `index.php?program=<courseid>` opens with that program already expanded.

## How programs, students and units are worked out

| What | Rule |
|---|---|
| Program | A course flagged by one of the custom fields under **Program flag fields** |
| Students | Users enrolled in the program course who appear in completion reports (the student role), including suspended enrolments so finished students still count |
| Units | Other courses in the program course's category (default), its category tree, or courses whose **unit link field** names the program |
| Completed | The program course is marked complete, or every unit course is |

### Settings

**Site administration › Plugins › Local plugins › Qualification tracker settings**

- **Program flag fields**: one per line. `qualification` matches a ticked
  checkbox (or any non-empty value) in the course custom field with that
  shortname. `coursetype=Funded program` matches a specific value or menu option.
  The default is `qualification`, so change it to match the shortnames of your
  existing course custom fields.
- **How units are found**: same category / category and sub-categories / link field.
- **Unit link field**: for the link-field mode, the custom field on unit courses
  holding the qualification's course shortname, ID number or id (comma-separate
  several).

Unit progress needs completion tracking enabled on the unit courses, with
activity completion set on the activities and course completion criteria
configured.

## Install

Copy this folder to `<moodle>/local/qualtracker`, then visit
Site administration › Notifications. Requires Moodle 4.1 or later.

Access is controlled by `local/qualtracker:view` (managers by default).
