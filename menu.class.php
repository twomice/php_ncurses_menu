<?php

define('MENU_Y_PADDING', 1);

class menu {
  var $parent_item;
  var $items;
  var $x;
  var $y;
  var $width;
  var $height;
  var $right_extent;
  var $window;
  var $data;
  var $current_index;
  var $max_index;

  function __construct($data, $parent_item = NULL){
    $this->data = $data;
    $index = 0;
    foreach ($data as $index => $item) {      
      $item['index'] = $index;
      if (array_key_exists('menu', $item)) {
        $item_object = new menu_item_branch($item, $this);
      }
      else {
        $item_object = new menu_item_leaf($item, $this);
      }
      $this->items[] = $item_object;
      $label_length = $item_object->label_length;
      $this->width = ($label_length > $this->width ? $label_length : $this->width);
    }
    $this->width += 4;
    $this->height = (count($this->items) + (MENU_Y_PADDING*2));
    $this->max_index = count($this->items) - 1;
    $this->current_index = 0;
    if ($parent_item) {
      $this->parent_item = $parent_item;
      $window_x = $this->parent_item->menu->right_extent;
    }
    else {
      $window_x = 0;
    }
    $this->right_extent = $window_x + $this->width;
    $this->window = ncurses_newwin($this->height, $this->width, 0, $window_x);
  }

  function render() {
    ncurses_wborder($this->window, 0,0, 0,0, 0,0, 0,0);
    foreach($this->items as $item){
      $item->render();
    }
    ncurses_wrefresh($this->window); // otherwise we will not see
  }

  function close() {
    // Can't find a way to actually remove the window,
    // so just remove the border and clear the contents.
    ncurses_wborder($this->window, 1,1, 1,1, 1,1, 1,1);
    ncurses_wclear($this->window);
    ncurses_wrefresh($this->window);
  }
  
  function process_chr($chr) {
    switch ($chr) {
      case NCURSES_KEY_DOWN:
        $this->current_index++;
        if ($this->current_index > $this->max_index) {
          $this->current_index = $this->max_index;
        }
        $this->render();
        break;
      case NCURSES_KEY_UP: 
        $this->current_index--;
        if ($this->current_index < 0) {
          $this->current_index = 0;
        }
        $this->render();
        break;
      case NCURSES_KEY_LEFT:
      case NCURSES_ESCAPE_KEY:
        if ($this->parent_item) {
          global $active_menu;
          $active_menu = $this->parent_item->menu;
          $this->close();
        }
        break;
      case NCURSES_KEY_RIGHT:
      case 13:
        $this->items[$this->current_index]->process_chr($chr);
        $this->render();
        break;
      default:
        $this->process_hotkey($chr);
        $this->render();
        break;
    }
  }

  function process_hotkey($chr) {
    $hotkey_items = $this->get_hotkey_items($chr);
    $hotkey_items_count = count($hotkey_items);
    if ($hotkey_items_count == 0) {
      return;
    }
    if ($hotkey_items_count == 1) {
      $this->current_index = $hotkey_items[0]->index;
      $this->items[$this->current_index]->process_chr($chr);
    }
    else {
      unset($next_hotkey_index);
      foreach ($hotkey_items as $item) {
        if ($item->index > $this->current_index) {
          $next_hotkey_index = $item->index;
          break;
        }
      }
      if (isset($next_hotkey_index)) {
        $this->current_index = $next_hotkey_index;
      } 
      else {
        $this->current_index = $hotkey_items[0]->index;
      }
    }
  }
  
  function get_hotkey_items($chr) {
    $hotkey_items = array();
    foreach ($this->items as $item) {
      if ($item->key_is_hotkey($chr)) {
        $hotkey_items[] = $item;
      }
    }
    return $hotkey_items;
  }
}

class menu_item {
  var $label;
  var $has_focus;
  var $menu;
  var $index;

  function __construct($data, $menu) {
    $this->index = $data['index'];
    $this->menu = $menu;
    $this->expand_label($data['label']);
  }

  function expand_label($label_string) {
    $underscore_pos = strpos($label_string, '_');
    if ($underscore_pos === FALSE 
      || $underscore_pos == (strlen($label_string) - 1)
    ) {
      // No underscore (or underscore is last character). 
      // Use first character.
      $this->label = array(
        'prefix' => '',
        'hotkey' => substr($label_string, 0, 1),
        'suffix' => substr($label_string, 1),        
      );
    }
    else {
      // Underscore embedded in the string. 
      // Use first character, after the underscore.
      $this->label = array(
        'prefix' => substr($label_string, 0, $underscore_pos),
        'hotkey' => substr($label_string, ($underscore_pos + 1), 1),
        'suffix' => substr($label_string, ($underscore_pos + 2)),        
      );
    }
    $plain_label = $this->label['prefix'] . $this->label['hotkey'] . $this->label['suffix'];
    $this->label_length = strlen($plain_label);
  }

  function render() {
    $line = $this->index +  MENU_Y_PADDING;
    if($this->menu->current_index == $this->index){ 
      ncurses_wattron($this->menu->window, NCURSES_A_REVERSE);
      $this->render_label($line);
      ncurses_wattroff($this->menu->window, NCURSES_A_REVERSE);
    }else{
      $this->render_label($line);
    }
  }

  function render_label($line) {
    $x = 1;
    $str = $this->label['prefix'];
    ncurses_mvwaddstr ($this->menu->window, $line, $x, $str);

    ncurses_wattron($this->menu->window, NCURSES_A_UNDERLINE);

    $x += strlen($str);
    $str = $this->label['hotkey'];
    ncurses_mvwaddstr ($this->menu->window, $line, $x, $str);

    ncurses_wattroff($this->menu->window, NCURSES_A_UNDERLINE);
 
    $x += strlen($str);
    $str = $this->label['suffix'];
    ncurses_mvwaddstr ($this->menu->window, $line, $x, $str);
  }

  function key_is_hotkey($chr) {
   return ($chr == ord(strtolower($this->label['hotkey'])));
  }

  function process_chr($chr) {
  }

  function build_label() {
  }
}

class menu_item_branch extends menu_item {
  var $child_menu_data;
  function __construct($data, $menu) {
    parent::__construct($data, $menu);
    $this->child_menu_data = $data['menu'];
    $this->label['suffix'] .= '  >';
  }

  function process_chr($chr) {
    if ($chr == NCURSES_KEY_RIGHT || $this->key_is_hotkey($chr)) {
      // instantiate new $menu
      $menu = new menu($this->child_menu_data, $this);
      // set $menu as global $active_menu
      global $active_menu;
      $active_menu = $menu;
      // render the menu
      $menu->render();
    }
  }
   
}

class menu_item_leaf extends menu_item {
  var $command;

  function __construct($data, $menu) {
    parent::__construct($data, $menu);
    $this->command = $data['command'];
  }

function process_chr($chr) {
    if ($chr == 13 || $this->key_is_hotkey($chr)) {
      menu_end($this->command);
   }
  }
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
