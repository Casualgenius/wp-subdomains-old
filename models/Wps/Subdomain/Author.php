<?php

class Wps_Subdomain_Author extends Wps_Subdomain_Abstract {
    
    var $posts = array();
    
    public function __construct( $author ) {
        global $wpdb;
        
        // Check if we've got the author array, if not it may be the ID so go get the row
        if ( ! is_object( $author ) ) {
            $author = $wpdb->get_row( "SELECT ID, user_nicename, display_name from $wpdb->users WHERE ID = $author" );
        }
    
        $this->WpsSubDomain($author->ID, Wps_Plugin::TYPE_AUTHOR);
    
        // Get Category details
        $this->name = $author->display_name;
        $this->slug = $author->user_nicename;
    }
    
    public function isPageMember( $pageID ) {
        foreach ( $this->getAllIDs() as $id ) {
            if ( $id == $pageID ) {
                return true;
            }
        }
    
        return false;
    }
    
    public function getChildren() {
        return array();
    }
    
}
