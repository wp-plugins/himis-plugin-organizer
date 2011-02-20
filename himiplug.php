<?php
/**
 * Plugin Name: himi's Plugin Organizer
 * Plugin URI:  http://geheimniswelten.de/himiPlug
 * Description: details link and plugin filter for admin>plugins
 *              details only works with plugins that are available on http://wordpress.org/extend/plugins/
 * Author:      himitsu
 * Version:     1.1
 * Author URI:  http://geheimniswelten.de
 *
 * Saved Data:  database > {wp_}options >
 *                 himiPlugin_options
 *                 himiPluginFilter_groups
 *                 himiPluginFilter_selected
 *                 himiPluginFilter_plugins
 *                 himiPluginComment_comments
**/

function himi_Plugin_extract_slug($plugin_file) {
  $slug = $plugin_file;
  while (($s = dirname($slug)) && ($s != '.')) $slug = $s;
  return $slug;
}

function himi_Plugin_admin_init() {
  $options = get_option('himiPlugin_options');
  if ($options === false) {
    $options = array(
      'detail_enabled' => true,
      'filter_enabled' => true,
      'comment_enabled' => true,
      'comment_new_line' => true,
      'comment_text_width' => 50);
    add_option('himiPlugin_options', $options, null, false);
  }

  if ($options['detail_enabled']) {
    add_filter('plugin_action_links', 'himi_PluginDetail_plugin_action_links', 10, 4);
  }
  if ($options['filter_enabled']) {
    add_action('pre_current_active_plugins', 'himi_PluginFilter_pre_current_active_plugins');
    add_filter('all_plugins', 'himi_PluginFilter_all_plugins', 10, 1);
    add_filter('plugin_action_links', 'himi_PluginFilter_plugin_action_links', 10, 4);
  }
  if ($options['comment_enabled']) {
    add_filter('all_plugins', 'himi_PluginComment_all_plugins', 9, 1);
    add_filter('plugin_action_links', 'himi_PluginComment_plugin_action_links', 10, 4);
  }
}

if (!function_exists('add_action')) {
  header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}
add_action('admin_init', 'himi_Plugin_admin_init');
add_action('admin_menu', 'himi_PluginOptions_admin_menu');

/************************
***** plugin detail *****
************************/

function himi_PluginDetail_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
  $slug = himi_Plugin_extract_slug($plugin_file);
  $actions['himi_details'] = '<a href="plugin-install.php?tab=plugin-information&amp;plugin=' . esc_attr($slug)
    . '&amp;TB_iframe=true&amp;width=600&amp;height=550' . '" class="thickbox" title="'
    . esc_attr(sprintf(__('More information about %s'), $plugin_data['Name'])) . '">' . __('Details') . '</a>';
  return $actions;
}

/************************
***** plugin filter *****
************************/

