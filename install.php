<?php

/*
* This file is part of Kryn.cms.
*
* (c) Kryn.labs, MArc Schmidt <marc@kryn.org>
*
* To get the full copyright and license informations, please view the
* LICENSE file, that was distributed with this source code.
*/

header("Content-Type: text/html; charset=utf-8");

$GLOBALS['krynInstaller'] = true;
define('PATH', dirname(__FILE__).'/');
define('PATH_CORE', 'core/');
define('PATH_MODULE', 'module/');
define('PATH_MEDIA', 'media/');

include(PATH_CORE.'misc.global.php');
include(PATH_CORE.'database.global.php');
include(PATH_CORE.'template.global.php');
include(PATH_CORE.'internal.global.php');
include(PATH_CORE.'framework.global.php');
$lang = 'en';
$cfg = array();


include('core/bootstrap.autoloading.php');

@ini_set('display_errors', 1);
@ini_set('error_reporting', E_ALL & ~E_NOTICE);

if( $_REQUEST['step'] == 'checkDb' )
    checkDb();

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de" dir="ltr">
  <head>
    <title>Kryn.cms installation</title>
      <link rel="stylesheet" type="text/css" href="media/admin/css/ka.Button.css"  />

      <style type="text/css">
      h1 {
        margin: 0px 0px 10px 0px;
        border-bottom: 1px solid #00273c;
        font-size: 12px;
        font-weight: bold;
        color: #145E84;
      }
      
      h2 {
        color: #145E84;
      }
      
      td {
        vertical-align: top;
      }

      a, a:link {
        text-decoration: none;
        color: gray;
      }

      body {
        text-align: center;
        font-size: 11px;
        font-family: Verdana,Arial,sans-serif;
      }

      table {
        font-size: 11px;
        margin: 5px;
        margin-left: 10px;
        width: 400px;
        color: #555;
      }

      table th {
        color: #444;
        border-bottom: 1px solid silver;
        font-weight: normal;
        text-align: left;
      }
      
      table.modulelist td {
      	border-bottom: 1px solid #eee;
      }

      input.text {
        border: 1px solid silver;
        width: 250px;
        text-indent: 4px;
      }

      .wrapper {
        text-align: left;
        margin: auto;
        width: 700px;
        left: 60px;
        border: 1px solid silver;
        -moz-border-radius: 10px;
        -webkit-border-radius: 10px;
        padding: 45px 35px;
        background-color: #f6f6f6;
        position: relative;
        color: #333;
      }

      .step a, .step a:link {
        display: block;
        text-align: left;
        padding: 12px 5px 12px 15px;
        -moz-border-radius: 10px;
        -webkit-border-radius: 10px;
      }

      .step a.active {
        color: black;
        background-color: #e8e8e8;
        font-weight: bold;
      } 

      .step {
        border: 1px solid silver;
        border-right: 0px;
        -moz-border-radius-topleft: 10px;
        -moz-border-radius-bottomleft: 10px;
        -webkit-border-top-right-radius: 10px;
        -webkit-border-bottom-right-radius: 10px;
        border-radius: 3px;
        position: absolute;
        top: 20px;
        left: -151px;
        width: 150px;
        background-color: #f2f2f2;
        margin-bottom: 15px;
      }
      
      h2.main {
      	font-size: 12px;
      	line-height:13px;
      	position: absolute;
      	top: 0px;
      	left: 35px;
      	right: 35px;
      	border-bottom: 1px dashed #ddd;
      	padding-bottom: 5px;
      	color: gray;
      }

      .breaker { clear: both }

    </style>
    <script type="text/javascript" src="media/kryn/mootools-core.js"></script>
    <script type="text/javascript">
        window.addEvent('domready', function(){
            $$('input.text').addEvent('focus', function(){
                this.setStyles({
                    border: '1px solid gray',
                    'background-color': '#feffc0'
                });
            });
            $$('input.text').addEvent('blur', function(){
                this.setStyles({
                    border: '1px solid silver',
                    'background-color': 'white'
                });
            });
           $$('a.button').each(function(a){
               if( !a.getElement('span') )
                   new Element('span').inject(a); 
           });
        });
    </script>
    <link rel="SHORTCUT ICON" href="media/admin/images/favicon.ico" />
  </head>
  <body>
    <div class="wrapper">
    <h2 class="main">Kryn.cms installation</h2>
<?php

$step = 1;
if( !empty($_REQUEST['step']) )
    $step = $_REQUEST['step'];
