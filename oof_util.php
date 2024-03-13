<?php

class oof_util
{
    /**
     * A utility function for inspecting a message for OOF-related structured data
     * and for saving OOF data to the contact of the message's sender
     *
     * @param array $args Arguments containing the message we want to inspect
     *
     * @return void
     */
    public static function save_oof_data_from_message($args)
    {
        // If the message has OOF data we extract it
        $message = $args['object'];

        $rcmail = rcmail::get_instance();

        // TODO: Only update OOF if the data is more recent
        // TEST 
        $date = $message->get_header('date');
        rcmail::console('DATE: ' . $date);

        $format_date = $rcmail->format_date($date, $rcmail->config->get('date_long'));
        rcmail::console('FORMAT DATE: ' . $format_date);
        // TEST END

        $oofData = self::extractOofData($message);
        if (!isset($oofData) || empty($oofData)) {
            return;
        }
        // TODO log
        

        // We obtain the contacts in the addressbook of the user
        $contacts = $rcmail->get_address_book(
            rcube_addressbook::TYPE_CONTACT, true
        );
        $senderEmail = $message->sender['mailto'];
        $senderName = $message->sender['name'];
        
        // BROKEN when trying to modify an existing contact
        // TODO: modify the oof data of an existing contact correctly
        // Mantis Ticket: https://web.audriga.com/mantis/view.php?id=6464#c35297

        // If the sender is already an existing contact, we fetch the contact
        if ($rcmail->contact_exists(
            $senderEmail,
            rcube_addressbook::TYPE_CONTACT
            | rcube_addressbook::TYPE_WRITEABLE
        )
        )
         {
            // LOG
            //rcmail::console('THERE IS AN EXISTING CONTACT WE WILL MODIFY');

            // Searching for the contact with the email of the sender
            $searchRes = $contacts->search(
                ['email'],
                $senderEmail,
                rcube_addressbook::SEARCH_ALL,
                true,
                true
            );

            // LOG
            //rcmail::console('SEARCH RESULTS: ' . serialize($searchRes));

            // If we find a contact to the corresponding email we take the first element of our search as the contact we will modify
            // TODO: only update the oof data if it is newer than the old 
            if (isset($searchRes)) {
                $senderContact = $searchRes->first();
                // TODO LOG
                //rcmail::console('FIRST LOG CONTACT BEFORE: ' . serialize($senderContact));
                if (isset($senderContact)) {
                    // We take the existing vCard propertiy and add the OOF data to it
                    $vcardString = self::add_oof_data_to_vcard(
                        $senderContact['vcard'], $oofData
                    );
                    // We give $senderContact the new vCard with updated OOF
                    $senderContact['vcard'] = $vcardString;


                    // TODO log
                   // rcmail::console('OOF DATA WE WILL UPDATE THE CONTACT WITH: ' . serialize($vcardString));

                    // because of troubles updating an existing contact we delete the contact first
                    // and then create a new one with the old data and the new oof to "update"
                    $contacts->delete($senderContact['ID'], true);
                    $contacts->insert($senderContact);
                    
                // TODO: TEST, obtain the new contact and log to see if we "updated" correctly 
                $searchRes = $contacts->search(
                    ['email'],
                    $senderEmail,
                    rcube_addressbook::SEARCH_ALL,
                    true,
                    true
                );
                $senderContact = $searchRes->first();
                //rcmail::console('Contact after "upate": ' . serialize($senderContact));
                // TODO TEST END
                                
                }
            }
        } else { // Otherwise, we create a new contact
            // TODO LOG
            rcmail::console('WE CREATE A NEW CONTACT');
            $vcardString = self::add_oof_data_to_vcard(null, $oofData);
            $contacts->insert(
                [
                    'email:internet' => $senderEmail,
                    'name' => $senderName,
                    'vcard' => $vcardString
                ]
            );
        }
    }

