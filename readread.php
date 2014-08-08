#!/usr/bin/php -q
<?php

function getORP($strlen)
{
	$orp = array(0,0,1,1,1,1,2,2,2,2,3,3,3,3);
	
	if(($strlen) > 13) { return 4; }
	else { return $orp[$strlen];  }
}

function rhd($flt)
{
	return round($flt, 0, PHP_ROUND_HALF_DOWN);
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

$middle = rhd($col/2);
$erase = str_repeat(" ", $col-2);

// orp marker
ncurses_wcolor_set($screen,2);
ncurses_mvwaddstr($screen, ($row / 2) -1 , $middle, "+");


for($i=0; isset( $words[$i] ); $i++)
{
	if(strcmp(trim($words[$i]),"")===0) { continue; }

	$string = trim($words[$i]);

	$length = strlen($string);
	$shifting = getORP($length);

	$orp_char = $string[$shifting];

	// erase line
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, 1, $erase);

	// word
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, $middle - $shifting, $string);

	// orp
	ncurses_wcolor_set($screen,2);
	ncurses_mvwaddstr($screen, ($row / 2), $middle, $orp_char);

	ncurses_mvwaddstr($screen, $row-2 , $col-3, " ");
	
	ncurses_wrefresh($screen);

	usleep(250000);
}

ncurses_wgetch($screen);

ncurses_end();

?>
