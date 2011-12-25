<?php

class Wps_Hooks_Actions extends Wps_Hooks_Abstract
{
    //--- Initial setup
    function wps_init ()
    {
        if (! is_admin()) {
            set_transient('rewrite_rules', "");
            update_option('rewrite_rules', "");
        }
    }

    //--- Check if we need to do any page redirection
    function wps_redirect ()
    {
        global $wp_query;
        
        // Check if Redirecting is turned on
        if (get_option(Wps_Plugin::OPTION_REDIRECTOLD) != "") {
            $redirect = false;
            
            if (! $this->_plugin->getSubdomain()) {
                // Check if it's a category
                if (is_category()) {
                    $catID = get_query_var('cat');
                    
                    if ($subdomain = $this->_plugin->getSubdomains()->getCategorySubdomain($catID)) {
                        $redirect = $this->_plugin->getSubdomains()->cats[$subdomain]->changeCategoryLink($catID, '');
                    }
                }
                
                // Check if it's a page
                if (is_page()) {
                    $pageId = the_ID();
                    //$pageId = the_post() $wp_query->post->ID;
                    
                    // Check if it's a subdomain page or a tied page
                    if ($subdomain = $this->_plugin->getSubdomains()->getPageSubdomain($pageId)) {
                        $redirect = $this->_plugin->getSubdomains()->pages[$subdomain]->changePageLink($pageId, '');
                    } else 
                        if ($catID = $this->_plugin->getSubdomains()->findTiedPage($pageId)) {
                            $redirect = $this->_plugin->getSubdomains()->cats[$catID]->changeCategoryLink($catID) .
                             $wp_query->query['pagename'];
                        }
                }
            
            }
            
            // If a redirect is found then do it
            if ($redirect) {
                header("HTTP/1.1 301 Moved Permanently");
                header("Location: " . $redirect);
                exit();
            }
        }
    }

    //--- Save Category settings
    function wps_edit_category ($cat_id)
    {
        // Check we have a category id
        if (! $cat_id) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . "category_subdomains";
        
        $data = array();
        $data['is_subdomain'] = ('true' == $_REQUEST['csd_include']) ? '1' : '0';
        $data['not_subdomain'] = ('true' == $_REQUEST['csd_exclude']) ? '1' : '0';
        $data['filter_pages'] = ('true' == $_REQUEST['csd_filterpages']) ? '1' : '0';
        $data['cat_link_title'] = addslashes(trim($_REQUEST['csd_link_title']));
        $data['cat_theme'] = addslashes($_REQUEST['csd_cat_theme']);
        if ($data['cat_theme'] == "(none)") {
            $data['cat_theme'] = "";
        }
        
        // Check if row exists for this cat_id. if it does then update, else insert
        if ($wpdb->get_var("SELECT cat_ID FROM {$table_name} WHERE cat_ID = '{$cat_id}'")) {
            $wpdb->update($table_name, $data, array('cat_ID' => $cat_id));
        } else {
            $data['cat_ID'] = $cat_id;
            $wpdb->insert($table_name, $data);
        }
    }

