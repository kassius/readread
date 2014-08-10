#!/usr/bin/php -q
<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

$keyboard = fopen("/dev/tty", "r");
stream_set_blocking($keyboard, false);

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

function calc_microsecs($wpm, $delay = 0)
{
	return ((60 / $wpm) * 1000000) + $delay ;
}

function calc_delay($word, $len, $wpm)
{
	/* Delay at the final dot, comma, words from 1 to 3 letters etc */

	$delay_factor = '50'; // percent delay to add to current speed for these words
	$delay_usecs = ( (calc_microsecs($wpm)/100) * $delay_factor );
	
	if($len <= 3) return $delay_usecs;
	else if(strstr($word,",") !== FALSE) return $delay_usecs;
	else if(strstr($word,".") !== FALSE) return $delay_usecs;
	else if(strstr($word,":") !== FALSE) return $delay_usecs;
	else if(strstr($word,";") !== FALSE) return $delay_usecs;
	else if(strstr($word,"!") !== FALSE) return $delay_usecs;
	else if(strstr($word,"?") !== FALSE) return $delay_usecs;
	else return 0;
}

function getch_nonblock($keyboard)
{
	$key = fgetc($keyboard);
	return $key;
}

function myusleep($wpm, $delay, $keyboard)
{
	$tax = 10000;

	for($i=0; ($i*$tax) < calc_microsecs($wpm, $delay); $i++)
	{
		usleep($tax);
	}

	usleep(calc_microsecs($wpm, $delay));
}

function change_wpm($cur, $what)
{
	if ($what=="[" && $cur>10)
	{
		if($cur <= 100) { return $cur-10; }
		if($cur > 100 ) { return $cur-50; }
	}
	else if ($what == "]" && $cur<20000)
	{
		if($cur < 100 ){ return $cur+10; }
		if($cur >= 100) { return $cur+50; }
	}
	else return $cur;
}

$shortopts  = "";
$shortopts .= "w:"; // words per sec
$shortopts .= "b"; // opaque if set, or transparent if not
$shortopts .= "c"; // capitalize
$shortopts .= "f:"; // open file instead of stdin
$shortopts .= "p:"; // start from word num

$longopts  = array(
	"words-per-minute:",
	"opaque",
	"capitalize",
	"file:",
	"position:",
);

$opts = getopt($shortopts, $longopts);

if(isset($opts["w"])) { $wpm = $opts["w"]; }
else if(isset($opts["words-per-minute"])) { $wpm = $opts["words-per-minute"]; }
else { $wpm = 250; }

if(isset($opts["b"]) || isset($opts["opaque"])) { $bgcolor = 0; }
else { $bgcolor = -1; }

if(isset($opts['capitalize']) || isset($opts['c'])) { $capitalize = true; }
else { $capitalize = false; }

if(isset($opts["f"]) && file_exists($opts["f"])) { $file = file_get_contents($opts["f"]); }
else if(isset($opts["file"]) && file_exists($opts["file"])) { $file = file_get_contents($opts["file"]); }
else { $file = ""; while( $data = fgets(STDIN, 64000) ) { $file .= $data; } }

if(isset($opts["p"])) { $starting_word = $opts["p"];}
else if(isset($opts["position"])) { $starting_word = $opts["position"]; }
else { $starting_word = 0; }

$file = str_ireplace("\n"," ",$file);
$file = str_ireplace("\r","",$file);
while(strstr($file, "  ") !== FALSE) $file = str_ireplace("  "," ",$file); 

$words = explode(" ",$file);
$words_count = count($words);
ncurses_init();

$screen = ncurses_newwin(0, 0, 0, 0);
ncurses_wborder($screen, 0,0, 0,0, 0,0, 0,0);

ncurses_getmaxyx($screen, $row, $col); // put inside the loop, later

if (ncurses_has_colors()) {
	ncurses_start_color();
	ncurses_assume_default_colors(NCURSES_COLOR_WHITE, $bgcolor);
	ncurses_init_pair(1, NCURSES_COLOR_WHITE, $bgcolor);
	ncurses_init_pair(2, NCURSES_COLOR_RED, $bgcolor);
	ncurses_wattron($screen, NCURSES_A_BOLD);
}

ncurses_curs_set(0);
ncurses_noecho();

$middle = rhd($col/2);
$erase = str_repeat(" ", $col-2);

// orp marker
ncurses_wcolor_set($screen,2);
ncurses_mvwaddstr($screen, ($row / 2) -1 , $middle, "+");

$prog_char = "_";
$ref_tax = "50000";

for($i = $starting_word; isset( $words[$i] ); $i++)
{
	$string = ($capitalize) ? strtoupper(trim($words[$i])) : trim($words[$i]);

	$length = strlen($string);
	$shifting = getORP($length);

	$orp_char = $string[$shifting];

	// erase line
	ncurses_mvwaddstr($screen, $row / 2, 1, $erase);

	// word
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, $middle - $shifting, $string);

	// orp
	ncurses_wcolor_set($screen,2);
	ncurses_mvwaddstr($screen, ($row / 2), $middle, $orp_char);

	$how_many_percent = rhd(($i / $words_count) * 100);
	$progress = rhd(($i/$words_count)*($col-4));
	$bar = str_repeat($prog_char, $progress);
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row-4, 1, $erase);
	ncurses_mvwaddstr($screen, $row-4, 2, "{$bar}");

	$j = $i+1;
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row-2, 1, $erase);
	ncurses_mvwaddstr($screen, $row-2, 1, " Words: {$j}/{$words_count} [{$how_many_percent}%] | W.P.M.: {$wpm}");
	ncurses_mvwaddstr($screen, $row-2, $col-4, "{$last_key}");
	
	ncurses_wrefresh($screen);

	$delay = calc_delay($string, $length, $wpm);
	$time = calc_microsecs($wpm, $delay);

	for($k=0; ($k*$ref_tax) < $time; $k++)
	{
		$delay = calc_delay($string, $length, $wpm);
		$time = calc_microsecs($wpm, $delay);

		if($key = getch_nonblock($keyboard))
		{
			if($key == 'q') { ncurses_end(); echo "You were reading word no. {$i} from {$words_count} words ($how_many_percent% of the text) at a rhythm of {$wpm} words per minute.\n\n"; exit(0); }
			if($key == '[' || $key == ']') { $wpm = change_wpm($wpm, $key); $time = calc_microsecs($wpm, $delay); $i = ($i-1 < 0 ? 0 : $i-1); continue 2; }
			if($key == 'r') { $i = ($i-11 < 0 ? 0 : $i-11); continue 2; }
			if($key == 'c') { $capitalize = ($capitalize ? false : true); $i = ($i-1 < 0 ? 0 : $i-1); continue 2; }
			if($key == 'p') { $paused = ($paused ? false : true); }
			ncurses_flushinp();
		}
		else
		{
			$key = null;
		}
		
		if($paused) { while('p' !== getch_nonblock($keyboard)) { usleep($ref_tax); } $paused = ($paused ? false : true); }
		usleep($ref_tax);
	}
}

fclose($keyboard);
ncurses_end();

?>
