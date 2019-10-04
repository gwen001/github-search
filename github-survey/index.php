<?php

define( 'N_RESULTS_DESIRED', 10 );

$f_tokens = dirname(__FILE__) . '/.tokens';
if( !is_file($f_tokens) ) {
    exit( 'Tokens file not found!' );
}
$content = file_get_contents( $f_tokens );
$m = preg_match_all( '([a-f0-9]{40})', $content, $matches );
if( $m ) {
        $t_tokens = $matches[0];
}
// var_dump( $t_tokens );

$f_config = dirname(__FILE__) . '/github-survey.json';
if( !is_file($f_config) ) {
    exit( 'Config file not found!' );
}
$content = file_get_contents( $f_config );
$t_json = json_decode( $content, true );
// var_dump( $t_json );

$t_blank_words = ['not'];

function highlightCode( $content )
{
    global $t_blank_words;

    $content = htmlentities( $content );
    $t_words = explode( ' ', $_GET['d'] );
    $s_chars = ['"',"'"];
    $r_chars = [''];

    foreach( $t_words as $word ) {
        if( in_array(strtolower($word),$t_blank_words) ) {
            continue;
        }
        $word = str_replace( $s_chars, $r_chars, $word );
        $content = preg_replace( '#('.$word.')#i', "<span class=\"result_code_highlight\">$1</span>", $content );
    }

    // $content = nl2br( $content );

    return $content;
}

function diff2str( $date_diff )
{
    $str = '';
    if( $date_diff->y ) {
        $str .= $date_diff->y.' years ';
    }
    if( $date_diff->m || strlen($str) ) {
        $str .= $date_diff->m.' months ';
    }
    if( $date_diff->d || strlen($str) ) {
        $str .= $date_diff->d.' days ';
    }
    if( $date_diff->h || strlen($str) ) {
        $str .= $date_diff->h.' hours ';
    }
    if( $date_diff->i || strlen($str) ) {
        $str .= $date_diff->i.' minutes ';
    }
    if( $date_diff->s || strlen($str) ) {
        $str .= $date_diff->s.' secondes ';
    }
    return $str.'ago';
}

function format_bytes( $size )
{
    $units = array('b', 'kb', 'mb', 'gb', 'tb');
    for( $i=0 ; $size>=1000 && $i<4 ; $i++ ) {
            $size /= 1000;
    }
    return sprintf( "%.2f %s", round($size,2), $units[$i] );
}

function __urlencode( $d ) {
    $d = urlencode( $d );
    $d = str_replace( '"', '%22', $d );
    $d = str_replace( "'", '%27', $d );
    return $d;
}

function doSearchGithub( $dork, $page )
{
    global $t_tokens;

    $token = $t_tokens[ rand(0,count($t_tokens)-1) ];
    $t_headers = [ 'Authorization: token '.$token, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36' ];
    $url = 'https://api.github.com/search/code?sort=indexed&order=desc&page=' . $page . '&q=' . __urlencode($dork);
    // echo $url."<br>\n";

    $c = curl_init();
    curl_setopt( $c, CURLOPT_URL, $url );
    curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $c, CURLOPT_HTTPHEADER, $t_headers );
    $r = curl_exec( $c );
    curl_close( $c );
    
    // var_dump( $r );
    $t_json = json_decode( $r, true );
    
    if( !isset($t_json['total_count']) ) {
        return false;
    } else {
        return $t_json;
    }
}

function filterResults( $t_results, $t_dork )
{
    $t_filtered = [];

    foreach( $t_results['items'] as $results )
    {
        $r = isFiltered( $results, $t_dork );

        if( !$r ) {
            $t_filtered[] = $results;
        }
    }

    return $t_filtered;
}

function isFiltered( $result, $t_dork )
{
    if( !isset($t_dork['exclude']) || !count($t_dork['exclude']) ) {
        return false;
    }

    $full_path = $result['repository']['full_name'].'/'.$result['path'];

    foreach( $t_dork['exclude']['filepath'] as $exclude ) {
        $p = strpos( $full_path, $exclude );
        if( $p !== false && $p == 0 ) {
            // echo "exlude: ".$full_path." -> ".$exclude."<br>\n";
            return true;
        }
    }

    return false;
}

function getCode( $url )
{
    global $t_tokens;

    $token = $t_tokens[ rand(0,count($t_tokens)-1) ];
    $t_headers = [ 'Authorization: token '.$token, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36' ];

    $c = curl_init();
    curl_setopt( $c, CURLOPT_URL, $url );
    curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $c, CURLOPT_HTTPHEADER, $t_headers );
    $r = curl_exec( $c );
    curl_close( $c );
    
    // var_dump( $r );
    $t_json = json_decode( $r, true );
    
    if( !isset($t_json['sha']) ) {
        return false;
    } else {
        return base64_decode( str_replace('\n','',$t_json['content']) );
    }
}

