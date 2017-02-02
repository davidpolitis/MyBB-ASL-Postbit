<?php
/**
 * ASL Postbit
 * Copyright 2017 David Politis, All Rights Reserved
 *
 * Website: https://github.com/davidpolitis/
 * License: https://opensource.org/licenses/MIT
 *
 * Flag icons: http://www.famfamfam.com/lab/icons/flags/
 *
 */

// Make sure we can't access this file directly from the browser.
if(!defined('IN_MYBB'))
{
    die('This file cannot be accessed directly.');
}

if(defined('IN_ADMINCP'))
{
    // Add our asl_settings() function to the setting management module to load language strings.
    $plugins->add_hook('admin_config_settings_manage', 'asl_settings');
    $plugins->add_hook('admin_config_settings_change', 'asl_settings');
    $plugins->add_hook('admin_config_settings_start', 'asl_settings');
    // We could hook at 'admin_config_settings_begin' only for simplicity sake.
}
else
{
    // Add our asl_postbit() function to the postbit hook so it gets executed on every post
    $plugins->add_hook("postbit", "asl_postbit");
}

function asl_info()
{
    global $lang;
    $lang->load('asl');

    /**
     * Array of information about the plugin.
     * name: The name of the plugin
     * description: Description of what the plugin does
     * website: The website the plugin is maintained at (Optional)
     * author: The name of the author of the plugin
     * authorsite: The URL to the website of the author (Optional)
     * version: The version number of the plugin
     * compatibility: A CSV list of MyBB versions supported. Ex, '121,123', '12*'. Wildcards supported.
     * codename: An unique code name to be used by updated from the official MyBB Mods community.
     */
    return array(
        'name'			=> 'ASL Postbit',
        'description'	=> $lang->asl_desc,
        'website'		=> 'https://github.com/davidpolitis/',
        'author'		=> 'David Politis',
        'authorsite'	=> 'https://github.com/davidpolitis/',
        'version'		=> '1.0',
        'compatibility'	=> '18*',
        'codename'		=> 'asl'
    );
}

/*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    'visible' by adding templates/template changes, language changes etc.
*/
function asl_activate()
{
    // Include this file because it is where find_replace_templatesets is defined
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    // Modify the postbit
    find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'usertitle\']}<br />').'#', "{\$post['asl']}\n\t\t\t\t{\$post['usertitle']}<br />");
    find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'useravatar\']}').'#', "{\$post['useravatar']}\n\t{\$post['asl']}");
}

/*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially 'hide' the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
*/
function asl_deactivate()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    // Remove edits from postbit
    find_replace_templatesets('postbit', "#\n\t\t\t\t".preg_quote('{$post[\'asl\']}').'#', '', 0);
    find_replace_templatesets('postbit_classic', "#\n\t".preg_quote('{$post[\'asl\']}').'#', '', 0);
}

/*
 * _install():
 *   Called whenever a plugin is installed by clicking the 'Install' button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
*/
function asl_install()
{
    global $db, $cache, $lang;
    $lang->load('asl');

    // Settings group array details
    $group = array(
        'name' => 'asl',
        'title' => $db->escape_string($lang->setting_group_asl),
        'description' => $db->escape_string($lang->setting_group_asl_desc),
        'isdefault' => 0
    );

    // Check if the group already exists.
    $query = $db->simple_select('settinggroups', 'gid', "name='" + $group['name'] + "'");

    if($gid = (int)$db->fetch_field($query, 'gid'))
    {
        // We already have a group. Update title and description.
        $db->update_query('settinggroups', $group, "gid='{$gid}'");
    }
    else
    {
        // We don't have a group. Create one with proper disporder.
        $query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
        $disporder = (int)$db->fetch_field($query, 'disporder');

        $group['disporder'] = ++$disporder;

        $gid = (int)$db->insert_query('settinggroups', $group);
    }

    // Deprecate all the old entries.
    $db->update_query('settings', array('description' => 'ASLDELETEMARKER'), "gid='{$gid}'");

    // add settings
    $settings = array(
        'displayage'	=> array(
            'optionscode'	=> 'yesno',
            'value'			=> 1
        ),
        'displaysex'	=> array(
            'optionscode'	=> 'yesno',
            'value'			=> 1
        ),
        'displaycountry'	=> array(
            'optionscode'	=> 'yesno',
            'value'			=> 1
        )
    );

    $disporder = 0;

    // Create and/or update settings.
    foreach($settings as $key => $setting)
    {
        // Prefix all keys with group name.
        $key = "asl_{$key}";

        $lang_var_title = "setting_{$key}";
        $lang_var_description = "setting_{$key}_desc";

        $setting['title'] = $lang->{$lang_var_title};
        $setting['description'] = $lang->{$lang_var_description};

        // Filter valid entries.
        $setting = array_intersect_key($setting,
            array(
                'title' => 0,
                'description' => 0,
                'optionscode' => 0,
                'value' => 0,
            ));

        // Escape input values.
        $setting = array_map(array($db, 'escape_string'), $setting);

        // Add missing default values.
        ++$disporder;

        $setting = array_merge(
            array('description' => '',
                'optionscode' => 'yesno',
                'value' => 0,
                'disporder' => $disporder),
            $setting);

        $setting['name'] = $db->escape_string($key);
        $setting['gid'] = $gid;

        // Check if the setting already exists.
        $query = $db->simple_select('settings', 'sid', "gid='{$gid}' AND name='{$setting['name']}'");

        if($sid = $db->fetch_field($query, 'sid'))
        {
            // It exists, update it, but keep value intact.
            unset($setting['value']);
            $db->update_query('settings', $setting, "sid='{$sid}'");
        }
        else
        {
            // It doesn't exist, create it.
            $db->insert_query('settings', $setting);
        }
    }

    // Delete deprecated entries.
    $db->delete_query('settings', "gid='{$gid}' AND description='ASLDELETEMARKER'");

    // Create country profilefield if it doesn't exist already
    if(!$db->fetch_field($db->simple_select("profilefields", "fid", "name='".$lang->asl_country_select_name."'", array('limit' => 1)), "fid"))
    {
        // Insert country profile field composed of all gifs in flags directory
        $countries = "select\n".$lang->asl_undisclosed."\n";
        $flags_dir = MYBB_ROOT."images/flags/";
        $flag_files = scandir($flags_dir);
        $last = count($flag_files);
        $counter = 1;

        foreach($flag_files as $flag_file)
        {
            if(is_file($flags_dir.$flag_file))
            {
                $last_dot = strrpos($flag_file, '.');
                $extension = substr($flag_file, $last_dot + 1);
                if($extension == 'gif')
                {
                    $country_name = substr($flag_file, 0, $last_dot);

                    $countries .= $country_name.($counter < $last ? "\n" : "");
                }
            }
            ++$counter;
        }

        $countryfield = array(
            "name" => $lang->asl_country_select_name,
            "description" => $lang->asl_country_select_desc,
            "disporder" => "0",
            "type" => $countries,
            "length" => "0",
            "maxlength" => "0",
            "profile" => "1",
            "viewableby" => "-1",
            "editableby" => "-1",
        );

        $fid = $db->insert_query('profilefields', $countryfield);
        $db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields ADD fid{$fid} TEXT");

        // This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
        rebuild_settings();

        $cache->update_profilefields();
    }
}

