<?php

class Wps_Subdomains {
    
    var $cats = array();
    var $pages = array();
    var $authors = array();
    //var $cats_root = array();
    //var $cats_nosub = array();
    var $pages_on_index = false;
    
    function __construct() {
        global $wpdb, $wps_page_metakey_subdomain;
    
        $table_name = $wpdb->prefix . "category_subdomains";
    
        //--- Get Root Categories
        $cats_root = get_terms( 'category', 'hide_empty=0&parent=0&fields=ids' );
    
        /* SQL Version
         $sql_cats = "select term_id from {$wpdb->term_taxonomy} where parent = 0 and taxonomy = 'category'";
        $cats_root = $wpdb->get_col( $sql_cats );
        */
    
        //--- Work out the Categories to subdomain
        if ( get_option( WPS_OPT_SUBALL ) != "" ) {
            $cats_exclude = $wpdb->get_col( "SELECT cat_ID FROM {$table_name} WHERE not_subdomain = 1" );
            $cats = array_diff( $cats_root, $cats_exclude );
        } else {
            $cats_include = $wpdb->get_col( "SELECT cat_ID FROM {$table_name} WHERE is_subdomain = 1" );
            $notcats = array_diff( $cats_include, $cats_root );
            $cats = array_diff( $cats_include, $notcats );
        }
    
        //--- Create Category Subdomains
        foreach ( $cats as $cat ) {
            $this->cats[$cat] = new Wps_Subdomain_Category($cat);
        }
    
        //--- Subdomain Pages if option is turned on
        if ( get_option( WPS_OPT_SUBPAGES ) != "" ) {
            //--- Get Pages that are to be Subdomains
            //$pages = get_posts( 'numberposts=-1&post_type=page&meta_key=' . $wps_page_metakey_subdomain . '&meta_value=true' );
            //$pages = get_pages( 'meta_key=' . $wps_page_metakey_subdomain . '&meta_value=true' );
            $pages = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '".$wps_page_metakey_subdomain."' and meta_value = '1'");
            	
            //--- Create Page Subdomains
            foreach ($pages as $page) {
                $this->pages[$page] = new Wps_Subdomain_Page($page);
            }
        }
    
        //--- Subdomain Authors if option is turned on
        if ( get_option( WPS_OPT_SUBAUTHORS ) != "" ) {
            //--- Get Authors
            $authors = wps_get_authors();
            	
            //--- Create Author Subdomains
            foreach ( $authors as $author ) {
                $this->authors[$author->ID] = new Wps_Subdomain_Author( $author );
            }
        }
    
    }
    
    function getPostSubdomain( $postID ) {
        foreach ( $this->cats as $id => $cat ) {
            if ( $cat->isPostMember( $postID ) ) {
                return $id;
            }
        }
    
        return false;
    }
    
    function getCategorySubdomain( $catID ) {
        foreach ( $this->cats as $id => $cat ) {
            if ( $cat->isCatMember( $catID ) ) {
                return $id;
            }
        }
    
        return false;
    }
    
    function getPageSubdomain( $pageID ) {
        foreach ( $this->pages as $id => $page ) {
            if ( $page->isPageMember( $pageID ) ) {
                return $id;
            }
        }
    
        return false;
    }
    
    function getCatIDs( $sort = '' ) {
        $sort_terms = array( 'name', 'slug' );
    
        if ( $sort && in_array( $sort, $sort_terms ) ) {
            $sd_sort = array();
            	
            foreach ( $this->cats as $id => $cat ) {
                $sd_sort[$id] = $cat->{$sort};
            }
            	
            asort( $sd_sort );
            	
            return array_keys( $sd_sort );
        } else {
            return array_keys( $this->cats );
        }
    }
    
    function getPageIDs( $sort = '' ) {
        $sort_terms = array( 'name', 'slug' );
    
        if ( $sort && in_array( $sort, $sort_terms ) ) {
            $sd_sort = array();
            	
            foreach ( $this->pages as $id => $pages ) {
                $sd_sort[$id] = $pages->{$sort};
            }
            	
            asort( $sd_sort );
            	
            return array_keys( $sd_sort );
        } else {
            return array_keys( $this->pages );
        }
    }
    
    function getThisSubdomain() {
        $url = $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
        $subdomains = explode( ".", $url );
        $subdomain = $subdomains[0];
    
        foreach ( $this->cats as $cat ) {
            if ( $cat->slug == $subdomain ) {
                return $cat;
            }
        }
    
        foreach ( $this->pages as $page ) {
            if ( $page->slug == $subdomain ) {
                return $page;
            }
        }
    
        foreach ( $this->authors as $author ) {
            if ( $author->slug == $subdomain ) {
                return $author;
            }
        }
    
        return false;
    }
    
    function findTiedPage( $pageID ) {
    
        foreach ( $this->cats as $catID => $cat ) {
            if ( in_array( $pageID, $cat->getTiedPages() ) ) {
                return $catID;
            }
        }
    
        return false;
    
    }
    
    function getTiedPages() {
        $tied_pages = array();
    
        foreach ( $this->cats as $cat ) {
            $tied_pages = array_merge( $tied_pages, $cat->getTiedPages() );
        }
    
        return array_unique( $tied_pages );
    }
    
    function getPagesOnIndex() {
        if ( ! $this->pages_on_index ) {
            global $wpdb, $wps_page_on_main_index;
            	
            $this->pages_on_index = array();
            	
            //$pages = get_posts( 'numberposts=-1&post_type=page&meta_key=' . $wps_page_on_main_index . '&meta_value=true' );
            //$pages = get_pages( 'meta_key=' . $wps_page_on_main_index . '&meta_value=true' );
            $pages = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '" . Wps_Plugin::METAKEY_ONMAININDEX . "' and meta_value = '1'");
            	
            foreach ($pages as $page) {
                /*
                 //$this->pages_on_index[] = $page->ID;
    
                //$this->pages_on_index = array_merge( $this->pages_on_index, getPageChildren($page->ID));
                */
                $this->pages_on_index[] = $page;
    
                $this->pages_on_index = array_merge( $this->pages_on_index, getPageChildren($page));
                /*
                 $children = & get_children( 'post_status=publish&post_type=page&post_parent=' . $page->ID );
    
                if ( $children ) {
                $this->pages_on_index = array_merge( $this->pages_on_index, array_keys( $children ) );
                }
                */
            }
            	
            // FIXME: I forgot why do we do this
            $this->pages_on_index = array_unique( $this->pages_on_index );
        }
    
        return $this->pages_on_index;
    }
    
    // Is this the right place for this? Perhaps it should be a stand alone function
    function isPageOnIndex($pageID) {
        if (!in_array($pageID, $this->getTiedPages()) || in_array($pageID, $this->getPagesOnIndex())) {
            return true;
        } else {
            return false;
        }
    }
    
    public function getNonSubCategories() {
        $cats_root = get_terms( 'category', 'hide_empty=0&parent=0&fields=ids' );
    
        return array_diff($cats_root, array_keys($this->cats));
    }
}
