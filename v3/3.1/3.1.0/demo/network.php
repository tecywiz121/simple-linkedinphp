<?php

/**
 * This file is used in conjunction with the 'Simple-LinkedIn' class, demonstrating 
 * the basic functionality and usage of the library.
 * 
 * COPYRIGHT:
 *   
 * Copyright (C) 2011, fiftyMission Inc.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a 
 * copy of this software and associated documentation files (the "Software"), 
 * to deal in the Software without restriction, including without limitation 
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, 
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in 
 * all copies or substantial portions of the Software.  
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING 
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS 
 * IN THE SOFTWARE.  
 *
 * SOURCE CODE LOCATION:
 * 
 *   http://code.google.com/p/simple-linkedinphp/
 *    
 * REQUIREMENTS:
 * 
 * 1. You must have cURL installed on the server and available to PHP.
 * 2. You must be running PHP 5+.  
 *  
 * QUICK START:
 * 
 * There are two files needed to enable LinkedIn API functionality from PHP; the
 * stand-alone OAuth library, and the Simple-LinkedIn library. The latest 
 * version of the stand-alone OAuth library can be found on Google Code:
 * 
 *   http://code.google.com/p/oauth/
 * 
 * The latest versions of the Simple-LinkedIn library and this demonstation 
 * script can be found here:
 * 
 *   http://code.google.com/p/simple-linkedinphp/
 *   
 * Install these two files on your server in a location that is accessible to 
 * this demo script. Make sure to change the file permissions such that your 
 * web server can read the files.
 * 
 * Next, make sure the path to the LinkedIn class below is correct.
 * 
 * Finally, read and follow the 'Quick Start' guidelines located in the comments
 * of the Simple-LinkedIn library file.   
 *
 * @version 3.1.0 - June 16, 2011
 * @author Paul Mennega <paul@fiftymission.net>
 * @copyright Copyright 2011, fiftyMission Inc. 
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License 
 */

/**
 * Session existance check.
 * 
 * Helper function that checks to see that we have a 'set' $_SESSION that we can
 * use for the demo.   
 */ 
function oauth_session_exists() {
  if((is_array($_SESSION)) && (array_key_exists('oauth', $_SESSION))) {
    return TRUE;
  } else {
    return FALSE;
  }
}

