<?php

class handlers_for_managesieve_hooks 
{
    public static function on_vacation_form_advanced_hook_oof($args) 
    {
        $structured_data_in_vacation_notice_checkbox = new html_inputfield(

            [   
                'type' => 'checkbox',
                'name' => 'include_structured_data',
                'id' => 'structured_data_in_vacation_notice_checkbox',   
                'class' => 'form-control'
            ]
        ); 
    
        
        $structured_data_in_email_checkbox = new html_inputfield(   
            [
                'type' => 'checkbox',
                'name' => 'include_structured_data_email',    
                'id' => 'structured_data_in_email_checkbox',
                'class' => 'form-control' 
            ] 
        );   
    
       $structured_data_send_before = new html_inputfield(    
            [
                'name' => 'structured_data_send_before',
                'id' => 'structured_data_send_before',
                'size' => 5,
                'class' => 'form-control'
            ]
        );
        

        // Advanced tab
        $rcmail = rcmail::get_instance();

        $table = $args['table'];
        $table->add('title', html::label('structured_data_in_vacation_notice_checkbox', $rcmail->gettext('roundcube_structured_vacation_notice.includeinvacationnotice')));
        $table->add(null, $structured_data_in_vacation_notice_checkbox->show());

        
        $table->add('title', html::label('structured_data_in_email_checkbox', $rcmail->gettext('roundcube_structured_vacation_notice.includeinoutgoingmail')));
        $table->add(null, $structured_data_in_email_checkbox->show());

        $structured_data_send_before_txt = $structured_data_send_before->show();
        $structured_data_send_before_txt .= html::span('input-group-append', html::span('input-group-text', $rcmail->gettext('roundcube_structured_vacation_notice.days')));

        $table->add('title', html::label('structured_data_send_before', $rcmail->gettext('roundcube_structured_vacation_notice.dayspriorinclude')));
        $table->add(null, html::span('input-group', $structured_data_send_before_txt));
        

    }
    
    /**
     * This method adds structured data to an vacation notice
     *
     * @param array $args contains the reason we will modify and the information if the reason is html
     *
     * @return array $args contains the modified reason if the user wants structured data added and the information if the reason is html
     */
    public static function on_vacation_reason_modify_on_mangesieve_store_hook($args)
    {   
        // Set reasons and is_html parameter to what they are when the hook is called
        $reason_html = $args['reason_html'];
        $reason_plain = $args['reason_plain'];
        $is_html = $args['is_html'];

        // HTML CONVERSION if only plain is sent
        if (!$is_html){
            $reason_html = (new rcube_text2html($reason_plain))->get_html();

            // div tags are automatically added through conversion
            // TODO: Could be done prettier, we already use the div clean before entering the hook
            // Remove anything before and after the <div> tags since the quotes interfere with the later added mime
            if (preg_match('/<div\b[^>]*>/', $reason_html)){
                preg_match("/<div[^>]*>(.*?)<\/div>/is", $reason_html, $matches);
                $reason_html = $matches[1];
            }

            // set html parameter for use in sieve after the hook
            $is_html = 1;
        }

        // NOW WE HAVE AN HTML VERSION WE CAN ATTACH THE JSON TO FOR SURE
        
        $include_structured_data = rcube_utils::get_input_string('include_structured_data', rcube_utils::INPUT_POST);
        $include_structured_data_email = rcube_utils::get_input_string('include_structured_data', rcube_utils::INPUT_POST);
        $structured_data_send_before = rcube_utils::get_input_string('structured_data_send_before', rcube_utils::INPUT_POST);
        
        // TODO access these values through parameters the hook is given
        $date_from     = rcube_utils::get_input_string('vacation_datefrom', rcube_utils::INPUT_POST);
        $date_to       = rcube_utils::get_input_string('vacation_dateto', rcube_utils::INPUT_POST);
        $from          = rcube_utils::get_input_string('vacation_from', rcube_utils::INPUT_POST, true);
        $subject       = rcube_utils::get_input_string('vacation_subject', rcube_utils::INPUT_POST, true);

        
        $rcmail = rcmail::get_instance();

        // Save the structured data preferences from the form as user preferences
       
        $rcmail->user->save_prefs(
            [   
                'include_structured_data_in_vacation_notice' => $include_structured_data,
                'include_structured_data_in_email' => $include_structured_data_email,
                'days_prior_include_structured_data_in_email' => $structured_data_send_before
            ]
        );
        
        
        // TODO: decide if this is needed (displays a message upon saving a notice with structured data)
        //$rcmail->output->show_message($user->get_prefs(), 'notice');
        // END
        // IDEAS for TODO
        //$user  = new rcube_user($rcmail->user->ID);
        //$prefs = $user->get_prefs();
        //rcmail::console('prefs: ' . serialize($prefs));
        //$rcmail->output->show_message('test');
        
        // We include structured data, if the user selects it
        if ($include_structured_data) {                       
            $structured_data = [            
                '@context' => 'https://schema.org',            
                '@type' => 'OutOfOffice',           
                'start' => $date_from,           
                'end' => $date_to,           
                'isForwarded' => false, // TODO: Find corresponding piece of data that maps to this           
                'replacement' => isset($from) && !empty($from) ?            
                    [            
                        (object)[            
                            '@type' => 'OutOfOfficeReplacement',            
                            'name' => '',            
                            'topic' => $subject,            
                            'email' => $from,            
                            'phone' => ''            
                        ]            
                    ] : [],            
                'note' => $reason_plain            
            ];
        }                         
            
        $jsonLd = $include_structured_data            
            ? '<script type="application/ld+json">'            
                . json_encode($structured_data)            
                . '</script>'            
            : '';            
            
        $html_json_reason = $jsonLd        
        . $reason_html;

        // Store the (modified) reasons and is_html parameter in $args and return them
        $args['reason_html'] = $html_json_reason;
        $args['reason_plain'] = $reason_plain;
        $args['is_html'] = $is_html;
        return $args;
    }
}
