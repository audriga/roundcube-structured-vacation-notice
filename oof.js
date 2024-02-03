/**
 * A function which is triggered whenever a recipient is added during mail composition
 * @param {Object} evt - The event object, passed once the "add-recipient" event is fired
 */
rcmail.check_recipients_for_oof = function(evt) {
    if (evt != null) {
        if (typeof evt.recipients !== 'undefined' && evt.recipients.length > 0) {
            // Get the list of recipients (if any) and only keep the recipients' emails
            var recipientsEmails = evt.recipients.map(function (recipient) {
                // Remove the recipients' names (if present)
                if (recipient.indexOf(' ') >= 0) {
                    recipientSplit = recipient.split(' ');
                    recipientEmail = recipientSplit.pop();

                    // Strip angle brackets from the recipients' emails
                    recipientEmail = recipientEmail.replace(/[<>]/g, '');
                    return recipientEmail;
                } else {
                    // If there are no names, then there are only emails and that's what we need
                    return recipient;
                }
            });

            // Send the recipients to the backend to check if any of them has OOF any information saved
            $var = rcmail.http_post('plugin.check_recipients_for_oof', {_recipients: recipientsEmails});
            console.log('VAR: ' + $var);
            console.log($var, JSON.stringify($var));
            console.dir($var);
            
        }
    }
}

/**
 * A helper function which check whether a string is a valid email address
 * @param {string} stringToCheck - The string to be checked
 * @returns {boolean} True if the string is a valid email address, false otherwise
 */
function validate_email(stringToCheck) {
    var re = /\S+@\S+\.\S+/;
    return re.test(stringToCheck);
}

/**
 * A function which creates a simple dialog, warning the user about the recipients being OOF
 * @param {Object} recipientsOofData - An object, mapping recipient email addresses to OOF data
 */
function create_simple_oof_data_dialog(recipientsOofData) {

    // Generate HTML about each recipient's OOF data
    // Note: we do filter(), since some keys in recipientsOofData might not be keys, representing a recipient's email address
    // and we only need the keys which are valid recipient email addresses
    var oofHtmlNoticeForRecipients = '';
    Object.keys(recipientsOofData)
        .filter(validate_email)
        .forEach(function (recipient) {
            var recipientOofStartDate = new Date(recipientsOofData[recipient].start);
            var recipientOofEndDate = new Date(recipientsOofData[recipient].end);
            var dateNow = new Date();
            var replacements = recipientsOofData[recipient].replacement;
            var replacementHtml = '';
            replacements.forEach(function (replacement) {
                var replacementSplit = replacement.split(',');
                var replacementName = replacementSplit[0],
                    replacementTopic = replacementSplit[1],
                    replacementEmail = replacementSplit[2],
                    replacementPhone = replacementSplit[3];
                
                if (replacementName !== null && replacementTopic !== null && replacementEmail !== null && replacementPhone !== null) {
                    replacementHtml += `<p>Regarding <b>${replacementTopic}</b>,
                        please contact <b>${replacementName}</b> via
                        <a href="mailto:${replacementEmail}">${replacementEmail}</a> or via
                        <a href="tel:${replacementPhone}">${replacementPhone}</a>.</p><br>`;
                } else {
                    replacementHtml += '';
                }
            });
            var shouldRenderForRecipient = dateNow > recipientOofStartDate && dateNow < recipientOofEndDate;
            oofHtmlNoticeForRecipients += shouldRenderForRecipient ? `<p>Recipient <b>${recipient}</b> is OOF
                from <b>${recipientsOofData[recipient].start}</b>
                till <b>${recipientsOofData[recipient].end}</b>.</p><br>` + replacementHtml
                : '';
        });

    // Only render the dialog if there's anything to render
    if (oofHtmlNoticeForRecipients !== '') {
        // The HTML, shown within the dialog
        var dialogHtml = $(
            `<div class="popupmenu" id="oof-data-dialog">${oofHtmlNoticeForRecipients}</div>`
        );
        
        // Show an alert dialog with OOF information about the recipients
        rcmail.alert_dialog(dialogHtml, null, {});
    }
}

/**
 * A handler function which receives the OOF data about the recipients from the backend
 * @param {Object} oofDataResponse - The OOF data, received from the backend as an object, mapping recipient email addresses to OOF data
 */
function receive_oof_data_for_recipients(oofDataResponse) {
    // Simply render an alert dialog with the OOF data
    create_simple_oof_data_dialog(oofDataResponse);
}
