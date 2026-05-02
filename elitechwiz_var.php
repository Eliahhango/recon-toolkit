<?php
$rhversion = "2.1.0";
$white = "\e[97m";
$black = "\e[30m\e[1m";
$yellow = "\e[93m";
$orange = "\e[38;5;208m";
$blue   = "\e[34m";
$lblue  = "\e[36m";
$cln    = "\e[0m";
$green  = "\e[92m";
$fgreen = "\e[32m";
$red    = "\e[91m";
$magenta = "\e[35m";
$bluebg = "\e[44m";
$lbluebg = "\e[106m";
$greenbg = "\e[42m";
$lgreenbg = "\e[102m";
$yellowbg = "\e[43m";
$lyellowbg = "\e[103m";
$redbg = "\e[101m";
$grey = "\e[37m";
$cyan = "\e[36m";
$bold   = "\e[1m";
function elitechwiz_banner(){
  global $rhversion;
  echo "\e[91;1m
  _____ _ _ _       _           _
 | ____| | | |_   _(_)_ __   ___| |__
 |  _| | | | | | | | | '_ \\ / _ \\ '_ \\
 | |___| | | | |_| | | | | |  __/ |_) |
 |_____|_|_|_|\\__, |_|_| |_|\\___|_.__/
              |___/
\e[36m  All In One Tool For Information Gathering\e[91m And\e[32m Vulnerability Scanning
\e[93m  Version: {$rhversion}
\e[91m  elitechwiz - Educational Use Only
\e[97m  [$] Shout Out - You ;)
\e[32m
  \n";
}
?>