?>

<div class="step">
    <a href="javascript:;" <?php if( $step == 1 ) echo 'class="active"'; ?>>1. Start</a>
    <a href="javascript:;" <?php if( $step == 2 ) echo 'class="active"'; ?>>2. Filecheck</a>
    <a href="javascript:;" <?php if( $step == 3 ) echo 'class="active"'; ?>>3. Database</a>
    <a href="javascript:;" <?php if( $step == 4 ) echo 'class="active"'; ?>>4. Package</a>
    <a href="javascript:;" <?php if( $step == 5 ) echo 'class="active"'; ?>>5. Installation</a>
    <div class="breaker"></div>
</div>

<?php

switch( $step ){
case '5':
    step5();     
    break;
case '4':
    step4();     
    break;
case '3':
    step3();     
    break;
case '2':
    step2();
    break;
case '1':
    welcome();
}

function checkDb(){
	global $cfg;
	
	
	$type = $_REQUEST['type'];
	
	$cfg = array(
		"db_server"		=> $_REQUEST['server'],
	    "db_user"		=> $_REQUEST['username'],
	    "db_passwd"		=> $_REQUEST['passwd'],
	    "db_name"		=> $_REQUEST['db'],
	    "db_prefix"		=> $_REQUEST['prefix'],
	    "db_type"		=> $_REQUEST['type'],
	    "db_pdo"		=> $_REQUEST['pdo']
	);
	
	require_once( PATH_CORE.'krynModule.class.php' );
	require_once( PATH_CORE.'kryn.class.php' );
	require_once( PATH_CORE.'krynAuth.class.php' );
    require( PATH_CORE.'database.class.php' );
	$res = array('res' => true);
	
	$usePdo = ($_REQUEST['pdo'] == 1) ? true : false;
	$forceutf8 = ($_REQUEST['forceutf8'] == 1) ? true : false;
	
    $kdb = new database($cfg['db_type'], $cfg['db_server'], $cfg['db_user'], $cfg['db_passwd'], $cfg['db_name'], $usePdo, $forceutf8);
    
    if( !$kdb->connected() ){
        $res['error'] = $kdb->lastError();
        $res['res'] = false;
    }

    $path = dirname($_SERVER['REQUEST_URI']);
    if( $path == '\\' ) $path = '/';
    if( substr($path, 0, -1) != '/' )
        $path .= '/';
    $path = str_replace('//', '/', $path);

    $timezone = @date_default_timezone_get();
    if( !$timezone )
        $timezone = 'Europe/Berlin';


    if( $res['res'] == true ){
        $cfg = array(
        
            'db_server' => $_REQUEST['server'],
            'db_user'   => $_REQUEST['username'],
            'db_passwd' => $_REQUEST['passwd'],
            'db_name'   => $_REQUEST['db'],
            'db_prefix' => $_REQUEST['prefix'],
            'db_type'   => $_REQUEST['type'],
            'db_pdo'    => $_REQUEST['pdo'],
            'db_forceutf8'   => $_REQUEST['forceutf8'],
            "cache_type"   => "files",
            "media_cache"    => "cache/media/",
            "display_errors" => "0",
            "log_errors"     => "0",
            "systemtitle"    => "Fresh install",
            "rewrite"        => false,
            "locale"         => "de_DE.UTF-8",
            "path"			 => $path,
            "passwd_hash_compatibility" => "0",
            "passwd_hash_key"           => krynAuth::getSalt(32),
            "timezone"       => $timezone
        );
        $config = '<?php $cfg = '. var_export($cfg,true) .'; ?>';

        $f = @fopen( 'config.php', 'w+' );
        if( !$f ){
            $res['error'] = 'Can not open file config.php - please change the permissions.';
            $res['res'] = false;
        } else {
            fwrite( $f, $config ); 
        }
    }
    die(json_encode($res));
}