function himi_PluginFilter_input($all_plugins) {
  $all = '(' . __('View All') . ')';
  $none = '(' . __('Untitled') . ')';
  if (get_option('himiPluginFilter_groups') === false) {
    add_option('himiPluginFilter_groups', array(), null, false);
    add_option('himiPluginFilter_selected', $all, null, false);
    add_option('himiPluginFilter_plugins', array(), null, false);
  }
  $groups = get_option('himiPluginFilter_groups');
  $selected = get_option('himiPluginFilter_selected');
  $plugins = get_option('himiPluginFilter_plugins');

  $groups_new = $groups;
  if (!is_array($groups_new)) $groups_new = array();
  if (isset($_POST['himiPluginFilter_delete']) && current_user_can('activate_plugins')) {
    $group = trim(strval($_POST['himiPluginFilter_group']));
    if (($key = array_search($group, $groups_new)) !== false) 
      unset($groups_new[$key]);
  } elseif (isset($_POST['himiPluginFilter_add']) && current_user_can('activate_plugins')) {
    $group = trim(strval($_POST['himiPluginFilter_new_group']));
    if (!in_array($group, $groups_new))
      $groups_new[] = $group;
  }
  if (($key = array_search($all, $groups_new)) !== false) unset($groups_new[$key]);
  if (($key = array_search($none, $groups_new)) !== false) unset($groups_new[$key]);
  if ((serialize($groups_new) != serialize($groups))
      && update_option('himiPluginFilter_groups', $groups_new))
    $groups = $groups_new;

  $selected_new = $selected;
  $groups_s = $groups;
  array_push($groups_s, $all, $none);
  if (isset($_POST['himiPluginFilter_select'])) {
    $group = trim(strval($_POST['himiPluginFilter_group']));
    if (in_array($group, $groups_s))
      $selected_new = $group;
  }
  if (!in_array($selected_new, $groups_s))
    $selected_new = $all;
  if ((serialize($selected_new) != serialize($selected))
      && update_option('himiPluginFilter_selected', $selected_new))
    $selected = $selected_new;

  $plugins_new = $plugins;
  if (!is_array($plugins_new)) $plugins_new = array();
  foreach ($plugins_new as $plugin) {
    $not_exists = true;
    foreach ($all_plugins as $plugin_file => $plugin_data)
      if ($plugin == himi_Plugin_extract_slug($plugin_file))
        $not_exists = false;
    if ($not_exists)
      unset($plugins_new[$plugin]);
  }
  if (isset($_POST['himiPluginFilter_change']) && current_user_can('activate_plugins')) {
    $group = trim(strval($_POST['himiPluginFilter_group']));
    $plugin = trim(strval($_POST['himiPluginFilter_name']));
    $exists = false;
    foreach ($all_plugins as $plugin_file => $plugin_data)
      if ($plugin == himi_Plugin_extract_slug($plugin_file))
        $exists = true;
    if ($exists)
      $plugins_new[$plugin] = $group;
  }
  if ((serialize($plugins_new) != serialize($plugins))
      && update_option('himiPluginFilter_plugins', $plugins_new))
    $plugins = $plugins_new;
}

function himi_PluginFilter_pre_current_active_plugins($all_plugins) {
  $all = '(' . __('View All') . ')';
  $none = '(' . __('Untitled') . ')';
  himi_PluginFilter_input($all_plugins);
  $groups = get_option('himiPluginFilter_groups');
  $selected = get_option('himiPluginFilter_selected');
  $plugins = get_option('himiPluginFilter_plugins');
  array_unshift($groups, $all);
  array_push($groups, $none);
  echo '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" style="display:inline">';
  echo __('Filters') . ': <select name="himiPluginFilter_group">';
  $f = '%0' . strlen((string)count($all_plugins)) . 'd';
  foreach ($groups as $group) {
    $g = esc_attr($group);
    $s = ($group == $selected) ? ' selected="selected"' : '';
    if ($selected == $all) {
      if ($group == $all) {
        $i = count($all_plugins);
      } elseif ($group == $none) {
        $i = 0;
        foreach ($all_plugins as $plugin_file => $plugin_data) {
          $slug = himi_Plugin_extract_slug($plugin_file);
          if (!isset($plugins[$slug]) || !in_array($plugins[$slug], $groups))
            $i++;
        }
      } else {
        $i = 0;
        foreach ($all_plugins as $plugin_file => $plugin_data) {
          $slug = himi_Plugin_extract_slug($plugin_file);
          if (isset($plugins[$slug]) && ($plugins[$slug] == $group))
            $i++;
        }
      }
      $c = '[' . sprintf($f, $i) . '] ';
    }
    echo '<option value="' . $g . '"' . $s . '>' . $c . $g . '</option>';
  }
  echo '</select>';
  echo '<input type="submit" class="button" name="himiPluginFilter_select" value="' . __('Select') . '" />';
  echo '<input type="submit" class="button" name="himiPluginFilter_delete" value="' . __('Remove') . '" /> ';
  echo '<input type="text" name="himiPluginFilter_new_group" value="" size="8" maxlength="15" />';
  echo '<input type="submit" class="button" name="himiPluginFilter_add" value="' . _x('Add New', 'post') . '" />';
  echo '</form>';
}

