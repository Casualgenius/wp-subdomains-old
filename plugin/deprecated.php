<?php

function sd_list_categories($id, $num = 1) {
	global $wpdb, $table_prefix, $post, $comment;
	$table_name = $table_prefix . "category_subdomains";
	
	if (is_array($id))
		$post = $id; else
		$post = get_post($id);
	
	$query = "SELECT post_category FROM {$wpdb->posts} WHERE ID = {$post->ID} ORDER BY post_category DESC LIMIT $num";
	
	$cats = $wpdb->get_results($query);
	$retstr = "";
	foreach ($cats as $cat) {
		$category = get_category($cat->category_id);
		
		$retstr .= $category->cat_name;
		$retstr .= " ";
	}
	return $retstr;
}


function csd_redir_subdomain() {
	global $wps_category_base, $csd_redir_wildcards, $wpdb, $table_prefix, $wps_page_metakey_subdomain;
	$table_name = $table_prefix . "category_subdomains";

	if (strpos(getenv('REQUEST_URI'), '/wp-admin/') === false) {
		
		update_option('rewrite_rules', "");
		
		//get all the categories here
		$categories = $wpdb->get_results("SELECT * FROM $wpdb->terms WHERE term_group = 0");
		
		//get all the pages here
		$pages = get_pages();
		
		$url = getenv('HTTP_HOST') . getenv('REQUEST_URI');
		
		$blogurl = wps_blogurl();
		
		//the stuff after the host
		$append = substr($url, strpos($url, $blogurl) + strlen($blogurl) + 1);
		if (substr($append, -1, 1) != "/") {
			$append .= "/";
		}
		
		//grab the potentional subdomains;
		$url = substr($url, 0, strpos($url, $blogurl));
		if (strlen($url))
			$url = substr($url, 0, strlen($url) - 1);
		
		$subdomains = split("\.", $url);
		$categorystr = $subdomains[sizeof($subdomains) - 1];
		
		if ($categorystr == "www")
			$categorystr = "";
		
		$pots = array();
		preg_match("/([^\/]*)\/(.*)/", $append, $pots);
		
		print('<pre>'.print_r($pots, true).'</pre>');
		
		if (substr_count($pots[2], "/") == 2) {
			preg_match("/([^\/]*)\/(.*?)\/(.*?)\/(.*)/", $append, $pots);
			$pot_category = $pots[2];
			print '1';
		} else if (substr_count($pots[2], "/") == 1) {
			preg_match("/([^\/]*)\/(.*?)\/(.*?)/", $append, $pots);
			$pot_category = $pots[1];
			$categorystr = $pots[1];
			print '2';
		} else {
			$pot_category = $pots[1];
			print '3';
		}
		
		print ' | '.$url .' | '. $categorystr.' | '.$pot_category;
		//print('<pre>'.print_r($pots, true).'</pre>');
		//$par_cat = get_category($cat);
		
		//print('<pre>'.print_r($wp_query, true).'</pre>');
		
		//if we have a potential category/page
		if ($categorystr) {
			$this_category = NULL;
			foreach ($categories as $cat) {
				if ($cat->slug == $categorystr) {
					$this_category = $cat;
					break;
				}
			}
			if ($this_category) {
				$cat = $this_category->term_id;
			//	$wp_query->query_vars['cat'] = $this_category->term_id;
			}
			
			//if we didn't find a category, this might be a page.  Only do this if the "Subdomain Pages" is set
			$this_page = NULL;
			if ($this_category == NULL) {
				foreach ($pages as $page) {
					if ($page->post_name == $categorystr && $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE Post_ID = {$page->ID} AND meta_key = '{$wps_page_metakey_subdomain}';") == "true") {
						$this_page = $page;
						break;
					}
				}
			}
			
			$arr = array();
			
			foreach ($categories as $cat) {
				if ($cat->slug == $categorystr) {
					$this_category = $cat;
					break;
				}
			}
			
			print('<pre>'.print_r($this_category, true).'</pre>');
			
			if ($pot_category != "") {
				$query = "SELECT term_id FROM {$wpdb->terms} WHERE slug = '{$pot_category}'";
				$cat_ids = $wpdb->get_results($query, ARRAY_A);
				if ($cat_ids) {
					foreach ($cat_ids as $the_cat_id) {
						$arr[] = $the_cat_id['term_id'];
					}
					if (sizeof($arr) == 1) {
						$cat_id = $arr[0];
					} else {
						$ars = implode(",", $arr);
						$query = "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ($ars) AND parent = '{$cat->term_id}'";
						$cat_id = $wpdb->get_var($query);
					}
					foreach ($categories as $cat) {
						if ($cat->term_id == $cat_id) {
							$this_category = $cat;
							break;
						}
					}
				} else {
					print('in here');
					$query = "SELECT ID FROM {$wpdb->posts} WHERE post_name = '{$pot_category}'";
					$post_id = $wpdb->get_var($query);
					$query = "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = '{$post_id}'";
					$post_cat_id = $wpdb->get_var($query);
					$query = "SELECT parent FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = '{$post_cat_id}'";
					$par_cat_id = $wpdb->get_var($query);
					if (($post_cat_id != $par_cat_id) && ($par_cat_id != 0)) {
						$query = "SELECT {$wpdb->terms}.slug FROM {$wpdb->terms},{$wpdb->term_taxonomy} WHERE {$wpdb->terms}.term_id = '{$post_cat_id}' AND {$wpdb->term_taxonomy}.term_id = '{$post_cat_id}' AND {$wpdb->term_taxonomy}.taxonomy = 'category'";
						$cat_slug = $wpdb->get_var($query);
						if ($cat_slug != '') {
							$redir = "http://" . $categorystr . "." . $blogurl . "/" . $cat_slug . "/" . $pot_category . "/";
							header("HTTP/1.1 301 Moved Permanently");
							header("Location: " . $redir);
							exit();
						}
					}
				}
			}
			
			//now we've either got a category or a page or both
			//if we don't, send this joker back to the main page
			if ($this_category == NULL && $this_page == NULL && $csd_redir_wildcards) {
				//we didn't find a category, so redirect it to the main blogurl.
				if (strpos(getenv('REQUEST_URI'), '/wp-admin/') === false) {
					$blogurl = get_bloginfo('url');
					$blogurl = substr($blogurl, 7, strlen($blogurl));
					$redir = "http://" . $blogurl . "/" . $append;
					header("HTTP/1.1 301 Moved Permanently");
					header("Location: " . $redir);
					exit();
				}
			} else {
				//404 the guy
			}
		} else { //we don't have a subdomain...this may require some redirection
			if (strpos($append, "rchives/") == 1) {
				$matches = array();
				//echo $append;
				preg_match("/archives\/(.*)\/(.*)\/(.*)\.html/", $append, $matches);
				//echo "it's old";
			}
			//check for either a $wps_category_base/cat_name structure or a /cat_name/ folder.  One is for oldstyle categories, the other is for oldstyle permalinks.
			if (strlen($wps_category_base)) {
				if (strpos($append, $wps_category_base) == 0 && strpos($append, $wps_category_base) !== false) {
					//echo "it's an old category.  Redirect it to the new one.";
					$append = substr($append, strpos($append, $wps_category_base) + strlen($wps_category_base));
					$cat_name = $append;
					if (strpos($cat_name, "/") !== false) {
						$cat_name = substr($cat_name, 0, strpos($cat_name, "/"));
						$append = substr($append, strpos($append, "/") + 1);
					} else
						$append = "";
						//grab the category id
					foreach ($categories as $cat) {
						if ($cat->slug == $cat_name) {
							$this_category = $cat;
							break;
						}
					}
					//now we have the category object.  If it doesn't want to be redirected, exit here
					$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$this_category->term_id} AND not_subdomain = 1;");
					if (!$subdomain_me)
						return;
						
					//else, redirect the old style category
					$redir = "http://" . $cat_name . "." . $blogurl . "/" . $append;
					header("HTTP/1.1 301 Moved Permanently");
					header("Location: " . $redir);
					exit();
				} else {
					$pots = array();
					preg_match("/([^\/]*)\/(.*)/", $append, $pots);
					//echo "it's an old post.  Redirect it to the new style.";
					

					//get the potential category 
					$pots = array();
					preg_match("/([^\/]*)\/(.*)/", $append, $pots);
					//preg_match("/([^-]*)-(.*)/", $append, $pots);
					$pot_category = $pots[1];
					//return;
					

					//loop through all the category names and see if it matches
					foreach ($categories as $cate) {
						if ($cate->slug == $pot_category) {
							//now we have the category object.  If it doesn't want to be redirected, exit here
							$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$this_category->term_id} AND not_subdomain = 1;");
							if (!$subdomain_me)
								return;
							$this_category = get_category($cate->term_id);
							if ($this_category->parent > 0) {
								$par_category = get_category($this_category->parent);
								$redir = "http://" . $par_category->slug . "." . $blogurl . "/" . $pot_category . "/" . $pots[2];
							} else {
								$redir = "http://" . $pot_category . "." . $blogurl . "/" . $pots[2];
							}
							//redirect it to the new style subdomain.
							header("HTTP/1.1 301 Moved Permanently");
							header("Location: " . $redir);
							exit();
						}
					}
					
					//if we get here, it means it's just domain.com/post-name
					//redirect it to the proper category page.
					$query = "SELECT ID FROM {$wpdb->posts} WHERE post_name = '{$pots[1]}' AND post_status='publish'";
					$postID = $wpdb->get_var($query);
					if (!intval($postID)) {
						//echo "Not Intval";	
					}
					return;
					if (intval($postID) > 0) {
						$page = $wpdb->get_row("SELECT post_type,post_name FROM {$wpdb->posts} WHERE ID = {$postID};", ARRAY_A);
						$is_page = ($page['post_type'] == "page");
						if ($is_page) {
							$subdomain_page = ($wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE Post_ID = {$postID} AND meta_key = '{$wps_page_metakey_subdomain}';") == "true");
							if ($subdomain_page) {
								$redir = "http://" . $page['post_name'] . "." . $blogurl;
							}
						} else {
							$query = "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = {$postID} ORDER BY term_taxonomy_id DESC LIMIT 1";
							$termid = $wpdb->get_var($query);
							if ($termid) {
								$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$termid} AND not_subdomain = 1;");
								if ($subdomain_me) {
									$this_category = get_category($termid);
									if ($this_category->parent > 0) {
										$par_category = get_category($this_category->parent);
										$redir = "http://" . $par_category->slug . "." . $blogurl . "/" . $this_category->slug . "/" . $pots[0];
									} else {
										$redir = "http://" . $this_category->slug . "." . $blogurl . "/" . $pots[0];
									}
								}
							}
						}
						if ($redir) {
							header("HTTP/1.1 301 Moved Permanently");
							header("Location: " . $redir);
							exit();
						}
					}
				}
			}
		}
	}
}


