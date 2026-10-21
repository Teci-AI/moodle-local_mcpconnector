# Changelog

## Unreleased

- `local_mcpconnector_get_info`: reports the installed plugin version and release, for client compatibility checks.
- `local_mcpconnector_create_page`: creates a Page activity.
- `local_mcpconnector_update_page`: replaces a Page's content and, optionally, its description.
- `local_mcpconnector_create_book`: creates a Book activity with its first chapter.
- `local_mcpconnector_create_label`: creates a Text and media area (label).
- `local_mcpconnector_create_url`: creates a URL resource.
- `local_mcpconnector_create_section`: creates a course section.
- `local_mcpconnector_delete_section`: deletes a course section and its activities.
- `local_mcpconnector_update_activity`: renames and/or shows or hides an activity.
- `local_mcpconnector_move_activity`: moves an activity to the end of another section.
- `local_mcpconnector_delete_activity`: deletes an activity.
- `local_mcpconnector_create_quiz`: creates an empty Quiz activity with the site's default quiz settings.
- `local_mcpconnector_add_quiz_question`: adds a new multiple-choice question to a quiz.
- `local_mcpconnector_add_truefalse_question`: adds a new true/false question to a quiz.
- `local_mcpconnector_add_shortanswer_question`: adds a new short-answer question to a quiz.
- `local_mcpconnector_add_essay_question`: adds a new essay question to a quiz.
- Rejected parameters report the reason in the error message, not only in the debug information.
- "MCP Connector Service" (`mcpconnector`), disabled by default, authorised users only.
