<?php
/*
Plugin Name: Camazee
Plugin URI: http://camazee.com
Description: With <a href="http://camazee.com" target="_blank">Camazee</a> plugin you can easily add video chat into your website.
Version: 1.2
Author: SoftService
Author URI: http://softservice.org
*/

// this is the function that outputs the background as a style tag in the <head> 
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
      
      
class CamazeePlugin {
  
  static public $serverUrl = 'http://camazee.com';
  static public $exec = '/exec';
  static public $basename;
  
  static public function no_magic_quotes() {
    $data = explode("\\",$query);
    $cleaned = implode("",$data);
    return $cleaned;
  }
  
  static public function do_ajaj_request($url, $data_ar)
  {
    $response = @file_get_contents($url . '?' . http_build_query($data_ar));
    return json_decode($response, true);
  }
  
  static public function activation_notice()
  {
    global $wv_options;
    if ($wv_options['activated']!='activated'){
      print(
        '<div class="error fade" style="background-color:#FFFFE0;">' .
        '<p>' .
        '<b>' .
        'Your Camazee plugin installation is incomplete. Please complete it on the <a style="color:#21759B;" href="' . admin_url( 'options-general.php?page=camazee-control' ) . '" >settings page</a>.' .
        '</b>' .
        '<br />' .
        '</p>' .
        '</div>'
      );
    }
  }
  
  /**
  * Put out the styling inside head tag.
  */
  static public function control_header() 
  {
    $current_user = wp_get_current_user();
    print ('<script type="text/javascript">');
    print ("var wv_info = {'screenName': '{$current_user->user_login}'};");
    print ('</script>');    
  }
  
  static public function control_config_page() 
  {
    if ( function_exists('add_submenu_page') ) {
      add_submenu_page('options-general.php', __('Camazee Configuration'), __('Camazee'), 'manage_options', 'camazee-control', array('CamazeePlugin', 'control_conf'));
    }
  }
  