function sd_bloginfo($info) {
	global $csd_new_theme_dir, $csd_old_theme_dir;
	
	if (strlen($csd_old_theme_dir) && strlen($csd_new_theme_dir))
		if (strpos($info, $csd_old_theme_dir) !== false)
			$info = str_replace($csd_old_theme_dir, $csd_new_theme_dir, $info);
	
	return $info;
}

function csd_wp_login($arg) {
	$cookiepath = COOKIEPATH;
	$blogurl = wps_blogurl();
	
	$cuser = "wordpressuser_" . COOKIEHASH;
	$cpass = "wordpresspass_" . COOKIEHASH;
	setcookie($cuser, "", time() + 31536000, $cookiepath, $blogurl);
	setcookie($cpass, "", time() + 31536000, $cookiepath, $blogurl);
	
	setcookie($cuser, $_POST['log'], time() + 31536000, $cookiepath, $blogurl);
	setcookie($cpass, md5(md5($_POST['pwd'])), time() + 31536000, $cookiepath, $blogurl);
	return $arg;
}

function csd_wp_logout($arg) {
	$cookiepath = COOKIEPATH;
	$blogurl = wps_blogurl();	
	
	$cuser = "wordpressuser_" . COOKIEHASH;
	$cpass = "wordpresspass_" . COOKIEHASH;
	setcookie($cuser, "", time() + 31536000, $cookiepath, $blogurl);
	setcookie($cpass, "", time() + 31536000, $cookiepath, $blogurl);
	return $arg;
}


