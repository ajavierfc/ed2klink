<?php
/**
*
* @package ED2K Links processing in posts text
* @copyright (c) 2016 bitERROR
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
* This code is ported from the original MOD for phpBB 3 originally written by Meithar,
* then updated by Bill Hicks, C0de_m0nkey and DonGato.
*
* I've need to rewrite some regular expresions, and added some patch due to
* url processing issues with ed2k url in the current phpBB 3.1.6.
*
* Stats function is moved to peerates.net or shortypower.org due to tothbenedek.hu
* were discontinued by it's author.
*
*/

namespace biterror\ed2klink\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/**
	* the path to the images directory
	*
	*@var string
	*/
	protected $images_path;

	/**
	* Constructor
	*
	* @param \phpbb\config\config $config
	* @param \phpbb\request\request $request
	* @param \phpbb\user $user
	* @return \biterror\ed2klink\event\listener
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\request\request $request, \phpbb\user $user, $images_path)
	{
		$this->config = $config;
		$this->request = $request;
		$this->user = $user;
		$this->images_path = $images_path;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_post_rowset_data'		=> 'process_rowset_data',
			'core.topic_review_modify_row'			=> 'topic_review_modify_row',
			'core.ucp_pm_view_messsage'			=> 'ucp_pm_view_messsage',
			'core.modify_format_display_text_before'	=> 'modify_format_display_text_before'
		);
	}

	/**
	* Process ed2k links in the post_text value of the post rowset data
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function process_rowset_data($event)
	{
		$rowset_data = $event['rowset_data'];
		$text = $rowset_data['post_text'];
		$text = $this->process_ed2k($text);
		$rowset_data['post_text'] = $this->make_addalled2k_link($text, $rowset_data['post_id']);
		$event['rowset_data'] = $rowset_data;
	}

	public function topic_review_modify_row($event)
	{
		$row = $event['post_row'];
		$text = $row['MESSAGE'];
		$row['MESSAGE'] = $this->process_ed2k($text);
		$event['post_row'] = $row;
	}

	public function ucp_pm_view_messsage($event)
	{
		$row = $event['msg_data'];
		$text = $row['MESSAGE'];
		$row['MESSAGE'] = $this->process_ed2k($text);
		$event['msg_data'] = $row;
	}

	public function modify_format_display_text_before($event)
	{
		$event['text'] = $this->process_ed2k($event['text']);
	}

	// eD2k links processing
	private function humanize_size ($size, $rounder = 0)
	{
		$sizes		= array('Bytes', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb', 'Zb', 'Yb');
		$rounders	= array(0, 1, 2, 2, 2, 3, 3, 3, 3);
		$ext		= $sizes[0];
		$rnd		= $rounders[0];

		if ($size < 1024)
		{
			$rounder	= 0;
			$format		= '%.' . $rounder . 'f Bytes';
		}
		else
		{
			for ($i = 1, $cnt = count($sizes); ($i < $cnt && $size >= 1024); $i++)
			{
				$size	= $size / 1024;
				$ext	= $sizes[$i];
				$rnd	= $rounders[$i];
				$format	= '%.' . $rnd . 'f ' . $ext;
			}
		}

		if (!$rounder)
		{
			$rounder = $rnd;
		}

		return sprintf($format, round($size, $rounder));
	}

	public function ed2k_link_callback ($m)
	{
		$max_len	= 100;
		$href		= 'href="' . $m[2] . '" class="postlink"';
		$fname		= rawurldecode($m[3]);
		$fname		= preg_replace('/&amp;/i', '&', $fname);
		$size		= $this->humanize_size($m[4]);

		if (strlen($fname) > $max_len)
		{
			$fname = substr($fname, 0, $max_len - 19) . '...' . substr($fname, -16);
		}
		if (preg_match('#[<>"]#', $fname))
		{
			$fname = htmlspecialchars($fname);
		}

	//	$result = "ed2k: <a $href>$fname&nbsp;&nbsp;[$size]</a>";
	//	return "ed2k: <a $href>$fname&nbsp;&nbsp;[$size]</a> <a href='http://www.emugle.com/details.php?f=$m[5]' target='_blank'><img src='images/emugle.gif' border='0' title='eMugle statistics' style='vertical-align: text-bottom;' /></a>";
	//	$result = "ed2k: <a $href>$fname&nbsp;&nbsp;[$size]</a> <a href='http://tothbenedek.hu/ed2kstats/ed2k?hash=$m[5]' target='_blank'><img src='". $this->images_path ."/stats.gif' border='0' title='File statistics' style='vertical-align: text-bottom;' /></a>";
	//	$result = "ed2k: <a $href>$fname&nbsp;&nbsp;[$size]</a> <a href='http://edk.peerates.net/check.php?p=$m[5]' target='_blank'><img src='". $this->images_path ."/stats.gif' border='0' title='File statistics' style='vertical-align: text-bottom;' /></a>";
		$result = "ed2k: <a $href>$fname&nbsp;&nbsp;[$size]</a> <a href='http://ed2k.shortypower.org/?hash=$m[5]' target='_blank'><img src='". $this->images_path ."/stats.gif' border='0' title='File statistics' style='vertical-align: text-bottom;' /></a>";
		return $result;
	}

	public function ed2k_link_callback_patch ($m)
	{
		$m[2] = preg_replace('/\&amp\;[^#]*\#/', '&#', $m[2]);
		$m[2] = preg_replace(array(
									'/"[^\)]*\)/',
									'/"[^\[]*\[/',
									'/"[^!]*!/'
									),
							array(
									')',
									'[',
									'!'
								), $m[2]);
		$m[2] = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
		$m[3] = explode("|", $m[2])[2];
		return $this->ed2k_link_callback($m);
	}

	private function process_ed2k($text)
	{
		// pad it with a space so we can match things at the start of the 1st line.
		$ret = ' ' . $text;

		// Patterns and replacements for URL processing
		$patterns = array();
		$replacements = array();

		// Ensure the colomn after "ed2k"
		$patterns[] 	= '#ed2k&\#58;#is';
		$replacements[] = 'ed2k:';
		// [url]ed2k://|file|...[/url] code (phpBB <3.1 wont work without processing)
		$patterns[] 	= '#\[url\](ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?(\|[a-z0-9\.,:]+\|/)?)\[/url\]#is';
		$replacements[] = '$1';
		// [url=ed2k://|file|...]name[/url] code (phpBB <3.1 wont work without processing)
		$patterns[] 	= '#\[url=(ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?(\|[a-z0-9\.,:]+\|/)?)\](.*?)\[/url\]#si';
		$replacements[] = '$1';
		// [url:xx]ed2k://|file|...[/url:xx] code
		$patterns[] 	= '#\[url:[a-z0-9]+\](ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?(\|[a-z0-9\.,:]+\|/)?)\[/url:[a-z0-9]+\]#is';
		$replacements[] = '$1';
//		// [url=ed2k://|file|...:xx]name[/url:xx] code
//		$patterns[] 	= '#\[url=(ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?):[a-z0-9]+\](.*?)\[/url:[a-z0-9]+\]#si';
//		$replacements[] = '<a href="$1" class="postlink">$4</a>';
		// [url]ed2k://|server|ip|port|/[/url] code
		$patterns[] 	= '#\[url\](ed2k://\|server\|([\d\.]+?)\|(\d+?)\|/?)\[/url\]#si';
		$replacements[] = 'ed2k server: <a href="$1" class="postlink">$2:$3</a>';
		// [url=ed2k://|server|ip|port|/]name[/url] code
		$patterns[] 	= '#\[url=(ed2k://\|server\|[\d\.]+\|\d+\|/?)\](.*?)\[/url\]#si';
		$replacements[] = '<a href="$1" class="postlink">$2</a>';
		// [url]ed2k://|friend|name|ip|port|/[/url] code
		$patterns[] 	= '#\[url\](ed2k://\|friend\|(.*?)\|[\d\.]+\|\d+\|/?)\[/url\]#si';
		$replacements[] = 'ed2k friend: <a href="$1" class="postlink">$2</a>';
		// [url=ed2k://|friend|name|ip|port|/]name[/url] code
		$patterns[] 	= '#\[url=(ed2k://\|friend\|(.*?)\|[\d\.]+\|\d+\|/?)\](.*?)\[/url\]#si';
		$replacements[] = '<a href="$1" class="postlink">$3</a>';

		$ret = preg_replace($patterns, $replacements, $ret);

		$that = $this;

		// ed2k://|file|name|size|fileHash|h=clientHash|others
		//$ret = preg_replace_callback("#(^|(?<=[^\w\"']))(ed2k://\|file\|([^\\/\|:<>\*\?\"]+?)\|(\d+?)\|([a-f0-9]{32})(\|[a-z0-9=]+)?\|(.*?)/?)(?![\"'])(?=([,\.]*?[\s<\[])|[,\.]*?$)#i", array($that, "ed2k_link_callback"), $ret);
		$ret = preg_replace_callback("#(^|(?<=[^\w\"'])|<a.*href=\")(?<!(?:code|mule):\w{8}\])(ed2k://\|file\|([^\\/\|:<>\*\?\"]+?)\|(\d+?)\|([a-f0-9]{32})(\|[a-z0-9=]+)?\|/?(\|[a-z0-9\.,:]+\|/)?)(\">[^<]*</a>)?(?![\"'])(?=([,\.]*?[\s<\[])|[,\.]*?$)#i", array($that, "ed2k_link_callback"), $ret);

		// Patching badly processed ed2k links by the core, $patching are the characters breaking an ed2k link when the core process them
		$patching = '\)\[!\#';
		$ret = preg_replace_callback("#(\<a[^>$patching]*href=\")(ed2k://\|file\|([^\\/\|:<\*\?\"]+\"\>ed2k[^<]+</a>.{10}[$patching][^\\/\|:<\*\?\"]*)\|(\d+?)\|([a-f0-9]{32})(\|[a-z0-9=]+)?\|/?(\|[a-z0-9\.,:]+\|/)?)#i", array($that, "ed2k_link_callback_patch"), $ret);

		// ed2k://|server|serverIP|serverPort
		$ret = preg_replace("#(^|(?<=[^\w\"'])|<a[^>]*>)(ed2k://\|server\|([\d\.]+?)\|(\d+?)\|/?)(?=</a>)?#i", "ed2k server: <a href=\"\\2\" class=\"postLink\">\\3:\\4</a>", $ret);
		// ed2k://|friend|name|clientIP|clientPort
		$ret = preg_replace("#(^|(?<=[^\w\"'])|<a[^>]*>)(ed2k://\|friend\|([^\\/\|:<>\*\?\"]+?)\|([\d\.]+?)\|(\d+?)\|/?)(?=</a>)?#i", "ed2k friend: <a href=\"\\2\" class=\"postLink\">\\3</a>", $ret);
		// -- END --

		// Remove our padding..
		$ret = substr($ret, 1);

		return($ret);
	}

	// eD2k Add all links feature
	private function make_addalled2k_link($text, $post_id)
	{
		// link literal
		$this->user->add_lang_ext('biterror/ed2klink', 'common');
		$link_text = $this->user->lang('ADD_ALL_ED2K');

		// padding
		$ret = ' ' . $text;

		// dig through the message for all ed2k links
		// split up by "ed2k:"
		$ed2k_raw = explode('ed2k://', $text);

		// The first item is garbage
		unset($ed2k_raw[0]);

		// no need to dig through it if there are not at least 2 links
		$ed2k_possibles = count($ed2k_raw);
		if ($ed2k_possibles > 1)
		{
			unset($ed2k_real_links);
			foreach ($ed2k_raw as $ed2k_raw_line)
			{
				$ed2k_parts = explode('|', $ed2k_raw_line);
				// This looks now like this (only important parts included)
				/*
				[1]=>
				string(4) "file"
				[2]=>
				string(46) "filename.extension"
				[3]=>
				string(9) "321456789"
				[4]=>
				string(32) "112233445566778899AABBCCDDEEFF11"
				[5]=>
				string(?) "source or AICH hash"
				*/

				// Check the obvious things
				if (strlen($ed2k_parts[1]) == 4 AND $ed2k_parts[1] == 'file' AND strlen($ed2k_parts[2]) > 0 AND floatval($ed2k_parts[3]) > 0 AND strlen($ed2k_parts[4]) == 32)
				{
					// This is a true link, lets paste it together and put it in an array
					if (substr($ed2k_parts[5], 0, 2) == 'h=' || substr($ed2k_parts[5], 0, 7) == 'sources')
					{
						$ed2k_link = 'ed2k://|file|' . str_replace('\'', '\\\'', $ed2k_parts[2]) . '|' . $ed2k_parts[3] . '|' . $ed2k_parts[4] . '|' . $ed2k_parts[5] . '|/';
					}
					else
					{
						$ed2k_link = 'ed2k://|file|' . str_replace('\'', '\\\'', $ed2k_parts[2]) . '|' . $ed2k_parts[3] . '|' . $ed2k_parts[4] . '|/';
					}
					$ed2k_real_links[] = $ed2k_link;
				}
			}

			// Now lets see if we have 2 or more links
			// Only then, we do our little trick, because otherwise, it would be wasted for one link alone
			$ed2k_confirmed = count($ed2k_real_links);
			if ($ed2k_confirmed > 1)
			{
				$link_text = str_replace('@', $ed2k_confirmed, $link_text);

				$ed2k_insert = '<br /><br />';
				$ed2k_insert .= '<SCRIPT>';
				$ed2k_insert .= 'filearray' . $post_id . '=new Array;';
				$ed2k_insert .= 'n=0;';
				$i = 0;
				foreach($ed2k_real_links as $ed2k_link)
				{
					$ed2k_insert .= 'filearray' . $post_id . '[' . $i . ']=\'' . $ed2k_link . '\';';
					$i++;
				}
				$ed2k_insert .= 'iv=false;';
				$ed2k_insert .= 'function addfile' . $post_id . '()';
				$ed2k_insert .= '{';
				$ed2k_insert .= '	var s=filearray' . $post_id . '[n];';
				$ed2k_insert .= '	n++;';
				$ed2k_insert .= '	if(n==filearray' . $post_id . '.length && iv)';
				$ed2k_insert .= '	{';
				$ed2k_insert .= '		top.clearInterval(iv);';
				$ed2k_insert .= '		n=0;';
				$ed2k_insert .= ' 	}';
				$ed2k_insert .= '	top.document.location=s;';
				$ed2k_insert .= '	return true;';
				$ed2k_insert .= '}';
				$ed2k_insert .= 'function addall' . $post_id . '(){iv=top.setInterval("addfile' . $post_id . '()",250)}';
				$ed2k_insert .= '</SCRIPT>';
				$ed2k_insert .= '<span class="gensmall"><a href="javascript:addall' . $post_id. '()" class="postlink">[ '. $link_text .' ]</a></span>';
				$ret = $ret . $ed2k_insert;
			}
		}

		// remove padding
		$ret = substr($ret, 1);

		return($ret);
	}
}
?>