/*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
*/
function asl_is_installed()
{
    global $db;

    return $db->fetch_field($db->simple_select("settinggroups", "gid", "name='asl'", array("limit" => 1)), "gid");
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function asl_uninstall()
{
    global $db, $mybb, $cache, $lang;

    // Load our language file
    $lang->load('asl');

    if($mybb->request_method != 'post')
    {
        global $page;

        $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=asl', $lang->asl_uninstall_message, $lang->asl_uninstall);
    }

    // Delete settings group
    $db->delete_query('settinggroups', "name='asl'");

    // Remove the settings
    $db->delete_query('settings', "name IN ('asl_displayage', 'asl_displaysex', 'asl_displaycountry')");

    // This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
    rebuild_settings();

    // Remove country profilefield and all data stored in userfields if desired
    if(!isset($mybb->input['no']))
    {
        $fid = $db->fetch_field($db->simple_select("profilefields", "fid", "name='".$lang->asl_country_select_name."'", array("limit" => 1)), "fid");

        if ($db->field_exists($fid, "userfields"))
            $db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields DROP $fid");

        $db->delete_query("profilefields", "fid=".$fid);

        $cache->update_profilefields();
    }
}

/*
 * Loads the settings language strings.
*/
function asl_settings()
{
    global $lang;

    // Load our language file
    $lang->load('asl');
}

/*
 * Displays the ASL in the postbit.
 * @param $post Array containing information about the current post. Note: must be received by reference otherwise our changes are not preserved.
*/
function asl_postbit(&$post)
{
    global $db, $lang, $settings;

    // Load our admin language file (so we don't have to redeclare the field name in a non-admin language file)
    $lang->load('admin/asl');

    $out = array();

    // Age
    if($settings['asl_displayage'] == 1)
    {
        $bdayvals = $db->fetch_array($db->simple_select("users", "birthdayprivacy, birthday", "uid=".$post['uid']));
        if ($bdayvals['birthdayprivacy'] != 'none' && $age = get_age($bdayvals['birthday']))
            array_push($out, $age);
    }

    // Sex
    if($settings['asl_displaysex'] == 1)
    {
        if (array_key_exists('fid3', $post) && $post['fid3'] != '' && $post['fid3'] != $lang->asl_undisclosed)
        {
            $sexgif = htmlspecialchars_uni(rawurlencode(strtolower($post['fid3'])));
            array_push($out, '<img src="images/sex/'.$sexgif.'.gif" alt="'.$post['fid3'].'" title="'.htmlspecialchars_uni($post['fid3']).'" border="0" />');
        }
    }

    // Flag
    if($settings['asl_displaycountry'] == 1)
    {
        $fid = "fid".$db->fetch_field($db->simple_select("profilefields", "fid", "name='".$lang->asl_country_select_name."'", array("limit" => 1)), "fid");
        if (array_key_exists($fid, $post) && $post[$fid] != '' && $post[$fid] != $lang->asl_undisclosed)
        {
            $flaggif = htmlspecialchars_uni(rawurlencode($post[$fid]));
            array_push($out, '<img src="images/flags/'.$flaggif.'.gif" alt="'.$post[$fid].'" title="'.htmlspecialchars_uni($post[$fid]).'" border="0" />');
        }
    }

    switch(count($out))
    {
        case 1:
            $post['asl'] = $out[0]."<br />";
            break;
        case 2:
            $post['asl'] = $out[0]." / ".$out[1]."<br />";
            break;
        case 3:
            $post['asl'] = $out[0]." / ".$out[1]." / ".$out[2]."<br />";
    }
}
