/**
 * This is an event listener which is triggered as soon as RC is initialized
 * (i.e., the 'init' event is fired)
 *
 * It is responsible for setting up and registering custom event listeners and
 * commands, used throughout the plugin
 */
window.rcmail && rcmail.addEventListener('init', function(evt) {

    // Register an action callback that is called by the backend whenever it returns an AJAX response to us
//    rcmail.addEventListener('plugin.action_callback', action_callback);

    // Register an action callback that is called by the backend whenever it returns OOF information about email recipients
    rcmail.addEventListener('plugin.receive_oof_data_for_recipients', receive_oof_data_for_recipients);

    // When recipients are added during email composition, send them to the backen to check if they have any OOF data
    rcmail.addEventListener('add-recipient', rcmail.check_recipients_for_oof);

    if (rcmail.task === 'mail') {
        // Render the structured data for a message when displaying it
        rcmail.render_structured_data();
    }
});

// A global variable which is meant to hold the username of the current user, logged into RC
var usernameVar = '';
