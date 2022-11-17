<?php

set_time_limit( 0 );

error_reporting( E_ALL );
ini_set( 'display_errors', true );
ini_set( 'display_startup_errors', true );

define( 'N_RESULTS_DESIRED', 15 );
define( 'DEFAULT_MAX_PAGE', 3 );


$f_config = dirname(__FILE__) . '/config.json';
if( !is_file($f_config) ) {
    exit( 'Config file not found!' );
}
$content = file_get_contents( $f_config );
$t_config = json_decode( $content, true );
// var_dump( $t_config );

$f_results = dirname(__FILE__) . '/results.json';
if( !is_file($f_results) ) {
    exit( 'Results file not found!' );
}
$content = file_get_contents( $f_results );
$t_results = json_decode( $content, true );
// var_dump( $t_results );

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
    $t_words = explode( ' ', $_GET['dork'] );
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
    // var_dump( $date_diff );
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

function excludeFusion( $t_config, $project=null, $dork=null )
{
    $t_exclude = [];

    if( isset($t_config['exclude']) && isset($t_config['exclude']['content']) ) {
        $t_exclude['content'] = $t_config['exclude']['content'];
    } else {
        $t_exclude['content'] = [];
    }
    if( $dork ) {
        if( isset($t_config['project'][$project]['dorks'][$dork]['exclude']) && isset($t_config['project'][$project]['dorks'][$dork]['exclude']['content']) ) {
            $t_exclude['content'] = array_merge( $t_exclude['content'], $t_config['project'][$project]['dorks'][$dork]['exclude']['content'] );
        }
    }
    if( isset($t_config['exclude']) && isset($t_config['exclude']['extension']) ) {
        $t_exclude['extension'] = $t_config['exclude']['extension'];
    } else {
        $t_exclude['extension'] = [];
    }
    if( $dork ) {
        if( isset($t_config['project'][$project]['dorks'][$dork]['exclude']) && isset($t_config['project'][$project]['dorks'][$dork]['exclude']['extension']) ) {
            $t_exclude['extension'] = array_merge( $t_exclude['extension'], $t_config['project'][$project]['dorks'][$dork]['exclude']['extension'] );
        }
    }
    if( isset($t_config['exclude']) && isset($t_config['exclude']['filepath']) ) {
        $t_exclude['filepath'] = $t_config['exclude']['filepath'];
    } else {
        $t_exclude['filepath'] = [];
    }
    if( $dork ) {
        if( isset($t_config['project'][$project]['dorks'][$dork]['exclude']) && isset($t_config['project'][$project]['dorks'][$dork]['exclude']['filepath']) ) {
            $t_exclude['filepath'] = array_merge( $t_exclude['filepath'], $t_config['project'][$project]['dorks'][$dork]['exclude']['filepath'] );
        }
    }

    return $t_exclude;
}

// function filterResults( $t_results, $t_exclude, $t_filters )
// {
//     $t_filtered = [];

//     foreach( $t_results as $result )
//     {
//         $r = isFiltered( $result, $t_exclude, $t_filters );
//         // var_dump( $result['repository']['full_name'].'/'.$result['path'] );
//         // var_dump( $r );
//         if( !$r ) {
//             $t_filtered[] = $result;
//         }
//     }

//     return $t_filtered;
// }

// function isFiltered( $result, $t_exclude, $t_filters )
// {
//     // exclude string in the content
//     if( in_array('content',$t_filters) )
//     {
//         foreach( $t_exclude['content'] as $exclude ) {
//             $m = preg_match( '#('.$exclude.')#', $result['code'] );
//             if( $m ) {
//                 // var_dump( $exclude );
//                 return true;
//             }
//         }
//     }

//     // exclude extension
//     if( in_array('extension',$t_filters) )
//     {
//         $pos = strrpos( $result['path'], '.' );

//         if( $pos !== false ) {
//             $ext = substr( $result['path'], $pos+1 );
//             if( in_array($ext,$t_exclude['extension']) ) {
//                 return true;
//             }
//        }
//     }

//     // exclude filepath
//     if( in_array('filepath',$t_filters) )
//     {
//         $full_path = $result['repository']['full_name'].'/'.$result['path'];

//         foreach( $t_exclude['filepath'] as $exclude ) {
//             $p = strpos( $full_path, $exclude );
//             if( $p !== false && $p == 0 ) {
//                 // echo "exlude: ".$full_path." -> ".$exclude."<br>\n";
//                 return true;
//             }
//         }
//     }

//     return false;
// }

// if( isset($_GET['d']) /*&& isset($t_config['github_dorks'][$_GET['d']])*/ )
// {
//     $n_desired = 0;
//     // $current_page = 0;
//     $max_page = isset($_GET['p']) ? (int)$_GET['p'] : DEFAULT_MAX_PAGE;