function himi_PluginFilter_all_plugins($all_plugins) {
  $all = '(' . __('View All') . ')';
  $none = '(' . __('Untitled') . ')';
  himi_PluginFilter_input($all_plugins);
  $selected = get_option('himiPluginFilter_selected');
  $plugins = get_option('himiPluginFilter_plugins');
  if ($selected == $none) {
    foreach ($all_plugins as $plugin_file => $plugin_data) {
      $slug = himi_Plugin_extract_slug($plugin_file);
      if (isset($plugins[$slug]))
        unset($all_plugins[$plugin_file]);
    }
  } elseif ($selected != $all)
    foreach ($all_plugins as $plugin_file => $plugin_data) {
      $slug = himi_Plugin_extract_slug($plugin_file);
      if (!isset($plugins[$slug]) || ($plugins[$slug] != $selected))
        unset($all_plugins[$plugin_file]);
    }
  return $all_plugins;
}

function himi_PluginFilter_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
  $none = '(' . __('Untitled') . ')';
  $groups = get_option('himiPluginFilter_groups');
  $plugins = get_option('himiPluginFilter_plugins');
  array_push($groups, $none);
  $slug = himi_Plugin_extract_slug($plugin_file);
  $selected = isset($plugins[$slug]) ? $plugins[$slug] : $none;
  $filter = '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" style="display:inline">';
  $filter .= __('Filters') . ': <select name="himiPluginFilter_group">';
  foreach ($groups as $group) {
    $g = esc_attr($group);
    $s = ($group == $selected) ? ' selected="selected"' : '';
    $filter .= '<option value="' . $g . '"' . $s . '>' . $g . '</option>';
  }
  $filter .= '</select>';
  $filter .= '<input type="hidden" name="himiPluginFilter_name" value="' . esc_attr($slug) . '" />';
  $filter .= '<input type="submit" class="button" name="himiPluginFilter_change" value="' . __('Apply') . '" />';
  $filter .= '</form>';
  $actions['himi_filter'] = $filter;
  return $actions;
}

/*************************
***** plugin comment *****
*************************/

function himi_PluginComment_all_plugins($all_plugins) {
  $all_comments = get_option('himiPluginComment_comments');

  $all_comments_new = $all_comments;
  if (!is_array($all_comments_new)) $all_comments_new = array();
  if (isset($_POST['himiPluginComment_save']) && current_user_can('activate_plugins')) {
    $text = trim(strval($_POST['himiPluginComment_text']));
    $slug = trim(strval($_POST['himiPluginComment_name']));
    if ($text) {
      $all_comments_new[$slug] = $text;
    } else
      unset($all_comments_new[$slug]);
  }
  foreach ($all_comments_new as $name => $text) {
    $not_exists = true;
    foreach ($all_plugins as $plugin_file => $plugin_data) {
      $slug = himi_Plugin_extract_slug($plugin_file);
      if ($name == $slug)
        $not_exists = false;
    }
    if ($not_exists)
      unset($all_comments_new[$name]);
  }
  if ((serialize($all_comments_new) != serialize($all_comments))
      && update_option('himiPluginComment_comments', $all_comments_new))
    $all_comments = $all_comments_new;

  return $all_plugins;
}

function himi_PluginComment_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
  $options = get_option('himiPlugin_options');
  $all_comments = get_option('himiPluginComment_comments');
  $slug = himi_Plugin_extract_slug($plugin_file);

  $comment = ($options['comment_new_line'] ? '<br />' : '');
  $comment .= '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" style="display:inline">';
  $comment .= __('Comment: ') . '<input type="text" name="himiPluginComment_text" value="'
    . (isset($all_comments[$slug]) ? esc_attr($all_comments[$slug]) : '') . '" size="' 
    . $options['comment_text_width'] . '" maxlength="250" />';
  $comment .= '<input type="hidden" name="himiPluginComment_name" value="' . esc_attr($slug) . '" />';
  $comment .= '<input type="submit" class="button" name="himiPluginComment_save" value="' . __('Apply') . '" />';
  $comment .= '</form>';
  $actions['himi_comment'] = $comment;
  return $actions;
}

