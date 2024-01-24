<?php

/**
 * TODO
 */

require __DIR__ . '/oof_util.php';
require __DIR__ . '/structured_data_util_oof.php';
require __DIR__ . '/message_send_util_oof.php';
require __DIR__ . '/handlers_for_managesieve_hooks.php';

class roundcube_structured_vacation_notice extends rcube_plugin
{
    /**
     * A regex-like variable which indicates that our plugin
     * should be active in all tasks excluding "login" and "logout"
     *
     * @var string $task
     */
    public $task = '?(?!login|logout).*';

    /**
     * Initialize the plugin
     *
     * @return void
     */
    function init()
    {
        // Register the relevant hooks that we use in our plugin
        $this->add_hook('message_load', [$this, 'on_message_load_hook']);
        $this->add_hook('message_before_send', [$this, 'on_message_before_send_hook_oof']);
        $this->add_hook('vacation_form_advanced', [$this, 'on_vacation_form_advanced_hook_oof']);
        $this->add_hook('vacation_reason_modify_on_mangesieve_store', [$this, 'on_vacation_reason_modify_on_mangesieve_store_hook']);
        $this->add_hook('message_objects', [$this, 'on_message_objects_hook']);

        // Register an action handler for checking whether message recipients are OOF
        $this->register_action(
            'check_recipients_for_oof',
            [$this, 'check_recipients_for_oof_action']
        );

        // Include our JS files
        $this->include_script('roundcube_structured_vacation_notice.js');
        $this->include_script('oof.js');


        // Include Internationalization
        $this->add_texts('localization/', true);
    }

    /**
     * A handler function for the "message_load" hook
     *
     * @param array $args The arguments, passed to the "message_load" hook
     *
     * @return void
     */
    function on_message_load_hook($args)
    {
        /**
         * Call our util function to check for OOF-related structured data
         * and save the relevant OOF data to the sender's contact
         */
        oof_util::save_oof_data_from_message($args);
    }

    /**
     * A handler function for our AJAX action for checking whether the recipients
     * of a message are currently OOF
     *
     * @return void
     */
    function check_recipients_for_oof_action()
    {
        oof_util::check_recipients_for_oof();
    }

    /**
     * A handler for the "message_before_send" hook
     * We use it to turn a hidden JSON-LD from a "div" into a "script" tag
     * (used when composing messages with structured data via our plugin)
     *
     * @param $args array The hook arguments that we receive
     * 
     * @return array The (potentially) modified hook arguments
     */
    function on_message_before_send_hook_oof($args)
    {
        return message_send_util_oof::on_message_before_send_hook_oof($args);
    }

    // Handlers for the managesieve hooks
    function on_vacation_form_advanced_hook_oof($args)
    {
        return handlers_for_managesieve_hooks::on_vacation_form_advanced_hook_oof($args);
    }

    
    function on_vacation_reason_modify_on_mangesieve_store_hook($args)
    {
        rcmail::console('triggers on save hook');
        return handlers_for_managesieve_hooks::on_vacation_reason_modify_on_mangesieve_store_hook($args);
    }
    
    public function on_message_objects_hook($args)
    {
        $rcmail = rcmail::get_instance();

        // A variable which holds an extracted JSON-LD with structured data
        $jsonLd = '';

        // A variable which holds the context type of a JSON-LD with structured data
        $contextType = '';

        // Obtain the arguments for the hook
        $content = $args['content'];
        $message = $args['message'];
        
        // Get the message's sender
        $messageSender = $message->sender['mailto'];

        // Check if we have a "text/html" part in the message
        foreach ($message->parts as $part) {
            if ($part->ctype_primary === 'text'
                && $part->ctype_secondary === 'html'
            ) {
                // Get the HTML part of the message's body
                $htmlBody = $message->get_part_body($part->mime_id, true);
                
                // Try to extract JSON-LD from the HTML part
                $jsonLd = structured_data_util_oof::html2jsonld_oof($htmlBody);

                // Get the message's "from" header
                $from = $message->get_header('from');

                // Set the context type appropriately
                $contextType = structured_data_util_oof::get_context_type($jsonLd);
            }
        }

        // The type of a trusted sender, we'll need it below
        $trustedSenderType = rcube_addressbook::TYPE_TRUSTED_SENDER;

        
        
        $showStructuredEmailForTrustedSenders = $rcmail->config->get(
            'showStructuredEmailForTrustedSenders',
            false
        );

        /**
         * Only show structured email data if:
         * * our config flag allows it
         * * and the sender is a trusted one
         */
        if ($showStructuredEmailForTrustedSenders
            && $rcmail->contact_exists($messageSender, $trustedSenderType)
        ) {
            // Set Dummy name & topic (no UI component yet)
            $replacement_name = 'John Doe';
            $replacement_topic = 'customer support';

           // Decode jsonLd for our banner 
           $decoded_jsonLd = json_decode($jsonLd);

           $start = $decoded_jsonLd->start;
           $end = $decoded_jsonLd->end;
           $replacement_email = $decoded_jsonLd->replacement[0]->email;
           // How we would access the name and topic if they were set (can't be set through ui currently)
           //$replacement_name = $decoded_jsonLd->replacement[0]->name;           
           //$replacement_topic = $decoded_jsonLd->replacement[0]->topic;
            
            // OUTPUT Banner similar to the "remote-objects-message"
            // TODO only show the parameters which are set
            $html = '<div id="oof-message-box" style="background-color:rgba(255,212,82,.2);" class="ui alert alert-warning boxwarning">';
            $html .= '<i class="icon"></i>';
            $html .= '<span>The user is out-of-office from ' . $start . ' till ' . $end . ' mails will not be forwarded.';
            $html .= ' During absence, contact ' . $replacement_name . ' regarding ' . $replacement_topic . ' under ' . $replacement_email . '.</span>';
            $html .= '</div>';
            array_push($content, $html);
            }    
        return array('content' => $content);
    }
}