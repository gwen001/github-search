<?php

/**
 * I don't believe in license
 * You can do want you want with this program
 * - gwen -
 */

class GitHubSearch
{
	const FS = '+';
	const GITHUB_URL = 'https://github.com';
	const GITHUB_PAGE_RESULT = 10;
	
	private $search_url = '/search?utf8=%E2%9C%93&o=desc&type=Code&s=&q='; 
	
	private $max_result = 50;
	
	private $search_string = null;

	private $cookie = null;
	
	private $filename = null;
	
	private $organization = null;
	
	private $string = null;
	
	private $search_params = [];

	private $t_result = [];
	
	
	public function getCookie() {
		return $this->cookie;
	}
	public function setCookie( $v ) {
		$this->cookie = trim( $v );
		return true;
	}
	
	
	public function getMaxResult() {
		return $this->max_result;
	}
	public function setMaxResult( $v ) {
		$this->max_result = (int)$v;
		return true;
	}


	public function getFilename() {
		return $this->filename;
	}
	public function setFilename( $v ) {
		$this->filename = trim( $v );
		$this->addParam( 'filename', $this->filename );
		return true;
	}
	
	
	public function getOrganization() {
		return $this->organization;
	}
	public function setOrganization( $v ) {
		$this->organization = trim( $v );
		$this->addParam( 'org', $this->organization );
		return true;
	}
	
	
	public function getString() {
		return $this->string;
	}
	public function setString( $v ) {
		$this->string = trim( $v );
		return true;
	}


	public function getParams() {
		return $this->search_params;
	}
	public function addParam( $k, $v ) {
		$this->search_params[$k] = $v;
		return true;
	}
	
	
	public function computeSearch()
	{
		$tmp = [];
		foreach ($this->search_params as $k => $v) {
			$tmp[] = $k . ':' . $v;
		}
		$tmp = array_map( 'urlencode', $tmp );
		$search_string = implode(self::FS, $tmp);
		if( strlen($this->string) ) {
			$search_string .= self::FS . $this->string;
		}
		return $search_string;
	}
	
	
	public function getSearch( $full_url=false ) {
		if( is_null($this->search_string) ) {
			$this->search_string = $this->computeSearch();
		}
		$url = $this->search_string;
		if( $full_url ) {
			$url = self::GITHUB_URL.$this->search_url.$url;
		}
		return $url;
	}

	
	public function run()
	{
		$n_result = 0;
		$max_page = ceil( $this->max_result / self::GITHUB_PAGE_RESULT );
		$url = $this->getSearch(true);
		echo "Calling url ".$url."\n";
		
		for( $p=1,$run=true ; $p<=$max_page && $run ; $p++ )
		{
			$url = $this->getSearch(true).'&p='.$p;
			
			$c = curl_init();
			curl_setopt( $c, CURLOPT_URL, $this->getSearch(true) );
			curl_setopt( $c, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $c, CURLOPT_HEADER, true );
			curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
			if( $this->cookie ) {
				curl_setopt( $c, CURLOPT_COOKIE, $this->cookie );
			}
			$r = curl_exec( $c );
			curl_close( $c );
			
			//$r = file_get_contents( 'test_org_file.html' );
			//$r = file_get_contents( 'test_org_str.html' );
			//$r = file_get_contents('test_org_str2.html');
			//$r = file_get_contents('test_str.html');
			//var_dump( $r );
			//exit();
	
			$doc = new DomDocument();
			@$doc->loadHTML($r);
			
			// extract results
			$xpath = new DOMXPath($doc);
			
			// number of result
			if( $p == 1 ) {
				$t_menu = $xpath->query('//nav[contains(@class,"menu")]/a[contains(@href,"type=Code")]/span');
				if ($t_menu->length) {
					$n_found = (int)preg_replace( '#[^0-9]#', '', $t_menu[0]->nodeValue );
					if( $n_found < $this->max_result ) {
						$this->max_result = $n_found;
					}
					echo $n_found . " result(s) found, displaying ".$this->max_result.".\n";
					$max_page = ceil( $n_found / self::GITHUB_PAGE_RESULT );
				} else {
					Utils::help('Cannot determine how many results has been found.');
					exit();
				}
				echo "\n";
			}
			
			echo "Parsing page ".$p."...\n";
			
			$t_result = $xpath->query('//div[contains(@class,"code-list-item")]');
			
			foreach ($t_result as $res)
			{
				$tmp = [
					'repository' => '',
					'file' => '',
					'link' => '',
					'language' => '',
					'summary' => [],
				];
				
				// extract results item title
				$entries = $xpath->query('p[contains(@class,"title")]/a', $res);
				$tmp['repository'] = trim( $entries[0]->textContent );
				$tmp['file'] = trim( $entries[1]->textContent );
				$tmp['link'] = trim( $entries[1]->getAttribute('href') );
				
				$entries = $xpath->query('span[contains(@class,"language")]', $res);
				if( $entries->length ) {
					$tmp['language'] = trim($entries[0]->textContent);
				}
				
				if( strlen($this->string) ) {
					$code = $xpath->query('div/table[contains(@class,"highlight")]', $res);
					if ($code->length) {
						$t_td = $xpath->query('tr/td', $code[0]);
						$n_td = $t_td->length; 
						for( $i=1 ; $i<$n_td ; $i+=2 ) {
							if( stristr($t_td[$i]->nodeValue,$this->string) ) {
								$n_line = (int)$t_td[$i-1]->nodeValue;
								$tmp['summary'][ $n_line ] = trim( $t_td[$i]->nodeValue );
							}
						}
					}
				}
				
				$this->t_result[] = $tmp;

				$n_result++;
				if( $n_result >= $this->max_result ) {
					// surely leave the loop please :)
					$run = false;
					break;
				}
			}
		}
		
		echo "\n";
		
		return count($this->t_result);
	}
	

	public function printResult()
	{
		foreach( $this->t_result as $k=>$r )
		{
			echo "#".($k+1);
			echo "\nrepository:\t".($r['repository']?$r['repository']:'-');
			echo "\nfile:\t\t".($r['file']?$r['file']:'-');
			echo "\nlanguage:\t".($r['language']?$r['language']:'-');
			echo "\nsummary:";
			if( !count($r['summary']) ) {
				echo "\t-";
			} else {
				for ($i = 0; list($line, $s) = each($r['summary']); $i++) {
					echo (($i == 0) ? "\t" : "\n\t\t") . $line . '- ' . $s;
				}
			}
			echo "\nlink:\t\t".($r['link']?self::GITHUB_URL.$r['link']:'-');
			echo "\n\n";
		}
	}
}

?>
