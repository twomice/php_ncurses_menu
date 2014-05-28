#!/usr/bin/php
<?php 
// Set to 1 to enable debug log via dump() function.
define('MENU_DEBUG', 0);
dump('======================= start');

start_pidfile();
kill_other_pids();

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
define("NCURSES_ESCAPE_KEY", 27);

// we begin by initializing ncurses
$ncurse = ncurses_init();

// turn off screen echo
ncurses_noecho();
// Hide the cursor.
ncurses_curs_set(0);

// let ncurses know we wish to use the whole screen
// $fullscreen = ncurses_newwin ( 0, 0, 0, 0);
// ncurses_border(0,0, 0,0, 0,0, 0,0); 
ncurses_refresh();// paint the window

// Echo screen info
// ncurses_getmaxyx(STDSCR, $y, $x);
// debug("height: $y; width: $x");

require_once 'menu.class.php';
$menu_config = get_menu_config();
$menu_data = get_menu_data($menu_config);

$menu_data = get_menu_from_config();
normalize_menu($menu_data);
$menu = new menu($menu_data);
$menu->render();

global $active_menu;
$active_menu = $menu;

while (1) {
// get key input
$pressed = ncurses_getch();// wait for a user keypres
$active_menu->process_chr($pressed);
}

function debug($line_string) {
  static $line = 20;
  
  ncurses_move($line,0);
  ncurses_clrtoeol();
  ncurses_mvaddstr($line, 0, $line_string);
  ncurses_refresh();// paint the window
}

function menu_end($cmd = NULL) {
  ncurses_end();// clean up our screen
  if ($cmd) {
    // Issue command with NOHUP so it doesn't die when we exit;
    // Pipe output to /dev/null so that PHP execution doesn't hang up
    // while waiting for the command to exit.
    exec('nohup '. $cmd . ' > /dev/null & ');
  }
  kill_pidfile();
  exit;
}

function get_menu_config() {
  $menu_config = array(
    'gedit',
    'x_term' => 'xterm',
    'more stuff' => array(
      'firefox',
      'xterm',
    ),
    'more stuff 2' => array(
      'firefox',
      'xterm',
    ),
  );
  return $menu_config;
}

function get_menu_data($menu_config) {
  $menu_data = array();
  foreach ($menu_config as $label => $item) {
    if (is_int($label)) {
      $label = $item;
    }
    $menu_item = array();
    $menu_item['label'] = menu_build_label($label);
    if (is_array($item)) {
      $menu_item['menu'] = get_menu_data($item);
    }
    else {
      $menu_item['command'] = $item;
    }
    $menu_data[] = $menu_item;
  }
  return $menu_data;
}

function menu_build_label($string) {
  return $string;
}

function dump($value, $label = NULL) {
  if (!MENU_DEBUG) {
    return;
  }
  $tmp = get_temp_dir();
  $fp = fopen($tmp . 'log.txt', 'a');
  if ($label) { 
    fwrite($fp, $label . "\n");
  }
  fwrite($fp, var_export($value, 1). "\n");
}

function normalize_menu(&$menu) {
  foreach ($menu as &$item) {
    if (is_array($item) && array_key_exists('menu', $item)) {
      normalize_menu($item['menu']);
    }
    else {
      $parts = str_getcsv($item, ' ');
      $item = array(
        'label' => $parts[0],
        'command' => ($parts[1] ? $parts[1] : $parts[0]),
      );
    }
  }
}

function get_menu_from_config() {
  $config_dir = dirname(__FILE__);
  $config_file = $config_dir . '/menu.conf';
  if (!file_exists($config_file)) {
    debug("Could not find menu configuration. Looking in $config_file");
    prompt_exit();
  }

  $config = file($config_file);
  
  // Define an array to hold the final menu tree.
  $menu_config = array();
  
  // Define an array to keep track of menus and lines we've alread seen.
  $menu_history = array();
  $history = array(
    'depth' => 0,
    'label' => 'root',
    'menu' => &$menu_config,
  );
  array_unshift($menu_history, $history);
  
  // Parse the lines.
  foreach ($config as $line) {
    // skip blank lines.
    if (preg_match('/^\s*$/', $line)) {
      continue;
    }
    // skip comment lines.
    if (preg_match('/^\s*\#/', $line)) {
      continue;
    }
    // count depth
    if (preg_match('/^ +/', $line, $matches)) { 
      $leading_spaces = $matches[0];
      $depth = strlen($leading_spaces);
    }
    else {
      $depth = 0;
    }
    // trim leading and trailing whitespace
    $line = trim($line);
  
    // If this line has a greater depth than the most recent menu,
    // create a new menu and add it to history.
    if ($menu_history[0]['depth'] < $depth) {
      // Unset this var to avoid by-reference problems in the loop.
      unset($new_menu);

      // Shorthand variable to reference the most recent menu.
      $my_menu = &$menu_history[0]['menu'];
  
      // The most recent menu's last item will become the menu
      // that contains this line and its siblings. We'll use that 
      // item's value as the label for this menu.
      $label = array_pop($my_menu);
  
      // Define a new menu containing only this one item.
      $new_menu = array($line);
  
      // Add this new menu to $my_menu (the most recent menu) as a menu array.
      $my_menu[] = array(
        'label' => $label,
        'menu' => &$new_menu,
      );

      // Add this new menu to history as the most recent menu.
      $history = array(
        'depth' => $depth,
        'label' => $label,
        'menu' => &$new_menu,
      );
      array_unshift($menu_history, $history);
   }
    else {
      // Unset this var to avoid by-reference problems in the loop.
      unset($my_menu);

      // Compare this line with each menu history, starting with
      // most recent; search until we find a menu with the same depth or 0,
      // and add this item to the found menu. 
      while (!isset($my_menu) && $history = array_shift($menu_history)) {
        if ($history['depth'] == $depth || $history['depth'] == 0) {
          $my_menu =& $history['menu'];
          array_unshift($menu_history, $history);
        }
      }
      $my_menu[] = $line;
    } 
  }
  return $menu_config;
}

function prompt_exit() {
  while ($pressed != NCURSES_ESCAPE_KEY){ // until the user hits ESC.
    // get key input
    $pressed = ncurses_getch();// wait for a user keypres
  }
  menu_end(); 
} 
  
function name_pidfile($pid) {
  return $tmp . 'php_ncurses_menu.pid.'. $pid;
}

function name_my_pidfile() {
  $mypid = getmypid();
  $pidfile = name_pidfile($mypid);
  return $pidfile;
}

function start_pidfile() {
  $pidfile = name_my_pidfile();
  $fp = fopen($pidfile, 'w');
  fwrite($fp, $mypid);
  fclose($fp);
}

function kill_pidfile() {
  $pidfile = name_my_pidfile();
  unlink($pidfile);
}

function kill_other_pids() {
  $pidfile = name_pidfile('*');
  $pidfiles = glob($pidfile);
  $mypidfile = name_my_pidfile();
  foreach ($pidfiles as $file) {
    if ($file != $mypidfile) {
      unlink($file);
      dump ("unlinking $file");
      $filename_parts = array_reverse(explode('.', $file));
      $pid = $filename_parts[0];
      dump("killing pid $pid");
      exec("kill $pid");
    }
  }
}

function get_temp_dir() {
  static $tmp;
  if (!isset($tmp)) {
    $tmp = rtrim(sys_get_temp_dir(), '/'). '/';
  }
  return $tmp;
}