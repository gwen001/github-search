<?php

class Utils
{
	const TMP_DIR = '/tmp/';
	const T_SHELL_COLORS = array(
		'nc' => '0',
		'black' => '0;30',
		'red' => '0;31',
		'green' => '0;32',
		'orange' => '0;33',
		'blue' => '0;34',
		'purple' => '0;35',
		'cyan' => '0;36',
		'light_grey' => '0;37',
		'dark_grey' => '1;30',
		'light_red' => '1;31',
		'light_green' => '1;32',
		'yellow' => '1;33',
		'light_blue' => '1;34',
		'light_purple' => '1;35',
		'light_cyan' => '1;36',
		'white' => '1;37',
	);


	public static function help( $error='' )
	{
        print("Usage: php github-search.php [OPTIONS]

Options:
    -c	set cookie session
    -e	file extension filter
    -f	looking for file
    -h	print this help
    -l	language filter
    -m	look for commit, -o and -p are required
    -n	no color
    -p	provide repository name
    -o	provide organization name
    -r	maximum number of results, default 100
    -s	search string
    -t	set authorization token (overwrite cookie, best option)
");

        if( $error ) {
			echo "\nError: ".$error."!\n";
		}

		exit();
	}


	public static function isIp( $str ) {
		return filter_var( $str, FILTER_VALIDATE_IP );
	}


	public static function isEmail( $str )
	{
		return filter_var( $str, FILTER_VALIDATE_EMAIL );
	}


	public static function _print( $str, $color )
	{
		echo "\033[".self::T_SHELL_COLORS[$color]."m".$str."\033[0m";
	}
	public static function _println( $str, $color )
	{
		self::_print( $str, $color );
		echo "\n";
	}


	public static function _array_search( $array, $search, $ignore_case=true )
	{
		if( $ignore_case ) {
			$f = 'stristr';
		} else {
			$f = 'strstr';
		}

		if( !is_array($search) ) {
			$search = array( $search );
		}

		foreach( $array as $k=>$v ) {
			foreach( $search as $str ) {
				if( $f($v, $str) ) {
					return $k;
				}
			}
		}

		return false;
	}
}

?>
