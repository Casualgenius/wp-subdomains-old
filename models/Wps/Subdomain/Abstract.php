<?php

abstract class Wps_Subdomain_Abstract
{

    var $id;
    var $name;
    var $type;
    var $slug;
    var $theme;
    var $archive;
    var $children = false;
    var $posts = false;
    var $archive_subdomains = array(Wps_Plugin::TYPE_CATEGORY, Wps_Plugin::TYPE_AUTHOR);

    public function __construct ($id, $type)
    {
        $this->id = $id;
        $this->type = $type;
        $this->archive = in_array($this->type, $this->archive_subdomains);
    }

    function getPosts ()
    {
        // Fetch the subdomain's posts
        if ($this->archive) {
            global $wpdb;
            
            // Use custom SQL or wordpress's get_posts function
            $where = '';
            $join = '';
            
            switch ($this->type) {
                case WPS_TYPE_AUTHOR:
                    $where = 'posts.post_author=' . $this->id;
                    break;
                case WPS_TYPE_CAT:
                    $join = "JOIN {$wpdb->term_relationships} tr ON posts.ID = tr.object_id JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
                    $where = "tt.taxonomy = 'category' AND tt.term_id in (" . implode(',', $this->getAllIDs()) . ")";
                    break;
                default:
                    break;
            }
            
            if ($where) {
                // Fetch just the posts, not pages
                $where .= " AND posts.post_type != 'page'";
                
                // If we're in the admin section, grab posts that don't appear
                // on the site yet
                if (is_admin()) {
                    $where .= " AND posts.post_status in ('publish', 'future' , 'draft' , 'pending')";
                } else {
                    $where .= " AND posts.post_status = 'publish'";
                }
                
                // Go get the IDs
                $this->posts = $wpdb->get_col(
                "SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts " . $join . " WHERE " . $where);
            } else {
                $this->posts = array();
            }
        }
    }

    function isPostMember ($postID)
    {
        if ($this->posts === false) {
            $this->getPosts();
        }
        
        return in_array($postID, $this->posts);
    }

    function changePostLink ($link, $postid = 0)
    {
        $path = wps_getUrlPath($link);
        $link = $this->getSubdomainLink();
        
        switch ($this->type) {
            case Wps_Plugin::TYPE_CATEGORY:
                $link .= $this->changePostPath($path, $postid);
                break;
            case Wps_Plugin::TYPE_AUTHOR:
                $link .= $path;
                break;
        }
        
        return $link;
    }

    function getSubdomainLink ($addslash = true)
    {
        $link = "http://" . $this->slug . "." . wps_domain();
        
        if ($addslash) {
            $link .= "/";
        }
        
        return $link;
    }

    function changeGeneralLink ($link)
    {
        $path = wps_getUrlPath($link);
        
        if ($path) {
            $link = $this->getSubdomainLink() . $path;
        } else {
            $link = $this->getSubdomainLink(false);
        }
        
        return $link;
    }

    function getTheme ()
    {
        if (! $this->theme || $this->theme == '(none)') {
            return false;
        } else {
            return $this->theme;
        }
    }

    function getAllIDs ()
    {
        $id_array = $this->getChildren();
        
        array_unshift($id_array, $this->id);
        
        return $id_array;
    }

    function getRewriteRules ()
    {
        
        switch ($this->type) {
            case WPS_TYPE_CAT:
                $field = 'category_name';
                break;
            case WPS_TYPE_PAGE:
                return false;
                break;
            case WPS_TYPE_AUTHOR:
                $field = 'author_name';
                break;
        }
        
        $rules = array();
        $rules["feed/(feed|rdf|rss|rss2|atom)/?$"] = "index.php?" . $field . "=" . $this->slug . "&feed=\$matches[1]";
        $rules["(feed|rdf|rss|rss2|atom)/?$"] = "index.php?" . $field . "=" . $this->slug . "&feed=\$matches[1]";
        $rules["page/?([0-9]{1,})/?$"] = "index.php?" . $field . "=" . $this->slug . "&paged=\$matches[1]";
        $rules["/?$"] = "index.php?" . $field . "=" . $this->slug;
        
        return $rules;
    }

    function addRewriteFilter ($rules)
    {
        if ($this->archive && ! empty($rules)) {
            // Filter by Author or Category
            switch ($this->type) {
                case WPS_TYPE_CAT:
                    $field = 'category_name';
                    break;
                case WPS_TYPE_AUTHOR:
                    $field = 'author_name';
                    break;
                default:
                    $field = false;
                    break;
            }
            
            if ($field) {
                // Add the filter to each rule
                foreach ($rules as $regexp => $rule) {
                    $rules[$regexp] = $rule . '&' . $field . '=' . $this->slug;
                }
            }
        }
        
        return $rules;
    }

    abstract public function getChildren ();
}