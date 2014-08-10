#!/usr/bin/php -q
<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

$keyboard = fopen("/dev/tty", "r");
stream_set_blocking($keyboard, false);

$themes = array(
	"default" => array( "fg" => NCURSES_COLOR_WHITE,  "bg" => -1 , "mk"=> NCURSES_COLOR_RED ),
	"light" => array( "fg" => NCURSES_COLOR_BLACK,  "bg" => NCURSES_COLOR_WHITE, "mk"=> NCURSES_COLOR_RED ),
	"opaque" => array( "fg" => NCURSES_COLOR_WHITE,  "bg" => NCURSES_COLOR_BLACK, "mk"=> NCURSES_COLOR_RED ),
);

function next_theme($theme, $themes, $screen)
{
	$num = count($themes);
	$last_index = $num-1;
	$names = array_keys($themes);
	for($i=0; $i<=$last_index;$i++) { if(strcmp($names[$i],$theme)===0) { $curpos = $i; } }
	
	$next_pos = ($curpos == $last_index) ? 0 : ($curpos+1);
	
	$new_theme_name = $names[$next_pos];
	
	if (ncurses_has_colors()) {
		ncurses_assume_default_colors($themes[$new_theme_name]["fg"], $themes[$new_theme_name]["bg"]);
		ncurses_init_pair(1, $themes[$new_theme_name]["fg"], $themes[$new_theme_name]["bg"]);
		ncurses_init_pair(2, $themes[$new_theme_name]["mk"], $themes[$new_theme_name]["bg"]);
	}
	
	return $new_theme_name;
}

function getORP($strlen)
{
	$orp = array(0,0,1,1,1,1,2,2,2,2,3,3,3,3);
	
	if(($strlen) > 13) { return 4; }
	else { return $orp[$strlen];  }
}

function calc_microsecs($wpm, $delay = 0)
{
	return ((60 / $wpm) * 1000000) + $delay ;
}

