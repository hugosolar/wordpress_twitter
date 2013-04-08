<?php
/*
Plugin Name: oAuth Twitter object
Plugin URI: http://hugosolar.net
Description: Connects Wordpress with twitter API 1.1 using oAuth PHP Library from Abraham Williams
Version: 0.1
Author: Hugo Solar
Author URI: http://www.hugosolar.net
License: GPL2
*/

//Require twitteroauth library from Abraham Williams
require_once('oauth/twitteroauth.php');

//Configure your consumer key and consumer secret
define('CONSUMER_KEY', 'TAYMbcrcPWai5qyHlPBbg');
define('CONSUMER_SECRET', 'cLCrkWWfbHHs3PwDoHCmJQqnXm0gOVYpLO3XU7hlQ');

/**
* oAuth Twitter Class
* This class authenticate with twitter to get the current user timeline (original singleton class by Felipe Lavin (@felipelavinz))
* @author Hugosolar.net hola@hugosolar.net
* @version 0.1
* 
*/
class oAuth_twitter {
    const version = 13;
    private static $instance;
    public $settings;
    private function __construct(){
        $this->setupActions();
        
        $this->settings = get_option(__CLASS__ .'_twit_settings');
    }
    public static function getInstance(){
        if ( !isset(self::$instance) ){
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }
    public function __clone(){
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }
    private function setupActions(){
        if ( is_admin() ) :
            add_action('admin_menu', array($this, 'theme_admin'));
            add_action('admin_init', array($this, 'save_theme_settings'));
            add_action('init',array($this,'check_oauth'),1);
        endif;
        add_shortcode( 'twitter', array($this,'loop_timeline') );
    }
    
    function theme_admin(){
        add_options_page('Conectar Twitter', 'Conectar Twitter', 'edit_others_posts', 'twit_settings', array($this, 'twit_settings'));
    }
    
    function twit_settings(){
        global $id_base, $name_base;
        $data = get_option(__CLASS__ .'_twit_settings');
        $id_base = str_replace('::', '_', __METHOD__ ) .'_';
        $name_base = __CLASS__ .'['. __FUNCTION__ .']';

        define('OAUTH_CALLBACK', admin_url('options-general.php?page=twit_settings&action=twitter_callback'));        

        function theme_settings_get_id($id){
            global $id_base;
            return  $id_base . $id;
        }
        function theme_settings_get_name($name){
            global $name_base;
            return $name_base .'['. $name .']';
        }
         if ($_GET['action'] == 'twitter_bye') {
            unset($_SESSION['oauth_token']);
            unset($_SESSION['oauth_token_secret']);
            delete_option( 'twitter_token' );
            delete_transient( 'twitter_timeline' );
            echo '<div class="updated"><p>Desconectado de Twitter</p></div>';
        }
        if ($_GET['action'] == 'twitter_callback') {
            $trans = get_transient( 'oauth_session' );
            if (isset($_REQUEST['oauth_token']) && $trans['oauth_token'] !== $_REQUEST['oauth_token']) {
              $trans['oauth_status'] = 'oldtoken';
            
              unset($trans['oauth_token']);
              unset($trans['oauth_token_secret']);

            }
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $trans['oauth_token'], $trans['oauth_token_secret']);
            $access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
            $_SESSION['access_token'] = $access_token;
            unset($trans['oauth_token']);
            unset($trans['oauth_token_secret']);

            if (200 == $connection->http_code) {
              /* Verified user */
              $_SESSION['status'] = 'verified';
              $access_token = $_SESSION['access_token'];
              update_option('twitter_token',$access_token);
              $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);

              /*
              * Now we can query the API
              * you can change the first query if you like
              * https://dev.twitter.com/docs/api/1.1
              */
              $content = $connection->get('statuses/user_timeline');
              
              //Transient the object returned for 10 minutes
              set_transient('twitter_timeline',$content,60*10);
              echo '<div class="updated"><p>Conectado Correctamente con Twitter</p></div>';
            
            } else {
              /* !200 -> Error.*/
              echo '<div class="error"><p>Error obteniendo credenciales de Twitter</p></div>';
            }
        }

        echo '<div class="wrap">';

            echo '<div id="icon-themes" class="icon32"><br /></div>';
            echo '<h2>Twitter oAuth</h2>';
            echo '<form action="'. admin_url('options-general.php?page=twit_settings') .'" method="post">';
                echo '<table class="form-table">';
                    
