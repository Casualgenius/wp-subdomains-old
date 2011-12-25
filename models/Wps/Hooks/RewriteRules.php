<?php

class Wps_Hooks_RewriteRules extends Wps_Hooks_Abstract
{
    function wps_category_rewrite_rules( $rules ) {
        global $wps_category_base;
    
        // See if we're on a category subdomain
        if ( $this->_plugin->getSubdomain() && ($this->_plugin->getSubdomain()->type == Wps_Plugin::TYPE_CATEGORY) ) {
            if ( strpos( $_SERVER['REQUEST_URI'], $wps_category_base ) == 1 ) {
                // if the url has the category base in it then we're in a sub category
                foreach ( $rules as $key => $value ) {
                    $rules[$key] = str_replace( '$matches[1]', $this->_plugin->getSubdomain()->slug . '/$matches[1]', $value );
                }
            } else {
                // Not in a sub category
                $rules = $this->_plugin->getSubdomain()->getRewriteRules();
            }
    
        }
    
        return $rules;
    }
    
    function wps_author_rewrite_rules( $rules ) {
        // See if we're on a category subdomain
        if ( $this->_plugin->getSubdomain() && ($this->_plugin->getSubdomain()->type == Wps_Plugin::TYPE_AUTHOR) ) {
            $rules = $this->_plugin->getSubdomain()->getRewriteRules();
        }
    
        return $rules;
    }
    
    // Check if we're on a subdomain and filter the date archive if we are
    function wps_date_rewrite_rules( $rules ) {
        if ( $this->_plugin->getSubdomain() ) {
            $rules = $this->_plugin->getSubdomain()->addRewriteFilter($rules);
        }
    
        return $rules;
    }
    
    function wps_post_rewrite_rules( $rules ) {
        global $wp_rewrite;
    
        // If we have %category% in the permalink we also need to create rules without it.
        // This is because the category might be the subdomain and so wouldn't be in the url
        if ( strstr( $wp_rewrite->permalink_structure, '%category%' ) && $this->_plugin->getSubdomain() && ($this->_plugin->getSubdomain()->type == Wps_Plugin::TYPE_CATEGORY) ) {
            // Grab the permalink structure
            $perma_tmp = $wp_rewrite->permalink_structure;
    
            // Remove the /%category section
            $perma_tmp = str_replace('/%category%','',$perma_tmp);
    
            // Create the extra rules using this new structure
            $extra_rules = $wp_rewrite->generate_rewrite_rules($perma_tmp, EP_PERMALINK);
    
            // Now we have to remove the rule that matches a category on it's own
            // this is reinstated later but just can't come before the extra rules
            $unset_key = array_search('index.php?category_name=$matches[1]', $extra_rules);
    
            if ($unset_key) {
                unset($extra_rules[$unset_key]);
            }
    
            // Check for the problem attachment rules and remove them.
            // Pray this doesn't break anything ;)
            foreach ($extra_rules as $regexp => $url) {
                if (strpos($url, 'attachment=$matches') && (strpos($regexp, 'attachment') === false)) {
                    unset($extra_rules[$regexp]);
                }
            }
    
            // merge to two rule sets into one
            $rules = array_merge($extra_rules, $rules);
        }
    
        // Check if the permalink structure has any date parts
        $has_date = false;
        foreach ( array( '%year%', '%monthnum%', '%day%' ) as $datepart ) {
            if ( strstr( $wp_rewrite->permalink_structure, $datepart ) ) {
                $has_date = true;
            }
        }
    
        // If there is a date part in the permalink structure filter by the subdomain we're on
        // This is incase we're actually looking at an date archive rather than a post
        if ( $has_date && $this->_plugin->getSubdomain()) {
            $rules = $this->_plugin->getSubdomain()->addRewriteFilter($rules);
        }
    
        return $rules;
    }
    