  /**
  * This is the function that outputs our configuration page
  */
  static public function control_conf() {
    $wv_exec_url = CamazeePlugin::$serverUrl . CamazeePlugin::$exec;
    $options = get_option('camazee_control_configuration');
                                            
    if (!empty($_POST['camazee-control-submit-li'])) {

      $resp = CamazeePlugin::do_ajaj_request($wv_exec_url.'/plugin/ajaj-gateway.jsp', array('cmd'=>'getIntegrationKey', 'name'=>$_POST['name'], 'passw'=>$_POST['passw']));
      
      if ($resp['res'] !== false)
      {
        $options['user'] = $_POST['name'];
        $options['integrationKey'] = $resp['integrationKey'];
        $options['broadcasterId'] = $resp['broadcasterId'];
      }
      else
      {
        $error_msg = "Wrong password, please try again.";
        unset($options['integrationKey'], $options['broadcasterId']);
      }
    }
    
    if ($options['integrationKey']) {
      
      $wv_options = get_option('wv_options');
      if ($options['integrationKey']!="")
      {

        if ($wv_options['activated']!="activated")
        {
          print(
            '<script type="text/javascript">' .
            'window.location.replace(window.location)' .
            '</script>'
          );
        }
        $wv_options['activated'] = "activated";
        
      }
      else
      {
        if ($wv_options['activated']=="activated")
        {
          print(
            '<script type="text/javascript">' .
            'window.location.replace(window.location)' .
            '</script>'
          );
        }
        $wv_options['activated'] = "0";
      }
      update_option('wv_options', $wv_options);
      update_option('camazee_control_configuration', $options);
      
    }
    
    echo <<<HTML
    <div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>Camazee Settings</h2>
HTML;
    $ref_url = get_home_url();
    echo <<<JS
      <script type="text/javascript">          
      function CamazeePlugin() {}
      
      CamazeePlugin.onSubmitRegistrationForm = function(form) 
      {
        var formResultContainer = jQuery('#wv-result'),
            arr = jQuery(form).serializeArray(),
            req_data = {
              "performer_custom_ref_url": "{$ref_url}",
              "cmd": "signupQuiet"
            };

        for (var i=0; i<arr.length; i++) {
          var k = arr[i].name;
          req_data[k] = jQuery.trim(arr[i].value);
        }
        jQuery.ajax({
          "dataType": "jsonp",
          "type": "POST",
          "url": "{$wv_exec_url}/plugin/ajaj-gateway.jsp?callback=?",
          "data": req_data,
          "success": function(obj) {
            if (obj.res) {
              //login
              formResultContainer.html("");
              var formLogin = document.forms["frm-camazee-login"];
              formLogin.elements["name"].value = form.elements["name"].value;
              formLogin.elements["passw"].value = form.elements["passw"].value;
              formLogin.submit();
            } else {
              //error
              formResultContainer.html('<div class="error"><p>' + obj.msg + '</p></div>');
            }
          },
          "error": function() {
            formResultContainer.html('<div class="error"><p>Connection timeout. Please try later.</p></div>');
          }
        });
        return false;
 
      };

      CamazeePlugin.showRegistrationForm = function() 
      {
        jQuery('#wv_broadcast_div').hide();
        jQuery('#wv_login_div').show();
      };

      CamazeePlugin.startBroadcast = function() 
      {
        var formResultContainer = jQuery('#wv-result'),
            req_data = {
              "broadcasterId": "{$options['broadcasterId']}",
              "integrationKey": "{$options['integrationKey']}",
              "cmd": "loginByIntegrationKey"
            };

        jQuery.ajax({
          "dataType": "jsonp",
          "type": "GET",
          "url": "{$wv_exec_url}/plugin/ajaj-gateway.jsp?callback=?",
          "data": req_data,
          "success": function(obj) {
            if (obj.res) {
              //login
              formResultContainer.html("");
              window.open(obj.redirectUrl, 'wv_plugin_broadcast', 'channelmode=no,directories=no,fullscreen=no,location=no,menubar=no,resizable=no,scrollbars=no,status=no,titlebar=no,toolbar=no,left=50,top=50,width='+obj.windowWidth+',height='+obj.windowHeight);
            } else {
              //error
              formResultContainer.html('<div class="error"><p>' + obj.msg + '</p></div>');
            }
          },
          "error": function() {
            formResultContainer.html('<div class="error"><p>Connection timeout. Please try later.</p></div>');
          }
        });
        return false;
 
      };
      </script>  
JS;

    print(
      '<div id="wv-result">' .
      (isset($error_msg) ? '<div class="error"><p>'.$error_msg.'</p></div>' : '') .
      '</div>'
    );
    
    if ($options['integrationKey']!="")
    {
      $options['activated'] = "activated";
      $wv_options['activated'] = "activated";
      
      $chat_code = <<<HTML
<script type="text/javascript" src="{$wv_exec_url}/plugin/loader.jsp?cmd=wordpress&broadcasterId={$options['broadcasterId']}&integrationKey={$options['integrationKey']}"></script>
HTML;
$chat_code = htmlentities($chat_code);
      
      echo <<<HTML
      <div id="wv_broadcast_div">
      <p>
      <b style="color:green;">The Camazee plugin is successfully configured.</b>
      <br />
      To use a different Camazee account with your plugin, please click <a href="#" onclick="CamazeePlugin.showRegistrationForm(); return false;">here</a>.
      </p>                                                            
      <a class="button-primary" href="{$wv_exec_url}/performer/broadcast" target="_blank">Click here to Start Broadcast</a>
      <br /><br />
      You can place following HTML code into any Page of Wordpress to insert video chat there.
      <br />
      <textarea style="width:600px; height:60px;" onclick="this.select();">{$chat_code}</textarea>
      </div>
HTML;

    }
    else
    {
      $options['activated'] = "0";
      $wv_options['activated'] = "0";
    }
    //begin html forms
    print(
      '<div id="wv_login_div"' .
      (isset($options['integrationKey']) ? ' style="display:none;"' : '') .
      '>'
    );
    echo <<<HTML
         <span><b>Please log-in to Camazee to complete the setup, or sign-up below.</b></span>
         <br />
          
          <form name="frm-camazee-login" action="" method="post">
            <input type="hidden" name="camazee-control-submit-li" value="1" />
            <TABLE BORDER="0" style='font-family: Verdana;font-size: 13px;'>
              <TR>
              <TD>Username: </TD>
              <TD>
                <input type="text" name="name" /><br />
              </TD>
              </TR>
              <TR>
              <TD>Password: </TD>
              <TD>
                <input type="password" name="passw" /><br />
              </TD>
              </TR>
            </table>
            <input type="submit" class="button-primary" value="Log-in" />
          </form>
          <br />
          <br />
          <b>New user? Please sign-up for a FREE Camazee account:</b><br />
          <b>* required fields. </b>
          <br />                                    
          <form action="" method="post" onsubmit="return CamazeePlugin.onSubmitRegistrationForm(this); ">
            <TABLE BORDER="0" style='font-family: Verdana;font-size: 13px;'>
              <TR>
              <TD>Username*: </TD>
              <TD>
                <input type="text" name="name" /><br />
              </TD>
              </TR>
              <TR>
              <TD>Email*: </TD>
              <TD>
                <input type="text" name="email" /><br />
              </TD>
              </TR>
              <TR>
              <TD>Password*: </TD>
              <TD>
                <input type="password" name="passw" /><br />
              </TD>
              </TR>
            </table>
            <input type="submit" class="button-primary" value="Sign-up" />
          </form>
          <br /><br />Further instructions on how to use Camazee will be sent to you by email.<br />
          <p>For any assistance please contact <a href="mailto:support@softservice.org">support@softservice.org</a></p>
      
      </div>
HTML;
    //end html forms
    print(
      '</div>'
    );
  }
  
  /**
  * This is the function that adds a configuration page to settings menu group
  */
  static public function control_init() 
  {
    CamazeePlugin::$basename = plugin_basename(__FILE__);
    add_action('admin_menu', array('CamazeePlugin', 'control_config_page'));
    add_filter('plugin_action_links', array('CamazeePlugin', 'manage_link'), 10, 2);
    add_filter('plugin_row_meta',     array('CamazeePlugin', 'meta_links'), 10, 2);
  }
  
  /**
  * Adds the manage link in the plugins list
  */
  static public function manage_link($links, $file)
  {
    if ($file == CamazeePlugin::$basename) {
      $settings_link = '<a href="options-general.php?page=camazee-control">' . __('Settings') . '</a>';
      array_unshift($links, $settings_link);
    }
    return $links;
  }
  
  /**
  * Adds links to the plugins manager page
  */
  static public function meta_links($links, $file) {
    if ($file == CamazeePlugin::$basename) {
      $links[] = '<a href="' . CamazeePlugin::$serverUrl . CamazeePlugin::$exec  . '/sign-in" target="_blank">' . __('Login') . '</a>';
      $links[] = '<a href="' . CamazeePlugin::$serverUrl . CamazeePlugin::$exec  . '/performer/broadcast" target="_blank">' . __('Start Broadcasting') . '</a>';
    }
    return $links;
  }

}

global $wv_options;
$wv_options = get_option('wv_options');


if( $wv_options['activated'] != 'activated' || $_POST['activated'] != 'activated') {
  add_action( 'admin_notices', array('CamazeePlugin', 'activation_notice'));
}

add_action('init',    array('CamazeePlugin', 'control_init'));
add_action('wp_head', array('CamazeePlugin', 'control_header'));

?>