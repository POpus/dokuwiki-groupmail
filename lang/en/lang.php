<?php
/**
 * English language file
 *
 * @license GNU General Public License 3 <http://www.gnu.org/licenses/>
 * @author Bob Baddeley <bob@bobbaddeley.com>
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 */

// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Modern Contact Form';

// custom language strings for the plugin
$lang["field"] = 'Field';
$lang["value"] = 'Value';
$lang["name"] = 'Your Name';
$lang["email"] = 'Your Email';
$lang["subject"] = 'Subject';
$lang["content"] = 'Message';
$lang["send"] = 'Submit';
$lang["from"] = 'from';
$lang["datetime"] = ' at %Y-%m-%d %H:%M'; 
$lang["sent by"] = 'sent by';
$lang["via"] = 'via DokuWiki at ';
$lang["viewonline"] = 'view the message online at ';

// error messages
$lang["error"]["unknown"] = 'Mail not sent. Please contact the administrator.';
$lang["error"]["name"] = 'Please enter a name. Should be at least 2 characters.';
$lang["error"]["email"] = 'Please enter your email address. It must be valid.';
$lang["error"]["content"] = 'Please add a comment. Should be at least 10 characters.';
$lang["error"]["captcha"] = 'Mail not sent. You could not be verified as a human.';
$lang["error"]["valid_name"] = 'Name has invalid input.';
$lang["error"]["valid_email"] = 'Email address has invalid input.';
$lang["error"]["valid_subject"] = 'Subject has invalid input.';
$lang["error"]["valid_to"] = 'Destination address has invalid input.';
$lang["error"]["valid_content"] = 'Comment has invalid input.';
$lang["error"]["external_email_forbidden"] = 'Emails cannot be specified in this form.';
$lang["error"]["acl_touser"] = 'You are not allowed to send mails to user "%s" (field: %s)';
$lang["error"]["acl_togroup"] = 'You are not allowed to send mails to members of "%s" (field: %s)';
$lang["error"]["acl_one"] = 'You are not allowed to send a mail to multiple recipients.';
$lang["error"]["acl_bcc"] = 'You are not allowed to send a message which shows the mail address of another user. Use bcc field alone to fix this issue.';
$lang["success"] = 'Mail sent successfully.';