try {
  // include the LinkedIn class
  require_once('../linkedin_3.1.0.class.php');
  
  // start the session
  if(!session_start()) {
    throw new LinkedInException('This script requires session support, which appears to be disabled according to session_start().');
  }
  
  // display constants
  $API_CONFIG = array(
    'appKey'       => '<your application key here>',
	  'appSecret'    => '<your application secret here>',
	  'callbackUrl'  => NULL 
  );
  define('CONNECTION_COUNT', 20);
  define('PORT_HTTP', '80');
  define('PORT_HTTP_SSL', '443');
  define('UPDATE_COUNT', 10);

  // set index
  $_REQUEST[LINKEDIN::_GET_TYPE] = (isset($_REQUEST[LINKEDIN::_GET_TYPE])) ? $_REQUEST[LINKEDIN::_GET_TYPE] : '';
  switch($_REQUEST[LINKEDIN::_GET_TYPE]) {
 	  case 'comment':
      /**
       * Handle comment requests.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      if(!empty($_POST['nkey'])) {
        $response = $OBJ_linkedin->comment($_POST['nkey'], $_POST['scomment']);
        if($response['success'] === TRUE) {
          // comment posted
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // problem with comment
          echo "Error commenting on update:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        echo "You must supply a network update key to comment on an update.";
      }
      break;
      
    case 'initiate':
      /**
       * Handle user initiated LinkedIn connection, create the LinkedIn object.
       */
        
      // check for the correct http protocol (i.e. is this script being served via http or https)
      if($_SERVER['HTTPS'] == 'on') {
        $protocol = 'https';
      } else {
        $protocol = 'http';
      }
      
      // set the callback url
      $API_CONFIG['callbackUrl'] = $protocol . '://' . $_SERVER['SERVER_NAME'] . ((($_SERVER['SERVER_PORT'] != PORT_HTTP) || ($_SERVER['SERVER_PORT'] != PORT_HTTP_SSL)) ? ':' . $_SERVER['SERVER_PORT'] : '') . $_SERVER['PHP_SELF'] . '?' . LINKEDIN::_GET_TYPE . '=initiate&' . LINKEDIN::_GET_RESPONSE . '=1';
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      
      // check for response from LinkedIn
      $_GET[LINKEDIN::_GET_RESPONSE] = (isset($_GET[LINKEDIN::_GET_RESPONSE])) ? $_GET[LINKEDIN::_GET_RESPONSE] : '';
      if(!$_GET[LINKEDIN::_GET_RESPONSE]) {
        // LinkedIn hasn't sent us a response, the user is initiating the connection
        
        // send a request for a LinkedIn access token
        $response = $OBJ_linkedin->retrieveTokenRequest();
        if($response['success'] === TRUE) {
          // check the session
          if(!oauth_session_exists()) {
            throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
          }
          
          // split up the response and stick the LinkedIn portion in the user session
          $_SESSION['oauth']['linkedin']['request'] = $response['linkedin'];
        
          // redirect the user to the LinkedIn authentication/authorisation page to initiate validation.
          header('Location: ' . LINKEDIN::_URL_AUTH . $_SESSION['oauth']['linkedin']['request']['oauth_token']);
        } else {
          // bad token request
          echo "Request token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        // LinkedIn has sent a response, user has granted permission, take the temp access token, the user's secret and the verifier to request the user's real secret key
        $response = $OBJ_linkedin->retrieveTokenAccess($_SESSION['oauth']['linkedin']['request']['oauth_token'], $_SESSION['oauth']['linkedin']['request']['oauth_token_secret'], $_GET['oauth_verifier']);
        if($response['success'] === TRUE) {
          // the request went through without an error, gather user's 'access' tokens
          $_SESSION['oauth']['linkedin']['access'] = $response['linkedin'];
          
          // set the user as authorized for future quick reference
          $_SESSION['oauth']['linkedin']['authorized'] = TRUE;
            
          // redirect the user back to the demo page
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // bad token access
          echo "Access token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      }
      break;
      
    case 'revoke':
      /**
       * Handle authorization revocation.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      $response = $OBJ_linkedin->revoke();
      if($response['success'] === TRUE) {
        // revocation successful, clear session
        session_unset();
        $_SESSION = array();
        if(session_destroy()) {
          // session destroyed
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // session not destroyed
          echo "Error clearing user's session";
        }
      } else {
        // revocation failed
        echo "Error revoking user's token:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
      }
      break;  
  	
  	case 'invite':
      /**
       * Handle invitation messaging.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      if(!empty($_POST['invite_to_id'])) {
        // send invite via LinkedIn ID
        $response = $OBJ_linkedin->invite('id', $_POST['invite_to_id'], $_POST['invite_subject'], $_POST['invite_body']);
        if($response['success'] === TRUE) {
          // message has been sent
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // an error occured
          echo "Error sending invite:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } elseif(!empty($_POST['invite_to_email'])) {
        // send invite via email
        $recipient = array('email' => $_POST['invite_to_email'], 'first-name' => $_POST['invite_to_firstname'], 'last-name' => $_POST['invite_to_lastname']);
        $response = $OBJ_linkedin->invite('email', $recipient, $_POST['invite_subject'], $_POST['invite_body']);
        if($response['success'] === TRUE) {
          // message has been sent
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // an error occured
          echo "Error sending invite:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        // no email or id supplied
        echo "You must supply an email address or LinkedIn ID to send out the invitation to connect.";
      }
      break;
      
    case 'like':
      /**
       * Handle 'likes'.
       */             
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      if(!empty($_GET['nKey'])) {
        $response = $OBJ_linkedin->like($_GET['nKey']);
        if($response['success'] === TRUE) {
          // update 'liked'
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // problem with 'like'
          echo "Error 'liking' update:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        echo "You must supply a network update key to 'like' an update.";
      }
      break;
      
    case 'message':
      /**
       * Handle connection messaging.
       */
      if(!empty($_POST['connections'])) {
        // check the session
        if(!oauth_session_exists()) {
          throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
        }
      
        $OBJ_linkedin = new LinkedIn($API_CONFIG);
        $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
        
        if(!empty($_POST['message_copy'])) {
          $copy = TRUE;
        } else {
          $copy = FALSE;
        }
        $response = $OBJ_linkedin->message($_POST['connections'], $_POST['message_subject'], $_POST['message_body'], $copy);
        if($response['success'] === TRUE) {
          // message has been sent
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // an error occured
          echo "Error sending message:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        echo "You must select at least one recipient.";
      }
      break;
      
    case 'reshare':
      /**
       * Handle re-shares.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      
      // prepare content for sharing
      $content = array();
      if(!empty($_POST['scomment'])) {
        $content['comment'] = $_POST['scomment'];
      }
      if(!empty($_POST['sid'])) {
        $content['id'] = $_POST['sid'];
      }
      if(!empty($_POST['sprivate'])) {
        $private = TRUE;
      } else {
        $private = FALSE;
      }
      
      // re-share content
      $response = $OBJ_linkedin->share('reshare', $content, $private);
      if($response['success'] === TRUE) {
        // status has been updated
        header('Location: ' . $_SERVER['PHP_SELF']);
      } else {
        // an error occured
        echo "Error re-sharing content:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
      }
      break;
      
    case 'unlike':
      /**
       * Handle 'unlikes'.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $OBJ_linkedin = new LinkedIn($API_CONFIG);
      $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      if(!empty($_GET['nKey'])) {
        $response = $OBJ_linkedin->unlike($_GET['nKey']);
        if($response['success'] === TRUE) {
          // update 'unliked'
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // problem with 'unlike'
          echo "Error 'unliking' update:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
        }
      } else {
        echo "You must supply a network update key to 'unlike' an update.";
      }
      break;
      
    default:
      // nothing being passed back, display demo page
      
      // check PHP version
      if(version_compare(PHP_VERSION, '5.0.0', '<')) {
        throw new LinkedInException('You must be running version 5.x or greater of PHP to use this library.'); 
      } 
      
      // check for cURL
      if(extension_loaded('curl')) {
        $curl_version = curl_version();
        $curl_version = $curl_version['version'];
      } else {
        throw new LinkedInException('You must load the cURL extension to use this library.'); 
      }
      ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
        <head>
          <title>Simple-LinkedIn Demo &gt; Your Network</title>
          <meta name="author" content="Paul Mennega <paul@fiftymission.net>" />
          <meta name="copyright" content="Copyright 2010 - 2011, fiftyMission Inc." />
          <meta name="license" content="http://www.opensource.org/licenses/mit-license.php" />
          <meta name="description" content="A demonstration page for the Simple-LinkedIn PHP class." />
          <meta name="keywords" content="simple-linkedin,php,linkedin,api,class,library" />
          <meta name="medium" content="mult" />
          <meta name="viewport" content="width=device-width" />
          <meta http-equiv="Content-Language" content="en" />
          <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
          <style>
            body {font-family: Courier, monospace; font-size: 0.8em;}
            pre {font-family: Courier, monospace; font-size: 0.8em;}
          </style>
        </head>
        <body>
          <h1><a href="../demo.php ">Simple-LinkedIn Demo</a> &gt; <a href="<?php echo $_SERVER['PHP_SELF'];?>">Your Network</a></h1>
          
          <p>Copyright 2010 - 2011, Paul Mennega, fiftyMission Inc. &lt;paul@fiftymission.net&gt;</p>
          
          <p>Released under the MIT License - http://www.opensource.org/licenses/mit-license.php</p>
          
          <p>Full source code for both the Simple-LinkedIn class and this demo script can be found at:</p>
          
          <ul>
            <li><a href="http://code.google.com/p/simple-linkedinphp/">http://code.google.com/p/simple-linkedinphp/</a></li>
          </ul>          

          <hr />
          
          <p style="font-weight: bold;">Demo using: Simple-LinkedIn v<?php echo LINKEDIN::_VERSION;?>, cURL v<?php echo $curl_version;?>, PHP v<?php echo phpversion();?></p>
          
          <ul>
            <li>Please note: The Simple-LinkedIn class requires PHP 5+</li>
          </ul>
          
          <hr />
          
          <?php
          $_SESSION['oauth']['linkedin']['authorized'] = (isset($_SESSION['oauth']['linkedin']['authorized'])) ? $_SESSION['oauth']['linkedin']['authorized'] : FALSE;
          if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
            ?>
            <ul>
              <li><a href="#manage">Manage LinkedIn Authorization</a></li>
              <li><a href="demo.php#application">Application Information</a></li>
              <li><a href="demo.php#profile">Your Profile</a></li>
              <li><a href="#network">Your Network</a>
                <ul>
                  <li><a href="#network_stats">Stats</a></li>
                  <li><a href="#network_connections">Your Connections</a></li>
                  <li><a href="#network_invite">Invite Others to Join your LinkedIn Network</a></li>
                  <li><a href="#network_updates">Recent Connection Updates</a></li>
                  <li><a href="#peopleSearch">People Search</a></li>
                </ul>
              </li>
            </ul>
            <?php
          } else {
            ?>
            <ul>
              <li><a href="#manage">Manage LinkedIn Authorization</a></li>
            </ul>
            <?php
          }
          ?>
          
          <hr />
          
          <h2 id="manage">Manage LinkedIn Authorization:</h2>
          <?php
          if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
            // user is already connected
            $OBJ_linkedin = new LinkedIn($API_CONFIG);
            $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
            ?>
            <form id="linkedin_revoke_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="get">
              <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="revoke" />
              <input type="submit" value="Revoke Authorization" />
            </form>
            
            <hr />
          
            <h2 id="network">Your Network:</h2>
            
            <h3 id="network_stats">Stats:</h3>
            
            <?php
            $response = $OBJ_linkedin->statistics();
            if($response['success'] === TRUE) {
              $response['linkedin'] = new SimpleXMLElement($response['linkedin']);
              echo "<pre>" . print_r($response['linkedin'], TRUE) . "</pre>"; 
            } else {
              // statistics retrieval failed
              echo "Error retrieving network statistics:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response) . "</pre>";
            } 
            ?>
            
            <hr />
            
            <h3 id="network_connections">Your Connections:</h3>
            
            <?php
            $response = $OBJ_linkedin->connections('~/connections:(id,first-name,last-name,picture-url)?start=0&count=' . CONNECTION_COUNT);
            if($response['success'] === TRUE) {
              $connections = new SimpleXMLElement($response['linkedin']); 
              if((int)$connections['total'] > 0) {
                ?>
                <p>First <?php echo CONNECTION_COUNT;?> of <?php echo $connections['total'];?> total connections being displayed:</p>

                <form id="linkedin_cmessage_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
                  <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="message" />
                  <?php
                  foreach($connections->person as $connection) {
                    ?>
                    <div style="float: left; width: 150px; border: 1px solid #888; margin: 0.5em; text-align: center;">
                      <?php
                      if($connection->{'picture-url'}) {
                        ?>
                        <img src="<?php echo $connection->{'picture-url'};?>" alt="" title="" width="80" height="80" style="display: block; margin: 0 auto; padding: 0.25em;" />
                        <?php
                      } else {
                        ?>
                        <img src="../anonymous.png" alt="" title="" width="80" height="80" style="display: block; margin: 0 auto; padding: 0.25em;" />
                        <?php
                      }
                      ?>
                      <input type="checkbox" name="connections[]" id="connection_<?php echo $connection->id;?>" value="<?php echo $connection->id;?>" />
                      <label for="connection_<?php echo $connection->id;?>"><?php echo $connection->{'first-name'};?></label>
                      <div><?php echo $connection->id;?></div>
                    </div>
                    <?php
                  }
                  ?>
                  
                  <br style="clear: both;" />
              
                  <h4 id="network_connections_message">Send a Message to the Checked Connections Above:</h4>
                  
                  <div style="font-weight: bold;">Subject:</div>            
                  <input type="text" name="message_subject" id="message_subject" length="255" maxlength="255" style="display: block; width: 400px;" />
                  
                  <div style="font-weight: bold;">Message:</div>
                  <textarea name="message_body" id="message_body" rows="4" style="display: block; width: 400px;"></textarea>
                  <input type="submit" value="Send Message" /><input type="checkbox" value="1" name="message_copy" id="message_copy" checked="checked" /><label for="message_copy">copy self on message</label>
                  
                  <p>(Note, any HTML in the subject or message bodies will be stripped by the LinkedIn->message() method)</p>
                
                </form>
                <?php
              } else {
                // no connections
                echo '<div>You do not have any LinkedIn connections to display.</div>';
              }
            } else {
              // connections retrieval failed
              echo "Error retrieving connections:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response) . "</pre>";
            }
            ?>           
            
            <hr />

            <h3 id="network_invite">Invite Others to Join your LinkedIn Network:</h3>
            <form id="linkedin_imessage_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
              <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="invite" />
   
              <div style="font-weight: bold;">By Email Address and Name:</div>            
              <input type="text" name="invite_to_email" id="invite_to_email" length="255" maxlength="255" style="display: block; width: 400px;" value="Email" />
              <input type="text" name="invite_to_firstname" id="invite_to_firstname" length="255" maxlength="255" style="display: block; width: 400px;" value="First Name" />
              <input type="text" name="invite_to_lastname" id="invite_to_lastname" length="255" maxlength="255" style="display: block; width: 400px;" value="Last Name" />
              
              <div style="font-weight: bold;">Or By LinkedIn ID:</div> 
              <input type="text" name="invite_to_id" id="invite_to_id" length="255" maxlength="255" style="display: block; width: 400px;" />
  
              <div style="font-weight: bold;">Subject:</div>            
              <input type="text" name="invite_subject" id="invite_subject" length="255" maxlength="255" style="display: block; width: 400px;" value="<?php echo LINKEDIN::_INV_SUBJECT;?>" />
              
              <div style="font-weight: bold;">Message:</div>
              <textarea name="invite_body" id="invite_body" rows="4" style="display: block; width: 400px;"></textarea>
              <input type="submit" value="Send Invitation" />
              
              <p>(Note, any HTML in the subject or message bodies will be stripped by the LinkedIn->invite() method)</p>
  
            </form>
            
            <hr />
            
            <h3 id="network_updates">Recent Connection Updates: (last <?php echo UPDATE_COUNT;?>, shared content only)</h3>
            
            <?php
            $query    = '?type=SHAR&count=' . UPDATE_COUNT;
            //$response = $OBJ_linkedin->updates($query);
            if($response['success'] === TRUE) {
              $updates = new SimpleXMLElement($response['linkedin']);
              if((int)$updates['total'] > 0) {
                foreach($updates->update as $update) {
                  $person = $update->{'update-content'}->person;
                  $share  = $update->{'update-content'}->person->{'current-share'};
                  ?>
                  <div style=""><span style="font-weight: bold;"><a href="<?php echo $person->{'site-standard-profile-request'}->url;?>"><?php echo $person->{'first-name'} . ' ' . $person->{'last-name'} . '</a></span> ' . $share->comment;?></div>
                  <?php
                  if($share->content) {
                    ?>
                    <div style="width: 400px; margin: 0.5em 0 0.5em 2em;"><a href="<?php echo $share->content->{'submitted-url'};?>"><?php echo $share->content->title;?></a></div>
                    <div style="width: 400px; margin: 0.5em 0 0.5em 2em;"><?php echo $share->content->description;?></div>
                    <div style="margin: 0.5em 0 0.5em 2em;"><span style="font-weight: bold;">Share this content with your network:</span>
                      <form id="linkedin_reshare_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
                        <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="reshare" />
                        <input type="hidden" id="sid" name="sid" value="<?php echo $share->id;?>" />
                        <textarea name="scomment" id="scomment_<?php echo $share->id;?>" rows="4" style="display: block; width: 400px;"></textarea>
                        <input type="submit" value="Re-Share Content" /><input type="checkbox" value="1" name="sprivate" id="sprivate_<?php echo $share->id;?>" checked="checked" /><label for="rsprivate">re-share with your connections only</label>
                      </form>
                    </div>
                    <?php
                  }
                  ?>
                  <div style="margin: 0.5em 0 0 2em;">
                    <?php
                    if($update->{'is-likable'} == 'true') {
                      if($update->{'is-liked'} == 'true') {
                        echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . LINKEDIN::_GET_TYPE . '=unlike&amp;nKey=' . $update->{'update-key'} . '">Unlike</a> (' . $update->{'num-likes'} . ')';
                      } else {
                        echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . LINKEDIN::_GET_TYPE . '=like&amp;nKey=' . $update->{'update-key'} . '">Like</a> (' . $update->{'num-likes'} . ')';
                      }
                      if($update->{'num-likes'} > 0) {
                        $likes = $OBJ_linkedin->likes((string)$update->{'update-key'});
                        if($likes['success'] === TRUE) {
                          $likes['linkedin'] = new SimpleXMLElement($likes['linkedin']);
                          echo "<pre>" . print_r($likes['linkedin'], TRUE) . "</pre>";
                        } else {
                          // likes retrieval failed
                          echo "Error retrieving likes:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($likes) . "</pre>";
                        }
                      }
                    }
                    ?>
                  </div>
                  <div style="margin: 0.5em 0 0 2em;">
                    <?php
                    if($update->{'is-commentable'} == 'true') {
                      if($update->{'update-comments'}) {
                        // there are comments for this update
                        echo $update->{'update-comments'}['total'] . ' Comment(s)';
                        
                        $comments = $OBJ_linkedin->comments((string)$update->{'update-key'});
                        if($comments['success'] === TRUE) {
                          $comments['linkedin'] = new SimpleXMLElement($comments['linkedin']);
                          echo "<pre>" . print_r($comments['linkedin'], TRUE) . "</pre>";
                        } else {
                          // comments retrieval failed
                          echo "Error retrieving comments:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($comments) . "</pre>";
                        }
                      } else {
                        // no comments for this update
                        echo 'No Comments';
                      }
                      ?>
                      <div style="margin: 0 0 0 2em;">
                        <form id="linkedin_comment_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
                          <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="comment" />
                          <input type="hidden" id="nkey" name="nkey" value="<?php echo $update->{'update-key'};?>" />
                          <textarea name="scomment" id="scomment_<?php echo $share->id;?>" rows="4" style="display: block; width: 400px;"></textarea>
                          <input type="submit" value="Post Comment" />
                        </form>
                      </div>
                      <?php
                    }
                    ?>
                  </div>
                  <div style="border-bottom: 1px dashed #000; margin: 1em 0;"></div>
                  <?php
                }
              } else {
                // no connection updates
                echo '<div>There are no recent connection updates to display.</div>';
              }
            } else {
              // update retrieval failed
              echo "Error retrieving updates:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response) . "</pre>";                
            }
            ?>
            
            <hr />
            
            <h2 id="peopleSearch">People Search:</h2>
            
            <!--  <p>1st degree connections living in the San Francisco Bay Area (returned in JSON format):</p>-->
            
            <?php
            $OBJ_linkedin->setResponseFormat(LINKEDIN::_RESPONSE_JSON);
            $keywords = (isset($_GET['keywords'])) ? $_GET['keywords'] : "Marketing";
            ?>
            <form action="<?php echo $_SERVER['PHP_SELF'];?>#peopleSearch" method="get">
            	Search by Keywords: <input type="text" value="<?php echo $keywords?>" name="keywords" /><input type="submit" value="Search" />
            </form>
            <?php 
            $query    = '?keywords='.$keywords;
            $response = $OBJ_linkedin->searchPeople($query);
            if($response['success'] === TRUE) {
              echo "<pre>" . print_r($response['linkedin'], TRUE) . "</pre>";
            } else {
              // people search retrieval failed
              echo "Error retrieving people search results:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response) . "</pre>";                
            }
            ?>
            
            <hr />
            <?php
          } else {
            // user isn't connected
            ?>
            <form id="linkedin_connect_form" action="<?php echo $_SERVER['PHP_SELF'];?>" method="get">
              <input type="hidden" name="<?php echo LINKEDIN::_GET_TYPE;?>" id="<?php echo LINKEDIN::_GET_TYPE;?>" value="initiate" />
              <input type="submit" value="Connect to LinkedIn" />
            </form>
            <?php
          }
          ?>
        </body>
      </html>
      <?php
      break;
  }
} catch(LinkedInException $e) {
  // exception raised by library call
  echo $e->getMessage();
}

?>