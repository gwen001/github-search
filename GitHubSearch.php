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
	const GITHUB_API_URL = 'https://api.github.com';
	const GITHUB_PAGE_RESULT = 10;
	const GITHUB_API_PAGE_RESULT = 50;
	const USER_AGENT = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0';

	private $search_url = '/search?utf8=%E2%9C%93&o=desc&type=Code&s=&q=';
	private $search_api_url = '/search/code';
    private $search_api_parameters = '?sort=updated&order=asc&q=';
    private $search_api_repos = '/repos';
    
	private $max_result = 100;

	private $search_string = null;

	private $cookie = null;
	
	private $auth_token = null;

	private $search_commit = false;

	private $filename = null;

	private $language = null;

	private $organization = null;

	private $repository = null;

	private $string = null;

	private $color_output = true;

	private $search_params = [];

	private $t_result = [];


	public function getCookie() {
		return $this->cookie;
	}
	public function setCookie( $v ) {
		$this->cookie = trim( $v );
		return true;
	}


	public function getColorOutput() {
		return $this->color_output;
	}
	public function setColorOutput( $v ) {
		$this->color_output = (bool)$v;
		return true;
	}


	public function getMaxResult() {
		return $this->max_result;
	}
	public function setMaxResult( $v ) {
		$this->max_result = (int)$v;
		return true;
	}


	public function getExtension() {
		return $this->extension;
	}
	public function setExtension( $v ) {
		$this->extension = trim( $v );
		$this->addParam( 'extension', $this->extension );
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


	public function getLanguage() {
		return $this->language;
	}
	public function setLanguage( $v ) {
		$this->language = trim( $v );
		$this->addParam( 'language', $this->language );
		return true;
	}

    
	public function getSearchCommit() {
		return $this->search_commit;
	}
	public function searchCommit( $v ) {
		$this->search_commit = (bool)$v;
        $this->addParam( 'since', '1970-01-01T00:00:00Z' );
		return true;
	}


	public function getOrganization() {
		return $this->organization;
	}
	public function setOrganization( $v ) {
		$this->organization = trim( $v );
		$this->addParam( 'user', $this->organization );
		return true;
	}


	public function getRepository() {
		return $this->repository;
	}
	public function setRepository( $v ) {
		$this->repository = trim( $v );
		return true;
	}


	public function getAuthToken() {
		return $this->auth_token;
	}
	public function setAuthToken( $v ) {
        $this->auth_token = explode( ',', trim($v) );
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
		if( strlen($this->string) ) {
			$tmp[] = preg_replace('#\s+#',self::FS,$this->string);
		}
		$search_string = implode(self::FS, $tmp);
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


	public function getSearchApi( $full_url=false ) {
		if( is_null($this->search_string) ) {
			$this->search_string = $this->computeSearch();
		}
		$url = $this->search_string;
		if( $full_url ) {
            $url = self::GITHUB_API_URL.$this->search_api_url.$this->search_api_parameters.$url;
		}
		return $url;
	}


	public function run()
	{
		$n_result = 0;
		$max_page = ceil( $this->max_result / self::GITHUB_PAGE_RESULT );
		$url = $this->getSearch( true );
		echo "Calling url ".$url." using regular search\n";

		for( $p=1,$run=true ; $p<=$max_page && $run ; $p++ )
		{
			$url = $this->getSearch(true).'&p='.$p;

			$c = curl_init();
			curl_setopt( $c, CURLOPT_URL, $url );
			curl_setopt( $c, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $c, CURLOPT_HEADER, true );
			curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
			if( $this->cookie ) {
				curl_setopt( $c, CURLOPT_COOKIE, $this->cookie );
			}
			curl_setopt( $c, CURLOPT_USERAGENT, self::USER_AGENT );
			$r = curl_exec( $c );
			//file_put_contents( 'result_'.$p.'.html', $r );
			//$r = file_get_contents( 'result_'.$p.'.html' );
			//var_dump( $r );
			curl_close( $c );

			if( stristr($r,'looks like something went wrong') ) {
				echo "\n";
				Utils::_println( 'Looks like something went wrong, breaking...', 'red' );
				echo "\n";
				return count($this->t_result);
			}
			
			if( stristr($r,'abuse detection mechanism') ) {
				echo "\n";
				Utils::_println( 'Abuse detection mechanism spotted, breaking...', 'red' );
				echo "\n";
				return count($this->t_result);
			}
			
			$doc = new \DomDocument();
			@$doc->loadHTML( $r );

			// extract results
			$xpath = new \DOMXPath( $doc );

			// number of result
			if( $p == 1 ) {
				//$t_menu = $xpath->query('//nav[contains(@class,"menu")]/a[contains(@href,"type=Code")]/span');
				$t_menu = $xpath->query('//nav[contains(@role,"navigation")]/a[contains(@href,"type=Code")]/span'); // maj 09/04/2017
				//var_dump( $t_menu );
				if( $t_menu->length ) {
					$n_found = (int)preg_replace( '#[^0-9]#', '', $t_menu[0]->nodeValue );
					if( $n_found < $this->max_result ) {
						$this->max_result = $n_found;
						$max_page = ceil( $n_found / self::GITHUB_PAGE_RESULT );
					}
					echo $n_found . " result(s) found, displaying ".$this->max_result.".\n\n";
				} else {
					echo "Nothing found.\n";
					exit();
				}
			}

			echo "Parsing page ".$p."...\n";

			$t_result = $xpath->query('//div[contains(@class,"code-list-item")]');
			//var_dump($t_result->length);

			foreach( $t_result as $res )
			{
				$tmp = [
					'repository' => '',
					'file' => '',
					'link' => '',
					'language' => '',
					'summary' => [],
				];

				$language = $xpath->query('span[contains(@class,"float-right f6 text-gray")]', $res);
				//var_dump( $language->length );
				if( $language->length ) {
					$tmp['language'] = trim($language[0]->textContent);
				}

				$details = $xpath->query('div[contains(@class,"d-inline-block col-10")]/a', $res); // maj 09/04/2017
				
				$tmp['repository'] = trim( $details[0]->textContent );
				$tmp['file'] = trim( $details[1]->textContent );
				$tmp['link'] = self::GITHUB_URL.trim( $details[1]->getAttribute('href') );

				if( strlen($this->string) ) {
					// extract code summary
					$code = $xpath->query('div/table[contains(@class,"highlight")]', $res);
					if ($code->length) {
						$t_td = $xpath->query('tr/td', $code[0]);
						$n_td = $t_td->length;
						for( $i=1 ; $i<$n_td ; $i+=2 ) {
							if( stristr($t_td[$i]->nodeValue,$this->string) ) {
								$n_line = (int)$t_td[$i-1]->nodeValue; // line number
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
			
			usleep( 100000 ); // 1 seconde
		}

		echo "\n";

		return count($this->t_result);
	}


	public function runApi()
	{
		$t_headers = [
			'User-agent: '.self::USER_AGENT,
			'Accept: application/vnd.github.v3.text-match+json',
            //'Accept: Accept: application/vnd.github.cloak-preview',
		];
		if( $this->auth_token ) {
            $i_token = 0;
            $n_token = count( $this->auth_token ) - 1;
            $i_header = count( $t_headers );
			$t_headers[] = 'Authorization: token '.$this->auth_token[$i_token];
		}
		//var_dump( $t_headers );
		
		$n_result = 0;
		$max_page = ceil( $this->max_result / self::GITHUB_API_PAGE_RESULT );
		//var_dump($max_page);
		$url = $this->getSearchApi( true );
		echo "Calling url ".$url." using API search\n";
        //echo $max_page;

		for( $p=1,$run=true ; $p<=$max_page && $run ; $p++ )
		{
            usleep( 10000 );
            
			$url = $this->getSearchApi(true).'&page='.$p;
			//var_dump( $url );

			$c = curl_init();
			curl_setopt( $c, CURLOPT_URL, $url );
			curl_setopt( $c, CURLOPT_FOLLOWLOCATION, true );
			//curl_setopt( $c, CURLOPT_HEADER, true );
			curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $c, CURLOPT_HTTPHEADER, $t_headers );
			$r = curl_exec( $c );
			//file_put_contents( 'result_'.$p.'.html', $r );
			//$r = file_get_contents( 'result_'.$p.'.html' );
			curl_close( $c );
			//echo strlen($r)."\n";
            //var_dump($r);
            
			$t_json = json_decode( $r );
			//file_put_contents( 'result_'.$p.'_json.html', print_r($t_json,true) );
			//var_dump( $t_json );
			//var_dump( count($t_json) );
			
			if( stristr($r,'looks like something went wrong') !=false  || stristr($r,'Only the first 1000 search') !==false ) {
				echo "\n";
				Utils::_println( 'Looks like something went wrong, breaking...', 'red' );
				echo "\n";
				return count($this->t_result);
			}
			
			if( stristr($r,'abuse detection mechanism') !==false || stristr($r,'API rate limit exceeded') !==false ) {
                echo "\n";
                Utils::_println( 'Abuse detection mechanism spotted, breaking...', 'red' );
                if( $this->auth_token && $i_token<$n_token ) {
                    Utils::_println( 'Using new token...', 'red' );
                    echo "\n";
                    $i_token++;
                    $t_headers[$i_header] = 'Authorization: token '.$this->auth_token[$i_token];
                    $p--;
                    continue;
                } else {
                    echo "\n";
                    return count($this->t_result);
                }
			}
			
			// number of result
			if( $p == 1 ) {
				$n_found = isset($t_json->total_count) ? $t_json->total_count : 0;
				if( $n_found ) {
					if( $n_found < $this->max_result ) {
						$this->max_result = $n_found;
						$max_page = ceil( $n_found / self::GITHUB_API_PAGE_RESULT );
					}
					echo $n_found . " result(s) found, displaying ".$this->max_result.".\n\n";
				}
				else {
					echo "Nothing found.\n";
					exit();
				}
			}

			echo "Parsing page ".$p."...\n";

            if( isset($t_json->items) )
            {
                foreach( $t_json->items as $item )
                {
                    $tmp = [
                        'repository' => '',
                        'file' => '',
                        'link' => '',
                        'language' => '',
                        'summary' => [],
                    ];
                    
                    $tmp['repository'] = $item->repository->full_name;
                    $tmp['file'] = $item->path;
                    $tmp['link'] = $item->html_url;
                    
					$l = 0;

					if( isset($item->text_matches) && is_array($item->text_matches) && count($item->text_matches) ) {
						$t_fragment = explode( "\n", $item->text_matches[0]->fragment );
						foreach( $t_fragment as $f ) {
							$f = trim( $f );
							if( stristr($f,$this->string) ) {
								$tmp['summary'][--$l] = $f;
							}
						}
					}

                    $this->t_result[] = $tmp;
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
			if( !$this->color_output ) {
				echo "#".($k+1);
			} else {
				Utils::_print( "#".($k+1), 'light_cyan' );
			}
			echo "\nrepository:\t".($r['repository']?$r['repository']:'-');
			echo "\nfile:\t\t".($r['file']?$r['file']:'-');
			echo "\nlanguage:\t".($r['language']?$r['language']:'-');
			echo "\nsummary:";
			if( !count($r['summary']) ) {
				echo "\t-";
			} else {
				for ($i = 0; list($line, $s) = each($r['summary']); $i++) {
					echo (($i == 0) ? "\t" : "\n\t\t");
					$this->printStringResult( $line, $s );
				}
			}
			echo "\nlink:\t\t".($r['link']?$r['link']:'-');
			echo "\n\n";
		}
	}
	
	
	private function printStringResult( $line, $str )
	{
		if( !$this->color_output ) {
			echo '('.($line>=0?$line:'-').') '.$str;
			return;
		}

		$p = 0;
		$l = strlen( $this->string );
		$m = preg_match_all( '#'.$this->string.'#i', $str, $matches, PREG_OFFSET_CAPTURE );
		//var_dump( $matches );
		//var_dump( $str );
		
		Utils::_print( '('.($line>=0?$line:'-').') ', 'yellow' );
		
		if( $m ) {
			$n = count( $matches[0] );
			//var_dump($n);
			for( $i=0 ; $i<$n ; $i++ ) {
				$s1 = substr( $str, $p, ($matches[0][$i][1]-$p) );
				$s2 = substr( $str, $matches[0][$i][1], $l );
				$p = $matches[0][$i][1] + $l;
				//$p = $matches[$i][1] + $l;
				Utils::_print( $s1, 'green' );
				Utils::_print( $s2, 'light_green' );
				//break;
			}
		}
		
		$s3 = substr( $str, $p );
		Utils::_print( $s3, 'green' );
	}
}
