#!/usr/bin/php
<?php 

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
$menu_data = get_menu_data();
$menu = new menu($menu_data);
$menu->render();

global $active_menu;
$active_menu = $menu;


while ($pressed != NCURSES_ESCAPE_KEY){ // until the user hits ESC.
  // get key input
  $pressed = ncurses_getch();// wait for a user keypress
  $char = chr($pressed);
  $ord = ord($char);
  if ($pressed == NCURSES_KEY_DOWN) {
    $keydown = " -- is keydown.";
  }
  else {
    $keydown = '';
  }
  $active_menu->process_chr($pressed);
//  debug("key code: $pressed ($char / $ord) $keydown");
}


function debug($line_string) {
  static $line = 20;
  
  ncurses_move($line,0);
  ncurses_clrtoeol();
  ncurses_mvaddstr($line, 0, $line_string);
  ncurses_refresh();// paint the window
}


ncurses_end();// clean up our screen

function get_menu_data() {
  return array(
    'a' => array(
      'label' => 'a',
      'hotkey' => 'a',
      'command' => 'gedit',
    ),
    'b' => array(
      'label' => 'b',
      'hotkey' => 'b',
      'command' => 'roxterm',
    ),
  );
}
