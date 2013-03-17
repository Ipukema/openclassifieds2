<?php
/**
 * Common functions to install
 */

// Sanity check, install should only be checked from index.php
defined('SYSPATH') or exit('Install must be loaded from within index.php!');

//prevents from new install to be done
if(!file_exists('install/install.lock')) die('Installation seems to be done, please remove /install/ folder');

define('VERSION','2.0 Beta');

if (isset($_POST['LANGUAGE'])) $locale_language=$_POST['LANGUAGE'];
elseif (isset($_GET['LANGUAGE'])) $locale_language=$_GET['LANGUAGE'];
else  $locale_language='en_EN';
//start translations
gettext_init($locale_language);


// Try to guess installation URL
$suggest_url = 'http://'.$_SERVER['SERVER_NAME'];
if ($_SERVER['SERVER_PORT'] != '80') 
    $suggest_url = $suggest_url.':'.$_SERVER['SERVER_PORT'];
//getting the folder, erasing the index
$suggest_url .=str_replace('/index.php','', $_SERVER['SCRIPT_NAME']).'/';


//bool to see if the isntallation was good
$install = FALSE;
//installation error messages here
$error_msg  = '';

//requirements checks correct?
$succeed = TRUE; 
//message to explain what was not correct
$msg     = '';


//Software requirements
$checks = array(

                'robots.txt'=>array('message'   => 'The <code>robots.txt</code> file is not writable.',
                                    'mandatory' => FALSE,
                                    'result'    => is_writable('robots.txt')
                                    ),
                '.htaccess' =>array('message'   => 'The <code>.htaccess</code> file is not writable.',
                                    'mandatory' => TRUE,
                                    'result'    => is_writable('.htaccess')
                                    ),
                'sitemap'   =>array('message'   => 'The <code>sitemap.xml.gz</code> file is not writable.',
                                    'mandatory' => FALSE,
                                    'result'    => is_writable('sitemap.xml.gz')
                                    ),
                'images'    =>array('message'   => 'The <code>images/</code> directory is not writable.',
                                    'mandatory' => TRUE,
                                    'result'    => is_writable('images')
                                    ),
                'cache'     =>array('message'   => 'The <code>'.APPPATH.'cache/</code> directory is not writable.',
                                    'mandatory' => TRUE,
                                    'result'    => (is_dir(APPPATH) AND is_dir(APPPATH.'cache') AND is_writable(APPPATH.'cache'))
                                    ),
                'logs'      =>array('message'   => 'The <code>'.APPPATH.'logs/</code> directory is not writable.',
                                    'mandatory' => TRUE,
                                    'result'    => (is_dir(APPPATH) AND is_dir(APPPATH.'logs') AND is_writable(APPPATH.'logs'))
                                    ),
                'SYS'       =>array('message'   => 'The configured <code>'.SYSPATH.'</code> directory does not exist or does not contain required files.',
                                    'mandatory' => TRUE,
                                    'result'    => (is_dir(SYSPATH) AND is_file(SYSPATH.'classes/kohana'.EXT))
                                    ),
                'APP'       =>array('message'   => 'The configured <code>'.APPPATH.'</code> directory does not exist or does not contain required files.',
                                    'mandatory' => TRUE,
                                    'result'    => (is_dir(APPPATH) AND is_file(APPPATH.'bootstrap'.EXT))
                                    ),
                'PHP'       =>array('message'   => 'PHP 5.2.4 or newer required, this version is '. PHP_VERSION,
                                    'mandatory' => TRUE,
                                    'result'    => version_compare(PHP_VERSION, '5.2.4', '>=')
                                    ),
                'PCRE UTF8' =>array('message'   => '<a href="http://php.net/pcre">PCRE</a> has not been compiled with UTF-8 support.',
                                    'mandatory' => TRUE,
                                    'result'    => (bool) (@preg_match('/^.$/u', 'ñ'))
                                    ),
                'PCRE Unicode'=>array('message' => '<a href="http://php.net/pcre">PCRE</a> has not been compiled with Unicode property support.',
                                    'mandatory' => TRUE,
                                    'result'    => (bool) (@preg_match('/^\pL$/u', 'ñ'))
                                    ),
                'SPL'       =>array('message'   => 'PHP <a href="http://www.php.net/spl">SPL</a> is either not loaded or not compiled in.',
                                    'mandatory' => TRUE,
                                    'result'    => (function_exists('spl_autoload_register'))
                                    ),
                'Reflection'=>array('message'   => 'PHP <a href="http://www.php.net/reflection">reflection</a> is either not loaded or not compiled in.',
                                    'mandatory' => TRUE,
                                    'result'    => (class_exists('ReflectionClass'))
                                    ),
                'Filters'   =>array('message'   => 'The <a href="http://www.php.net/filter">filter</a> extension is either not loaded or not compiled in.',
                                    'mandatory' => TRUE,
                                    'result'    => (function_exists('filter_list'))
                                    ),
                'Iconv'     =>array('message'   => 'The <a href="http://php.net/iconv">iconv</a> extension is not loaded.',
                                    'mandatory' => TRUE,
                                    'result'    => (extension_loaded('iconv'))
                                    ),
                'Mbstring'  =>array('message'   => 'The <a href="http://php.net/mbstring">mbstring</a> extension is not loaded.',
                                    'mandatory' => TRUE,
                                    'result'    => (extension_loaded('mbstring'))
                                    ),
                'CType'     =>array('message'   => 'The <a href="http://php.net/ctype">ctype</a> extension is not enabled.',
                                    'mandatory' => TRUE,
                                    'result'    => (function_exists('ctype_digit'))
                                    ),
                'URI'       =>array('message'   => 'Neither <code>$_SERVER[\'REQUEST_URI\']</code>, <code>$_SERVER[\'PHP_SELF\']</code>, or <code>$_SERVER[\'PATH_INFO\']</code> is available.',
                                    'mandatory' => TRUE,
                                    'result'    => (isset($_SERVER['REQUEST_URI']) OR isset($_SERVER['PHP_SELF']) OR isset($_SERVER['PATH_INFO']))
                                    ),
                'cUrl'      =>array('message'   => 'OC requires the <a href="http://php.net/curl">cURL</a> extension for the Request_Client_External class.',
                                    'mandatory' => TRUE,
                                    'result'    => (extension_loaded('curl'))
                                    ),
                'mcrypt'    =>array('message'   => 'OC requires the <a href="http://php.net/mcrypt">mcrypt</a> for the Encrypt class.',
                                    'mandatory' => TRUE,
                                    'result'    => (extension_loaded('mcrypt'))
                                    ),
                'GD'        =>array('message'   => 'OC requires the <a href="http://php.net/gd">GD</a> v2 for the Image class',
                                    'mandatory' => TRUE,
                                    'result'    => (function_exists('gd_info'))
                                    ),
                'MySQL'     =>array('message'   => 'OC requires the <a href="http://php.net/mysql">MySQL</a> extension to support MySQL databases.',
                                    'mandatory' => TRUE,
                                    'result'    => (function_exists('mysql_connect'))
                                    ),
                'PDO'       =>array('message'   => 'OC requires the <a href="http://php.net/pdo">PDO</a> to support additional databases.',
                                    'mandatory' => TRUE,
                                    'result'    =>  (class_exists('PDO'))
                                    ),

                );