    function wps_page_rewrite_rules( $rules ) {
        global $wp_rewrite;
    
        if ( $this->_plugin->getSubdomain() && $this->_plugin->getSubdomain()->type == Wps_Plugin::TYPE_PAGE ) {
            $pagestr = $this->_plugin->getSubdomain()->slug;
    
            if ( $wp_rewrite->use_verbose_page_rules ) {
                $strToMatch = $pagestr;
            } else {
                $strToMatch = '.+?';
            }
    
            $temparray = array();
            if ( ($_SERVER['REQUEST_URI'] != '/') && (substr($_SERVER['REQUEST_URI'], 0, 2) != '/?') ) {
                $pagestr = $pagestr . '/$matches[1]';
                	
                foreach ( $rules as $key => $value ) {
                    if ( strpos( $key, $strToMatch ) !== false ) {
                        if ( strpos( $key, "($strToMatch/" ) == 0 && strpos( $key, "($strToMatch/" ) !== false ) {
                            if ( $wp_rewrite->use_verbose_page_rules ) {
                                $key = str_replace( "($strToMatch/", "(", $key );
                            }
                            $value = str_replace( "\$matches[1]", "$pagestr", $value );
                        } elseif ( strpos( $key, "$strToMatch/" ) == 0 && strpos( $key, "$strToMatch/" ) !== false ) {
                            if ( $wp_rewrite->use_verbose_page_rules ) {
                                $key = substr( $key, strlen( $strToMatch ) + 1 );
                            }
                        } elseif ( strpos( $key, "($strToMatch)/" ) == 0 && strpos( $key, "($strToMatch)/" ) !== false ) {
                            if ( $wp_rewrite->use_verbose_page_rules ) {
                                $key = str_replace( "($strToMatch)/", "", $key );
                            }
                            $value = str_replace( "\$matches[1]", "$pagestr", $value );
                        } elseif ( strpos( $key, "($strToMatch)" ) == 0 && strpos( $key, "($strToMatch)" ) !== false ) {
                            if ( $wp_rewrite->use_verbose_page_rules ) {
                                $key = str_replace( "($strToMatch)", "", $key );
                            }
                            $value = str_replace( "\$matches[1]", "$pagestr", $value );
                        }
                        $temparray[$key] = $value;
                    }
                }
            } else {
                foreach ( $rules as $key => $value ) {
                    if ( strpos( $key, $strToMatch ) !== false ) {
                        if ( strpos( $key, "($strToMatch/" ) == 0 && strpos( $key, "($strToMatch/" ) !== false ) {
                            $key = str_replace( "($strToMatch/", "(", $key );
                            $value = str_replace( "\$matches[1]", "$pagestr/\$matches[1]", $value );
                        } elseif ( strpos( $key, "$strToMatch/" ) == 0 && strpos( $key, "$strToMatch/" ) !== false ) {
                            $key = substr( $key, strlen( $strToMatch ) + 1 );
                        } elseif ( strpos( $key, "($strToMatch)/" ) == 0 && strpos( $key, "($strToMatch)/" ) !== false ) {
                            $key = str_replace( "($strToMatch)/", "", $key );
                            $value = str_replace( "\$matches[1]", "$pagestr", $value );
                        } elseif ( strpos( $key, "($strToMatch)" ) == 0 && strpos( $key, "($strToMatch)" ) !== false ) {
                            $key = str_replace( "($strToMatch)", "", $key );
                            $value = str_replace( "\$matches[1]", "$pagestr", $value );
                        }
                        $temparray[$key] = $value;
                    }
                }
            }
            $rules = $temparray;
        }
    
        return $rules;
    }
    
    
    function wps_tag_rewrite_rules( $rules ) {
        if ( $this->_plugin->getSubdomain() ) {
            $rules = $this->_plugin->getSubdomain()->addRewriteFilter($rules);
        }
    
        return $rules;
    }
    
    function wps_root_rewrite_rules( $rules ) {
        if ( $this->_plugin->getSubdomain() && $this->_plugin->getSubdomain()->type == Wps_Plugin::TYPE_CATEGORY ) {
            $rules = array();
        }
    
        return $rules;
    }
    
    function wps_rewrite_rules( $rules ) {
        return $rules;
    }
    
}