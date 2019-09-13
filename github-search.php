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
	$options = '';
	$options .= 'c:';
	$options .= 'd';
	$options .= 'e:';
	$options .= 'f:';
	$options .= 'h';
	$options .= 'l:';
	$options .= 'm';
	$options .= 'n';
	$options .= 'p:';
	$options .= 'o:';
	$options .= 'r:';
	$options .= 's:';
	$options .= 't:';

	$t_options = getopt( $options );
	//var_dump( $t_options );

	$gsearch = new GitHubSearch();

	foreach( $t_options as $k=>$v )
	{
		switch( $k )
		{
			case 'c':
				$gsearch->setCookie( $v );
				break;

			case 'd':
				$gsearch->enableDownload();
				break;

			case 'e':
				$gsearch->setExtension( $v );
				break;

			case 'f':
				$gsearch->setFilename( $v );
				break;
			
			case 'h':
				Utils::help();
				break;
			
			case 'l':
				$gsearch->setLanguage( $v );
				break;
			
			case 'm':
				$gsearch->searchCommit( true );
				break;
			
			case 'n':
				$gsearch->setColorOutput( false );
				break;
			
			case 'o':
				$gsearch->setOrganization( $v );
				break;
			
			case 'p':
				$gsearch->setRepository( $v );
				break;
			
			case 'r':
				$gsearch->setMaxResult( $v );
				break;

			case 's':
				$gsearch->setString( $v );
				break;

			case 't':
				$gsearch->setAuthToken( $v );
				break;

			default:
				Utils::help( 'Unknown option: '.$k );
		}
	}
	
	if( !$gsearch->getString() && !$gsearch->getFilename() ) {
		Utils::help( 'Search param not found, provide at least a filename or a string' );
	}
	if( !$gsearch->getOrganization() && !$gsearch->getCookie() && !$gsearch->getAuthToken() ) {
		Utils::help( 'You must provide cookie session to perform queries without organization name' );
	}
}
// ---


// main loop
{
	if( $gsearch->getAuthToken() ) {
		$cnt_result = $gsearch->runApi();
	} else {
		$cnt_result = $gsearch->run();
	}
	
	if( $cnt_result ) {
		$gsearch->printResult();
	}
}
// ---


exit();

?>
