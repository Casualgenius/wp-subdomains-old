<?php

class Wps_Subdomain_Page extends Wps_Subdomain_Abstract {
    
    function WpsSubDomainPage( $page ) {
        global $wpdb, $wps_page_metakey_theme;
    
        $meta_data = array('theme' => $wps_page_metakey_theme);
    
        // Check if we've got the page object, if not it may be the ID so go get the row
        if ( ! is_object( $page ) ) {
            $page = get_post( $page );
        }
    
        $this->WpsSubDomain( $page->ID, WPS_TYPE_PAGE );
    
        // Get Category details
        $this->name = $page->post_title;
        $this->slug = $page->post_name;
    
        foreach ($meta_data as $var => $field) {
            $this->{$var} = get_post_meta($this->id, $field, true);
        }
    }
    
    function isPageMember( $pageID ) {
        foreach ( $this->getAllIDs() as $id ) {
            if ( $id == $pageID ) {
                return true;
            }
        }
    
        return false;
    }
    
    function changePageLink( $pageID, $link ) {
    
        if ( in_array( $pageID, $this->getAllIDs() ) ) {
            if ( $pageID == $this->id ) {
                $link = $this->getSubdomainLink(false);
            } else {
                // FIXME: need a way to do this without get_post
                $this_page = get_post( $pageID );
    
                $kid_string = '';
    
                while ( $this_page->ID != $this->id ) {
                    $kid_string = $this_page->post_name . "/" . $kid_string;
                    $this_page = get_post( $this_page->post_parent );
                }
    
                $link = $this->getSubdomainLink() . $kid_string;
            }
        }
    
        return $link;
    }
    
    function getChildren() {
        if ( ! $this->children ) {
            // Get Page Children
            $this->children = getPageChildren($this->id);
            /*
             $this->children = array();
            	
            $children = & get_children( 'post_status=publish&post_type=page&post_parent=' . $this->id );
            	
            if ( $children ) {
            $this->children = array_keys( $children );
            }
            */
        }
    
        return $this->children;
    }
}
