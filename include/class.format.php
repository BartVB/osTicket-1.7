<?php
/*********************************************************************
    class.format.php

    Collection of helper function used for formatting 

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/


class Format {


    function file_size($bytes) {
        
        if(!is_numeric($bytes))
            return $bytes;
        if($bytes<1024)
            return $bytes.' bytes';        
        if($bytes <102400)
            return round(($bytes/1024),1).' kb';

        return round(($bytes/1024000),1).' mb';
    }

    function file_name($filename) {
        return preg_replace('/\s+/', '_', $filename);
    }

    /* re-arrange $_FILES array for the sane */
    function files($files) {

        foreach($files as $k => $a) {
            if(is_array($a))
                foreach($a as $i => $v)
                    $result[$i][$k] = $v;
        }

        return $result?array_filter($result):$files;
    }


    /* encode text into desired encoding - taking into accout charset when available. */
    function encode($text, $charset=null, $encoding='utf-8') {

        //Try auto-detecting charset/encoding
        if(!$charset && function_exists('mb_detect_encoding')) 
            $charset = mb_detect_encoding($text);

        //Cleanup - junk 
        if($charset && in_array(trim($charset), array('default','x-user-defined')))
            $charset = 'ISO-8859-1'; 

        if(function_exists('iconv') && $charset)
            return iconv($charset, $encoding.'//IGNORE', $text);
        elseif(function_exists('iconv_mime_decode'))
            return iconv_mime_decode($text, 0, $encoding);
        else //default to utf8 encoding.
            return utf8_encode($text);
    }

    //Wrapper for utf-8 encoding.
    function utf8encode($text, $charset=null) {
        return Format::encode($text, $charset, 'utf-8');
    }

	function phone($phone) {

		$stripped= preg_replace("/[^0-9]/", "", $phone);
		if(strlen($stripped) == 7)
			return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2",$stripped);
		elseif(strlen($stripped) == 10)
			return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3",$stripped);
		else
			return $phone;
	}

    function truncate($string,$len,$hard=false) {
        
        if(!$len || $len>strlen($string))
            return $string;
        
        $string = substr($string,0,$len);

        return $hard?$string:(substr($string,0,strrpos($string,' ')).' ...');
    }

    function strip_slashes($var) {
        return is_array($var)?array_map(array('Format','strip_slashes'),$var):stripslashes($var);
    }

    function wrap($text,$len=75) {
        return wordwrap($text,$len,"\n",true);
    }

    function html($html, $config=array('balance'=>1)) {
        require_once(INCLUDE_DIR.'htmLawed.php');
        return htmLawed($html, $config);
    }

    function safe_html($html) {
        return Format::html($html,array('safe'=>1,'balance'=>1));
    }

    function htmlchars($var) {
        $flags = ENT_COMPAT | ENT_QUOTES;
        if (phpversion() >= '5.4.0')
            $flags |= ENT_HTML401;
        return is_array($var)
            ? array_map(array('Format','htmlchars'),$var)
            : htmlentities($var, $flags, 'UTF-8');
    }

    function input($var) {
        return Format::htmlchars($var);
    }

    //Format text for display..
    function display($text) {
        global $cfg;

        $text=Format::htmlchars($text); //take care of html special chars
        if($cfg && $cfg->clickableURLS() && $text)
            $text=Format::clickableurls($text);

        //Wrap long words...
        $text=preg_replace_callback('/\w{75,}/',
            create_function(
                '$matches',                                     # nolint
                'return wordwrap($matches[0],70,"\n",true);'),  # nolint
            $text);

        return nl2br($text);
    }

    function striptags($var) {
        $flags = ENT_COMPAT;
        if (phpversion() >= '5.4.0')
            $flags |= ENT_HTML401;
        return is_array($var)
            ? array_map(array('Format','striptags'),$var)
              //strip all tags ...no mercy!
            : strip_tags(html_entity_decode($var, $flags, 'UTF-8'));
    }

    //make urls clickable. Mainly for display 
    function clickableurls($text) {
        global $ost;
        
        $token = $ost->getLinkToken();
        //Not perfect but it works - please help improve it. 
        $text=preg_replace_callback('/(((f|ht){1}tp(s?):\/\/)[-a-zA-Z0-9@:%_\+.~#?&;\/\/=]+)/',
                create_function('$matches',
                    sprintf('return "<a href=\"l.php?url=".urlencode($matches[1])."&auth=%s\" target=\"_blank\">".$matches[1]."</a>";',
                        $token)),
                $text);

        $text=preg_replace_callback("/(^|[ \\n\\r\\t])(www\.([a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+)(\/[^\/ \\n\\r]*)*)/",
                create_function('$matches',
                    sprintf('return "<a href=\"l.php?url=".urlencode("http://".$matches[2])."&auth=%s\" target=\"_blank\">".$matches[2]."</a>";',
                        $token)),
                $text);

        $text=preg_replace("/(^|[ \\n\\r\\t])([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4})/",
            '\\1<a href="mailto:\\2" target="_blank">\\2</a>', $text);

        return $text;
    }

    function stripEmptyLines ($string) {
        //return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);
        //return preg_replace('/\s\s+/',"\n",$string); //Too strict??
        return preg_replace("/\n{3,}/", "\n\n", $string);
    }

    
    function linebreaks($string) {
        return urldecode(ereg_replace("%0D", " ", urlencode($string)));
    }


    /**
     * Thanks, http://us2.php.net/manual/en/function.implode.php
     * Implode an array with the key and value pair giving
     * a glue, a separator between pairs and the array
     * to implode.
     * @param string $glue The glue between key and value
     * @param string $separator Separator between pairs
     * @param array $array The array to implode
     * @return string The imploded array
    */
    function array_implode( $glue, $separator, $array ) {
        
        if ( !is_array( $array ) ) return $array;

        $string = array();
        foreach ( $array as $key => $val ) {
            if ( is_array( $val ) )
                $val = implode( ',', $val );
        
            $string[] = "{$key}{$glue}{$val}";
        }
    
        return implode( $separator, $string );
    }

    /* elapsed time */
    function elapsedTime($sec) {

        if(!$sec || !is_numeric($sec)) return "";

        $days = floor($sec / 86400);
        $hrs = floor(bcmod($sec,86400)/3600);
        $mins = round(bcmod(bcmod($sec,86400),3600)/60);
        if($days > 0) $tstring = $days . 'd,';
        if($hrs > 0) $tstring = $tstring . $hrs . 'h,';
        $tstring =$tstring . $mins . 'm';

        return $tstring;
    }
    
    /* Dates helpers...most of this crap will change once we move to PHP 5*/
    function db_date($time) {
        global $cfg;
        return Format::userdate($cfg->getDateFormat(), Misc::db2gmtime($time));
    }

    function db_datetime($time) {
        global $cfg;
        return Format::userdate($cfg->getDateTimeFormat(), Misc::db2gmtime($time));
    }
    
    function db_daydatetime($time) {
        global $cfg;
        return Format::userdate($cfg->getDayDateTimeFormat(), Misc::db2gmtime($time));
    }

    function userdate($format, $gmtime) {
        return Format::date($format, $gmtime, $_SESSION['TZ_OFFSET'], $_SESSION['TZ_DST']);
    }
    
    function date($format, $gmtimestamp, $offset=0, $daylight=false){
        
        if(!$gmtimestamp || !is_numeric($gmtimestamp))
            return ""; 
        
        $offset+=$daylight?date('I', $gmtimestamp):0; //Daylight savings crap.
        
        return date($format, ($gmtimestamp+ ($offset*3600)));
    }
                      
}
?>