//     if( !isset($t_config['github_dorks'][$_GET['d']]) || !is_array($t_config['github_dorks'][$_GET['d']]) ) {
//         $t_config['github_dorks'][$_GET['d']] = [
//             'title' => 'github search code \'' . $_GET['d'] . '\'',
//             'info' => 'https://github.com/search?o=desc&s=indexed&type=Code&q=' . urlencode($_GET['d']),
//             'last_sha' => '',
//             'data' => 0,
//             'exclude' => [
//                 'filepath' => [],
//                 'content' => [],
//                 'extension' => [],
//             ],
//         ];
//         file_put_contents( $f_config, json_encode($t_config,JSON_PRETTY_PRINT) );
//     }

//     $t_exclude = excludeFusion( $t_config, $_GET['d'] );
//     // var_dump( $t_exclude );

//     $t_filtered = [];
//     $t_results = getPagesResults( $_GET['d'], $max_page );
//     $t_temp = filterResults( $t_results, $t_exclude, ['filepath','extension'] );
//     getCodes( $t_temp );
//     $t_temp2 = filterResults( $t_temp, $t_exclude, ['content'] ); // yes yes again ! (content filtering)
//     getCommitDates( $t_temp2 );

//     $t_filtered = array_merge( $t_filtered, $t_temp2 );
// }

if( isset($_GET['action']) && $_GET['action'] == 'exclude' )
{
    if( isset($_POST['exclude']) && isset($_POST['type']) )
    {
        // this is GLOBAL exclusion !!!

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

        $t_config['exclude'][$_POST['type']][] = $_POST['exclude'];
        // $t_config['projects'][$_POST['project']]['dorks'][$_POST['dork']]['exclude'][$_POST['type']][] = $_POST['exclude'];
        $t_exclude = excludeFusion( $t_config, $_POST['project'], $_POST['dork'] );

        file_put_contents( $f_config, json_encode($t_config,JSON_PRETTY_PRINT) );

        header( 'Content-Type: application/json' );
        echo json_encode( $t_exclude );
    }

    exit();
}

if( isset($_GET['action']) && $_GET['action'] == 'lastsha' )
{
    if( isset($_POST['project']) && isset($_POST['dork']) && isset($_POST['sha']) )
    {
        $t_config['projects'][$_POST['project']]['dorks'][$_POST['dork']]['last_sha'] = $_POST['sha'];
        file_put_contents( $f_config, json_encode($t_config,JSON_PRETTY_PRINT) );
    }

    exit();
}