/******************
***** options *****
******************/

function himi_PluginOptions_admin_menu() {
  add_options_page('himi\'s Plugin Organizer - Options', 'Plugin Organizer',
    10, 'himi_plugin_options', 'himi_PluginOptions_add_options_page');
}

function himi_PluginOptions_add_options_page() {
  $options = get_option('himiPlugin_options');
  if (isset($_POST['himiPluginOptions_save']) && current_user_can('manage_options')) {
    $options['detail_enabled'] = (bool)$_POST['himiPluginDetail_enabled'];
    $options['filter_enabled'] = (bool)$_POST['himiPluginFilter_enabled'];
    $options['comment_enabled'] = (bool)$_POST['himiPluginComment_enabled'];
    $options['comment_new_line'] = (bool)$_POST['himiPluginComment_new_line'];
    $i = intval($_POST['himiPluginComment_text_width']);
    $options['comment_text_width'] = (($i == 0) ? 50 : min(max($i, 10), 99));
    update_option('himiPlugin_options', $options);
  }
  ?>
    <h2>himi's Plugin Organizer - Options</h2><br />
    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">

      <h3><u>Plugin &gt; Details-Link:</u></h3>
      <input type="checkbox" name="himiPluginDetail_enabled" value="yes" <?php
        if ($options['detail_enabled']) echo ' checked="checked"'; ?> /> enabled<br /><br />
      <br />

      <h3><u>Plugin &gt; Filter:</u></h3>
      <input type="checkbox" name="himiPluginFilter_enabled" value="yes" <?php
        if ($options['filter_enabled']) echo ' checked="checked"'; ?> /> enabled<br /><br />
      <br />

      <h3><u>Plugin &gt; Comment:</u></h3>
      <input type="checkbox" name="himiPluginComment_enabled" value="yes" <?php
        if ($options['comment_enabled']) echo ' checked="checked"'; ?> /> enabled<br /><br /><br />
      <input type="checkbox" name="himiPluginComment_new_line" value="yes" <?php
        if ($options['comment_new_line']) echo ' checked="checked"'; ?> /> comments on new line<br /><br />
      width of the input field: <input type="text" name="himiPluginComment_text_width" value="<?php
        echo $options['comment_text_width']; ?>" size="2" maxlength="2"><br /><br />
      <br />

      <br />
      <input type="submit" class="button-primary" name="himiPluginOptions_save" value="<?php echo __('Apply'); ?>" /><br />
      <br />
    </form>
  <?php
  if (isset($_GET['view_data'])) {
    $filter_groups = get_option('himiPluginFilter_groups');
    $filter_selected = get_option('himiPluginFilter_selected');
    $filter_plugins = get_option('himiPluginFilter_plugins');
    $comment_comments = get_option('himiPluginComment_comments');

    $filter_plugins_sort = array();
    foreach ($filter_plugins as $plugin => $group)
      $filter_plugins_sort[$group][] = $plugin;
    ksort($filter_plugins_sort);
    foreach ($filter_plugins_sort as $group => $plugins)
      asort($filter_plugins_sort[$group]);

    ?>
      <h3><u>Plugin &gt; internal Data:</u></h3>

      <b>Filter - Groups</b><br />
      <pre><?php print_r($filter_groups); ?></pre><br />
      <b>Filter - Current Selected</b><br />
      <pre><?php print_r($filter_selected); ?></pre><br />
      <b>Filter - Plugins</b><br />
      <table>
        <tr align="left" valign="top">
          <td><pre><?php print_r($filter_plugins); ?></pre></td>
         <td><pre><?php print_r($filter_plugins_sort); ?></pre></td>
        </tr>
      </table>
      <br />

      <b>Comment - Comments</b><br />
      <pre><?php print_r($comment_comments); ?></pre><br />
      <br />
    <?php
  } else { 
    ?>
      <a class="button" href="<?php echo $_SERVER['REQUEST_URI'] . ($_SERVER['QUERY_STRING'] ? '&amp;' : '?'); ?>view_data=1">Show Data</a>
      <br />
    <?php
  }
}

?>