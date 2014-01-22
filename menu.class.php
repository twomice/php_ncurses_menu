<?php

class menu {
  var $parent_menu;
  var $items;
  var $x;
  var $y;
  var $width;
  var $height;
  var $window;
  var $data;
  var $current_index;
  var $max_index;

  function __construct($data){
    $this->data = $data;
    foreach ($data as $item) {
      if (array_key_exists('menu', $item)) {
      }
      else {
        $this->items[] = new menu_item_leaf($item, $this);
        $label_length = strlen($item['label']);
      }
      $this->width = ($label_length > $this->width ? $label_length : $this->width);
    }
    $this->width += 4;
    $this->height = (count($this->items) + 2);
    $this->max_index = count($this->items) - 1;
    $this->current_index = 0;
    $this->window = ncurses_newwin($this->height, $this->width, 0, 0);
  }

  function render() {
    ncurses_wborder($this->window, 0,0, 0,0, 0,0, 0,0);
    $i = 0;
    foreach($this->items as $item){
      $item->render($i, ++$i);
    }
    ncurses_wrefresh($this->window); // otherwise we will not see
  }

  function close() {
  }
  
  function process_chr($chr) {
    switch ($chr) {
      case NCURSES_KEY_DOWN:
        $this->current_index++;
        if ($this->current_index > $this->max_index) {
          $this->current_index = $this->max_index;
        }
       break;
      case NCURSES_KEY_UP: 
        $this->current_index--;
        if ($this->current_index < 0) {
          $this->current_index = 0;
        }
       break;
      case NCURSES_KEY_LEFT:
        break;
      case NCURSES_KEY_RIGHT:
        break;
    }
    // Fixme: don't always need to render (if we're opening a submenu or command).
    $this->render();
  }

}

class menu_item {
  var $label;
  var $hotkey;
  var $has_focus;
  var $menu;

  function __construct($data, $menu) {
    $this->label = $data['label'];
    $this->hotkey = $data['hotkey'];
    $this->menu = $menu;
  }

  function render($index, $line) {
    $out = str_pad(' '.$this->label, $this->menu->width - 2);
    if($this->menu->current_index == $index){ 
      ncurses_wattron($this->menu->window,NCURSES_A_REVERSE);
      ncurses_mvwaddstr ($this->menu->window, $line, 1, $out);
      ncurses_wattroff($this->menu->window,NCURSES_A_REVERSE);
    }else{
      ncurses_mvwaddstr ($this->menu->window, $line, 1, $out);
    }
  }

  function build_label() {
  }
}

class menu_item_branch extends menu_item {
  var $child_menu_key;
 
}

class menu_item_leaf extends menu_item {
  var $command;
}

/*
$small = ncurses_newwin(10, 30, 7, 25);
ncurses_wborder($small,0,0, 0,0, 0,0, 0,0);
$menu = array("one","two","three","four");
for($a=0;$a<count($menu);$a++){
  $out = str_pad($menu[$a], 28);
  if($currently_selected == intval($a)){ 
    ncurses_wattron($small,NCURSES_A_REVERSE);
    ncurses_mvwaddstr ($small, 1+$a, 1, $out);
    ncurses_wattroff($small,NCURSES_A_REVERSE);
  }else{
    ncurses_mvwaddstr ($small, 1+$a, 1, $out);
  }
}
ncurses_wrefresh($small); // otherwise we will not see

*/