function sd_post_rewrite_rules($rules) {

	global $wpdb, $table_prefix, $csd_post_rules;
	$table_name = $table_prefix . "category_subdomains";
	
	print('<pre>'.print_r($rules, true).'</pre>');
	//grab the potentional subdomains;
	$url = getenv('HTTP_HOST') . getenv('REQUEST_URI');
	$subdomains = split("\.", $url);
	$categorystr = $subdomains[0];
	
	if ($categorystr == "www")
		$categorystr = "";
		
	//if we have a potential category/page
	if ($categorystr) {
		$this_category = NULL;
		
		//get all the categories here
		$categories = $wpdb->get_results("SELECT * FROM $wpdb->terms");
		
		foreach ($categories as $cat) {
			if ($cat->slug == $categorystr) {
				$this_category = $cat;
				break;
			}
		}
		
		//clear the existing lame-o rules and makes some sexy new ones
		if ($this_category) {
			
			$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$this_category->term_id} AND not_subdomain = 1;");
			if (!$subdomain_me)
				return $rules;
			
			$holdarray = $rules;
			
			$rules = array();
			$temparray = array();
			
			$kids_str = get_category_children($this_category->term_id);
			$kids = split("/", $kids_str);
			array_shift($kids);
			foreach ($kids as $kid) {
				$cat = get_category($kid);
				if ($cat->parent != $this_category->term_id) {
					$catPar = get_category($cat->parent);
					$path = $catPar->slug . "/" . $cat->slug;
				} else {
					$path = $cat->slug;
				}
				foreach ($holdarray as $key => $value) {
					$key = $path . "/" . $key;
					$temparray[$key] = $value;
					//echo "<br>".$key." &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;".$value;
				}
				$rules = array_merge($temparray, $rules);
				$temparray = array();
			}
			
			$rules = array_merge($rules, $holdarray);
		}
	
	}
	print('<pre>'.print_r($rules, true).'</pre>');

	return $rules;
}

