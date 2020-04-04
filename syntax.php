<?php

/* Modern Contact Plugin for Dokuwiki
 * 
 * Copyright (C) 2008 Bob Baddeley (bobbaddeley.com)
 * Copyright (C) 2010-2012 Marvin Thomas Rabe (marvinrabe.de)
 * Copyright (C) 2020 Luffah
 * 
 * This program is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, see <http://www.gnu.org/licenses/>. */

/**
 * Embed a send email form onto any page
 * @license GNU General Public License 3 <http://www.gnu.org/licenses/>
 * @author Bob Baddeley <bob@bobbaddeley.com>
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Luffah <contact@luffah.xyz>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(dirname(__file__).'/recaptchalib.php');

class syntax_plugin_groupmail extends DokuWiki_Syntax_Plugin {

  public static $captcha = false;
  public static $lastFormIdx = 1;

  private static $recipientFields = array(
    'toemail', 'touser', 'togroup', 
    'ccemail', 'ccuser', 'ccgroup', 
    'bccemail', 'bccuser', 'bccgroup'
  );
  private $formId = '';
  private $status = 1;
  private $statusMessage;
  private $errorFlags = array();
  private $recipient_groups = array();
  private $sender_groups = array();

  /**
   * Syntax type
   */
  public function getType(){
    return 'container';
  }

  public function getPType(){
    return 'block';
  }

  /**
   * Where to sort in?
   */
  public function getSort(){
    return 300;
  }

  /**
   * Connect pattern to lexer.
   */
  public function connectTo($mode) {
    $this->Lexer->addSpecialPattern('\{\{groupmail>[^}]*\}\}',$mode,'plugin_groupmail');
  }

  /**
   * Handle the match.
   */
  public function handle($match, $state, $pos, Doku_Handler $handler){
    if (isset($_REQUEST['comment']))
      return false;

    $match = substr($match,12,-2); //strip markup from start and end
    $data = array();

    //handle params
    foreach(explode('|',$match) as $param){
      $splitparam = explode('=',$param,2);
      $key = $splitparam[0];
      $val = count($splitparam)==2 ? $splitparam[1]:Null;
      //multiple targets/profils possible for the email
      //add multiple to field in the dokuwiki page code
      // example : {{groupmail>to=profile1,profile2|subject=Feedback from Site}}
      if (in_array($key, syntax_plugin_groupmail::$recipientFields)){
        if (is_null($val)) continue;
        if (isset($data[$key])){
          $data[$key] .= ",".$val; //it is a "toemail" param but not the first
        }else{
          $data[$key] = $val; // it is the first "toemail" param
        }

      } else if ($key=='autofrom'){  // autofrom doesn't require value
        $data[$key] = is_null($val) ? 'true' : $val;
      } else {
        $data[$splitparam[0]] = $splitparam[1]; // All other parameters
      }
    }
    return $data;
  }

  private function check_recipient_access($data, $field){
    if (isset($data[$field])){
      $typ=substr($field, -4);
      $vals = explode(',' , $data[$field]);

      if ($typ == 'roup') {  # group list type
        foreach ($vals as $group) {
          if (!in_array($group, $this->recipient_groups)){
            $this->_set_error("acl_togroup", array($group, $field), 'acl');
          }
        }
      } elseif ($typ == 'user') {  # user list type
        foreach ($vals as $userId) {
          $info = $auth->getUserData($userId);
          if (isset($info)) {
            if (count(array_intersect($info['grps'], $this->recipient_groups))==0) {
              $this->_set_error("acl_touser", array($userId, $field), 'acl');
            }
          } else {
            $this->_set_error("acl_touser", array($userId, $field), 'acl');
          }
        }
      }
    }
  }

  private function getexplodedvals($data, $fields){
    $res = array();
    foreach ($fields as $field){
      if (isset($data[$field]))
        $res[$field] = explode(',' , $data[$field]);
    }
    return $res;
  }

  /**
   * Create output.
   */
  public function render($mode, Doku_Renderer $renderer, $data) {
    global $USERINFO;

    if($mode == 'xhtml'){
      // Disable cache
      $renderer->info['cache'] = false;

      /**
       * Basic access rights based on group
       */
      $this->sender_groups = explode(',', $this->getConf('sender_groups'));
      $this->recipient_groups = explode(',', $this->getConf('recipient_groups'));

      if (count(array_intersect($USERINFO['grps'], $this->sender_groups))==0) {
        // user have no right to see this
        return true;
      }

      if ( !$this->getConf('allow_email') && (
        isset($data['toemail']) || isset($data['bccemail']) || isset($data['ccemail'])
      )) {
        $this->_set_error("external_email_forbidden", Null, 'acl');
      }
      $vals = $this->getExplodedVals($data, syntax_plugin_groupmail::$recipientFields);
      if ( $this->getConf('confidentiality') == 'one' ) { 
        $nb_recipients=0;
        if (preg_grep('/(cc|group)/', array_keys($vals)))
          $nb_recipients+=2;

        foreach(array('touser', 'toemail', 'bccuser', 'bccemail') as $field)
          if (isset($vals[$field])) $nb_recipients+=count($vals[$field]);

        if ($nb_recipients > 1)
          $this->_set_error("acl_one", Null, 'acl');
      } elseif ( $this->getConf('confidentiality') == 'bcc' ) { 
        $nb_recipients=0;
        if (preg_grep('/^(cc|togroup)/', array_keys($vals)))
          $nb_recipients+=2;

        if (preg_grep('/^bcc/', array_keys($vals)))
          $nb_recipients+=1;

        foreach(array('touser', 'toemail') as $field)
          if (isset($vals[$field])) $nb_recipients+=count($vals[$field]);

        if ($nb_recipients > 1)
          $this->_set_error("acl_bcc", Null, 'acl');
      }
      $this->check_recipient_access($vals, 'togroup');
      $this->check_recipient_access($vals, 'touser');
      $this->check_recipient_access($vals, 'ccgroup');
      $this->check_recipient_access($vals, 'ccuser');
      $this->check_recipient_access($vals, 'bccgroup');
      $this->check_recipient_access($vals, 'bccuser');
      if ($this->errorFlags['acl']){
        $renderer->doc .= $this->_html_status_box();
        return true;
      }

      // Define unique form id
      $this->formId = 'groupmail-form-'.(syntax_plugin_groupmail::$lastFormIdx++);


      $renderer->doc .= $this->mailgroup_form($data, $vals);
      return true;
    }
    return false;
  }

  /*
   * Build the mail form
   */
  private function mailgroup_form ($data, $recipientVals){
    global $USERINFO;

    // Is there none captcha on the side?
    $captcha = ($this->getConf('captcha') == 1 && syntax_plugin_groupmail::$captcha == false)?true:false;

    // Setup send log destination
    if ( isset($data['sendlog']) )
      $sendlog = $data['sendlog'];
    elseif ( '' != $this->getConf('sendlog') )
      $sendlog = $this->getConf('sendlog');

    $ret = '<form id="'.$this->formId.'" action="'.$_SERVER['REQUEST_URI'].'#'.$this->formId.'" method="POST">';

    // Send message and give feedback
    if (isset($_POST['submit-'.$this->formId]))
      if($this->_send_groupmail($captcha, $sendlog))
        $ret .= $this->_html_status_box();

    if (isset($data['subject']))
      $ret .= '<input type="hidden" name="subject" value="'.$data['subject'].'" />';

    foreach (array_keys($recipientVals) as $field) {
      $ret .= '<input type="hidden" name="'.$field.'" value="'.$data[$field].'" />';
    }

    // Build view for form items
    $ret .= "<fieldset>";
    if  (isset($data['title'])) {
      $title = $data['title'];
    } else {
      $title = '';
      $sep = '';
      if (isset($data['subject'])) { $title .= '"'.$data['subject'].'"'; $sep=' ';  }
      $and = False;
      if (isset($data['touser']))  { $title .= $sep.'to '. $data['touser']; $sep=', '; $and=True;}
      if (isset($data['togroup'])) { $title .= $sep.($and? '': 'to '). $data['togroup']; $sep=', ';}
      $and = False;
      if (isset($data['ccuser']))  { $title .= $sep.'cc '.  $data['ccuser']; $sep=', '; $and=True; }
      if (isset($data['ccgroup'])) { $title .= $sep.($and? '': 'cc ').  $data['ccgroup']; $sep=', ';} 
      $and = False;
      if (isset($data['bccuser'])) { $title .= 'bcc '.  $data['bccuser']; $sep=', '; $and=True;} 
      if (isset($data['bccgroup'])){ $title .= $sep.($and? '': 'bcc ').  $data['bccgroup']; $sep=', ';} 
    }
    $ret .= "<legend>".$title."</legend>";
    if ( !isset($data['autofrom']) || $data['autofrom'] != 'true' ) {
      $ret .= $this->_form_row($this->getLang("name"), 'name', 'text', $USERINFO['name']);
      $ret .= $this->_form_row($this->getLang("email"), 'required_email', 'text', $USERINFO['mail']);
    }
    if ( !isset($data['subject']) )
      $ret .= $this->_form_row($this->getLang("subject"), 'subject', 'text');

    $ret .= $this->_form_row($this->getLang("content"), 'content', 'textarea',
      isset($data['content']) ? $data['content'] : '');

    // Captcha
    if($captcha) {
      if($this->errorFlags["captcha"]) {
        $ret .= '<style>#recaptcha_response_field { border: 1px solid #e18484 !important; }</style>';
      }
      $ret .= "<tr><td colspan='2'>"
        . "<script type='text/javascript'>var RecaptchaOptions = { lang : '".$conf['lang']."', "
        . "theme : '".$this->getConf('recaptchalayout')."' };</script>"
        . recaptcha_get_html($this->getConf('recaptchakey'))."</td></tr>";
      syntax_plugin_groupmail::$captcha = true;
    }


    if (isset($data['autofrom']) && $data['autofrom'] == 'true' ) {
      $ret .= '<input type="hidden" name="email" value="'.$USERINFO['mail'].'" />';
      $ret .= '<input type="hidden" name="name" value="'.$USERINFO['name'].'" />';
    }

    $ret .= '<input type="submit" name="submit-'.$this->formId.'" value="'.$this->getLang('send').'" />';
    $ret .= "</fieldset>";

    $ret .= "</form>";

    return $ret;
  }

  private function send_mail ($to, $subject, $content, $from, $cc, $bcc) {
    // send a mail
    $mail = new Mailer();
    $mail->to($to);
    $mail->cc($cc);
    $mail->bcc($bcc);
    $mail->from($from);
    $mail->subject($subject);
    $mail->setBody($content);
    $ok = $mail->send();
    return $ok;
  }

  private function _email_list(){ // string, string ...
    global $auth;
    $items = array();
    foreach (func_get_args() as $field) {
      if (!isset($_REQUEST[$field])) continue;
      $typ=substr($field, -4);
      $vals=explode(',' , $_POST[$field]);
      if ($typ == 'roup') {  # group list type
        if (!method_exists($auth, "retrieveUsers")) continue;
        foreach ($vals as $grp) {
          $userInfoHash = $auth->retrieveUsers(0,-1,array('grps'=>'^'.preg_quote($grp,'/').'$'));
          foreach ($userInfoHash as $u => $info) { array_push($items, $info['mail']); }
        }
      } elseif ($typ == 'user') {  # user list type
        foreach ($vals as $userId) {
          $info = $auth->getUserData($userId);
          if (isset($info)) {
            array_push($items, $info['mail']);
          }
        }
      } else { # mail list type
        foreach($email as $vals){  array_push($items, $email); }
      }
    }
    return $items;
  }

  /**
   * Check values are somehow valid
   */
  private function _validate_value($val, $typ, $as_array=False, $multiline=False){
    # FIXME improve security if possible
    if ($as_array) {
      foreach ($val as $v) { $this->_validate_value($v, $typ, False, $multiline); }
      return;
    }
    if ($typ == 'email' || $typ == 'to' || $typ == 'from' || $typ == 'cc' || $typ == 'bcc') {
      if(!mail_isvalid($val)) $this->_set_error("valid_".$typ, Null, $typ);
    }
    if ((!$multiline  && preg_match("/(\r|\n)/",$val)) || preg_match("/(MIME-Version: )/",$val) || preg_match("/(Content-Type: )/",$val)){
      $this->_set_error("valid_".$typ, Null, $typ);
    }
  }

  /**
   * Verify and send email content.´
   */
  protected function _send_groupmail($captcha=false, $sendlog){
    global $auth;
    global $USERINFO;
    global $ID;

    require_once(DOKU_INC.'inc/mail.php');

    $name  = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $comment = $_POST['content'];

    // required fields filled
    if(strlen($_POST['content']) < 10) $this->_set_error('content');
    if(strlen($name) < 2) $this->_set_error('name');

    // checks recaptcha answer
    if($this->getConf('captcha') == 1 && $captcha == true) {
      $resp = recaptcha_check_answer ($this->getConf('recaptchasecret'),
        $_SERVER["REMOTE_ADDR"],
        $_POST["recaptcha_challenge_field"],
        $_POST["recaptcha_response_field"]);
      if (!$resp->is_valid) $this->_set_error('captcha');
    }

    // record email in log
    $lastline = '';
    if ( isset($sendlog)  &&  $sendlog != '' ) {
      $targetpage = htmlspecialchars(trim($sendlog));
      $oldrecord = rawWiki($targetpage);
      $bytes = bin2hex(random_bytes(8));
      $newrecord = '====== msg'.$bytes.' ======'."\n";
      $newrecord .= "**<nowiki>".$subject."</nowiki>** \n";
      $newrecord .= '//'.$this->getLang("from").' '.$name.($this->getConf('confidentiality') =='all'?' <'.$email.'>':'');
      $newrecord .= ' '.strftime($this->getLang("datetime"))."//\n";
      $newrecord .= "\n<code>\n".trim($comment,"\n\t ")."\n</code>\n\n";
      saveWikiText($targetpage, $newrecord.$oldrecord, "New entry", true);
      $lastline .= $this->getLang("viewonline").wl($ID,'', true).'?id='.$targetpage."#msg".$bytes."\n\n\n";

      $this->statusMessage = $this->getLang("viewonline").'<a href="'.wl($ID,'', true).'?id='.$targetpage."#msg".$bytes.'">'.$bytes."</a>";
    }

    $comment .= "\n\n";
    $comment .= '---------------------------------------------------------------'."\n";
    $comment .= $this->getLang("sent by")." ".$name.' <'.$email.'>'."\n";
    $comment .= $this->getLang("via").wl($ID,'',true)."\n";
    $comment .= $lastline;

    $to = $this->_email_list('toemail', 'touser', 'togroup');
    if (count($to) == 0) { 
      array_push($to, $this->getConf('default'));
    }

    $ccs = array_diff($this->_email_list('ccemail', 'ccuser', 'ccgroup'), $to);
    $bccs = array_diff($this->_email_list('bccemail', 'bccuser', 'bccgroup'), $to, $ccs);

    // A bunch of tests to secure content
    $this->_validate_value($name, 'name');
    $this->_validate_value($email, 'email');
    $this->_validate_value($subject, 'subject');
    $this->_validate_value($to, 'to', True);
    $this->_validate_value($css, 'cc', True);
    $this->_validate_value($bccs, 'bcc', True);
    $this->_validate_value($comment, 'content', False, True);

    // Status has not changed.
    if($this->status != 0) {
      // send only if message is not empty
      // this should never be the case anyway because the form has
      // validation to ensure a non-empty comment
      if (trim($comment, " \t") != ''){
        if ($this->send_mail($to, $subject, $comment, $email, $ccs, $bccs)){
          $this->statusMessage = $this->getLang("success")."\n".$this->statusMessage;
        } else {
          $this->_set_error('unknown');
        }
      }
    }

    return true;
  }

  /**
   * Manage error messages.
   */
  protected function _set_error($msgid, $args=Null, $type=Null) {
    $lang = $this->getLang("error");
    if (is_null($type)) $type=$msgid;
    $msgstr = $lang[$msgid];
    if (!is_null($args)){
      $msgstr = vprintf($msgstr, $args);
    }
    $this->status = 0;
    $this->statusMessage .= empty($this->statusMessage)?$msgstr:'<br>'.$msgstr;
    $this->errorFlags[$type] = true;
  }

  /**
   * Show up error messages.
   */
  protected function _html_status_box() {
    $res = '<p class="'.(($this->status == 0)?'groupmail_error':'groupmail_success').'">'.$this->statusMessage.'</p>';
    $this->statusMessage = '';
    $this->errorFlags = array();
    return $res;
  }

  /**
   * Renders a form row.
   */
  protected function _form_row($label, $name, $type, $default='') {
    $value = (isset($_POST['submit-'.$this->formId]) && $this->status == 0)?$_POST[$name]:$default;
    $class = ($this->errorFlags[$name])?'class="error_field"':'';
    $row = '<label for="'.$name.'">'.$label.'</label>';
    if($type == 'textarea')
      $row .= '<textarea name="'.$name.'" wrap="on" cols="40" rows="6" '.$class.' required>'.$value.'</textarea>';
    elseif($type == 'multiple_email')
      $row .= '<input type="email" name="'.$name.'" '.$class.' multiple>';
    elseif($type == 'required_email')
      $row .= '<input type="email" name="'.$name.'" '.$class.' required>';
    else
      $row .= '<input type="'.$type.'" value="'.$value.'" name="'.$name.'" '.$class.'>';
    return $row;
  }

}
