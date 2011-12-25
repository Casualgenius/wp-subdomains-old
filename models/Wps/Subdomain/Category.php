<?php

class Wps_Subdomain_Category extends Wps_Subdomain_Abstract {
    
    var $filter_pages;
    var $tied_pages = false;
    var $link_title = false;
    
    function WpsSubDomainCat( $id ) {
        global $wpdb;
    
        $this->WpsSubDomain( $id, WPS_TYPE_CAT );
    
        $cat = get_category( $this->id );
    
        // Get Category details
        $this->name = $cat->name;
        $this->slug = $cat->slug;
    
        // Get Sub Domain options
        $table_name = $wpdb->prefix . "category_subdomains";
        $sd_options = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE cat_ID = {$this->id}" );
        $this->filter_pages = $sd_options->filter_pages;
        $this->theme = $sd_options->cat_theme;
        if ($sd_options->cat_link_title) {
            $this->link_title = $sd_options->cat_link_title;
        }
    }
    
    function changePostPath( $path, $postid ) {
        $permalink = get_option( 'permalink_structure' );
    
        if ( strpos( $permalink, '%category%' ) != false ) {
            $cats = get_the_category( $postid );
            	
            if ( $cats ) {
                usort( $cats, '_usort_terms_by_ID' ); // order by ID
                $original_path = $this->getCategoryPath( $cats[0], false );
            }
            	
            $common_cats = array();
            	
            foreach ( $cats as $cat ) {
                if ( in_array( $cat->term_id, $this->getAllIDs() ) ) {
                    $common_cats[] = $cat->term_id;
                }
            }
            	
            reset( $common_cats );
            $catid = current( $common_cats );
            	
            $new_path = $this->getCategoryPath( get_category( $catid ) );
            	
            $path = str_replace( $original_path, $new_path, $path );
        }
    
        return $path;
    }
    
    function getCategoryPath( $cat, $hide_subdomain = true ) {
        $category_path = $cat->slug;
    
        if ( $parent = $cat->parent ) {
            $category_path = get_category_parents( $parent, false, '/', true ) . $category_path;
        } else {
            $category_path .= '/';
        }
    
        if ( $hide_subdomain && in_array( $cat->term_id, $this->getAllIDs() ) ) {
            if ( $parent ) {
                $slug_length = strlen( $this->slug );
                if ( substr( $category_path, 0, $slug_length ) == $this->slug ) {
                    $category_path = substr( $category_path, $slug_length + 1 );
                }
            } else {
                $category_path = '';
            }
        }
    
        return ($category_path);
    }
    
    function changeCategoryLink( $catID, $link = '' ) {
        global $wps_category_base;
    
        if ( $catID == $this->id ) {
            $link = $this->getSubdomainLink(false);
        } else {
            $this_category = get_category( $catID );
            	
            $kid_string = '';
            	
            while ( $this_category->term_id != $this->id ) {
                $kid_string = $this_category->slug . '/' . $kid_string;
                $this_category = get_category( $this_category->category_parent );
            }
            	
            if ( get_option( WPS_OPT_NOCATBASE ) ) {
                $link = $this->getSubdomainLink() . $kid_string;
            } else {
                $link = $this->getSubdomainLink() . $wps_category_base . $kid_string;
            }
        }
    
        return $link;
    }
    
    function getTiedPages() {
        global $wpdb, $wps_page_metakey_tie;
    
        if ( get_option( WPS_OPT_PAGEFILTER ) ) {
            if ( ! $this->tied_pages ) {
                // FIXME: URGENT!!! Can use the get_posts function for this, this causes a bug
                $this->tied_pages = $wpdb->get_col( "SELECT Post_ID FROM {$wpdb->postmeta} WHERE meta_key = '{$wps_page_metakey_tie}' and meta_value = '" . $this->id ."'" );
    
                foreach ( $this->tied_pages as $pageID ) {
                    $this->tied_pages = array_merge( $this->tied_pages, getPageChildren( $pageID ) );
                    /*
                     $children = & get_children( 'post_type=page&post_parent=' . $pageID );
                    if ( $children ) {
                    $this->tied_pages = array_merge( $this->tied_pages, array_keys( $children ) );
                    }
                    */
                }
    
                $this->tied_pages = array_unique( $this->tied_pages );
            }
        } else {
            $this->tied_pages = array();
        }
    
        return $this->tied_pages;
    }
    
    function getChildren() {
        if ( ! $this->children ) {
            // Get Subdomain Children
            $this->children = array();
            foreach ( get_categories( 'child_of=' . $this->id ) as $child ) {
                $this->children[] = $child->term_id;
            }
        }
    
        return $this->children;
    }
    
    function isCatMember( $catID ) {
        if ( in_array( $catID, $this->getAllIDs() ) ) {
            return true;
        } else {
            return false;
        }
    }
    
}
