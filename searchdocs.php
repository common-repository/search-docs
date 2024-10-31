<?php
/*
Plugin Name: Search Docs
Plugin URI: http://redalt.com/downloads
Description: Get Codex and WordPress Support Forum search results directly in any page of the Admin panel. This plugin is a collaborative effort of <a href="http://asymptomatic.net">Owen Winkler</a> and <a href="http://blog.jalenack.com/">Andrew Sutherland</a>. Thanks also to <a href="http://somethingunpredictable.com/">Robert Deaton</a>.
Author: Owen Winkler, Andrew Sutherland
Version: 2.0
Author URI: http://blog.jalenack.com
SVN Version: $id$
*/

$search_prefixes = array(
//'source'=>array('searchterms',1=decsriptions/0=link-only,# of links),
	'Codex'=>array('site:codex.wordpress.org+',1,3),
	'Forum'=>array('site:wordpress.org+inurl:wordpress.org/support/+-inurl:rss+',0,7),
);

$omit_admin_pages = array('bookmarklet.php');

function codex_include_up($filename)
{
	$c=0;
	while(!is_file($filename))
	{
		$filename = '../' . $filename;
		$c++;
		if($c==30) {
			echo 'Could not find ' . basename($filename) . '.';
			return '';
		}
	}
	return $filename;
}

$codex_solo = false;
if(!defined('ABSPATH')) {
	include_once(codex_include_up('wp-config.php'));

	$adminurl = strtolower( get_settings('siteurl') ) . '/wp-admin';
	$referer = strtolower( $_SERVER['HTTP_REFERER'] );
	if ( !strstr($referer, $adminurl) )
		die(__('Sorry, you need to <a href="http://codex.wordpress.org/Enable_Sending_Referrers">enable sending referrers</a> for this feature to work.'));
	do_action('check_admin_referer');
	$codex_solo = true;
}

require_once( ABSPATH . WPINC . '/class-snoopy.php');

// Return results via javascript
if(isset($_GET['codex_keywords']) && $codex_solo) {
	$keywords = $_GET['codex_keywords'];
	$keywords = str_replace(' ', '+', $keywords);
	$results = codex_search_results($keywords);
	$output = '';
	if (count($results)) {
		$output .= "<a href=\"#\" onclick=\"document.getElementById('codex_search_results').style.display = 'none';\" style=\"float:right;\">Hide Results</a>\n";
		$cur_key = '';
		$close_dl = '';
		// Clean the codex summary results
		$results_replace = array (". From Codex", "From Codex. ", "Table of contents. ", "WordPress Codex: The Online Manual", "...", "Retrieved from", '"');
		foreach($results as $result) {
			if($cur_key != $result['key']) {
				$cur_key = $result['key'];
				$output .= "{$close_dl}<h2>{$cur_key}</h2><dl>";
				$close_dl = '</dl>';
			}
			switch($search_prefixes[$result['key']][1]) {
			case 1:
				$output .= '<dt><a href="'.$result['url'].'" target="codex">'.str_replace('" WordPress Codex', "", $result['title'])."</a></dt>\n<dd>".str_replace($results_replace, '', $result['summary'])."</dd>\n";
				break; 
			default:
				$output .= '<dt class="forum"><a href="'.$result['url'].'" target="codex">'.str_replace('" WordPress Support', "", $result['title'])."</a></dt>\n";
				break;
			}
		}
		$output .= "</dl>\n";
	} else {
		$output = "<a href=\"#\" onclick=\"document.getElementById('codex_search_results').style.display = 'none';\" style=\"float:right;\">Hide</a>";
		$output .= __('No Results'). ".";
	}
	
	$output = str_replace("\n", '', addslashes($output));
	
	echo "
	<script type=\"text/javascript\"><!--//
		var e = window.parent.document.createElement('div');
		e.id = 'codex_search_results';
		var existing = window.parent.document.getElementById('codex_search_results');
		if(existing) {
			window.parent.document.getElementById('codex-search').removeChild(existing);
		}
		
		e.innerHTML = '{$output}';
		window.parent.document.getElementById('codex-search').appendChild(e);
	//--></script>
	";
}

function codex_search_results($term) {
	global $search_prefixes;
	$results = array();
	foreach($search_prefixes as $key => $prefix) {
		$result_temp = codex_search_term($key, $prefix[0] . $term, $prefix[2]);
		//echo "<pre>$prefix  $term:\n" . print_r($result_temp, 1) . "</pre>" ;
		$results = array_merge($results, $result_temp);
	}
	return $results;
}