function getCommitDate( $url )
{
    global $t_tokens;

    $token = $t_tokens[ rand(0,count($t_tokens)-1) ];
    $t_headers = [ 'Authorization: token '.$token, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36' ];

    $c = curl_init();
    curl_setopt( $c, CURLOPT_URL, $url );
    curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $c, CURLOPT_HTTPHEADER, $t_headers );
    $r = curl_exec( $c );
    curl_close( $c );
    
    // var_dump( $r );
    $t_json = json_decode( $r, true );
    
    if( !isset($t_json['sha']) ) {
        return false;
    } else {
        return $t_json['committer']['date'];
    }
}

function getCommitDates( &$t_filtered )
{
    global $t_tokens;

    $mh = curl_multi_init();

    foreach( $t_filtered as &$result )
    {
        $token = $t_tokens[ rand(0,count($t_tokens)-1) ];
        $t_headers = [ 'Authorization: token '.$token, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36' ];

        $commit_id = explode( '=', $result['url'] )[1];
        $result['commit_url'] = str_replace('{/sha}','/',$result['repository']['git_commits_url']) . $commit_id;
        
        $result['curl'] = curl_init( $result['commit_url'] );
        curl_setopt( $result['curl'], CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $result['curl'], CURLOPT_HTTPHEADER, $t_headers );
        curl_multi_add_handle( $mh, $result['curl'] );
    }

    $running = null;
    do {
        curl_multi_exec( $mh, $running );
    } while( $running );

    foreach( $t_filtered as $result ) {
        curl_multi_remove_handle( $mh, $result['curl'] );
    }
    curl_multi_close( $mh );

    foreach( $t_filtered as &$result )
    {
        $r = curl_multi_getcontent( $result['curl'] );

        // var_dump( $r );
        $t_json = json_decode( $r, true );

        if( isset($t_json['sha']) ) {
            $result['commit_date'] = new DateTime( $t_json['committer']['date'] );
            $now = new DateTime();
            $date_diff = date_diff( $result['commit_date'], $now );
            $result['commit_date_diff'] = diff2str( $date_diff );
        }
    }
}

function getCodes( &$t_filtered )
{
    global $t_tokens;

    $mh = curl_multi_init();

    foreach( $t_filtered as &$result )
    {
        $token = $t_tokens[ rand(0,count($t_tokens)-1) ];
        $t_headers = [ 'Authorization: token '.$token, 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36' ];
        
        $result['curl'] = curl_init( $result['git_url'] );
        curl_setopt( $result['curl'], CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $result['curl'], CURLOPT_HTTPHEADER, $t_headers );
        curl_multi_add_handle( $mh, $result['curl'] );
    }

    $running = null;
    do {
        curl_multi_exec( $mh, $running );
    } while( $running );

    foreach( $t_filtered as $result ) {
        curl_multi_remove_handle( $mh, $result['curl'] );
    }
    curl_multi_close( $mh );

    foreach( $t_filtered as &$result )
    {
        $r = curl_multi_getcontent( $result['curl'] );

        // var_dump( $r );
        $t_json = json_decode( $r, true );

        if( isset($t_json['sha']) ) {
            $result['code'] = base64_decode( str_replace('\n','',$t_json['content']) );
        }
    }
}


if( isset($_GET['d']) )
{
    $n_desired = 0;
    $page = 0;

    do
    {
        $t_results = doSearchGithub( $_GET['d'], $page );
        if( $t_results === false ) {
            break;
        }
        $n_results = count( $t_results['items'] );
        if( !$n_results ) {
            break;
        }
        $t_filtered = filterResults( $t_results, $t_json['github_dorks'][$_GET['d']] );
        $n_desired += count( $t_filtered );
        $page++;
    }
    while( $n_desired < N_RESULTS_DESIRED );

    if( count($t_filtered) > N_RESULTS_DESIRED ) {
        $t_filtered = array_slice( $t_filtered, 0, N_RESULTS_DESIRED );
    }

    getCodes( $t_filtered );
    getCommitDates( $t_filtered );
}

if( isset($_GET['a']) && $_GET['a'] == 'exclude' )
{
    if( isset($_POST['d']) )
    {
        if( !isset($t_json['github_dorks'][ $_POST['d'] ]['exclude']) ) {
            $t_json['github_dorks'][ $_POST['d'] ]['exclude'] = [
                'filepath' => [],
                'content' => [],
                'extension' => [],
            ];
        }

        $t_json['github_dorks'][ $_POST['d'] ]['exclude']['filepath'][] = $_POST['e'];

        file_put_contents( $f_config, json_encode($t_json,JSON_PRETTY_PRINT) );
    }

    exit();
}

?>

<html>
    <head>
        <script src="js/jquery-3.4.1.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
        <link href="css/bootstrap.min.css" rel="stylesheet" />
        <style>
            body {
                margin-left: 15px;
                margin-top: 15px;
            }
            .result {
                margin-bottom: 20px;
            }
            .result_repository_full_name a {
                color: #555;
                font-size: 0.9em;
                font-weight: bold;
            }
            .result_path {

            }
            .result_size {
                font-size: 0.8em;
            }
            .result_commit_date {
                color: #777;
                font-size: 0.8em;
            }
            .result_code {
                border: 1px solid #CCC;
                border-radius: 3px;
                font-size: 0.8em;
                max-height: 300px;
                margin-bottom: 0px;
                overflow: scroll;
                padding: 10px;
                position: relative;
            }
            .result_code_highlight {
                background-color: #FFF5B1 !important;
                /* color: #F00 !important; */
                font-weight: bold;
            }
            .result_profile_picture {
                float: left;
                margin-right: 10px;
            }
            .result_action {
                float: right;
            }
            .result_action img {
                padding-left: 10px;
                /* width: 24px; */
            }
            pre {
                white-space: pre-wrap;       /* css-3 */
                white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
                white-space: -pre-wrap;      /* Opera 4-6 */
                white-space: -o-pre-wrap;    /* Opera 7 */
                word-wrap: break-word;
            }
        </style>
    </head>

    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-2 list-group">
                    <?php foreach( $t_json['github_dorks'] as $dork=>$datas ) { ?>
                        <a href="?d=<?php echo __urlencode($dork); ?>" class="list-group-item list-group-item-action"><?php echo $dork; ?></a>
                    <?php } ?>
                </div>
                <?php if( isset($t_filtered) && count($t_filtered) ) { ?>
                <div class="col-md-10">
                    <?php foreach( $t_filtered as $result ) { ?>
                        <div class="result" data-full-path="<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>">
                            <div class="result_action">
                                <a href="javascript:excludeResult('<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>');" title="exclude file"><img src="img/page_delete.png" title="exclude file" /></a>
                                <a href="javascript:excludeResult('<?php echo $result['repository']['full_name']; ?>');" title="exclude repository"><img src="img/folder_delete.png" title="exclude repository" /></a>
                                <a href="javascript:excludeResult('<?php echo $result['repository']['owner']['login']; ?>');" title="exclude user"><img src="img/user_delete.png" title="exclude user" /></a>
                            </div>
                            <div class="result_profile_picture"><a href="<?php echo $result['repository']['owner']['html_url']; ?>" target="_blank"><img src="<?php echo $result['repository']['owner']['avatar_url']; ?>&s=40" width="40" /></a></div>
                            <div class="result_repository_full_name"><a href="<?php echo $result['repository']['html_url']; ?>" target="_blank"><?php echo $result['repository']['full_name']; ?></a></div>
                            <div class="result_path">
                                <a href="<?php echo $result['html_url']; ?>" target="_blank"><?php echo $result['path']; ?></a>
                                <?php if( isset($result['code']) ) { ?>
                                    <span class="result_size">(<?php echo format_bytes(strlen($result['code'])); ?>)</span>
                                <?php } ?>
                            </div>
                            <?php if( isset($result['code']) ) { ?>
                                <pre class="result_code"><?php echo highlightCode($result['code']); ?></pre>
                            <?php } ?>
                            <?php if( isset($result['commit_date']) ) { ?>
                                <div class="result_commit_date">
                                    <?php echo $result['commit_date']->format('d/m/Y H:i:s') ?>
                                    -
                                    <?php echo $result['commit_date_diff'] ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>

        <script type="text/javascript">
            $(document).ready(function() {
                $('.result_code').each(function(){
                    c = $(this).find('.result_code_highlight').first();
                    p = c.position().top - 47;
                    $(this).scrollTop( p );
                });
            });

            function excludeResult( result )
            {
                datas = 'd=' + getQueryParam('d') + '&e=' + result;

                $.ajax({
                    type: 'POST',
                    url: '?a=exclude',
                    data: datas,
                    dataType: 'json',
                    success: function(){
                        ;
                    }
                });

                $('.result[data-full-path^="'+result+'"]').hide();
            }

            function getQueryParam(param) {
                location.search.substr(1)
                    .split("&")
                    .some(function(item) {
                        return item.split("=")[0] == param && (param = item.split("=")[1])
                    })
                return param
            }
        </script>
    </body>
</html>