    private static function extractOofData($message)
    {
        $htmlPart = null;
        $oofData = [];
        if ($message->has_html_part(true, $htmlPart)) {
            $extractedJsonLd = structured_data_util_oof::html2jsonld_oof(
                $message->get_part_body($htmlPart->mime_id)
            );
            if (isset($extractedJsonLd) && !empty($extractedJsonLd)) {
                $extractedJsonLd = json_decode($extractedJsonLd, true);
                if (strcmp($extractedJsonLd['@type'], 'OutOfOffice') === 0) {
                    $oofData['X-OOF-START'] = [$extractedJsonLd['start']];
                    $oofData['X-OOF-END'] = [$extractedJsonLd['end']];
                    $oofData['X-OOF-ISFORWARDED'] = [
                        $extractedJsonLd['isForwarded']
                    ];

                    // Store replacement as a single string with
                    // commas separating the individual parts
                    $oofData['X-OOF-REPLACEMENT'] = [
                        implode(
                            ',',
                            [
                                $extractedJsonLd['replacement'][0]['name'],
                                $extractedJsonLd['replacement'][0]['topic'],
                                $extractedJsonLd['replacement'][0]['email'],
                                $extractedJsonLd['replacement'][0]['phone']
                            ]
                        )
                    ];
                    $oofData['X-OOF-REPLACEMENT2'] = [
                        implode(
                            ',',
                            [
                                $extractedJsonLd['replacement'][1]['name'],
                                $extractedJsonLd['replacement'][1]['topic'],
                                $extractedJsonLd['replacement'][1]['email'],
                                $extractedJsonLd['replacement'][1]['phone']
                            ]
                        )
                    ];
                    $oofData['X-OOF-NOTE'] = [$extractedJsonLd['note']];
                }
            }
        }

        return $oofData;
    }

    /**
     * A function for adding OOF data to a vCard
     *
     * @param string|null $vcard   The vCard (as a string)
     *                             that we want to add data to.
     *                             If null, then a new vCard is created
     * @param array       $oofData An associative array with OOF data
     *
     * @return string The vCard, now containing OOF data, as a string
     */
    private static function add_oof_data_to_vcard($vcard, $oofData)
    {
        // TODO log
        //rcmail::console('FIRST LOG vcard: ' . serialize($vcard));
        // We create an object out of the vCard 
        $vcardObj = new rcube_vcard($vcard);
        //rcmail::console('vcard Object before: ' . serialize($vcardObj));

        // We add / replace the OOF data in the vCard 
        foreach ($oofData as $key => $value) {
            // If the X-OOF-REPLACEMENT2 property is 0 we append the X-OOF-REPLACEMENT instead of replacing it
            //
            /* 
            if (strcmp($key, 'X-OOF-REPLACEMENT2') === 0) {
                //rcmail::console('If statement with this key: ' . $key);
                $vcardObj->set_raw('X-OOF-REPLACEMENT', $value, true);    
            } else {
                $vcardObj->set_raw($key, $value, false);
                //rcmail::console('Else statement with this key: ' . $key);
            }
            */

            $vcardObj->set_raw($key, $value, false);

        }

        //rcmail::console('vcard Object after: ' . serialize($vcardObj));

        // TODO Log export
        //$exportVcardObj = $vcardObj->export();
        //rcmail::console('Exported vcardObj: ' . $exportVcardObj);

        return $vcardObj->export();

        // Result of debug logs: the code above returns a workig vcard, without deleting properties 
    }

