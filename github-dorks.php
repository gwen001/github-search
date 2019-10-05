#!/usr/bin/php
<?php

include( 'Utils.php' );

$gh_url = 'https://github.com/search?o=desc&s=indexed&type=Code&q=';

$t_dorks = [
'',
	'filename:constants',
	'filename:settings',
	'filename:database',
	'filename:config',
	'filename:environment',
	'filename:env',
	'filename:cfg',
	'filename:ini',
	'filename:yml',
	'filename:yaml',
	'filename:properties',
	'filename:zhrc',
	'filename:bat',
	'filename:sh',
	'filename:zsh',
	'filename:bash',
	'filename:py',
	'filename:npmrc',
	'filename:dockercfg',
	'filename:pem',
	'filename:ppk',
	'filename:sql',
	'filename:pass',
	'filename:global',
	'filename:credentials',
	'filename:connections',
	'filename:s3cfg',
	'filename:wp-config',
	'filename:htpasswd',
	'filename:git-credentials',
	'filename:id_dsa',
	'filename:id_rsa',
'',
	'filename:bash_history',
	'filename:bash_profile',
	'filename:bashrc',
	'filename:cshrc',
	'filename:history',
	'filename:netrc',
	'filename:pgpass',
	'filename:tugboat',
	'filename:dhcpd.conf',
	'filename:express.conf',
	'filename:filezilla.xml',
	'filename:idea14.key',
	'filename:makefile',
	'filename:gitconfig',
	'filename:prod.exs',
	'filename:prod.secret.exs',
	'filename:proftpdpasswd',
	'filename:recentservers.xml',
	'filename:robomongo.json',
	'filename:server.cfg',
	'filename:shadow',
	'filename:sshd_config',
'',
	'dotfiles',
	'dot-files',
	'mydotfiles',
	'config',
	'dbpasswd',
	'db_password',
	'db_username',
	'dbuser',
	'dbpassword',
	'keyPassword',
	'storePassword',
	'passwords',
	'password',
	'secret.password',
	'database_password',
	'sql_password',
	'passwd',
	'pass',
	'pwd',
	'pwds',
	'root_password',
	'credentials',
	'security_credentials',
	'connectionstring',
	'private',
	'private_key',
	'master_key',
	'token',
	'access_token',
	'auth_token',
	'oauth_token',
	'authorizationToken',
	'secret',
	'secrets',
	'secret_key',
	'secret_token',
	'api_secret',
	'app_secret',
	'appsecret',
	'client_secret',
	'key',
	'send_keys',
	'send.keys',
	'sendkeys',
	'apikey',
	'api_key',
	'app_key',
	'application_key',
	'appkey',
	'appkeysecret',
	'access_key',
	'secret_access_key',
	'auth',
	'secure',
	'login',
	'conn.login',
	'ssh2_auth_password',
	'irc_pass',
	'fb_secret',
	'sf_username',
	'aws_key',
	'aws_token',
	'aws_secret',
	'aws_access',
	'slack_api',
	'slack_token',
	'bucket_password',
	'redis_password',
	'github_token',
	'codecov_token',
	'gsecr',
	'jdbc',
'',
	'ldap',
	'rsync',
	'ftp',
	'sftp',
	'ssh',
	'smtp',
	'redis',
	'mongodb',
	'mysql',
	'postgresql',
	'sqlite',
	'sqlite3',
	'cassandra',
'',
	'basic',
	'auth',
	'authorize',
	'authorization',
	'account_authorization',
	'bearer',
	'x-auth',
	'x-authorize',
	'x-bearer',
'',
	'amazonaws.com',
	'firebaseio.com',
	'cloudfront.net',
	'storage.google',
	'storage.cloud.google',
	'digitalocean',
	'heroku',
	'mailchimp',
'',
];

function __urlencode( $str )
{
	$str = str_replace( ':', '%3A', $str );
	$str = str_replace( '"', '%22', $str );
	$str = str_replace( ' ', '+', $str );
	return $str;
}

function usage( $err=null ) {
	echo 'Usage: '.$_SERVER['argv'][0]." <o/u/n> <org/user> [<dork file>]\n";
	if( $err ) {
		echo 'Error: '.$err."\n";
	}
	exit();
}

if( $_SERVER['argc'] < 3 || $_SERVER['argc'] > 4 ) {
	usage();
}

$ou = $_SERVER['argv'][1];
$t_orguser = explode( ',', $_SERVER['argv'][2] );
if( $_SERVER['argc'] == 4 ) {
	$t_dorks = [];
	$t_files = explode( ',', $_SERVER['argv'][3] );
	foreach( $t_files as $f ) {
		if( is_file($f) ) {
			$t_dorks = array_merge( $t_dorks, file( $f, FILE_IGNORE_NEW_LINES ) );
		}
	}
}
// var_dump( $t_dorks );

$t_tokens = [];
$f_tokens = dirname(__FILE__).'/.tokens';
if( file_exists($f_tokens) ) {
	$content = file_get_contents( $f_tokens );
	$m = preg_match_all( '([a-f0-9]{40})', $content, $matches );
	if( $m ) {
		$t_tokens = $matches[0];
	}
}
if( !count($t_tokens) ) {
	usage( 'auth token is missing' );
}
$l_token = count( $t_tokens ) - 1;
// var_dump( $t_tokens );

$t_exclude_extension = ['md','css','scss','sass','po','class','pyc','hqx','rst','pac','dex','ipynb'];
// var_dump( $t_orguser );
// var_dump( $t_dorks );

$q_suffix = '';
foreach( $t_exclude_extension as $ext ) {
	$q_suffix .= ' -extension:'.$ext;
}

foreach( $t_orguser as $orguser )
{
	echo ">>>>> https://github.com/".$orguser."\n";
	// array_unshift( $t_dorks, $orguser );
	
	foreach( $t_dorks as $d )
	{
		$output = [];
		$n_found = -1;

		if( trim($d) == '' ) {
			echo "\n";
		} else {
			if( $ou == 'o' ) {
				$d = 'org:'.$orguser.' '.$d;
			} elseif( $ou == 'u' ) {
				$d = 'user:'.$orguser.' '.$d;
			} else {
				$d = $orguser.' '.$d;
			}

			$d = $d . $q_suffix;

			if( $l_token >= 0 ) {
				usleep( 5000 );
				$cmd = 'github-search -n -t '.$t_tokens[rand(0,$l_token)].' -r 10 -s "'.$d.'" | grep "result(s) found"';
				// echo $cmd."\n";
				exec( $cmd, $output );

				if( count($output) ) {
					$tmp = explode( ' ', $output[0] );
					$n_found = $tmp[0];
				} else {
					$n_found = 0;
				}
			}

			$str = $gh_url.__urlencode($d);
			if( $l_token >= 0 && $n_found >= 0 ) {
				$str .= ' ('.$n_found.')';
			}
			$str .= "\n";

			if( $l_token >= 0 && $n_found > 0 ) {
				$color = 'white';
			} else {
				$color = 'light_grey';
			}

			Utils::_print( $str, $color );
		}
	}

	// array_shift( $t_dorks );

	echo "\n";
}


exit();