$p = isset($_GET['project']) ? $_GET['project'] : null;
$d = isset($_GET['dork']) ? $_GET['dork'] : null;
$t_exclude = excludeFusion( $t_config, $p, $d );

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
                font-size: 0.8rem;
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
            .result_clear {
                clear: both;
                margin-bottom: 5px;
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
                max-height: 200px;
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
                margin-left: 10px;
                /* width: 24px; */
            }
            .result_action input[type="text"] {
                margin-left: 10px;
                /* width: 24px; */
            }
            pre {
                white-space: pre-wrap;       /* css-3 */
                white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
                white-space: -pre-wrap;      /* Opera 4-6 */
                white-space: -o-pre-wrap;    /* Opera 7 */
                word-wrap: break-word;
            }
            div.lastsha div.result_repository_full_name a,
            div.lastsha div.result_path a,
            div.lastsha div.result_commit_date {
                color: #F00;
            }
            div.lastsha pre.result_code {
                border: 1px solid #F00;
            }
            #btn-run {
                position: absolute;
                right: 10px;
                z-index: 999;
            }
            .item-dork:last-child {
                margin-bottom: 10px;
            }
            .item-dork a.active {
                color: #f00;
            }
        </style>
    </head>

    <body>
        <div class="container-fluid">
            <!-- <button type="button" class="btn btn-danger" id="btn-run">Run</button> -->
            <div class="row">
                <div class="col-md-2">
                    <?php foreach( $t_config['projects'] as $project=>$t_project ) { ?>
                        <div class="item-project">
                            <b><?php echo $project; ?> (<?php echo count($t_project['dorks']); ?>)</b>
                            <?php foreach( $t_project['dorks'] as $dork=>$t_dork ) { ?>
                                <div class="item-dork"><a href="?project=<?php echo __urlencode($project); ?>&dork=<?php echo __urlencode($dork); ?>" class="<?php if( isset($_GET['dork']) && $_GET['dork'] == $dork ) { echo 'active'; } ?>"><?php echo $dork; ?></a> (<?php if(isset($t_results[$project][$dork])) echo count($t_results[$project][$dork]); else echo '-'; ?>)</div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php if( isset($_GET['project']) && isset($_GET['dork']) ) {
                    $t_config_dork = $t_config['projects'][$_GET['project']]['dorks'][$_GET['dork']];
                    // var_dump($t_dork);
                    ?>
                    <div class="col-md-7">
                        <?php foreach( $t_results[$_GET['project']][$_GET['dork']] as $result ) {
                            $result_content = '';
                            if( isset($result['content']) && strlen($result['content']) ) {
                                $result_content = base64_decode( str_replace('\n','',$result['content']) );
                            }
                            $result_commit_date = '';
                            if( isset($result['commit_date']) && strlen($result['commit_date']) ) {
                                $result_commit_date = new DateTime( $result['commit_date'] );
                                $now = new DateTime();
                                $date_diff = date_diff( $result_commit_date, $now );
                                $result_commit_date_diff = diff2str( $date_diff );
                            }
                            ?>
                            <div class="result <?php if( $result['sha']==$t_config_dork['last_sha'] ) { echo 'lastsha'; }; ?>" data-sha="<?php echo $result['sha']; ?>" data-full-path="<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>">
                                <div class="result_action">
                                    <a href="javascript:setLastSha('<?php echo $result['sha']; ?>');" title="set as last viewed"><img src="img/macro_names.png" title="set as last viewed" width="24" /></a>
                                    <input type="text" size="15" name="exclude_string" placeholder="exclude results with string..." />
                                    <input type="submit" name="btn_exclude_string" class="btn-exclude-string" value="EX" />
                                    <a href="javascript:excludeFilepath('<?php echo $result['repository']['full_name'].'/'.$result['path']; ?>');" title="exclude file"><img src="img/page_delete.png" title="exclude file" width="24" /></a>
                                    <a href="javascript:excludeFilepath('<?php echo $result['repository']['full_name']; ?>');" title="exclude repository"><img src="img/folder_delete.png" title="exclude repository" width="24" /></a>
                                    <a href="javascript:excludeFilepath('<?php echo $result['repository']['owner']['login']; ?>');" title="exclude user"><img src="img/user_delete.png" title="exclude user" width="24" /></a>
                                </div>
                                <div class="result_profile_picture"><a href="<?php echo $result['repository']['owner']['html_url']; ?>" target="_blank"><img src="<?php echo $result['repository']['owner']['avatar_url']; ?>&s=40" width="40" /></a></div>
                                <div class="result_repository_full_name"><a href="<?php echo $result['repository']['html_url']; ?>" target="_blank"><?php echo $result['repository']['full_name']; ?></a></div>
                                <div class="result_path">
                                    <a href="<?php echo getRawUrl($result); ?>" target="_blank"><?php echo $result['path']; ?></a>
                                    <?php if( strlen($result_content) ) { ?>
                                        <span class="result_size">(<?php echo format_bytes(strlen($result_content)); ?>)</span>
                                    <?php } ?>
                                </div>
                                <div class="result_clear"></div>
                                <?php if( strlen($result_content) ) { ?>
                                    <pre class="result_code"><?php echo highlightCode($result_content); ?></pre>
                                <?php } ?>
                                <?php if( strlen($result['commit_date']) ) { ?>
                                    <div class="result_commit_date">
                                        <?php echo $result_commit_date->format('d/m/Y H:i:s') ?>
                                        -
                                        <?php echo $result_commit_date_diff ?>
                                        -
                                        <?php echo $result['sha'] ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if( isset($_GET['dork']) ) { ?>
                <div class="col-md-3">
                    <div class="github_search_link">
                        <a href="https://github.com/search?o=desc&s=indexed&type=Code&q=<?php echo __urlencode($_GET['dork']); ?>" target="_blank">https://github.com/search?o=desc&s=indexed&type=Code&q=<?php echo __urlencode($_GET['dork']); ?></a>
                    </div>
                    <div>
                        Exclude:
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
                $("#btn-run").click(function(){
                    $.ajax({
                        type: 'POST',
                        url: '?action=runsurvey'
                    });
                });
            });

            function setLastSha( sha )
            {
                datas = 'project=' + getQueryParam('project') + '&dork=' + getQueryParam('dork') + '&sha=' + sha;

                $.ajax({
                    type: 'POST',
                    url: '?action=lastsha',
                    data: datas
                });

                $('div.result').removeClass('lastsha');
                $('div.result[data-sha="'+sha+'"]').addClass('lastsha');
            }

            function doExclude( exclude, type )
            {
                datas = 'project=' + getQueryParam('project') + '&dork=' + getQueryParam('dork') + '&exclude=' + exclude + '&type=' + type;

                $.ajax({
                    type: 'POST',
                    url: '?action=exclude',
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