                    echo '<tr>';
                            echo '<th><label for="'. theme_settings_get_id('twitter') .'">Twitter</label></th>';
                    $twitter_option = get_option('twitter_token');
                    if (empty($twitter_option)) {
                            echo '<td><a href="'.admin_url('options-general.php?page=twit_settings&action=authorize_twitter').'" class="button">Identificarse con twitter</a></td>';
                       
                    } else {
                        $logged_in = $this->get_my_info();
                        echo '<td>Logeado como: '.$logged_in->screen_name.' <a href="'.admin_url('options-general.php?page=twit_settings&action=twitter_bye').'" class="button">Desconectar</a></td>';                        
                    }
                     echo '</tr>';
                     if (!empty($twitter_option)) :
                     echo '<tr>';
                        echo '<th><label for="name="'.theme_settings_get_id('twitter_screen_name').'">Búsqueda:</label></th>';
                        echo '<td>';
                            echo '<input type="text" name="'.theme_settings_get_name('twitter_screen_name').'" name="'.theme_settings_get_id('twitter_screen_name').'" value="'.$data['twitter_screen_name'].'">';
                            echo '<br><small>Se puede buscar en la cuenta de un usuario (ej. @usuario) o en hashtags (ej. #mbqlp ) o cualquier palabra, si no se especifica, retornará datos del TL de la cuenta logeada </small>';
                        echo '</td>';
                     echo '</tr>';
                     endif;
                     
