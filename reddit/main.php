<?php

/*

Saves subreddit threads as HTM files for offline viewing.

gen/0.htm top-level index
gen/0$subreddit.htm subreddit index
gen/$subreddit-$title.htm thread including comments

*/

define('nl',"\r\n");
define('REDDIT','https://www.reddit.com');
define('HTML_PRE','<link rel="stylesheet" type="text/css" href="style.css">');

$subreddits= <<< EOD
PHP
,github
EOD;
run(preg_replace('/\s/','',trim($subreddits)));

//TODO , $maxComments=50, $enableImages=true)
function run($subreddits, $limit=5, $which='new')
{
	if(!file_exists('gen'))
	{
		mkdir('gen');
	}
	copy('style.css','gen/style.css');
	$topHtml=HTML_PRE.'<ul>';
	
	foreach(explode(',',$subreddits) as $subreddit)
	{
		echo($subreddit.nl);
		//https://www.reddit.com/r/PHP/new.json?limit=3
		$json=get(REDDIT.'/r/'.$subreddit.'/'.$which.'.json?limit='.$limit,$subreddit);
		$threads=json_decode($json) -> data -> children;
		
		foreach($threads as $thread)
		{
			$url=REDDIT.rtrim($thread -> data -> permalink,'/').'.json';
			$title=$thread -> data -> title;
			
			$filename=getFilename($subreddit,$title);
			$html=getThreadHtml($title,$url);
			file_put_contents("gen/$filename.htm",$html);
		}
		
		generateNav($subreddit,$threads);
		$topHtml.='<li><a href="0'.$subreddit.'.htm">'.$subreddit.'</a>';
	}
	
	file_put_contents("gen/0.htm",$topHtml);
}

function getThreadHtml($title, $url)
{
	$json=get($url);
	$thread=json_decode($json);
	$html=HTML_PRE;
	
	$post=$thread[0] -> data -> children[0] -> data;
	$html.='<p>'.($post -> author).' @ '.date('Y-m-d H:i:s', $post -> created).'</p>';
	$html.='<h1>'.($post -> title).'</h1>';
	$h=html_entity_decode($post -> selftext_html);
	$h=str_replace('<!-- SC_OFF --><div class="md">','',$h);
	$h=str_replace('</div><!-- SC_ON -->','',$h);
	$html.=$h;
	
	$html.=getCommentsHtml($thread[1]);
	
	return $html;
}

function getCommentsHtml($comments, $level=0)
{
	$html='';
	
	foreach($comments -> data -> children as $comment)
	{
		if(isset($comment -> data -> author))
		{
			$html.='<h3>'.str_repeat('&gt;',$level).' '.($comment -> data -> author).' @ '.date('Ymd H:i', $comment -> data -> created).'</h3>';
			$h=html_entity_decode($comment -> data -> body_html);
			$h=str_replace('<div class="md">','',$h);
			$h=str_replace('</div>','',$h);
			$html.=$h;
			
			if(($comment -> data -> replies) !='')
			{
				$html.=getCommentsHtml($comment -> data -> replies, $level+1);
			}
		}
	}
	
	return $html;
}

function generateNav($subreddit, $threads)
{
	$html=HTML_PRE."<h1>$subreddit</h1>";
	
	foreach($threads as $i => $thread)
	{
		$title=$thread -> data -> title;
		$filename=getFilename($subreddit,$title);
		$html.='<li><a href="'.$filename.'.htm">'.date('Ymd-His', $thread -> data -> created).' '.$title.'</a>'.nl;
	}
	
	file_put_contents("gen/0$subreddit.htm",$html);
}

function getFilename($subreddit, $title)
{
	$filename=$subreddit.'-'.preg_replace("/[^a-z0-9\_\-\.]/i",'-',$title);
	return (strlen($filename)<=64)?$filename:substr($filename,0,64);
}

function get($url, $slug='')
{
	if($slug=='')
	{
		$slug=preg_replace("/[^a-z0-9\_\-\.]/i",'-',$url);
	}
	
	$filename='cache/'.$slug.'.json';
	
	if(file_exists($filename))
	{
		$contents=file_get_contents($filename);
		
		if($contents!='')
		{
			//echo('got cache '.$filename.nl);
			return file_get_contents($filename);
		}
	}
	
	echo('call web '.$url.nl);
	$json=file_get_contents($url);
	file_put_contents($filename,$json);
	return $json;
}

?>