// a very special function to display only the pages of this category
function sd_list_cat_pages() {
	global $wpdb, $cat, $post, $wps_page_metakey_tie;
	
	$termid = $cat;
	//if no cat ID else get the category from the post and do the same
	if (!$cat && $post->post_status == "static") { //it's a page, we have to pull the category from the tie_page_metakey
		$termid = intval($wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE Post_ID = {$post->ID} AND meta_key = '{$wps_page_metakey_tie}';"));
	}
	if (!$termid && $post) {
		$query = "SELECT post_category FROM {$wpdb->posts} WHERE ID = {$post->ID} ORDER BY post_category DESC LIMIT 1";
		$termid = $wpdb->get_var($query);
	}
	if ($termid) {
		//grab all the pages
		$pages = get_pages();
		
		foreach ($pages as $page) {
			if (intval($wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE Post_ID = {$page->ID} AND meta_key = '{$wps_page_metakey_tie}';")) == $termid) {
				echo "<li><a href=\"" . get_page_link($page->ID) . "\">" . $page->post_title . "</a></li>\n";
			}
		}
	}
}


function get_page_hierarchy_links($posts, $parent = 0, $link = "") {
	$result = array();
	if ($posts) {
		foreach ($posts as $post) {
			if ($post->post_parent == $parent) {
				//echo "post: ".$post->post_name."\n";
				//echo "link: ".$link."\n";
				$result[$post->ID] = $link . $post->post_name . "/";
				$children = get_page_hierarchy_links($posts, $post->ID, $link . $post->post_name . "/");
				$result += $children; //append $children to $result
			}
		}
	}
	return $result;
}

//-------------  LINKS  ---------------//

function sd_previous_posts_link($Prevstr = "&laquo; Previous Page") {
	global $request, $posts_per_page, $wpdb, $max_num_pages, $paged, $wpdb, $table_prefix;
	$this_category = get_the_category();
	$this_category = $this_category[0];
	
	$table_name = $table_prefix . "category_subdomains";
	//if this category wants to be subdomained
	$subdomain_me = false;
	if ($this_category)
		$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$this_category->term_id} AND not_subdomain = 1;");
	
	if (!is_single() && is_category() && $subdomain_me) {
		if ('posts' == get_query_var('what_to_show')) {
			if (!isset($max_num_pages)) {
				preg_match('#FROM\s(.*)\sGROUP BY#siU', $request, $matches);
				$fromwhere = $matches[1];
				$numposts = $wpdb->get_var("SELECT COUNT(DISTINCT ID) FROM $fromwhere");
				$max_num_pages = ceil($numposts / $posts_per_page);
			}
		} else {
			$max_num_pages = 999999;
		}
		//now strip off the blog name
		/*
		$blogurl = get_bloginfo('url');
		$blogurl = substr($blogurl, 7, strlen($blogurl));
		$blogurl = str_replace("www.", "", $blogurl);
		*/
		$blogurl = wps_blogurl();		

		if ($paged > 1) {
			$nextpage = intval($paged) - 1;
			if ($nextpage < 1)
				$nextpage = 1;
			$prevlink = get_pagenum_link($nextpage);
			$prevlink = substr($prevlink, strpos($prevlink, $blogurl) + strlen($blogurl));
			
			//strip off the nicename for the category
			$pos = strpos($prevlink, $this_category->slug);
			
			if ($pos !== false) {
				$pos += strlen($this_category->slug);
				$prevlink = substr($prevlink, $pos);
			}
			
			//if we're supposed to keep the parents, find the parent of this guy and make it the subdomain
			$kid_string = "";
			while ( $this_category->category_parent > 0 ) {
				$kid_string = $this_category->slug . "/" . $kid_string;
				$this_category = get_category($this_category->category_parent);
			}
			$prevlink = "http://" . $this_category->slug . "." . $blogurl . $kid_string . $prevlink;
			
			echo "<a href='$prevlink'>" . $Prevstr . "</a>";
		}
	} else
		previous_posts_link($Prevstr);
}

function sd_next_posts_link($Nextstr = "Next Page &raquo;") {
	global $request, $posts_per_page, $wpdb, $max_num_pages, $paged, $wpdb, $table_prefix;
	$this_category = get_the_category();
	$this_category = $this_category[0];
	
	$table_name = $table_prefix . "category_subdomains";
	//if this category wants to be subdomained
	$subdomain_me = false;
	if ($this_category)
		$subdomain_me = !$wpdb->get_var("SELECT not_subdomain FROM {$table_name} WHERE cat_ID = {$this_category->term_id} AND not_subdomain = 1;");
	
	if (!is_single() && is_category() && $subdomain_me) {
		if ('posts' == get_query_var('what_to_show')) {
			if (!isset($max_num_pages)) {
				preg_match('#FROM\s(.*)\sGROUP BY#siU', $request, $matches);
				$fromwhere = $matches[1];
				$numposts = $wpdb->get_var("SELECT COUNT(DISTINCT ID) FROM $fromwhere");
				$max_num_pages = ceil($numposts / $posts_per_page);
			}
		} else {
			$max_num_pages = 999999;
		}
		
		//now strip off the blog name
		/*
		$blogurl = get_bloginfo('url');
		$blogurl = substr($blogurl, 7, strlen($blogurl));
		$blogurl = str_replace("www.", "", $blogurl);
		*/
		$blogurl = wps_blogurl();
		
		if (!$max_page) {
			if (isset($max_num_pages)) {
				$max_page = $max_num_pages;
			} else {
				preg_match('#FROM\s(.*)\sGROUP BY#siU', $request, $matches);
				$fromwhere = $matches[1];
				$numposts = $wpdb->get_var("SELECT COUNT(DISTINCT ID) FROM $fromwhere");
				$max_page = $max_num_pages = ceil($numposts / $posts_per_page);
			}
		}
		if (!$paged)
			$paged = 1;
		$nextpage = intval($paged) + 1;
		
		if ((empty($paged) || $nextpage <= $max_page)) {
			if (!$paged)
				$paged = 1;
			$nextpage = intval($paged) + 1;
			if (!$max_page || $max_page >= $nextpage) {
				$nextlink = get_pagenum_link($nextpage);
				$nextlink = substr($nextlink, strpos($nextlink, $blogurl) + strlen($blogurl));
				
				$pos = strpos($nextlink, $this_category->slug);
				
				if ($pos !== false) {
					$pos += strlen($this_category->slug);
					$nextlink = substr($nextlink, $pos);
				}
				
				$kid_string = "";
				while ( $this_category->category_parent > 0 ) {
					$kid_string = $this_category->slug . "/" . $kid_string;
					$this_category = get_category($this_category->category_parent);
				}
				$nextlink = "http://" . $this_category->slug . "." . $blogurl . $kid_string . $nextlink;
				echo "<a href='$nextlink'>" . $Nextstr . "</a>";
			}
		}
	} else
		next_posts_link($Nextstr);
}

function sd_posts_nav_link($sep = ' &#8212; ', $Prevstr = "&laquo; Previous Page", $Nextstr = "Next Page &raquo;") {
	
	if (!is_category())
		posts_nav_link($sep, $Prevstr, $Nextstr); else {
		sd_previous_posts_link($Prevstr);
		echo $sep;
		sd_next_posts_link($Nextstr);
	}
	return;
}

function sd_get_next_post($in_same_cat = false, $excluded_categories = "") {
	global $post, $wpdb;
	$current_post_date = $post->post_date;
	$join = '';
	if ($in_same_cat) {
		$join = " ";
		$cat_array = get_the_category($post->ID);
		$join .= ' AND (post_category = ' . intval($cat_array[0]->term_id);
		for($i = 1; $i < (count($cat_array)); $i++) {
			$join .= ' OR post_category = ' . intval($cat_array[$i]->term_id);
		}
		$join .= ')';
	}
	$sql_exclude_cats = '';
	if (!empty($excluded_categories)) {
		$blah = explode('and', $excluded_categories);
		foreach ($blah as $category) {
			$category = intval($category);
			$sql_exclude_cats .= " AND post_category != $category";
		}
	}
	$now = current_time('mysql');
	
	return @$wpdb->get_row("SELECT ID,post_title FROM $wpdb->posts $join WHERE post_date > '$current_post_date' AND post_date < '$now' AND post_status = 'publish' $sqlcat $sql_exclude_cats AND ID != $post->ID ORDER BY post_date ASC LIMIT 1");
}

function sd_next_post_link($format = '%link &raquo;', $link = '%title', $in_same_cat = false, $excluded_categories = '') {
	global $post, $wpdb;
	$postholder = $post;
	$post = sd_get_next_post($in_same_cat, $excluded_categories);
	
	if (!$post) {
		$post = $postholder;
		return;
	}
	
	$title = apply_filters('the_title', $post->post_title, $post);
	$string = '<a href="' . get_permalink($post->ID) . '">';
	$link = str_replace('%title', $title, $link);
	$link = $string . $link . '</a>';
	$format = str_replace('%link', $link, $format);
	
	$post = $postholder;
	echo $format;
}

function sd_get_previous_post($in_same_cat = false, $excluded_categories = '') {
	global $post, $wpdb;
	if (!is_single() || is_attachment())
		return null;
	
	$current_post_date = $post->post_date;
	
	$join = '';
	if ($in_same_cat) {
		$join = " ";
		$cat_array = get_the_category($post->ID);
		$join .= ' AND (post_category = ' . intval($cat_array[0]->term_id);
		for($i = 1; $i < (count($cat_array)); $i++) {
			$join .= ' OR post_category = ' . intval($cat_array[$i]->term_id);
		}
		$join .= ')';
	}
	
	$sql_exclude_cats = '';
	if (!empty($excluded_categories)) {
		$blah = explode('and', $excluded_categories);
		foreach ($blah as $category) {
			$category = intval($category);
			$sql_exclude_cats .= " AND post_category != $category";
		}
	}
	
	return @$wpdb->get_row("SELECT ID, post_title FROM $wpdb->posts $join WHERE post_date < '$current_post_date' AND post_status = 'publish' $sqlcat $sql_exclude_cats ORDER BY post_date DESC LIMIT 1");
}

function sd_previous_post_link($format = '&laquo; %link', $link = '%title', $in_same_cat = false, $excluded_categories = '') {
	global $post, $wpdb;
	$postholder = $post;
	if (is_attachment())
		$post = & get_post($GLOBALS['post']->post_parent); else
		$post = sd_get_previous_post($in_same_cat, $excluded_categories);
	
	if (!$post) {
		$post = $postholder;
		return;
	}
	
	$title = apply_filters('the_title', $post->post_title, $post);
	$string = '<a href="' . get_permalink($post->ID) . '">';
	$link = str_replace('%title', $title, $link);
	$link = $pre . $string . $link . '</a>';
	
	$format = str_replace('%link', $link, $format);
	$post = $postholder;
	
	echo $format;
}

?>