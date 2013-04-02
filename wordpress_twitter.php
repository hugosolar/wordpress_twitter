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
define('CONSUMER_KEY', 'YOUR_CONSUMER_KEY');
define('CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET');

/**
* oAuth Twitter Class
* This class authenticate with twitter to get the current user timeline
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
        
        $this->settings = get_option(__CLASS__ .'_theme_settings');
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
    }
    
    function theme_admin(){
        add_theme_page('Conectar Twitter', 'Conectar Twitter', 'edit_others_posts', 'twit_settings', array($this, 'twit_settings'));
    }
    
    function twit_settings(){
        global $id_base, $name_base;
        $data = get_option(__CLASS__ .'_theme_settings');
        $id_base = str_replace('::', '_', __METHOD__ ) .'_';
        $name_base = __CLASS__ .'['. __FUNCTION__ .']';

        define('OAUTH_CALLBACK', admin_url('themes.php?page=twit_settings&action=twitter_callback'));        

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
              * you can change the query if you like
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
            echo '<form action="'. admin_url('themes.php?page=twit_settings') .'" method="post">';
                echo '<table class="form-table">';
                    
                    echo '<tr>';
                            echo '<th><label for="'. theme_settings_get_id('twitter') .'">Twitter</label></th>';
                    $twitter_option = get_option('twitter_token');
                    if (empty($twitter_option)) {                        
                            echo '<td><a href="'.admin_url('themes.php?page=twit_settings&action=authorize_twitter').'" class="button">Identificarse con twitter</a></td>';
                       
                    } else {
                        $logged_in = $this->get_twitter_recent();
                        echo '<td>Logeado como: '.$logged_in->user.' <a href="'.admin_url('themes.php?page=twit_settings&action=twitter_bye').'" class="button">Desconectar</a></td>';                        
                    }
                     echo '</tr>';
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
        if ( isset($_POST['action']) && $_POST['action'] === __CLASS__ .'::theme_settings' ) {
            if ( wp_verify_nonce($_POST['twit_settings_nonce'], __CLASS__ .'::theme_settings') ) {
                foreach ( $_POST[__CLASS__]['theme_settings'] as $k => $v ){
                    if ( is_string($v) ) $_POST[__CLASS__]['theme_settings'][$k] = stripcslashes($v);
                }
                update_option(__CLASS__ .'_'. theme_settings, $_POST[__CLASS__]['theme_settings']);
            }
        }
    }
    function check_oauth() {
    if ( current_user_can( 'manage_options' ) && is_admin() ) {

        if ($_GET['action'] == 'authorize_twitter') {            
            //session_start();
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
            $request_token = $connection->getRequestToken(admin_url('themes.php?page=twit_settings&action=twitter_callback'));
            
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
function get_twitter_recent() {

    $option = get_option('twitter_token');
    if (!empty($option)) {
        $content = get_transient( 'twitter_timeline' );
        if (false === get_transient('twitter_timeline')) {
            $access_token = get_option('twitter_token');
            $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            $content = $connection->get('statuses/user_timeline');
            set_transient('twitter_timeline',$content,60*10);
        }
        if (!empty($content)) {
            $last_tweet = $content[0];
            
            $return_tweet = new stdClass;
            $return_tweet->id = $last_tweet->id_str;
            $return_tweet->date = $last_tweet->created_at;
            $return_tweet->text = $this->twitter_parse($last_tweet->text);
            $return_tweet->user = $last_tweet->user->screen_name;
            $return_tweet->user_thumb = $last_tweet->user->profile_image_url;
            $return_tweet->full_name = $last_tweet->user->name;

            return $return_tweet;
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