function welcome(){
?>

<h2>Thank you for choosing Kryn.cms!</h2>
<br />
Your installation folder is <strong style="color: gray;"><?php echo getcwd(); ?></strong>
<br />
<br />
<b>Kryn.cms license</b><br />
<br />
<div style="height: 350px; background-color: white; padding: 5px; overflow: auto; white-space: pre;">
    <?php $f = fopen("LICENSE", "r"); if($f) while (!feof($f)) print fgets($f, 4096) ?>
</div>
<br /><br />
<b style="color: gray">Kryn.core comes with amazing additional third party software.</b><br />
      Please respect all of their licenses too:<br />
<br />
<table style="width: 100%" cellpadding="3">
    <tr>
    <th width="160">Name</th>
    <th width="250">Author/Link</th>
    <th>License</th>
</tr>
<tr>
    <td  width="160">Mootools</td>
    <td  width="250"><a href="http://mootools.net/">mootools.net</a></td>
    <td>&raquo; <a href="http://www.opensource.org/licenses/mit-license.php">MIT license</a></td>
</tr>
<tr>
    <td  width="160">Mooeditable fork</td>
    <td  width="250"><a href="https://github.com/MArcJ/mooeditable">https://github.com/MArcJ/mooeditable</a></td>
    <td>&raquo; <a href="http://www.opensource.org/licenses/mit-license.php">MIT license</a></td>
</tr>
<tr>
    <td>Smarty</td>
    <td><a href="http://www.smarty.net/">www.smarty.net</a></td>
    <td>&raquo; <a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a></td>
</tr>
<tr>
    <td>Codemirror</td>
    <td><a href="http://codemirror.net/">codemirror.net</a></td>
    <td>&raquo; <a href="lib/codemirror/LICENSE">MIT-style license</a></td>
</tr>

<tr>
    <td>Silk icon set 1.3</td>
    <td><a href="http://www.famfamfam.com/lab/icons/silk/">www.famfamfam.com/lab/icons/silk/</a></td>
    <td>&raquo; <a href="http://creativecommons.org/licenses/by/2.5/">Creative Commons Attribution 2.5 License.</a></td>
</tr>


<tr>
    <td>[PEAR] JSON</td>
    <td><a href="http://pear.php.net/package/Services_JSON">PEAR/Services_JSON</a></td>
    <td>&raquo; <a href="http://www.opensource.org/licenses/bsd-license.php">BSD</a></td>
</tr>

<tr>
    <td>[PEAR] Archive</td>
    <td><a href="http://pear.php.net/package/File_Archive/">PEAR/File_Archive</a></td>
    <td>&raquo; <a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a></td>
</tr>

<tr>
    <td>[PEAR] MIME</td>
    <td><a href="http://pear.php.net/package/MIME_Type">PEAR/MIME_Type</a></td>
    <td>&raquo; <a href="http://www.php.net/license/3_0.txt">PHP License 3.0</a></td>
</tr>

<tr>
    <td>[PEAR] Structures_Graph</td>
    <td><a href="http://pear.php.net/package/MIME_Type">PEAR/Structures_Graph</a></td>
    <td>&raquo; <a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a></td>
</tr>
<tr>
    <td  width="160">[Mootools plugin] Stylesheet</td>
    <td  width="250"><a href="http://mifjs.net">Anton Samoylov</a></td>
    <td>&raquo; <a href="http://mifjs.net/license.txt">MIT-Style License</a></td>
</tr>


<tr>
    <td colspan="3">IconSet:
    </td>
</tr>
<tr>
    <td colspan="3" style="white-space: pre; background-color: white;"><?php print file_get_contents('media/admin/icons/license.txt'); ?>
    </td>
</tr>

</table>
<a href="?step=2" class="ka-Button" >Accept</a>

<?php
}

