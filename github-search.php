#!/usr/bin/php
<?php

/**
 * I don't believe in license
 * You can do want you want with this program
 * - gwen -
 */

function __autoload( $c ) {
	include( $c.'.php' );
}


set_time_limit( 0 );


// parse command line
{
	$gsearch = new GitHubSearch();

	$argc = $_SERVER['argc'] - 1;

	for ($i = 1; $i <= $argc; $i++) {
		switch ($_SERVER['argv'][$i]) {
			case '-c':
				$gsearch->setCookie( $_SERVER['argv'][$i + 1] );
				$i++;
				break;

			case '-f':
				$gsearch->setFilename( $_SERVER['argv'][$i + 1] );
				$i++;
				break;
			
			case '-h':
				Utils::help();
				break;
			
			case '-o':
				$gsearch->setOrganization( $_SERVER['argv'][$i + 1] );
				$i++;
				break;
			
			case '-r':
				$gsearch->setMaxResult( $_SERVER['argv'][$i + 1] );
				$i++;
				break;

			case '-s':
				$gsearch->setString( $_SERVER['argv'][$i + 1] );
				$i++;
				break;

			default:
				Utils::help('Unknown option: '.$_SERVER['argv'][$i]);
		}
	}
	
	if( !$gsearch->getString() && !$gsearch->getFilename() ) {
		Utils::help('Search param not found!');
	}
	if( !$gsearch->getOrganization() && !$gsearch->getCookie() ) {
		Utils::help('You must provide cookie session to perform queries without organization name!');
	}
}
// ---


// main loop
{
	$cnt_result = $gsearch->run();

	if( $cnt_result ) {
		$gsearch->printResult();
	}
}
// ---


exit();

?>