function calc_delay($word, $len, $wpm)
{
	/* Delay at the final dot, comma, words from 1 to 3 letters etc */

	$delay_factor = '20'; // percent delay to add to current speed for these words
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
$shortopts .= "t:"; // theme
$shortopts .= "c"; // capitalize
$shortopts .= "f:"; // open file instead of stdin
$shortopts .= "p:"; // start from word num
$shortopts .= "s"; // hide status bars
$shortopts .= "h"; // hide status bars

$longopts  = array(
	"words-per-minute:",
	"theme:",
	"capitalize",
	"file:",
	"position:",
	"status",
	"help",
);

$opts = getopt($shortopts, $longopts);

// show help
if(isset($opts['help']) || isset($opts['h'])) { show_help(); }

// else
if(isset($opts["w"])) { $wpm = $opts["w"]; }
else if(isset($opts["words-per-minute"])) { $wpm = $opts["words-per-minute"]; }
else { $wpm = 250; }

if(isset($opts["t"]) && array_key_exists($opts["t"], $themes)) { $theme = $opts["t"]; }
else if(isset($opts["theme"]) && array_key_exists($opts["t"], $themes)) { $theme = $opts["theme"]; }
else { $theme = "default"; }

if(isset($opts['capitalize']) || isset($opts['c'])) { $capitalize = true; }
else { $capitalize = false; }

if(isset($opts["f"]) && file_exists($opts["f"])) { $file = file_get_contents($opts["f"]); }
else if(isset($opts["file"]) && file_exists($opts["file"])) { $file = file_get_contents($opts["file"]); }
else { $file = ""; while( $data = fgets(STDIN, 64000) ) { $file .= $data; } }

if(isset($opts["p"])) { $starting_word = $opts["p"];}
else if(isset($opts["position"])) { $starting_word = $opts["position"]; }
else { $starting_word = 0; }

if(isset($opts['status']) || isset($opts['s'])) { $status = false; }
else { $status = true; }

$file = str_ireplace("\n"," ",$file);
$file = str_ireplace("\r","",$file);
while(strstr($file, "  ") !== FALSE) $file = str_ireplace("  "," ",$file); 

$words = explode(" ", trim($file));
$words_count = count($words);

ncurses_init();

ncurses_curs_set(0);
ncurses_noecho();

if (ncurses_has_colors()) {
	ncurses_start_color();
	ncurses_assume_default_colors($themes[$theme]["fg"], $themes[$theme]["bg"]);
	ncurses_init_pair(1, $themes[$theme]["fg"], $themes[$theme]["bg"]);
	ncurses_init_pair(2, $themes[$theme]["mk"], $themes[$theme]["bg"]);
}

$screen = ncurses_newwin(0, 0, 0, 0);
ncurses_wborder($screen, 0,0, 0,0, 0,0, 0,0);
ncurses_getmaxyx($screen, $row, $col); // put inside the loop, later

if (ncurses_has_colors()) {
	ncurses_wattron($screen, NCURSES_A_BOLD);
}

$middle = floor($col/2);
$erase = str_repeat(" ", $col-2);

$prog_char = "_";
$opr_char = "+";
$ref_tax = "50000";

// orp marker
ncurses_wcolor_set($screen,2);
ncurses_mvwaddstr($screen, ($row / 2) -1 , $middle, $opr_char);

for($i = $starting_word; isset( $words[$i] ); $i++)
{
	$string = ($capitalize) ? strtoupper(trim($words[$i])) : trim($words[$i]);

	$length = strlen($string);
	$shifting = getORP($length);

	$orp_char = $string[$shifting];

	// erase word line
	ncurses_wcolor_set($screen,1);
	ncurses_mvwaddstr($screen, $row / 2, 1, $erase);

	// word
	ncurses_mvwaddstr($screen, $row / 2, $middle - $shifting, $string);

	// orp
	ncurses_wcolor_set($screen,2);
	ncurses_mvwaddstr($screen, ($row / 2), $middle, $orp_char);

	if($status){
		$how_many_percent = floor(($i / $words_count) * 100);
		$progress = floor(($i/$words_count)*($col-4));
		$bar = str_repeat($prog_char, $progress);

		// progress bar
		ncurses_wcolor_set($screen,1);
		ncurses_mvwaddstr($screen, $row-4, 1, $erase);
		ncurses_mvwaddstr($screen, $row-4, 2, "{$bar}");

		// status bar
		$j = $i+1;
		ncurses_wcolor_set($screen,1);
		ncurses_mvwaddstr($screen, $row-2, 1, $erase);
		ncurses_mvwaddstr($screen, $row-2, 1, " Words: {$j}/{$words_count} [{$how_many_percent}%] | W.P.M.: {$wpm}");
	}
	
	ncurses_wrefresh($screen);

	$delay = calc_delay($string, $length, $wpm);
	$time = calc_microsecs($wpm, $delay);

	for($k=0; ($k*$ref_tax) < $time; $k++)
	{
		if($key = getch_nonblock($keyboard))
		{
			/* maybe use switch() */
			if($key == 'q') { ncurses_end(); echo "You were reading word no. {$i} from {$words_count} words ($how_many_percent% of the text) at a rhythm of {$wpm} words per minute.\n\n"; exit(0); }
			if($key == '[' || $key == ']') { $wpm = change_wpm($wpm, $key); $delay = calc_delay($string, $length, $wpm); $time = calc_microsecs($wpm, $delay); $i = ($i-1 < 0 ? 0 : $i-1); continue 2; }
			if($key == 'r') { $i = ($i-11 < 0 ? 0 : $i-11); continue 2; }
			if($key == 'c') { $capitalize = ($capitalize ? false : true); $i = ($i-1 < 0 ? 0 : $i-1); continue 2; }
			if($key == 't') { $theme = next_theme($theme, $themes, $screen); }
			if($key == 'p') { $paused = ($paused ? false : true); }
			if($key == 's') { $status = ($status ? false : true); ncurses_mvwaddstr($screen, $row-4, 1, $erase); ncurses_mvwaddstr($screen, $row-2, 1, $erase); }
		}

		$key = false;
		
		//if($paused) { while('p' !== getch_nonblock($keyboard)) { $i = ($i-1 < 0 ? $i : $i-1); continue 3; /* this flushes for other commands while paused */ } $paused = ($paused ? false : true); }
		while($paused) { $i = ($i-1 < 0 ? 0 : $i-1); continue 3; /* this flushes for other commands while paused */ }
		usleep($ref_tax);
	}
}

fclose($keyboard);
ncurses_end();

function show_help()
{
	$help = <<<EOT
READREAD - quick reading

    USAGE:
	
    ./readread.php

        -h,	--help
        Show help and exit.

        -w,	--words-per-minute	<number>
        Number of words that it shows per minute, approx.

        -t,	--theme		<theme>
        Use to choose the theme! current themes are: default, light and opaque.

        -c,	--capitalize
        Transform words to uppercase

        -f,	--file	<file>
        Reads text from file, instead of from STDIN (standard input)

        -s, --status
        Hides status bar and progress bar
		
    KEYBOARD SHORTCUTS
	
    These are the keyboard shortcuts currently avaliable.

    '[' and ']'
        Change how many words per minute are running.

    c
        Toggle between upper case / lower case.

    r
        Rewind the text by ~ 10 words.

    t
        Change theme to the next avaliable.

    p
        Toggle between play / pause.

    s
        Toggle status bar on/off

    q
        Quit.
		
    USAGE EXAMPLE
	
    Running the script directly

        ./readread.php -f tea.txt

    or

        ./readread.php --file tea.txt

   Running via pipe

        cat tea.txt | ./readread.php

   or

        ./readread.php < tea.txt
EOT;

echo "{$help}\n\n";

exit(0);
}

?>