function codex_box() {
	global $user_identity;
	echo '<form action="" method="post" id="codex-search" onsubmit="return codex_search();">
		<input id="codex_keywords" name="codex_keywords" type="text" value="' . $_POST['codex_keywords'] . '"/><input type="submit" name="codex_submit" id="codex_submit" class="button" value="'.__('Search Help').'" />
	';
	
	if(isset($_POST['codex_keywords'])) {
		$results = codex_search_results($_POST['codex_keywords']);
		echo "<div id=\"codex_search_results\">\n";
		echo "<a href=\"#\" onclick=\"document.getElementById('codex_search_results').style.display = 'none';\" style=\"float:right;\">Hide Results</a>";
		echo "<dl>\n";
		foreach($results as $result) {
			echo "<dt><a href=\"{$result['url']}\" target=\"codex\">{$result['title']}</a></dt>\n<dd>{$result['summary']}</dd>\n";
		}
		echo "</dl>\n";
		echo "</div>";
	}
	
	$pbasename = preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', __FILE__);
	$pbasename = str_replace('\\', '/', $pbasename);
	$pg_name = explode('?', basename($_SERVER['REQUEST_URI']), 2);
	$pg_name = $pg_name[0];

	$user_info = '<p>' . sprintf(__('Howdy, <strong>%s</strong>.'), $user_identity) . ' [<a href="' . get_settings('siteurl')
	 . '/wp-login.php?action=logout" title="' . __('Log out of this account') . '">' . __('Sign Out') . '</a>, <a href="profile.php">' . __('My Account') . '</a>,' 
	 . ' <a href="?codexhelp=true" onclick="return show_search(\'Admin Help ' . $pg_name . '\');">' . __('Help') . '</a>] </p>';
	$user_info = addslashes($user_info);

	echo '</form><iframe src="about:blank" id="codex_frame"></iframe>
		<script type="text/javascript"><!--//
		function show_search(searchterm) {
			var fram = document.getElementById("codex_frame");
			var term = document.getElementById("codex_keywords");
			var existing = window.parent.document.getElementById("codex_search_results");
			if(existing) {
				window.parent.document.getElementById("codex-search").removeChild(existing);
			}
			var e = document.createElement("div");
			e.setAttribute("id", "codex_search_results");
			document.getElementById("codex-search").appendChild(e);
			e.innerHTML = "<h3 style=\"display:block;text-align:center; font-size: 25px\">'.__('Searching').'...</h3>";
			
			searchterm.replace(/ /g, "+");
			fram.src = "' . get_settings('siteurl') . '/wp-content/plugins/' . $pbasename . '?codex_keywords=" + searchterm;
			if(navigator.userAgent.toLowerCase().indexOf("safari")+1) fram.style.display = "block";
			return false;
		}
		function codex_search() {
			var term = document.getElementById("codex_keywords");
			var searchterm = term.value;
			return show_search(searchterm);
		}
		document.getElementById("user_info").innerHTML = "' . $user_info . '"; 
		//--></script>
	';
}

function codex_search_term($key, $term, $count) {
	global $results_per_engine;
	
	$client = new Snoopy();
	$client->agent = 'WordPress Codex/Support Search Plugin';
	$client->read_timeout = 2;
	$client->use_gzip = true;
	$url = 'http://api.search.yahoo.com/WebSearchService/V1/webSearch?appid=WordPressCodexSearch&query=' . $term . '&results=' . $count;	
	@$client->fetch($url);
	preg_match_all('/<Title>(.*?)<\/Title>.*?<Summary>(.*?)<\/Summary>.*?<Url>(.*?)<\/Url>/si', $client->results, $matches, PREG_SET_ORDER);
	$results = array();
	foreach($matches as $match) {
		$results[] = array('key'=>$key, 'url'=>strip_tags($match[3]), 'title'=>strip_tags($match[1]), 'summary'=>strip_tags($match[2]));
	}
	return $results;
}



// We need some CSS to position the paragraph
function codex_css() { 
	global $wp_version;
?>
		<style type="text/css">
			#codex-search {
				position: absolute;
				top: 4px;
				margin: 0; 
				padding: 0;
				right: 3em;
				z-index: 100;
				color: #666;
			}
			#codex_submit { font-weight: bold; }
			#codex_submit:focus { padding: 6px 5px 5px 6px; }
			#codex_submit:active { padding: 4px 3px 3px 4px; }
			#codex_search_results {
				background-color:white;
				font-size: small;
				border:2px solid #69c;
				padding: 4px 10px;
				background-color:white;
				margin-top: 3px;
				position: absolute;
				right: 0;
				width: 350px;
			}
			
			#codex_frame {
				display:none; height: 1px;  /* Not in Safari :(  */
			}
			#codex-search h2 { margin: 4px -2px -2px -2px; }
			#codex-search input { font-size: 14px; vertical-align: middle;}
			.forum {
			  display: list-item;
			  list-style-type: square;
			  padding: 3px 1px;
			  margin-left: 15px;
			}
			<?php if (function_exists('wp_admin_tiger_css')) { ?>
			#codex-search {
				position: fixed;
				top: 3px;
				right: 120px;
				margin: 0;
				z-index: 100;
				padding: 0;
				color: #666;
			}
			#codex_keywords { font-size: 11px !important; border: 1px inset #888; padding: 1px 2px; display: inline; float: none; }
			#codex_submit { display: none; }
			<?php } ?>
			<?php if ($wp_version >= '1.6') { ?>
			#codex-search {
				position: absolute;
				top: 28px;
				margin: 0; 
				padding: 0;
				right: 1em;
				color: #666;
			}
			#codex_submit {
				font-weight: bold;
				height:26px;
			}
		<?php }?>		
		</style>

<?php }
$pg_name = basename($_SERVER['REQUEST_URI']);
$pg_name = explode('?', $pg_name, 2);
$pg_name = $pg_name[0];
if(!in_array($pg_name, $omit_admin_pages)) {
	add_action('admin_footer', 'codex_box');
	add_action('admin_head', 'codex_css');
}

?>
