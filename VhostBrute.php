<?php

/**
 * I don't believe in license
 * You can do want you want with this program
 * - gwen -
 */

class VhostBrute
{
	private $reference = null;
	private $reference_random = null;
	
	
	private $domain = null;

	public function getDomain() {
		return $this->domain;
	}
	public function setDomain( $v ) {
		$this->domain = trim( $v );
		return true;
	}


	private $ip = null;

	public function getIp() {
		return $this->ip;
	}
	public function setIp( $v ) {
		$this->ip = trim( $v );
		return true;
	}

	private $port = null;

	public function getPort() {
		return $this->port;
	}
	public function setPort( $v ) {
		$this->port = (int)$v;
		return true;
	}


	private $ssl = false;

	public function getSsl() {
		return $this->ssl;
	}
	public function forceSsl( $v ) {
		$this->ssl = (bool)$v;
		return true;
	}

	private $max_child = 1;
	private $n_child = 0;
	private $loop_sleep = 100000;
	private $t_process = [];
	private $t_signal_queue = [];

	public function getMaxThreads() {
		return $this->max_child;
	}
	public function setMaxThreads( $v ) {
		$this->max_child = (int)$v;
		return true;
	}


	private $wordlist = null;
	private $_wordlist = [];
	private $_wordlists = [];
	private $n_words = 0;

	public function getWordlist() {
		return $this->wordlist;
	}
	public function setWordlist( $v ) {
		$f = trim( $v );
		if( !is_file($f) ) {
			return false;
		}
		$this->wordlist = $f;
		return true;
	}


