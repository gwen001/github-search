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
	'filename:py',
	'filename:npmrc',
	'filename:dockercfg',
	'filename:pem',
	'filename:ppk',
	'filename:sql',
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
	'ftp://',
	'sftp://',
	'ssh://',
	'smtp://',
	'redis://',
	'mongodb://',
	'mysql://',
	'postgresql://',
	'cassandra://',
	'sqlite://',
	'sqlite3://',
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
	echo 'Usage: '.$_SERVER['argv'][0]." <o/u> <org/user>\n";
	if( $err ) {
		echo 'Error: '.$err."\n";
	}
	exit();
}

if( $_SERVER['argc'] != 3 ) {
	usage();
}

$ou = $_SERVER['argv'][1];

$cmd = 'cat ~/.gitrobrc | egrep -o "[a-f0-9]{40}"';
// echo $cmd."\n";
exec( $cmd, $t_tokens );
$l_token = count( $t_tokens ) - 1;
// var_dump( $t_tokens );

for( $i=2 ; $i<$_SERVER['argc'] ; $i++ )
{
	$orguser = $_SERVER['argv'][$i];
	
	foreach( $t_dorks as $d )
	{
		$output = [];
		$n_found = -1;

		if( trim($d) == '' ) {
			echo "\n";
		} else {
			if( $ou == 'o' ) {
				$d = 'org:'.$orguser.' '.$d;
			} else {
				$d = 'user:'.$orguser.' '.$d;
			}

			if( $l_token >= 0 ) {
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
	
	echo "\n";
}


exit();

?>