function step5(){
?>

<br />
<h2>Installation in progress:</h2>
<br />
<?php
    global $kdb, $cfg;

    $dir = opendir( PATH_MODULE."" );
    if(! $dir ) return;
    while (($file = readdir($dir)) !== false){
        if( $file != '..' && $file != '.' && $file != '.svn' && $file != 'admin' ){
            $modules[] = $file;
        }
    }
    $modules[] = "admin"; //because the install() of admin should be called as latest
    
    require( 'config.php' );
    require( PATH_MODULE.'admin/adminDb.class.php' );
    require( PATH_CORE.'database.class.php' );
    require_once( PATH_CORE.'krynModule.class.php' );
	require_once( PATH_CORE.'kryn.class.php' );
	kryn::$config = $cfg;
    kryn::$config['db_error_print_sql'] = 1;
	require_once( PATH_CORE.'krynAuth.class.php' );

    
    @mkdir( 'cache/' );
    @mkdir( 'cache/media' );
    @mkdir( 'cache/object' );
    @mkdir( 'cache/smarty_compile' );
    
    define('pfx', $cfg['db_prefix']);
    $kdb = new database(
                 $cfg['db_type'],
                 $cfg['db_server'],
                 $cfg['db_user'],
                 $cfg['db_passwd'],
                 $cfg['db_name'],
                 ($cfg['db_pdo']+0 == 1 || $cfg['db_pdo'] === '' )?true:false,
                 ($cfg['db_forceutf8']=='1')?true:false
    );
    kryn::$configs = array();
    
    foreach( $modules as $module ){
        if( $_REQUEST['modules'][$module] == '1' || $module == 'admin' || $module == 'users') {
            kryn::$configs[$module] = adminModule::loadInfo( $module );
        }
    }
    

    foreach( kryn::$configs as $extension => $config ){
                            
        if( is_array($config['extendConfig']) ){
            foreach( $config['extendConfig'] as $extendModule => &$extendConfig ){
                if( kryn::$configs[$extendModule] ){
                    kryn::$configs[$extendModule] = 
                        array_merge_recursive_distinct(kryn::$configs[$extendModule], $extendConfig);
                }
            }
        }
    }

    foreach( kryn::$configs as $extension => $config ){

        if( $config['db'] ){
            foreach( $config['db'] as $key => &$table ){
                if( kryn::$tables[$key] )
                   kryn::$tables[$key] = array_merge(kryn::$tables[$key], $table);
                else
                   kryn::$tables[$key] = $table;
            }
        }

        if ($config['objects'] && is_array($config['objects'])){

            foreach ($config['objects'] as $objectId => $objectDefinition){
                $objectDefinition['_extension'] = $extension; //caching
                if (kryn::$objects[$objectId]){
                    kryn::$objects[$objectId] = array_merge(kryn::$objects[$objectId], $objectDefinition);
                } else {
                    kryn::$objects[$objectId] = $objectDefinition;
                }
            }
        }

    }
            
    foreach( kryn::$configs as $module => $config ){
        print "Install <b>$module</b>:<br />
        <div style='padding-left: 15px; margin-bottom: 4px; color: #999; white-space: pre; font-family: monospace;'>";

        $removedTables = adminDb::remove($config);

        if (is_array($removedTables) && count($removedTables) > 0){
            foreach ($removedTables as $table){
                print "\t[-] $table removed.\n";
            }
        }

        $installedTables = adminDb::sync($config);
        if (is_array($installedTables) && count($installedTables) > 0){
            foreach ($installedTables as $table => $status){
                print "\t".($status?"[+] $table installed":"[#] $table updated").".\n";
            }
        } else {
            print "\tno tables to install.\n";
        }
        print "</div>";
        flush();
    }

    dbDelete( 'system_domains' );

    $path = dirname($_SERVER['REQUEST_URI']);
    if( substr($path, 0, -1) != '/' )
        $path .= '/';
    $path = str_replace("//", "/", $path);
    $path = str_replace('\\', '', $path);

    dbInsert( 'system_domains', array(
        'domain' => $_SERVER['SERVER_NAME'], 'title_format' => '%title | Pagetitle', 'master' => 1, 'lang' => 'en',
        'startpage_rsn'=>1, 'resourcecompression' => 1, 'path' => $path,
        'search_index_key' => md5($_SERVER['SERVER_NAME'].'-'.@time().'-'.rand())
    ));
    
    
    dbDelete( 'system_modules' );
    foreach( $modules as $module ){
        if( $_REQUEST['modules'][$module] == '1' || $module == 'admin' || $module == 'users') {
            if( $module != "kryn" ){
                if( file_exists(PATH_MODULE."$module/$module.class.php") ){
                    require_once( PATH_MODULE."$module/$module.class.php" );
                    $m = new $module();
                    $m->install();
                }
            }
            if( $module != '' ){
            	dbInsert( 'system_modules', array('name' => $module, 'activated' => 1) );
            }
        }
    }

    require( PATH_MODULE.'admin/adminPages.class.php' );
    
    foreach( kryn::$configs as $config ){
        if( $config && $config['db'] )
            $kdb->updateSequences( $config['db'] );
    }
    
    admin::clearCache();

    @mkdir( PATH_MEDIA.'trash' );
    @mkdir( PATH_MEDIA.'css' );
    @mkdir( PATH_MEDIA.'js' );
    
    @mkdir( 'data', 0777 );
    @mkdir( 'data/upload', 0777 );
    @mkdir( 'data/packages', 0777 );
    @mkdir( 'data/upload/modules', 0777 );

    
    if( !rename( 'install.php', 'install.doNotRemoveIt.'.rand(123,5123).rand(585,2319293).rand(9384394,313213133) ) ){
        print '<div style="margin: 25px; border: 2px solid red; padding: 10px; padding-left: 25px;">
        	Can not rename install.php - please remove or rename the file for security reasons!
        	</div>';
    }
?>
<br />
<div style="margin: 25px; border: 1px solid green; padding: 10px; padding-left: 25px;">
    <b>Installation successful.</b><br /><br />
    <b>Your login</b><br />
    Username: admin<br />
    Password: admin<br />
    <a href="./admin">Click here to go to Administration.</a><br />
</div>
<?php
}

