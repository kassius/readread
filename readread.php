#!/usr/bin/php -q
<?php

function getORP($strlen)
{
	$orp = array(0,0,1,1,1,1,2,2,2,2,3,3,3,3);
	
	if(($strlen) > 13) { return 4; }
	else { return $orp[$strlen];  }
}
	
ncurses_init();

$screen = ncurses_newwin(0, 0, 0, 0);
ncurses_wborder($screen, 0,0, 0,0, 0,0, 0,0);

$file = file_get_contents("tea.txt");
$file = str_ireplace("\n"," ",$file);
$file = str_ireplace("\r","",$file);

$words = explode(" ",$file);

ncurses_getmaxyx($screen, $row, $col); // put inside the loop, later

if (ncurses_has_colors()) {
	ncurses_start_color();
	ncurses_init_pair(1, NCURSES_COLOR_WHITE, 0);
	ncurses_init_pair(2, NCURSES_COLOR_RED, 0);
	ncurses_wattron($screen, NCURSES_A_BOLD);
}

for($i=0; isset( $words[$i] ); $i++)
{
	if(strcmp(trim($words[$i]),"")===0) { continue; }

	$string = trim($words[$i]);

	$length = strlen($string);
	$shifting = getORP($length);
	$space_left = str_repeat(" ", ((($col-4) - $length ) / 2) - $shifting);
	$space_right = str_repeat(" ",((($col-4) - $length ) / 2) + $shifting);
	// $string = $space_left . $string . $space_right;
	$whole_string = $space_left . $string . $space_right;

	$orp_char = $string[$shifting];
	$left_piece = $space_left . substr($string, 0, $shifting-0);
	$right_piece = substr($string, $shifting+1, $length-($shifting+1)) . $space_right;

	$left_piece_len = strlen($left_piece);
	$right_piece_len = strlen($right_piece);
/*
 *  'I' shft = 0 , $orpchar = I, $lft, $right = "";
 *  'It' shft = 1, $orp = 't', $left = 'I', $rght = ""
 *  'Use' $shft = 1, $orp = 's', $left = 'U', $right = 'e';
 *
 *
 *
 *
 */

	ncurses_wcolor_set($screen,2);
	ncurses_mvwaddstr($screen, ($row / 2) - 1 , ($col / 2) - 1, "+");

	// left space + begin of the string, before orp
	$where_left = ($col / 2) - (strlen($whole_string) / 2); 
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, $where_left, $left_piece);

	// orp
	ncurses_wcolor_set($screen,2);
	ncurses_mvwaddstr($screen, ($row / 2), ($col / 2) - 1, $orp_char);

	// end of the string + right space, after orp
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, ($where_left + $left_piece_len + 1), $right_piece);

	ncurses_wrefresh($screen);

	usleep(250000);
}

ncurses_wgetch($screen);

ncurses_end();

?>
