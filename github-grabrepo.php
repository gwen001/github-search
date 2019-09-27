#!/usr/bin/php

<?php

function usage( $err=null ) {
	echo 'Usage: php '.$_SERVER['argv'][0]." -o/-u <organization/user> [OPTIONS]\n\n";
	echo "Options:\n";
	echo "\t-d\tset destination directory (required)\n";
	echo "\t-o\tset organization (org or user required)\n";
	echo "\t-u\tset user (org or user required)\n";
	echo "\t-f\tgrab forked repositories as well\n";
	echo "\n";
	if( $err ) {
		echo 'Error: '.$err."!\n";
	}
	exit();
}

define( 'PER_PAGE', 100 );

$options = '';
$options .= 'd:'; // source directory
$options .= 'f'; // forks
$options .= 'o:'; // organization
$options .= 'u:'; // user
$t_options = getopt( $options );
//var_dump($t_options);
if( !count($t_options) ) {
	usage();
}

if( isset($t_options['f']) ) {
	$_forks = true;
} else {
	$_forks = false;
}

if( isset($t_options['d']) ) {
	$_directory = $t_options['d'];
} else {
	$_directory = __DIR__;
}
$_directory = rtrim( $_directory, '/' );

if( isset($t_options['o']) ) {
	$_org = $t_options['o'];
} else {
	$_org = null;
}

if( isset($t_options['u']) ) {
	$_user = $t_options['u'];
} else {
	$_user = null;
}

if( is_null($_org) && is_null($_user) ) {
	usage( 'Organization/User not found' );
}

if( !is_null($_org) ) {
	$url = 'https://api.github.com/orgs/'.$_org.'/repos?per_page='.PER_PAGE.'&page=';
} else {
	$url = 'https://api.github.com/users/'.$_user.'/repos?per_page='.PER_PAGE.'&page=';
}

$run = true;
$page = 1;
$n_clone = 0;

do
{
	$c = curl_init();
	curl_setopt( $c, CURLOPT_URL, $url.$page );
	curl_setopt( $c, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0' );
	//curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
	//curl_setopt( $c, CURLOPT_NOBODY, true );
	curl_setopt( $c, CURLOPT_CONNECTTIMEOUT, 3 );
	curl_setopt( $c, CURLOPT_FOLLOWLOCATION, false );
	curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
	$r = curl_exec( $c );
	$t_info = curl_getinfo( $c );
	//var_dump( $r );
	//var_dump( $t_info );
	
	if( $t_info['http_code'] != 200 ) {
		usage( 'Cannot grab datas' );
	}
	
	if( !is_dir($_directory) ) {
		if( !mkdir($_directory,0777,true) ) {
			usage( 'Cannot create destination directory' );
		}
	}
	
	$t_json = json_decode( $r, true );
	$cnt = count( $t_json );
	//var_dump( $t_json );
	
	if( !$cnt ) {
		break;
	}
	
	foreach( $t_json as $repo )
	{
		if( (int)$repo['fork'] == 0 || $_forks ) {	
			$n_clone++;
			$cmd = 'git clone '.$repo['html_url'].' "'.$_directory.'/'.$repo['name'].'"';
			echo $cmd."\n";
			exec( $cmd );
			echo "\n";
		}
	}
		
	if( $cnt < PER_PAGE ) {
		break;
	}
	
	$page++;
}
while( $run );

echo $n_clone." repository cloned\n";
exit();

?>