    /**
     * An action handler function which is called whenever a user
     * selects a recipient in the UI during message composition.
     * The frontend calls the action via AJAX and this handler
     * returns back OOF data about the recipients (if they have any such data)
     *
     * @return void
     */
    public static function check_recipients_for_oof()
    {
        // Obtain the recipients' email addresses, sent by the frontend
        $recipients = rcube_utils::get_input_value('_recipients', rcube_utils::INPUT_POST);
        if (isset($recipients) && !empty($recipients)) {
            $rcmail = rcmail::get_instance();
            $contacts = $rcmail->get_address_book(
                rcube_addressbook::TYPE_CONTACT, true
            );

            // Check if each recipient is a contact and
            // if they have any OOF-related data in their vCard
            foreach ($recipients as $recipient) {
                if ($rcmail->contact_exists(
                    $recipient,
                    rcube_addressbook::TYPE_CONTACT
                    | rcube_addressbook::TYPE_WRITEABLE
                )
                ) {
                    $searchRes = $contacts->search(
                        ['email'],
                        $recipient,
                        rcube_addressbook::SEARCH_ALL,
                        true,
                        true
                    );
        
                    if (isset($searchRes)) {
                        $recipientContact = $searchRes->first();
                        if (isset($recipientContact)) {
                            $vcard = new rcube_vcard($recipientContact['vcard']);
                            
                            // When obtaining the vCard as an associative array,
                            // we need to tell it to include the X-OOF-* properties
                            // That's why we need the extend_fieldmap call below
                            $vcard->extend_fieldmap(
                                [
                                    'oof-start' => 'X-OOF-START',
                                    'oof-end' => 'X-OOF-END',
                                    'oof-replacement' => 'X-OOF-REPLACEMENT',
                                    'oof-note' => 'X-OOF-NOTE'
                                ]
                            );

                            // Turn the vCard string into an associative array
                            $vcardAssocArray = $vcard->get_assoc();
                            
                            // Collect the X-OOF-* properties' contents
                            $oofData = [
                                $recipient => [
                                    'start' => $vcardAssocArray['oof-start'][0],
                                    'end' => $vcardAssocArray['oof-end'][0],
                                    'replacement' => $vcardAssocArray['oof-replacement'],
                                    'note' => $vcardAssocArray['oof-note']
                                ]
                            ];

                            $rcmail->output->set_env('testdata', 'boo');
                            
                            // Return the OOF-related data to the frontend
                            $rcmail->output->command(
                                'plugin.receive_oof_data_for_recipients',
                                $oofData
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * This function adds OOF structured data, based on the user's vacation notice
     *
     * The structured data is added only if the OOF hasn't started yet
     *
     * @param array $args The arguments of the "message_before_send" hook
     *
     * @return array The updated arguments of the "message_before_send" hook.
     *               Note: the arguments are updated only in case we can find a
     *               vacation notice starting after the current date and time.
     */
    public static function add_oof_on_send($args)
    {
        $rcmail = rcmail::get_instance();
        $available_plugins = $rcmail->config->get('plugins', []);

        /**
         * // If the list of available plugins is empty or
         * if 'managesieve' is not one of them, then we have no more work here
         */
        if (empty($available_plugins)
            || !in_array('managesieve', $available_plugins)
        ) {
            return $args;
        }


        // Broken
        //$vacation = (new managesieve($rcmail->plugins))->get_engine('vacation')->get_vacation();

        // OPtion  B
        //$managesieve = (new managesieve($rcmail->plugins));
        //$managesieve->init();
        //$vacation = $managesieve->get_engine('vacation')->get_vacation();

        // Option A
        $vacation_engine = (new managesieve($rcmail->plugins))->get_engine('vacation');
        $vacation_engine->start('vacation');
        $vacation = $vacation_engine->get_vacation();


        $now = new DateTime();
        
        $user_prefs = $rcmail->user->get_prefs();
        $add_structured_data_in_email = $user_prefs['include_structured_data_in_email'];
        $days_prior = 0;

        // Check if we have a integer
        if(intval(isset($user_prefs['days_prior_include_structured_data_in_email']))!= 0)
            $days_prior = $user_prefs['days_prior_include_structured_data_in_email'];

        // Calculating when to start sending or replying messages
        $now_minus_days_prior = (new DateTime())->modify("- {$days_prior} day");

        /*
         * Add OOF structured data iff the user prefs allow us and
         * the current time is within the time period during
         * which the structured data should be added to the email
         */
        if (isset($add_structured_data_in_email)
            && $add_structured_data_in_email =="on"
            && $now_minus_days_prior >= $vacation['start']
            && $now <= $vacation["end"]
        ) {
            $structured_data = [
                '@context' => 'https://schema.org',
                '@type' => 'OutOfOffice',
                'start' => $vacation['start'],
                'end' => $vacation['end'],
                'isForwarded' => false,
                'replacement' => isset($vacation['from'])
                    && !empty($vacation['from'])
                    ? [
                        (object)[
                            '@type' => 'OutOfOfficeReplacement',
                            'name' => '',
                            'topic' => $vacation['subject'],
                            'email' => $vacation['from'],
                            'phone' => ''
                        ]
                    ]
                    : [],
                'note' => $vacation['reason']
            ];

            $jsonLd = json_encode($structured_data);
            $message = $args['message'];
            $htmlBody = $message->getHTMLBody();
            $htmlBody = '<script type="application/ld+json">'
                . $jsonLd
                . '</script>'
                . $htmlBody;
            $message->setHTMLBody($htmlBody);
            $args['message'] = $message;
            // rcmail::console('message :'. $message);
        
            return $args;

        // If there is no user pref for sending the OOF data even if the user is OOF
        // We still need to return the message    
        } else {
            return $args;
        }
    }
}
