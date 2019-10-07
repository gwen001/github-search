<?php

set_time_limit( 0 );

error_reporting( E_ALL );
ini_set( 'display_errors', true );
ini_set( 'display_startup_errors', true );

define( 'N_RESULTS_DESIRED', 25 );
define( 'MAX_PAGE', 10 );

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
$t_config = json_decode( $content, true );
// var_dump( $t_config );

$t_blank_words = ['not'];

function getRawUrl( $result )
{
    $raw_url = $result['html_url'];
    $raw_url = str_replace( 'https://github.com/', 'https://raw.githubusercontent.com/', $raw_url );
    $raw_url = str_replace( '/blob/', '/', $raw_url );
    return $raw_url;
}

function highlightCode( $content )
{
    global $t_blank_words;

    $content = htmlentities( $content );
    $t_words = explode( ' ', $_GET['d'] );
    $s_chars = ['"',"'"];
    $r_chars = [''];

    foreach( $t_words as $k=>$word ) {
        if( in_array(strtolower($word),$t_blank_words) ) {
            continue;
        }
        $word = str_replace( $s_chars, $r_chars, $word );
        $class = 'result_code_highlight';
        if( $k == 0 ) {
            $class .= ' first_word_match';
        }
        $content = preg_replace( '#('.$word.')#i', "<span class=\"".$class."\">$1</span>", $content );
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
    $t_config = json_decode( $r, true );
    
    if( !isset($t_config['total_count']) ) {
        return false;
    } else {
        return $t_config;
    }
}

function filterResults( $t_results, $t_exclude, $t_filters )
{
    $t_filtered = [];

    foreach( $t_results as $results )
    {
        $r = isFiltered( $results, $t_exclude, $t_filters );

        if( !$r ) {
            $t_filtered[] = $results;
        }
    }

    return $t_filtered;
}

function excludeFusion( $t_config, $dork )
{
    $t_exclude = [];

    if( isset($t_config['exclude']) && isset($t_config['exclude']['content']) ) {
        $t_exclude['content'] = $t_config['exclude']['content'];
    } else {
        $t_exclude['content'] = [];
    }
    if( isset($t_config['github_dorks'][$dork]['exclude']) && isset($t_config['github_dorks'][$dork]['exclude']['content']) ) {
        $t_exclude['content'] = array_merge( $t_exclude['content'], $t_config['github_dorks'][$dork]['exclude']['content'] );
    }

    if( isset($t_config['exclude']) && isset($t_config['exclude']['extension']) ) {
        $t_exclude['extension'] = $t_config['exclude']['extension'];
    } else {
        $t_exclude['extension'] = [];
    }
    if( isset($t_config['github_dorks'][$dork]['exclude']) && isset($t_config['github_dorks'][$dork]['exclude']['extension']) ) {
        $t_exclude['extension'] = array_merge( $t_exclude['extension'], $t_config['github_dorks'][$dork]['exclude']['extension'] );
    }

    if( isset($t_config['exclude']) && isset($t_config['exclude']['filepath']) ) {
        $t_exclude['filepath'] = $t_config['exclude']['filepath'];
    } else {
        $t_exclude['filepath'] = [];
    }
    if( isset($t_config['github_dorks'][$dork]['exclude']) && isset($t_config['github_dorks'][$dork]['exclude']['filepath']) ) {
        $t_exclude['filepath'] = array_merge( $t_exclude['filepath'], $t_config['github_dorks'][$dork]['exclude']['filepath'] );
    }

    return $t_exclude;
}

function isFiltered( $result, $t_exclude, $t_filters )
{
    // exclude string in the content
    if( in_array('content',$t_filters) )
    {
        foreach( $t_exclude['content'] as $exclude ) {
            $m = preg_match( '#('.$exclude.')#', $result['code'] );
            if( $m ) {
                return true;
            }
        }
    }

    // exclude extension
    if( in_array('extension',$t_filters) )
    {
        $pos = strrpos( $result['path'], '.' );

        if( $pos !== false ) {
            $ext = substr( $result['path'], $pos+1 );
            if( in_array($ext,$t_exclude['extension']) ) {
                return true;
            }
       }
    }

    // exclude filepath
    if( in_array('filepath',$t_filters) )
    {
        $full_path = $result['repository']['full_name'].'/'.$result['path'];

        foreach( $t_exclude['filepath'] as $exclude ) {
            $p = strpos( $full_path, $exclude );
            if( $p !== false && $p == 0 ) {
                // echo "exlude: ".$full_path." -> ".$exclude."<br>\n";
                return true;
            }
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
    $t_config = json_decode( $r, true );
    
    if( !isset($t_config['sha']) ) {
        return false;
    } else {
        return base64_decode( str_replace('\n','',$t_config['content']) );
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
    $t_config = json_decode( $r, true );
    
    if( !isset($t_config['sha']) ) {
        return false;
    } else {
        return $t_config['committer']['date'];
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
        $t_config = json_decode( $r, true );

        if( isset($t_config['sha']) ) {
            $result['commit_date'] = new DateTime( $t_config['committer']['date'] );
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
        $t_config = json_decode( $r, true );

        if( isset($t_config['sha']) ) {
            $result['code'] = base64_decode( str_replace('\n','',$t_config['content']) );
        }
    }
}


if( isset($_GET['d']) )
{
    $n_desired = 0;
    $current_page = 0;
    $t_exclude = excludeFusion( $t_config, $_GET['d'] );

    do
    {
        $t_results = doSearchGithub( $_GET['d'], $current_page );
        if( $t_results === false ) {
            break;
        }
        $n_results = count( $t_results['items'] );
        if( !$n_results ) {
            break;
        }
        $t_filtered = filterResults( $t_results['items'], $t_exclude, ['filepath','extension'] );
        // break;

        getCodes( $t_filtered );
        getCommitDates( $t_filtered );

        // yes yes again ! (content filtering)
        $t_filtered = filterResults( $t_filtered, $t_exclude, ['content'] );
        $n_desired += count( $t_filtered );
        $current_page++;

        if( $current_page >= MAX_PAGE ) {
            break;
        }
    }
    while( $n_desired < N_RESULTS_DESIRED );

    if( count($t_filtered) > N_RESULTS_DESIRED ) {
        $t_filtered = array_slice( $t_filtered, 0, N_RESULTS_DESIRED );
    }
}

if( isset($_GET['a']) && $_GET['a'] == 'exclude' )
{
    if( isset($_POST['d']) && isset($_POST['t']) )
    {
        // if( !isset($t_config['github_dorks'][ $_POST['d'] ]['exclude']) ) {
        //     $t_config['github_dorks'][ $_POST['d'] ]['exclude'] = [
        //         'filepath' => [],
        //         'content' => [],
        //         'extension' => [],
        //     ];
        // }

        // $t_config['github_dorks'][ $_POST['d'] ]['exclude'][$_POST['t']][] = $_POST['e'];

        if( !isset($t_config['exclude']) ) {
            $t_config['exclude'] = [
                'filepath' => [],
                'content' => [],
                'extension' => [],
            ];
        }

        $t_config['exclude'][$_POST['t']][] = $_POST['e'];
        $t_exclude = excludeFusion( $t_config, $_POST['d'] );

        file_put_contents( $f_config, json_encode($t_config,JSON_PRETTY_PRINT) );

        header( 'Content-Type: application/json' );
        echo json_encode( $t_exclude );
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
                    <?php foreach( $t_config['github_dorks'] as $dork=>$datas ) { ?>
                        <a href="?d=<?php echo __urlencode($dork); ?>" class="list-group-item list-group-item-action"><?php echo $dork; ?></a>
                    <?php } ?>
                </div>
                <?php if( isset($t_filtered) && count($t_filtered) ) { ?>
                <div class="col-md-7">
                    <?php foreach( $t_filtered as $result ) { ?>
                        <div class="result" data-full-path="<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>">
                            <div class="result_action">
                                <input type="text" size="10" name="exclude_string" placeholder="exclude results with string..." />
                                <input type="submit" name="btn_exclude_string" class="btn-exclude-string" value="EX" />
                                <a href="javascript:excludeFilepath('<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>');" title="exclude file"><img src="img/page_delete.png" title="exclude file" /></a>
                                <a href="javascript:excludeFilepath('<?php echo $result['repository']['full_name']; ?>');" title="exclude repository"><img src="img/folder_delete.png" title="exclude repository" /></a>
                                <a href="javascript:excludeFilepath('<?php echo $result['repository']['owner']['login']; ?>');" title="exclude user"><img src="img/user_delete.png" title="exclude user" /></a>
                            </div>
                            <div class="result_profile_picture"><a href="<?php echo $result['repository']['owner']['html_url']; ?>" target="_blank"><img src="<?php echo $result['repository']['owner']['avatar_url']; ?>&s=40" width="40" /></a></div>
                            <div class="result_repository_full_name"><a href="<?php echo $result['repository']['html_url']; ?>" target="_blank"><?php echo $result['repository']['full_name']; ?></a></div>
                            <div class="result_path">
                                <a href="<?php echo getRawUrl($result); ?>" target="_blank"><?php echo $result['path']; ?></a>
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
                <?php if( isset($_GET['d']) ) { ?>
                <div class="col-md-3">
                    <div class="github_search_link">
                        <a href="https://github.com/search?o=desc&s=indexed&type=Code&q=<?php echo __urlencode($_GET['d']); ?>" target="_blank">https://github.com/search?o=desc&s=indexed&type=Code&q=<?php echo __urlencode($_GET['d']); ?></a>
                        (page <?php echo ($current_page-1); ?>)
                    </div>
                    <div>
                        <pre class="exclude_list"><?php echo json_encode( $t_exclude, JSON_PRETTY_PRINT ); ?></pre>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        <script type="text/javascript">
            $(document).ready(function() {
                $('.result_code').each(function(){
                    c = $(this).find('.first_word_match');
                    if( c.length ) {
                        p = c.first().position().top - 47;
                        $(this).scrollTop( p );
                    } else {
                        c = $(this).find('.result_code_highlight');
                        if( c.length ) {
                            p = c.first().position().top - 47;
                            $(this).scrollTop( p );
                        }
                    }
                });
                $('.btn-exclude-string').click(function(){
                    v = $(this).parent().find('input[name="exclude_string"]').val();
                    if( v.length ) {
                        excludeString( v );
                    }
                });
            });

            function doExclude( exclude, type )
            {
                datas = 'd=' + getQueryParam('d') + '&e=' + exclude + '&t=' + type;

                $.ajax({
                    type: 'POST',
                    url: '?a=exclude',
                    data: datas,
                    dataType: 'json',
                    success: function(response){
                        $('.exclude_list').text( JSON.stringify(response,null,4) );
                    }
                });
            }

            function excludeExtension( exclude )
            {
                ext = exclude.substring(2);
                doExclude( ext, 'extension' );
            }

            function excludeString( exclude )
            {
                if( exclude.substring(0,2) == '*.' ) {
                    excludeExtension( exclude );
                    return;
                }

                doExclude( exclude, 'content' );

                $('.result_code').each(function(){
                    code = $(this).text();
                    if( code.indexOf(exclude) >= 0 ) {
                        $(this).parent('.result').hide();
                    }
                });
            }

            function excludeFilepath( exclude )
            {
                doExclude( exclude, 'filepath' );

                $('.result[data-full-path^="'+exclude+'"]').hide();
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