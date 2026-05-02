<?php
//functions for elitechwiz
function getTitle($url) {
  $data = readcontents($url);
  $title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches) ? $matches[1] : null;
  return $title;
  }
  function userinput($message){
    global $white, $bold, $greenbg, $redbg, $bluebg, $cln, $lblue, $fgreen;
    $yellowbg = "\e[100m";
    $inputstyle = $cln . $bold . $lblue . "[#] " . $message . ": " . $fgreen ;
  echo $inputstyle;
  }
function ew_os_family(){
  if (defined('PHP_OS_FAMILY')) {
    return PHP_OS_FAMILY;
  }
  if (stripos(PHP_OS, 'WIN') === 0) {
    return 'Windows';
  }
  return 'Linux';
}
function ew_is_termux(){
  $prefix = getenv('PREFIX');
  return (getenv('TERMUX_VERSION') !== false) || ($prefix && strpos($prefix, 'com.termux') !== false);
}
function ew_command_exists($command){
  $os = ew_os_family();
  if ($os === 'Windows') {
    $output = shell_exec('where ' . $command . ' 2>NUL');
    return !empty($output);
  }
  $output = shell_exec('command -v ' . $command . ' 2>/dev/null');
  return !empty($output);
}
function ew_clear_screen(){
  $os = ew_os_family();
  if ($os === 'Windows') {
    system('cls');
  } else {
    system('clear');
  }
}
function ew_integrity_manifest_path($rootDir){
  return rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'elitechwiz_integrity.json';
}
function ew_enforce_integrity($rootDir){
  global $bold, $red, $yellow, $cln;
  $manifestPath = ew_integrity_manifest_path($rootDir);
  if (!file_exists($manifestPath)) {
    echo $bold . $red . "\n[!] Integrity check failed: missing manifest.\n" . $cln;
    echo $yellow . "[!] This copy appears modified or incomplete.\n" . $cln;
    echo $yellow . "[!] Please use the original repository or a fork from: https://https://github.com/Eliahhango/elitechwiz-elitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkit\n" . $cln;
    exit(1);
  }
  $raw = file_get_contents($manifestPath);
  $manifest = json_decode($raw, true);
  if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
    echo $bold . $red . "\n[!] Integrity check failed: invalid manifest.\n" . $cln;
    echo $yellow . "[!] Please use the original repository or a fork from: https://https://github.com/Eliahhango/elitechwiz-elitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkit\n" . $cln;
    exit(1);
  }
  $algo = isset($manifest['algo']) ? (string) $manifest['algo'] : 'sha256';
  if ($algo !== 'sha256') {
    echo $bold . $red . "\n[!] Integrity check failed: unsupported hash algorithm.\n" . $cln;
    exit(1);
  }
  $missing = [];
  $modified = [];
  foreach ($manifest['files'] as $relPath => $expectedHash) {
    $path = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relPath;
    if (!file_exists($path)) {
      $missing[] = $relPath;
      continue;
    }
    $actual = hash_file($algo, $path);
    if (strtolower((string) $actual) !== strtolower((string) $expectedHash)) {
      $modified[] = $relPath;
    }
  }
  if (!empty($missing) || !empty($modified)) {
    echo $bold . $red . "\n[!] Integrity check failed. This copy appears modified.\n" . $cln;
    if (!empty($missing)) {
      echo $yellow . "[!] Missing files: " . implode(', ', $missing) . "\n" . $cln;
    }
    if (!empty($modified)) {
      echo $yellow . "[!] Modified files: " . implode(', ', $modified) . "\n" . $cln;
    }
    echo $yellow . "[!] Developer policy: modifications must be done in a fork of the original repository.\n" . $cln;
    echo $yellow . "[!] Please use or fork: https://https://github.com/Eliahhango/elitechwiz-elitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkitelitechwiz-educational-recon-toolkit\n" . $cln;
    exit(1);
  }
}
function ew_fix_dependencies(){
  global $bold, $red, $fgreen, $blue, $yellow, $cln, $lblue;
  echo $bold . $lblue . "[i] Platform: " . ew_os_family() . " | PHP: " . PHP_VERSION . "\n" . $cln;
  $missingCurl = !extension_loaded('curl');
  $missingDom = !extension_loaded('dom');

  echo $bold . $blue . "[+] Checking required PHP extensions ...\n";
  if (!$missingCurl && !$missingDom) {
    echo $bold . $fgreen . "[i] All required modules are installed. You are all set.\n" . $cln;
    return;
  }

  if ($missingCurl) {
    echo $bold . $red . "[!] cURL Module Not Installed!\n";
  }
  if ($missingDom) {
    echo $bold . $red . "[!] DOM/XML Module Not Installed!\n";
  }

  $os = ew_os_family();
  if (ew_is_termux()) {
    echo $yellow . "[*] Termux detected. Installing modules using pkg...\n" . $cln;
    if (ew_command_exists('pkg')) {
      system("pkg install -y php php-xml");
      echo $bold . $fgreen . "[i] Termux install completed. Restart elitechwiz.\n" . $cln;
    } else {
      echo $bold . $red . "[!] pkg not found. Install manually: pkg install php php-xml\n" . $cln;
    }
    return;
  }

  if ($os === 'Windows') {
    echo $yellow . "[*] Windows detected. Enable extensions in php.ini:\n" . $cln;
    echo "    - extension=curl\n";
    echo "    - extension=dom (or php-xml)\n";
    echo $yellow . "[*] Then restart your terminal and re-run elitechwiz.\n" . $cln;
    return;
  }

  if (ew_command_exists('apt-get')) {
    echo $yellow . "[*] Installing required modules via apt-get (Ubuntu/Debian)...\n" . $cln;
    $aptPrefix = ew_command_exists('sudo') ? 'sudo ' : '';
    system($aptPrefix . "apt-get -qq --assume-yes install php-curl php-xml");
    echo $bold . $fgreen . "[i] Installation completed. Restart elitechwiz.\n" . $cln;
    return;
  }

  echo $yellow . "[*] Unsupported package manager. Install PHP extensions manually:\n" . $cln;
  echo "    - curl\n";
  echo "    - dom/xml\n";
}
function WEBserver($urlws){
  stream_context_set_default( [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);
  $wsheaders = get_headers($urlws, 1);
  if (is_array($wsheaders['Server'])) { $ws = $wsheaders['Server'][0];}else{
    $ws = $wsheaders['Server'];
  }
  if ($ws == "")
    {
      echo "\e[91mCould Not Detect\e[0m";
    }
  else
    {
      echo "\e[92m$ws \e[0m";
    }
}


function cloudflaredetect($reallink){

  $urlhh    = "http://api.hackertarget.com/httpheaders/?q=" . $reallink;
  $resulthh = file_get_contents($urlhh);
  if (strpos($resulthh, 'cloudflare') !== false)
    {
      echo "\e[91mDetected\n\e[0m";
    }
  else
    {
      echo "\e[92mNot Detected\n\e[0m";
    }
}


function CMSdetect($reallink){
  $cmssc  = readcontents($reallink);
  if (strpos($cmssc, '/wp-content/') !== false)
    {
      $tcms = "WordPress";

    }
  else
    {
      if (strpos($cmssc, 'Joomla') !== false)
        {
          $tcms = "Joomla";
        }
      else
        {
          $drpurl = $reallink . "/misc/drupal.js";
          $drpsc  = readcontents("$drpurl");
          if (strpos($drpsc, 'Drupal') !== false)
            {
              $tcms = "Drupal";
            }
          else
            {
              if (strpos($cmssc, '/skin/frontend/') !== false)
                {
                  $tcms = "Magento";
                }
              else
                {
                  if (strpos($cmssc, 'content="WordPress')!== false) {
                    $tcms = "WordPress";
                  }
                  else {


                  $tcms = "\e[91mCould Not Detect";
                }
                }
            }
        }
    }
    return $tcms;
}
function robotsdottxt($reallink){
  $rbturl    = $reallink . "/robots.txt";
  $rbthandle = curl_init($rbturl);
  curl_setopt($rbthandle, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($rbthandle, CURLOPT_RETURNTRANSFER, TRUE);
  $rbtresponse = curl_exec($rbthandle);
  $rbthttpCode = curl_getinfo($rbthandle, CURLINFO_HTTP_CODE);
  if ($rbthttpCode == 200)
    {
      $rbtcontent = readcontents($rbturl);
      if ($rbtcontent == "")
        {
          echo "Found But Empty!";
        }
      else
        {
          echo "\e[92mFound \e[0m\n";
          echo "\e[36m\n-------------[ contents ]----------------  \e[0m\n";
          echo $rbtcontent;
          echo "\e[36m\n-----------[end of contents]-------------\e[0m";
        }
    }
  else
    {
      echo "\e[91mCould NOT Find robots.txt! \e[0m\n";
    }
}
function gethttpheader($reallink){
  $hdr = get_headers($reallink);
  foreach ($hdr as $shdr) {
    echo "\n\e[92m\e[1m[i]\e[0m  $shdr";
  }
  echo "\n";

}
function extract_social_links($sourcecode){
  /* This is really a simple code for now i will work around it on the upcoming version.
     For now only these social media are supported:
      - Facebook
      - Twitter
      - Instagram
      - YouTube
      - Google +
      - Pinterest
      - GitHUB
  */
  global $bold, $lblue, $fgreen, $red, $blue, $magenta, $orange, $white, $green, $grey, $cyan;
  $fb_link_count = 0;
  $twitter_link_count = 0;
  $insta_link_count = 0;
  $yt_link_count = 0;
  $gp_link_count = 0;
  $pint_link_count = 0;
  $github_link_count = 0;
  $total_social_link_count = 0;

  $social_links_array = array (
    'facebook' => array(),
    'twitter' => array(),
    'instagram' => array(),
    'youtube' => array(),
    'google_p' => array(),
    'pinterest' => array(),
    'github' => array()
  );

  $fb_links = $social_links_array['facebook'];
  $twitter_links = $social_links_array['twitter'];
  $insta_links = $social_links_array['instagram'];
  $youtube_links = $social_links_array['youtube'];
  $googlep_links = $social_links_array['google_p'];
  $pinterest_links = $social_links_array['pinterest'];
  $github_links = $social_links_array['github'];

  $sm_dom = new DOMDocument;
  @$sm_dom->loadHTML($sourcecode);
  $links = $sm_dom->getElementsByTagName('a');
  foreach ($links as $link) {
    $link = $link->getAttribute('href');
    if (strpos ($link, "facebook.com/") !== false){
      $total_social_link_count++;
      $fb_link_count++;
      array_push($social_links_array['facebook'], $link);
    }
    elseif (strpos ($link, "twitter.com/") !== false) {
      $total_social_link_count++;
      $twitter_link_count++;
      array_push($social_links_array['twitter'], $link);
    }
    elseif (strpos ($link, "instagram.com/") !== false) {
      $total_social_link_count++;
      $insta_link_count++;
      array_push($social_links_array['instagram'], $link);
    }
    elseif (strpos ($link, "youtube.com/") !== false) {
      $total_social_link_count++;
      $yt_link_count++;
      array_push($social_links_array['youtube'], $link);
    }
    elseif (strpos ($link, "plus.google.com/") !== false) {
      $total_social_link_count++;
      $gp_link_count++;
      array_push($social_links_array['google_p'], $link);
    }
    elseif (strpos ($link, "github.com/") !== false) {
      $total_social_link_count++;
      $github_link_count++;
      array_push($social_links_array['github'], $link);
    }
    elseif (strpos ($link, "pinterest.com/") !== false) {
      $total_social_link_count++;
      $pint_link_count++;
      array_push($social_links_array['pinterest'], $link);
    }
    else {
      // I know this has nothing to do with the code but again i love comments ;__; it's feels good to waste time :p
    }
  }
  if ($total_social_link_count == 0){
    echo $bold . $red . "[!] No Social Link Found In Source Code. \n\e[0m";
  }
  elseif ($total_social_link_count == "1") {
    // As much as i hate to admit grammer is important :p
    echo $bold . $lblue . "[i] " . $fgreen . $total_social_link_count . $lblue . " Social Link Was Gathered From Source Code \n\n";
    foreach ($social_links_array['facebook'] as $link) {
      echo $bold . $blue . "[ facebook  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['twitter'] as $link) {
      echo $bold . $cyan . "[  twitter  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['instagram'] as $link) {
      echo $bold . $magenta . "[ instagram ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['youtube'] as $link) {
      echo $bold . $red . "[  youtube  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['google_p'] as $link) {
      echo $bold . $orange . "[  google+  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['pinterest'] as $link) {
      echo $bold . $red . "[ pinterest ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['github'] as $link) {
      echo $bold . $grey . "[  github   ] " . $white . $link . "\n";
    }
    echo "\n";
  } else {
    echo $bold . $lblue . "[i] " . $fgreen . $total_social_link_count . $lblue . " Social Links Were Gathered From Source Code \n\n";
    foreach ($social_links_array['facebook'] as $link) {
      echo $bold . $blue . "[ facebook  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['twitter'] as $link) {
      echo $bold . $cyan . "[  twitter  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['instagram'] as $link) {
      echo $bold . $magenta . "[ instagram ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['youtube'] as $link) {
      echo $bold . $red . "[  youtube  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['google_p'] as $link) {
      echo $bold . $orange . "[  google+  ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['pinterest'] as $link) {
      echo $bold . $red . "[ pinterest ] " . $white . $link . "\n";
    }
    foreach ($social_links_array['github'] as $link) {
      echo $bold . $grey . "[  github   ] " . $white . $link . "\n";
    }
    echo "\n";
  }
}
function extractLINKS($reallink){
  global $bold, $lblue, $fgreen;
  $arrContextOptions=array(
      "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );
  $ip = str_replace("https://","",$reallink);
  $lwwww = str_replace("www.","",$ip);
  $elsc = file_get_contents($reallink, false, stream_context_create($arrContextOptions));
  $eldom     = new DOMDocument;
  @$eldom->loadHTML($elsc);
  $elinks = $eldom->getElementsByTagName('a');
  $elinks_count = 0;
  foreach ($elinks as $ec) {
    $elinks_count++;
  }
  echo $bold . $lblue . "[i] Number Of Links Found In Source Code : " . $fgreen . $elinks_count . "\n";
  userinput("Display Links ? (Y/N) ");
  $bv_show_links = trim(fgets(STDIN, 1024));
  if ($bv_show_links == "y" or $bv_show_links =="Y"){
    foreach ($elinks as $elink) {
      $elhref = $elink->getAttribute('href');
      if (strpos($elhref, $lwwww) !== false ) {
        echo "\n\e[92m\e[1m*\e[0m\e[1m $elhref";

      }
      else {
        echo "\n\e[38;5;208m\e[1m*\e[0m\e[1m $elhref";
      }
    }
    echo "\n";
  }

else {
  // not showing links.
}
}
function readcontents($urltoread){
  $arrContextOptions=array(
      "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );
    $filecntns = file_get_contents($urltoread, false, stream_context_create($arrContextOptions));
    return $filecntns;
}

function MXlookup ($site){
  $Mxlkp = dns_get_record($site, DNS_MX);
	$mxrcrd = $Mxlkp[0]['target'];
	$mxip = gethostbyname($mxrcrd);
	$mx = gethostbyaddr($mxip);
  $mxresult = "\e[1m\e[36mIP      :\e[32m " . $mxip ."\n\e[36mHOSTNAME:\e[32m " . $mx ;
  return $mxresult;
}

function bv_get_alexa_rank($url){
  $xml = simplexml_load_file("http://data.alexa.com/data?cli=10&url=".$url);
    if(isset($xml->SD)):
        return $xml->SD->POPULARITY->attributes()->TEXT;
    endif;
}
function bv_moz_info($url){
  global $bold, $red, $fgreen, $lblue, $blue;
  require ("elitechwiz_config.php");
  if (strpos($accessID, " ") !== false OR strpos($secretKey, " ") !== false){
    echo $bold . $red . "\n[!] Some Results Will Be Omited (Please Put Valid MOZ API Keys in elitechwiz_config.php file)\n\n";
  }
  else {
  	$expires = time() + 300;
  	$SignInStr = $accessID. "\n" .$expires;
  	$binarySignature = hash_hmac('sha1', $SignInStr, $secretKey, true);
  	$SafeSignature = urlencode(base64_encode($binarySignature));
  	$objURL = $url;
    $flags = "103079231492";
  	$reqUrl = "http://lsapi.seomoz.com/linkscape/url-metrics/".urlencode($objURL)."?Cols=".$flags."&AccessID=".$accessID."&Expires=".$expires."&Signature=".$SafeSignature;
    $opts = array(
  	    CURLOPT_RETURNTRANSFER => true
  	    );
   $curlhandle = curl_init($reqUrl);
  	curl_setopt_array($curlhandle, $opts);
  	$content = curl_exec($curlhandle);
  	curl_close($curlhandle);
  	$resObj = json_decode($content);
    echo $bold . $lblue . "[i] Moz Rank : " . $fgreen . $resObj->{'umrp'} . "\n";
  	echo $bold . $lblue . "[i] Domain Authority : " . $fgreen . $resObj->{'pda'} . "\n";
  	echo $bold . $lblue . "[i] Page Authority : " . $fgreen . $resObj->{'upa'} . "\n";
  }
}
?>