    function wps_action_edit_category ($category)
    {
        global $wpdb;
        
        $themes = get_themes();
        $cat_id = $category->term_id;
        
        $table_name = $wpdb->prefix . "category_subdomains";
        $csd_cat_options = $wpdb->get_row("SELECT * FROM {$table_name} WHERE cat_ID = {$cat_id};");
        $cat_theme = stripslashes($csd_cat_options->cat_theme);
        $checked_exclude = ('1' == $csd_cat_options->not_subdomain) ? ' checked="checked"' : '';
        $checked_include = ('1' == $csd_cat_options->is_subdomain) ? ' checked="checked"' : '';
        $checked_filterpages = ('1' == $csd_cat_options->filter_pages) ? ' checked="checked"' : '';
        $link_title = stripslashes($csd_cat_options->cat_link_title);
        ?>
<tr class="form-field">
	<th><h2>WP Subdomains</h2></th>
	<td>&nbsp;</td>
</tr>
<tr class="form-field">
	<th>Make Subdomain</th>
	<td><input style="width: auto;" type="checkbox" name="csd_include"
		value="true" <?php echo $checked_include; ?> /> <br /> <span
		class="description">Select this to turn the category into a Subdomain.
			<br />Category must be a main category.
	</span></td>
</tr>
<tr class="form-field">
	<th>Exclude from All</th>
	<td><input style="width: auto;" type="checkbox" name="csd_exclude"
		value="true" <?php echo $checked_exclude; ?> /> <br /> <span
		class="description">Select this to exclude the Category from being a
			subdomain when <b>Make all Subdomains</b> is selected in the plugin
			settings.
	</span></td>
</tr>
<tr class="form-field">
	<th>Select Category Theme</th>
	<td><select name="csd_cat_theme" id="wps_cat_theme" />
		<option value="">Use Default Blog Theme</option>
    <?php
        foreach ($themes as $theme) {
            if ($cat_theme == $theme['Template']) {
                print('<option selected="selected" value="' . $theme['Template'] . '">' . $theme['Name'] . '</option>');
            } else {
                print('<option value="' . $theme['Template'] . '">' . $theme['Name'] . '</option>');
            }
        }
        ?>
    </select> <br /> <span class="description">Pick your theme name and
			activate Subdomain Themes in <a
			href="/wp-admin/admin.php?page=wps_settings">Plugin Settings</a>.
	</span></td>
</tr>
<tr class="form-field">
	<th>Custom Link Title</th>
	<td><input type="text" name="csd_link_title"
		value="<?php	echo $link_title; ?>" /> <br /> <span class="description">Pick
			a custom title to appear in any links to this Subdomain.</span></td>
</tr>
<tr class="form-field">
	<th>Show only tied pages</th>
	<td><input style="width: auto;" type="checkbox" name="csd_filterpages"
		value="true" <?php echo $checked_filterpages; ?> /> <br /> <span
		class="description">Select this to filter out pages not tied to this
			category, page lists will only show pages tied to this category.</span>
	</td>
</tr>
<?php
    }

    function wps_action_parse_query ($query)
    {
        //--- If user wants root of subdomain to be an index
        if (get_option(Wps_Plugin::OPTION_SUBISINDEX) != '') {
            // Check if we're on the root of a subdomain.
            // If so then tell WP_Query it's index not archive
            if ($this->_plugin->getSubdomain() && $this->_plugin->getSubdomain()->archive &&
                ($_SERVER["REQUEST_URI"] == '/')) {
                $query->is_archive = false;
            }
        }
    }

    function wps_action_page_meta ($type, $place, $post)
    {
        add_meta_box('subdomainsdiv', __('WP Subdomains'), 'wps_page_meta_box', 'page', 'normal', 'high');
    }