/*
function __($s)
{
	return (function_exists('_'))?_($s):T_($s);
}*/

function gettext_init($locale,$domain = 'messages',$charset = 'utf8')
{
    include APPPATH.'vendor/php-gettext/gettext.inc';

    /**
     * check if gettext exists if not uses gettext dropin
     */
    if ( !function_exists('_') )
    {
        T_setlocale(LC_MESSAGES, $locale);
        bindtextdomain($domain,DOCROOT.'languages');
        bind_textdomain_codeset($domain, $charset);
        textdomain($domain);
    }
    /**
     * gettext exists using fallback in case locale doesn't exists
     */
    else
    {
        T_setlocale(LC_MESSAGES, $locale);
        T_bindtextdomain($domain,DOCROOT.'languages');
        T_bind_textdomain_codeset($domain, $charset);
        T_textdomain($domain);
    }
}

function hostingAd()
{
    if (SAMBA){
    ?>
    <div class="alert alert-info">Get free 100% compatible hosting or a professional hosting for just $3.95 month.
	    <a class="btn btn-info" href="http://open-classifieds.com/hosting/">
	        <i class="icon-ok icon-white"></i> Sign now!
	    </a>
    </div>
    <?php }
}


function formatOffset($offset) 
{
        $hours = $offset / 3600;
        $remainder = $offset % 3600;
        $sign = $hours > 0 ? '+' : '-';
        $hour = (int) abs($hours);
        $minutes = (int) abs($remainder / 60);

        if ($hour == 0 AND $minutes == 0) {
            $sign = ' ';
        }
        return $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) .':'. str_pad($minutes,2, '0');
}

