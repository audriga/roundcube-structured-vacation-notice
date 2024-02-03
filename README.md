# Roundcube Structured Vacation Notice

⚠️  This version is still in its early stages. Handle with care.

The Roundcube Structured Vacation Notice plugin optimizes the way structured Vacation Notice / Out of office (OOF) data is handled.

This plugin implements the IETF specification [draft-happel-structured-vacation-notices](https://datatracker.ietf.org/doc/draft-happel-structured-vacation-notices/) of the SML working group. The specification and this plugin are still a work-in-progress. Interested parties should consider reaching out to sml@ietf.org .

## Requirements

This plugin requires a patched version of the [roundcube/managesieve](https://github.com/roundcube/roundcubemail/tree/master/plugins/managesieve) plugin that is still work-in-progress.

## Installation

Simply follow the usual plugin installation instructions for [Roundcube Plugins](https://plugins.roundcube.net/). Alternatively, place the contents of this repo in the `plugins` folder of your Roundcube installation.

## Usage

A contact's OOF is checked whenever she/he is added as a recipient during mail composition. If the recipient is on vacation, a dialog opens including the OOF dates and a replacement the user can mail to instead.

## Open Issues
* When a user sends an email, his own OOF information is sent with the mail.