    //--- One day this function will let you configure Pages using a pretty form.... one day
    function wps_page_meta_box ($post)
    {
        $page_meta_values = array(
            Wps_Plugin::METAKEY_THEME, 
            Wps_Plugin::METAKEY_SUBDOMAIN, 
            Wps_Plugin::METAKEY_TIE, 
            Wps_Plugin::METAKEY_SHOWALL, 
            Wps_Plugin::METAKEY_ONMAININDEX
        );
        
        // Get WPS relevant Meta Keys and Values for this page
        $page_meta = array();
        
        foreach (has_meta($post->ID) as $meta) {
            if (in_array($meta['meta_key'], $page_meta_values)) {
                $page_meta[$meta['meta_key']] = $meta['meta_value'];
            }
        }
        
        // Get all the themes
        $themes = get_themes();
        
        // Get the Category Subdomains
        $subdomain_cats = array();
        foreach ($this->_plugin->getSubdomains()->cats as $cat_id => $cat) {
            $subdomain_cats[$cat_id] = $cat->name;
        }
        
        ?>
<table>
	<tr>
		<td style="width: 60%"><label
			for="<?php echo Wps_Plugin::METAKEY_SUBDOMAIN; ?>"> Make the Page a
				subdomain? </label></td>
		<td><select style="width: 95%"
			name="<?php echo Wps_Plugin::METAKEY_SUBDOMAIN; ?>"
			id="<?php echo Wps_Plugin::METAKEY_SUBDOMAIN; ?>">
				<option value="0">No</option>
    <?php
        if ($page_meta[Wps_Plugin::METAKEY_SUBDOMAIN] == 1) {
            print '<option selected="selected" value="1">Yes</option>';
        } else {
            print '<option value="1">Yes</option>';
        }
        ?>
    </select></td>
	</tr>
	<tr>
		<td><label for="<?php echo Wps_Plugin::METAKEY_THEME; ?>"> Select Custom
				Theme </label></td>
		<td><select style="width: 95%"
			name="<?php echo Wps_Plugin::METAKEY_THEME; ?>"
			id="<?php echo Wps_Plugin::METAKEY_THEME; ?>">
				<option value="">-- None --</option>
    <?php
        foreach ($themes as $theme) {
            if ($page_meta[Wps_Plugin::METAKEY_THEME] == $theme['Template']) {
                print('<option selected="selected" value="' . $theme['Template'] . '">' . $theme['Name'] . '</option>');
            } else {
                print('<option value="' . $theme['Template'] . '">' . $theme['Name'] . '</option>');
            }
        }
        ?>
    </select></td>
	</tr>
	<tr>
		<td><label for="<?php echo Wps_Plugin::METAKEY_TIE; ?>"> Tie Page to
				Category Subdomain </label></td>
		<td><select style="width: 95%"
			name="<?php echo Wps_Plugin::METAKEY_TIE; ?>"
			id="<?php echo Wps_Plugin::METAKEY_TIE; ?>">
				<option value="">-- None --</option>
    <?php
        foreach ($subdomain_cats as $cat_id => $cat_name) {
            if ($page_meta[Wps_Plugin::METAKEY_TIE] == $cat_id) {
                print('<option selected="selected" value="' . $cat_id . '">' . $cat_name . '</option>');
            } else {
                print('<option value="' . $cat_id . '">' . $cat_name . '</option>');
            }
        }
        ?>
    </select></td>
	</tr>
	<tr>
		<td><label for="<?php echo Wps_Plugin::METAKEY_ONMAININDEX; ?>"> Still show on
				Blog index when tied </label></td>
		<td><select style="width: 95%"
			name="<?php echo Wps_Plugin::METAKEY_ONMAININDEX; ?>"
			id="<?php echo Wps_Plugin::METAKEY_ONMAININDEX; ?>">
				<option value="0">No</option>
    <?php
        if ($page_meta[Wps_Plugin::METAKEY_ONMAININDEX] == 1) {
            print '<option selected="selected" value="1">Yes</option>';
        } else {
            print '<option value="1">Yes</option>';
        }
        ?>
    </select></td>
	</tr>
	<tr>
		<td><label for="<?php echo Wps_Plugin::METAKEY_SHOWALL; ?>"> Show on
				Subdomains that show only tied pages. </label></td>
		<td><select style="width: 95%"
			name="<?php echo Wps_Plugin::METAKEY_SHOWALL; ?>"
			id="<?php echo Wps_Plugin::METAKEY_SHOWALL; ?>">
				<option value="0">No</option>
    <?php
        if ($page_meta[Wps_Plugin::METAKEY_SHOWALL] == 1) {
            print '<option selected="selected" value="1">Yes</option>';
        } else {
            print '<option value="1">Yes</option>';
        }
        ?>
    </select></td>
	</tr>
</table>
<input type="hidden" name="nonce-wpspage-edit"
	value="<?php echo wp_create_nonce('edit-wpspage-nonce') ?>" />
<?php
    }

    function wps_action_page_meta_save ($id)
    {
        $nonce = $_POST['nonce-wpspage-edit'];
        
        if (wp_verify_nonce($nonce, 'edit-wpspage-nonce')) {
            $theme = $_POST[Wps_Plugin::METAKEY_THEME];
            $subdomain = $_POST[Wps_Plugin::METAKEY_SUBDOMAIN];
            $tie = $_POST[Wps_Plugin::METAKEY_TIE];
            $showall = $_POST[Wps_Plugin::METAKEY_SHOWALL];
            $mainindex = $_POST[Wps_Plugin::METAKEY_ONMAININDEX];
            
            delete_post_meta($id, Wps_Plugin::METAKEY_THEME);
            delete_post_meta($id, Wps_Plugin::METAKEY_SUBDOMAIN);
            delete_post_meta($id, Wps_Plugin::METAKEY_TIE);
            delete_post_meta($id, Wps_Plugin::METAKEY_SHOWALL);
            delete_post_meta($id, Wps_Plugin::METAKEY_ONMAININDEX);
            
            if (isset($theme) && ! empty($theme)) {
                add_post_meta($id, Wps_Plugin::METAKEY_THEME, $theme);
            }
            if (isset($subdomain) && ! empty($subdomain)) {
                add_post_meta($id, Wps_Plugin::METAKEY_SUBDOMAIN, $subdomain);
            }
            if (isset($tie) && ! empty($tie)) {
                add_post_meta($id, Wps_Plugin::METAKEY_TIE, $tie);
            }
            if (isset($showall) && ! empty($showall)) {
                add_post_meta($id, Wps_Plugin::METAKEY_SHOWALL, $showall);
            }
            if (isset($mainindex) && ! empty($mainindex)) {
                add_post_meta($id, Wps_Plugin::METAKEY_ONMAININDEX, $mainindex);
            }
        }
    }

}