	private function print_recap()
	{
		echo str_pad( '', 100, '-' )."\n";
		echo "IP: ".$this->ip."\n";
		echo "Domain: ".$this->domain."\n";
		echo "Wordlist: ".$this->wordlist."\n";
		echo "Count: ".$this->n_words."\n";
		echo "Threads: ".$this->max_child."\n";
		echo str_pad( '', 100, '-' )."\n\n";
	}
	
	
	public function run()
	{
	
		$this->_wordlist = file( $this->wordlist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		sort( $this->_wordlist );
		$this->n_words = count( $this->_wordlist );
		
		if( $this->max_child <= 0 ) {
			$this->max_child = 1;
		}
		if( $this->max_child > $this->n_words ) {
			$this->max_child = $this->n_words;
		}
		
		for( $i=0,$j=0 ; $i<$this->n_words ; $i++,$j++ ) {
			$c = $j % $this->max_child;
			if( !isset($this->_wordlists[$c]) ) {
				$this->_wordlists[$c] = [];
			}
			$this->_wordlists[$c][] = $this->_wordlist[$i];
		}
		
		$this->print_recap();

		//echo str_pad( '', 100, '-' )."\n";
		$this->reference = $this->doRequest( '' );
		$this->printResult( $this->ip, $this->reference );
		//$this->reference_random = $this->doRequest( ($h=uniqid('')) );
		//$this->printResult( $h, $this->reference_random );
		//$this->reference_random_domain = $this->doRequest( ($h=uniqid('').'.'.$this->domain) );
		//$this->printResult( $h, $this->reference_random_domain );
		echo "\n".str_pad( '', 100, '-' )."\n\n";
		//echo str_pad( '', 100, '-' )."\n\n";
		//exit();
		
		posix_setsid();
		declare( ticks=1 );
		pcntl_signal( SIGCHLD, array($this,'signal_handler') );
		
		for( $windex=0 ; $windex<$this->max_child ; $windex++ )
		{
			$pid = pcntl_fork();
			
			if( $pid == -1 ) {
				// fork error
			} elseif( $pid ) {
				// father
				$this->n_child++;
				$this->t_process[$pid] = uniqid();
		        if( isset($this->t_signal_queue[$pid]) ){
		        	$this->signal_handler( SIGCHLD, $pid, $this->t_signal_queue[$pid] );
		        	unset( $this->t_signal_queue[$pid] );
		        }
			} else {
				// child process
				$this->testWordlist( $windex );
				exit( 0 );
			}

			usleep( $this->loop_sleep );
		}
		
		while( $this->n_child ) {
			// surely leave the loop please :)
			sleep( 1 );
		}
	}

	
	private function testWordlist( $windex )
	{
		foreach( $this->_wordlists[$windex] as $w )
		{
			ob_start();
			$host = $w . '.' . $this->domain;
			$request = $this->doRequest( $host );
			//var_dump( $request->getResultHeader() );
			$this->printResult( $host, $request );
			$result = ob_get_contents();
			ob_end_clean();
			echo $result;
		}
	}
	
	
	private function doRequest( $host )
	{
		$request = new HttpRequest();
		$request->setSsl( $this->ssl );
		$request->setUrl( $this->ip );
		if( $host != '' ) {
			$request->setHeader( $host, 'Host' );
		}
		if( $this->port && $this->port!=80 && $this->port!=443 ) {
			$request->setPort( $this->port );
		}
		$request->request();
		
		return $request;
	}
	
	
	private function printResult( $host, $request, $compare=false )
	{
		$color = 'white';
		$output = $host;
		
		$output .= "\t\tC=".$request->getResultCode();
		$output .= "\t\tL=".$request->getResultBodySize();
		//$output .= "\t\tH:";

		$t_compare = null;
		$diff_header = $this->compareHeaders( $this->reference, $request, $t_compare );

		if( $request->getResultBodySize() != $this->reference->getResultBodySize() || $diff_header ) {
			$color = 'yellow';
		}
		
		Utils::_println( $output, $color );

		if( $diff_header ) {
			foreach( $t_compare[3] as $k=>$v ) {
				Utils::_println( "\t= ".$k.': '.$v, 'light_grey' );
			}
			foreach( $t_compare[2] as $k=>$v ) {
				Utils::_println( "\t~ ".$k.': '.$v, 'blue' );
			}
			foreach( $t_compare[0] as $k=>$v ) {
				Utils::_println( "\t- ".$k.': '.$v, 'red' );
			}
			foreach( $t_compare[1] as $k=>$v ) {
				Utils::_println( "\t+ ".$k.': '.$v, 'green' );
			}
		}
	}
	
	
	private function compareHeaders( $reference, $request, &$t_compare )
	{
		$h1 = $reference->getResultHeader();
		$h2 = $request->getResultHeader();
		$t_compare = [ 0=>[], 1=>[], 2=>[] ];
		
		unset( $h1['Date'] );
		unset( $h2['Date'] );
		
		$t_compare[0] = array_diff_key( $h1, $h2 );
		$t_compare[1] = array_diff_key( $h2, $h1 );
		
		foreach( $h1 as $k=>$v ) {
			if( isset($h2[$k]) ) {
				if( $h1[$k]!=$h2[$k] ) {
					$t_compare[2][$k] = $h1[$k] . '  ->  ' . $h2[$k];
				} else {
					$t_compare[3][$k] = $v;
				}
			}
		}

		return count($t_compare[0])+count($t_compare[1])+count($t_compare[2]);
	}

	
	// http://stackoverflow.com/questions/16238510/pcntl-fork-results-in-defunct-parent-process
	// Thousand Thanks!
	public function signal_handler( $signal, $pid=null, $status=null )
	{
		$pid = (int)$pid;
		
		// If no pid is provided, Let's wait to figure out which child process ended
		if( !$pid ){
			$pid = pcntl_waitpid( -1, $status, WNOHANG );
		}
		
		// Get all exited children
		while( $pid > 0 )
		{
			if( $pid && isset($this->t_process[$pid]) ) {
				// I don't care about exit status right now.
				//  $exitCode = pcntl_wexitstatus($status);
				//  if($exitCode != 0){
				//      echo "$pid exited with status ".$exitCode."\n";
				//  }
				// Process is finished, so remove it from the list.
				$this->n_child--;
				unset( $this->t_process[$pid] );
			}
			elseif( $pid ) {
				// Job finished before the parent process could record it as launched.
				// Store it to handle when the parent process is ready
				$this->t_signal_queue[$pid] = $status;
			}
			
			$pid = pcntl_waitpid( -1, $status, WNOHANG );
		}
		
		return true;
	}
}