function step4(){
?>

<br />
Your installation file contains following extensions:<br />
<br />
<br />
<form action="?step=5" method="post" id="form.modules">

<table style="width: 98%" class="modulelist" cellpadding="4">
<?php
    require_once( PATH_CORE.'krynModule.class.php' );

    $systemModules = array('kryn','admin','users');
    buildModInfo( $systemModules );

    $dir = opendir( PATH_MODULE."" );
    $modules = array();
    if(! $dir ) return;
    while (($file = readdir($dir)) !== false){
        if( $file != '..' && $file != '.' && $file != '.svn' && (array_search($file, $systemModules) === false) ){
            $modules[] = $file;
        }
    }
    buildModInfo( $modules );
?>
</table>
</form>
<b style="color: red;">All database tables we install will be dropped in the next step!</b><br /><br/>
<a href="?step=3" class="ka-Button" >Back</a>
<a href="javascript: $('form.modules').submit();" class="ka-Button" >Install!</a>
<?php
}

function buildModInfo( $modules ) {
    global $lang;
    foreach( $modules as $module ){
         $config = adminModule::loadInfo( $module );
         $version = $config['version'];
         $title = $config['title'][$lang];
         $desc = $config['desc'][$lang];

         $checkbox = '<input name="modules['.$module.']" checked type="checkbox" value="1" />';
         if( $config['system'] == "1"){
             $checkbox = '<input name="modules['.$module.']" checked disabled type="checkbox" value="1" />';
         }
        ?>
        <tr>
        	<td valign="top" width="30"><?php print $checkbox ?></td>
        	<td valign="top" width="150"><b><?php print $title ?></b></td>
        	<td valign="top" width="90"><div style="color: gray; margin-bottom: 11px;">#<?php print $module ?></div></td>
        	<td valign="top" >
        	<?php print $desc ?>
        	</td>
        </tr>
        <?php
    }

}

function step2(){
?>

<h2>Checking file permissions</h2>
<br />
The minimum requirements to work with Kryn.cms without installing extension or updates is with write access to following folders:<br />
&bull; ./<br />
&bull; cache/<br />
&bull; data/<br />
&bull; media/<br />
<br />
When you want to install extensions, then you need to make sure, that Kryn.cms can modify or add files in following folders:<br />
&bull; module/<br />
<br />
Sometimes, extensions comes with files which aren't in these two folders. If this is the case then you need to make sure, that
such extensions gets the correct file permissions.<br />
<br />
<b>Important:</b> To install Kryn.cms core updates, you need to make sure, that <b>all</b> files are writable.<br />
<br />
<br />
<div style="border-top: 1px solid silver;"></div>
<br />
<?php

    $t = explode("-", PHP_VERSION);
    $v = ( $t[0] ) ? $t[0] : PHP_VERSION;

    if(! version_compare($v, "5.2.0", "ge") ){
        print "<b>PHP version tot old.</b><br />";
        print "You need PHP version 5.2.0 or greater.<br />";
        print "Installed version: $v (".PHP_VERSION.")<br/><br/>";
    } else {
        $versionOk = true;
    }

    $step2 = "";

    function checkFile( $pDir, $pFile ){
        global $step2;

        $res = '';
        $file = $pDir.'/'.$pFile;
        if(! is_dir( $file ) ) {
            $fh = @fopen( $file, 'a+' );
            if( !$fh ){
                $step2 .= "#";
                $res .=  "<br />$file";
            }
        } elseif( opendir($file) === FALSE ) {
            $res .= "<br />$file";
        }
        if( is_dir($file) === TRUE ){
            $res .= checkDir( $file );
        }
        return $res;
    }

    function checkDir( $pDir ){
        $pDir .= "";
        $res = '';
        $dir = opendir( $pDir );
        if(! $dir ) return;
        while (($file = readdir($dir)) !== false){
            if( substr($file, 0, 1 ) != '.' || $file == '.htaccess' ){
                $res .= checkFile($pDir, $file);
            }
        }
        return $res;
    }

    
    $files = checkDir( "." );
    if( $files != "" ){
        print '<b>Following files are not writeable.</b><br/><br/>Please set write permissions to webserver or to everyone:<br/>
               <br />
               Use your FTP client to adjust the permissions or directly through ssh:
               <div style="border: 1px solid silver;  font-family: monospace; background-color: white; padding: 5px; margin: 5px;">
               chown -R <i>WebserverOwner</i> '.getcwd().'; <b>or</b><br />
               chmod -R 777 '.getcwd().'</div>';
        print '<div style="border: 1px solid silver; overflow: auto; font-family: monospace; height: 350px; overflow: auto;  background-color: white; margin: 5px;">'.$files.'</div>';
    } else {
        print '<b style="color: green;">OK</b>';
        $filesOk = true;
    }

    ?>
    <br />
    <a href="?step=1" class="ka-Button" >Back</a>
    <?php

    if( $filesOk && $versionOk ){
        print '<a href="?step=3" class="ka-Button" >Next</a>'; 
    } else {
        print '<a href="?step=2" class="ka-Button" >Re-Check</a>';
    }
    
    echo $step2;

}