                echo '</table>';
                echo '<p class="submit">';
                    echo '<input type="hidden" name="action" value="'. __METHOD__ .'" />';
                    wp_nonce_field(__METHOD__, 'twit_settings_nonce');
                    echo '<input type="submit" value="Guardar" class="button-primary" />';
                echo '</p>';
            echo '</form>';
        echo '</div>';
    }
    function save_theme_settings(){
        
        if ( isset($_POST['action']) && $_POST['action'] === __CLASS__ .'::twit_settings' ) {

            if ( wp_verify_nonce($_POST['twit_settings_nonce'], __CLASS__ .'::twit_settings') ) {

                delete_transient( 'twitter_timeline' );
                foreach ( $_POST[__CLASS__]['twit_settings'] as $k => $v ){
                    if ( is_string($v) ) $_POST[__CLASS__]['twit_settings'][$k] = stripcslashes($v);
                }

                update_option(__CLASS__ .'_'. twit_settings, $_POST[__CLASS__]['twit_settings']);
            }
        }
    }
    function check_oauth() {
    if ( current_user_can( 'manage_options' ) && is_admin() ) {

        if ($_GET['action'] == 'authorize_twitter') {            
            //session_start();
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
            $request_token = $connection->getRequestToken(admin_url('options-general.php?page=twit_settings&action=twitter_callback'));
            
            $_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
            $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
            set_transient( 'oauth_session', $request_token, 60*10 );
            switch ($connection->http_code) {
              case 200:
                $url = $connection->getAuthorizeURL($token);               
                //wp_redirect($url,301);
                header('Location:'.$url);
                exit();
                break;
              default:
                echo '<div class="error"><p>No se ha podido conectar con Twitter, por favor intenta denuevo.</p></div>';
            }
        }
    }
}
/**
* Twitter parse
* Parse links, hashtags and usernames based on the Saturnboy function (http://saturnboy.com/2010/02/parsing-twitter-with-regexp/)
* @author saturnboy
*/
function twitter_parse($text) {
    //parse links
    $text = preg_replace('@(https?://([-\w\.]+)+(/([\w/_\.]*(\?\S+)?(#\S+)?)?)?)@', ' <a href="$1">$1</a> ', $text);
    //parse users
    $text = preg_replace('/@(\w+)/', ' <a href="http://twitter.com/$1">@$1</a> ', $text);
    //parse hashtag
    $text = preg_replace('/\s#(\w+)/', ' <a href="http://search.twitter.com/search?q=%23$1">#$1</a> ', $text);

    return $text;
}
/**
* Function twitter_time
* parse twitter time (http://webcodingeasy.com/PHP/Convert-twitter-createdat-time-format-to-ago-format)
*/
function twitter_time($a) {    
    //get current timestampt
    $b = strtotime("now");
    //get timestamp when tweet created
    $c = strtotime($a);
    //get difference
    $d = $b - $c;
    //calculate different time values
    $minute = 60;
    $hour = $minute * 60;
    $day = $hour * 24;
    $week = $day * 7;

    if(is_numeric($d) && $d > 0) {
        //if less then 3 seconds
        if($d < 3) return "ahora";
        //if less then minute
        if($d < $minute) return "hace ". floor($d) . " segundos";
        //if less then 2 minutes
        if($d < $minute * 2) return "hace 1 minuto";
        //if less then hour
        if($d < $hour) return "hace ". floor($d / $minute) . " minutos";
        //if less then 2 hours
        if($d < $hour * 2) return "hace 1 hora";
        //if less then day
        if($d < $day) return "hace ". floor($d / $hour) . " horas";
        //if more then day, but less then 2 days
        if($d > $day && $d < $day * 2) return "ayer";
        //if less then year
        if($d < $day * 365) return "hace ". floor($d / $day) . " días";
        //else return more than a year
        return "mas de 1 año";
    }    
}
/**
* Loop timeline
* loops a specific timeline
* @param string $user user screen name
* @return hFeed layout looping the specific timeline
* @author hugosolar
*/
function loop_timeline($atts) {
    extract( shortcode_atts( array(
        'user' => null,
    ), $atts ) );
    $args = array();

    if (!empty($user)):        
        $twits = $this->get_twitter_user_timeline($user);
    else:
        $twits = $this->get_twitter_timeline();
    endif;
    /*
    * twits loop
    */
    $out ='';
    $out .= '<div class="twitts-entries">';
    foreach($twits as $twit):
        $out .= '<div class="hentry">';
            $out .= '<h5 class="entry-title">'.$this->twitter_parse($twit->text).'</h5>';
            $out .= '<span class="published">'.$this->twitter_time($twit->created_at).'</span>';
        $out .= '</div>';
    endforeach;
    $out .= '</div>';
    
    return $out;
    
}
/**
* Search in twitter
* Perform a search in twitter and transient its result
* @param string $q search string to find in twitter (could be @user or #hastag or just a string)
* @return Twitter search API object
* @author hugosolar
*/
function search($search_id,$q) {
    
    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'twitter_search_'.$search_id );
        if (false === get_transient('twitter_search_'.$search_id)) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            
            $content = $connection->get('search/tweets',array('q' => urlencode($q)) );
            set_transient('twitter_search_'.$search_id,$content,60*10);
        }

        if (!empty($content)) {
            return $content;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
/**
* Auto search in twitter
* Perfomr a search in twitter with the specified config data and transient its result
* @return Twitter search API object
* @author hugosolar
*/
function auto_search() {
    
    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'twitter_timeline' );
        if (false === get_transient('twitter_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            
            $content = $connection->get('search/tweets',array('q' => urlencode($q)) );
            set_transient('twitter_timeline',60*10);
        }

        if (!empty($content)) {
            return $content->statuses;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
/**
* Get twitter recent tweet
* get the last tweet of a timeline search or a twitter search
* @return Twitter search API object first element (last post)
* @author hugosolar
*/
function get_twitter_recent() {
    
    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'twitter_timeline' );
        if (false === get_transient('twitter_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            if (!empty($this->settings['twitter_screen_name'])) {
                $content = $connection->get('search/tweets',array('q' => urlencode($this->settings['twitter_screen_name'])) );
            } else {
                $content = $connection->get('statuses/user_timeline');    
            }
            
            set_transient('twitter_timeline',$content,60*10);
        }

        if (!empty($content)) {
            //if the search is activated, return a diferent object 
            if (!empty($this->settings['twitter_screen_name']))
                $return_tweet = $content->statuses[0];
            else
                $return_tweet = $content[0];

            return $return_tweet;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
/**
* Get my info
* get the object with the user info of the logged account and transients it
* @return Twitter user object from the last post
* @author hugosolar
*/
function get_my_info() {
    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'current_timeline' );
        if (false === get_transient('current_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            $content = $connection->get('statuses/user_timeline');
            set_transient('current_timeline',$content,60*10);
        }
        if (!empty($content)) {            
            return $content[0]->user;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
/**
* Get user info
* get the object with the user info of the specified user account and transients it
* @param string $user screen_name from user
* @return Twitter user object from the last post
* @author hugosolar
*/
function get_user_info($user) {
    $option = get_option('twitter_token');
    if (!empty($user)):
        if (!empty($option)) {
            $content = get_transient( 'twitter_'.$user.'_timeline' );
            if (false === get_transient('twitter_'.$user.'_timeline')) {
                $access_token = get_option('twitter_token');
                $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
                $content = $connection->get('statuses/user_timeline',array('screen_name'=>$user));
                set_transient('twitter_'.$user.'_timeline',$content,60*30);
            }
            if (!empty($content)) {
                return $content[0]->user;
            } else {
                return null;
            }
        } else {
            return null;
        }
    endif;
}
/**
* Get my twitter timeline
* get the Twitter object with the timeline of the logged user and transients it
* @return Twitter object with the logged user timeline
* @author hugosolar
*/
function get_my_twitter_timeline() {

    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'current_timeline' );
        if (false === get_transient('current_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            $content = $connection->get('statuses/user_timeline');
            set_transient('current_timeline',$content,60*10);
        }
        if (!empty($content)) {
            return $content;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
/**
* Get twitter user timeline
* get the Twitter object with the timeline of the specified user and transients it
* @param string $user screen_name of user
* @return Twitter object with the user timeline
* @author hugosolar
*/
function get_twitter_user_timeline($user) {

    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'twitter_'.$user.'_timeline' );
        if (false === get_transient('twitter_'.$user.'_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            $content = $connection->get('statuses/user_timeline',array('screen_name'=>$user));
            set_transient('twitter_'.$user.'_timeline',$content,60*10);
        }
        if (!empty($content)) {
            return $content;
        } else {
            return null;
        }
    } else {
        return null;
    }
}
}
// Instantiate the class object
$oauth_twitter = oAuth_twitter::getInstance();


?>