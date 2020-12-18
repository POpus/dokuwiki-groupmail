<?php
/**
 * Options for the contact plugin
 *
 * @license GNU General Public License 3 <http://www.gnu.org/licenses/>
 * @author Bob Baddeley <bob@bobbaddeley.com>
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Roland Wunderling <bzfwunde@gmail.com>
 * @author Luffah <contact@luffah.xyz>
 */

$conf['sender_groups'] = 'admin';
$conf['recipient_groups'] = 'admin,user';
$conf['default'] = 'user@localhost';
$conf['allow_email'] = 0;
$conf['confidentiality'] = 'bcc';
$conf['sendlog'] = '';
$conf['captcha'] = 0;
$conf['recaptchakey'] = '';
$conf['recaptchasecret'] = '';
$conf['recaptchalayout'] = 'red';