function step3(){

    
    ?>

Please enter your database credentials.<br />
<br/>
    Please note: All tables which already exists will be deleted!
<br/>
<script type="text/javascript">
    window.checkDBEntries = function(){
        var ok = true;
        
        if( $('db_server').value == '' ){ $('db_server').highlight(); ok = false; }
        if( $('db_prefix').value == '' ){ $('db_prefix').highlight(); ok = false; }
        if( ok ){
            $( 'status' ).set('html', '<span style="color:green;">Check data ...</span>');
            var req = {};
            req.type = $('db_type').value;
            req.server = $('db_server').value;
            req.db = $('db_db').value;
            req.prefix = $('db_prefix').value;
            req.username = $('db_username').value;
            req.passwd = $('db_passwd').value;
            //req.pdo = $('db_pdo').checked?1:0;

            new Request.JSON({url: 'install.php?step=checkDb', onComplete: function(stat){
                if( stat != null && stat.res == true )
                   location = '?step=4';
                else if( stat != null )
                    $( 'status' ).set('html', '<span style="color:red;">Login failed:<br />'+stat.error+'</span>');
                else
                    $( 'status' ).set('html', '<span style="color:red;">Fatal Error. Please take a look in server logs.</span>');
            },
            onError: function(res){
                $( 'status' ).set('html', '<span style="color:red;">Fatal Error. Please take a look in server logs.</span> Maybe this helps: <br />'+res);
            }}).post(req);
        }
    }
</script>
<form id="db_form">
<table style="width: 100%" cellpadding="3">
 	<tr>
        <td width="250">Database driver</td>
        <td><select name="db_type" id="db_type">
        	<option value="mysql">MySQL</option>
        	<option value="mysqli">MySQLi</option>
        	<option value="postgresql">PostgreSQL</option>
        	<option value="oracle">Oracle (experimental)</option> 
        	<option value="oracle">MSSql (experimental, no pdo)</option>
        </select></td>
    </tr>
    <!--<td>PDO driver</td>
    <td>
        <input type="checkbox" id="db_pdo" />
    </td>
    </tr>
    -->
    <tr>
        <td>
        	Host
        </td>
        <td><input class="text" type="text" name="server" id="db_server" value="localhost" /></td>
    </tr>
    <tr id="ui_username">
        <td>Username</td>
        <td><input class="text" type="text" name="username" id="db_username" /></td>
    </tr>
    <tr id="ui_passwd">
        <td>Password</td>
        <td><input class="text" type="password" name="passwd" id="db_passwd" /></td>
    </tr>
    <tr id="ui_db">
        <td>
        	Database name
        </td>
        <td><input class="text" type="text" name="db" id="db_db" /></td>
    </tr>
    <tr>
        <td>Prefix
	        <div style="color: silver">
	        	Please use only a lowercase string.
	        </div></td>
        <td><input class="text" type="text" name="prefix" id="db_prefix" value="kryn_" /></td>
    </tr>
</table>
</form>
<div id="status" style="padding: 4px;"></div>
<br />
<br />
<a href="?step=2" class="ka-Button" >Back</a>
<a href="javascript: checkDBEntries();" class="ka-Button" >Next</a>

<?php
}

?>
    </div>
  </body>
</html>