///timezones functions
function get_timezones()
{
    if (method_exists('DateTimeZone','listIdentifiers'))
    {
        $utc = new DateTimeZone('UTC');
        $dt  = new DateTime('now', $utc);

        $timezones = array();
        $timezone_identifiers = DateTimeZone::listIdentifiers();

        foreach( $timezone_identifiers as $value )
        {
            $current_tz = new DateTimeZone($value);
            $offset     =  $current_tz->getOffset($dt);

            if ( preg_match( '/^(America|Antartica|Africa|Arctic|Asia|Atlantic|Australia|Europe|Indian|Pacific)\//', $value ) )
            {
                $ex=explode('/',$value);//obtain continent,city
                $city = isset($ex[2])? $ex[1].' - '.$ex[2]:$ex[1];//in case a timezone has more than one
                $timezones[$ex[0]][$value] = $city.' ['.formatOffset($offset).']';
            }
        }
        return $timezones;
    }
    else//old php version
    {
        return FALSE;
    }
}

function get_select_timezones($select_name='TIMEZONE',$selected=NULL)
{
	$sel='';
    $timezones = get_timezones();
    $sel.='<select id="'.$select_name.'" name="'.$select_name.'">';
    foreach( $timezones as $continent=>$timezone )
    {
        $sel.= '<optgroup label="'.$continent.'">';
        foreach( $timezone as $city=>$cityname )
        {            
            if ($selected==$city)
            {
                $sel.= '<option selected="selected" value="'.$city.'">'.$cityname.'</option>';
            }
            else $sel.= '<option value="'.$city.'">'.$cityname.'</option>';
        }
        $sel.= '</optgroup>';
    }
    $sel.='</select>';

    return $sel;
}

function cP($name,$default = NULL)
{
    return (isset($_POST[$name])? $_POST[$name]:$default);
}


function replace_file($filename,$search, $replace)
{
    //check file is writable
    if (is_writable($filename))
    {
        //read file content
        $content = file_get_contents($filename);
        //replace fields
        $content = str_replace($search, $replace, $content);
        //save file
        return write_file($filename,$content);
    }
    
    return FALSE;
}


/**
 * write to file
 * @param $filename fullpath file name
 * @param $content
 * @return boolean
 */
function write_file($filename,$content)
{
    $file = fopen($filename, 'w');
    if ($file)
    {//able to create the file
        fwrite($file, $content);
        fclose($file);
        return TRUE;
    }
    return FALSE;   
}

function generate_password ($length = 16)
{
    $password = '';
    // define possible characters
    $possible = '0123456789abcdefghijklmnopqrstuvwxyz_-';

    // add random characters to $password until $length is reached
    for ($i=0; $i <$length ; $i++) 
    { 
        // pick a random character from the possible ones
        $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
        $password .= $char;
    }

    return $password;
}

define('SAMBA',TRUE);