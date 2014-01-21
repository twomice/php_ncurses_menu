#!/usr/bin/php
<?php 

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);


$debug_start_y = 10;

// we begin by initializing ncurses
$ncurse = ncurses_init();

// turn off screen echo
ncurses_noecho();
// Hide the cursor.
ncurses_curs_set(0);

// let ncurses know we wish to use the whole screen
$fullscreen = ncurses_newwin ( 0, 0, 0, 0);
ncurses_border(0,0, 0,0, 0,0, 0,0); 
ncurses_refresh();// paint the window

// Echo screen info
ncurses_getmaxyx(STDSCR, $y, $x);
debug("height: $y; width: $x");

while ($pressed != 27){ // until the user hits ESC.
  // get key input
  $pressed = ncurses_getch();// wait for a user keypress
  debug("key code: $pressed");
}


function debug($line_string) {
  static $line = 10;
  
  ncurses_move($line,0);
  ncurses_clrtoeol();
  ncurses_mvaddstr($line, 0, $line_string);
  ncurses_refresh();// paint the window
}


ncurses_end();// clean up our screen
