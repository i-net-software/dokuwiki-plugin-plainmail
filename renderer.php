<?php
/**
 * Renderer for plaintext email output
 * Based on the renderer for text output
 *
 * @author i-net software <tools@inetsoftware.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',fullpath(dirname(__FILE__).'/../../').'/');

if ( !defined('DOKU_LF') ) {
	define ('DOKU_LF',"\n");
}

use dokuwiki\Utf8\PhpString;

class renderer_plugin_plainmail extends Doku_Renderer {

	// @access public
	var $doc = '';        // will contain the whole document
	var $toc = array();   // will contain the Table of Contents

	var $footnotes = array();
	var $store = '';
	var $nSpan = 0;
	var $separator = '';
	var $_counter = array();
	var $table = array();
	var $listStyle = array();
	
	var $defaultLineLength = 40;

	function getFormat(){
		return 'plainmail';
	}

	//handle plugin rendering
	function plugin($name, $data, $state = '', $match = ''){
		$plugin =& plugin_load('syntax',$name);
		if($plugin != null){
			if(!$plugin->render($this->getFormat(),$this,$data)) {

				// probably doesn't support text, so use PhpString::stripped-down xhtml
				$tmpData = $this->doc;
				$this->doc = '';
				if($plugin->render('xhtml',$this,$data) && $tmpData != '' ) {
					$search = array('@<script[^>]*?>.*?</script>@si', // javascript
                  '@<style[^>]*?>.*?</style>@siU',                // style tags
                  '@<[\/\!]*?[^<>]*?>@si',                        // HTML tags
                  '@<![\s\S]*?--[ \t\n\r]*>@',                    // multi-line comments
                  '@\s+@'                                         // extra whitespace
					);
					$this->doc = $tmpData . PhpString::trim(html_entity_decode(preg_replace($search,' ',$this->doc),ENT_QUOTES));
				}
				else
				$this->doc = $tmpData;
			}
		}
	}

	function document_start() {
		global $ID;

		$this->doc = '';
		$toc = array();
		$footnotes = array();
		$store = '';
		$nSpan = 0;
		$separator = '';

		$metaheader = array();
		$metaheader['Content-Type'] = 'plain/text; charset=utf-8';
		$metaheader['Content-Disposition'] = 'attachment; filename="' . noNS($ID) . '.txt"';

		$meta = array();
		$meta['format']['plainmail'] = $metaheader;
		p_set_metadata($ID,$meta);

		$name = ""; // Not the ID as first line
		if (useHeading('navigation')) {
			$title = p_get_first_heading($ID);
			if ($title) $name = $title;
		}

		$this->doc .= $this->_xmlEntities($name) . DOKU_LF;

	}

	function document_end() {
		if ( count ($this->footnotes) > 0 ) {
			$this->doc .= DOKU_LF;

			$id = 0;
			foreach ( $this->footnotes as $footnote ) {
				$id++;   // the number of the current footnote

				// check its not a placeholder that indicates actual footnote text is elsewhere
				if (PhpString::substr($footnote, 0, 5) != "@@FNT") {
					$this->doc .= $id.') ';
					// get any other footnotes that use the same markup
					$alt = array_keys($this->footnotes, "@@FNT$id");
					if (count($alt)) {
						foreach ($alt as $ref) {
							$this->doc .= ($ref+1).') ';
						}
					}
					$this->doc .= $footnote . DOKU_LF;
				}
			}
		}

		// Prepare the TOC
		if($this->info['toc'] && is_array($this->toc) && count($this->toc) > 2){
			global $TOC;
			$TOC = $this->toc;
		}

		// make sure there are no empty paragraphs
		$this->doc = preg_replace('#'.DOKU_LF.'\s+'.DOKU_LF.'\s+'.DOKU_LF.'#',DOKU_LF.DOKU_LF,$this->doc);
		$doc = explode(DOKU_LF, $this->doc);
		$this->doc = '';
		foreach( $doc as $line ) {
		    if ( $this->getConf('line_length') > 0 ) {
	    			while ( PhpString::strlen($line) > $this->getConf('line_length') ) {
	    				$index = strrpos($line, ' ', -PhpString::strlen($line) + $this->getConf('line_length'));
	    				$this->doc .= PhpString::substr($line, 0, $index) . DOKU_LF;
	    				$line = PhpString::trim(PhpString::substr($line, $index));
	    			}
		    }
			
			$this->doc .= $line . DOKU_LF;
		}
	}

	function header($text, $level, $pos, $returnonly = false) {

	    $headerLineLength = $this->getConf('line_length');
	    if ( $headerLineLength == 0 ) $hrLineLength = $this->defaultLineLength;
	    
		switch($level) {
			case 1: $char = '='; break;
			default: $char = ' ';
		}
			
		$text = PhpString::trim($text);
		$LEFT = $RIGHT = floor(($headerLineLength- PhpString::strlen($text) - 2) / 2);
		if ( $LEFT + $RIGHT + PhpString::strlen($text) < $headerLineLength) {
			$RIGHT += $headerLineLength - $LEFT - $RIGHT - PhpString::strlen($text) -2;
		}
		
		if ( $LEFT < 0 ) $LEFT = 0;
		if ( $RIGHT < 0 ) $RIGHT = 0;
		
		$this->doc .= DOKU_LF.DOKU_LF.str_repeat($char, $LEFT).($LEFT>0?' ':'').$this->_xmlEntities($text).($RIGHT>0?' ':'').str_repeat($char, $RIGHT).DOKU_LF.DOKU_LF;
	}

	function section_close() {
		$this->doc .= DOKU_LF . DOKU_LF;
	}

	function cdata($text) {
		$this->doc .= $this->_xmlEntities($text);
	}

	function p_close() {
		$this->doc .= DOKU_LF . DOKU_LF;
	}

	function linebreak() {
		$this->doc .= DOKU_LF;
	}

	function hr() {
	    $hrLineLength = $this->getConf('line_length');
	    if ( $hrLineLength == 0 ) $hrLineLength = $this->defaultLineLength;
	    
		$this->doc .= str_repeat('-', $hrLineLength) . DOKU_LF . DOKU_LF;
	}

	function utf8_strong_open() {
		$this->doc .= ' >';
	}

	function utf8_strong_close() {
		$this->doc .= '< ';
	}

	/**
	 * Callback for footnote start syntax
	 *
	 * All following content will go to the footnote instead of
	 * the document. To achieve this the previous rendered content
	 * is moved to $store and $doc is cleared
	 *
	 * @author Andreas Gohr <andi@splitbrain.org>
	 */
	function footnote_open() {

		// move current content to store and record footnote
		$this->store = $this->doc;
		$this->doc   = '';
	}

	/**
	 * Callback for footnote end syntax
	 *
	 * All rendered content is moved to the $footnotes array and the old
	 * content is restored from $store again
	 *
	 * @author Andreas Gohr
	 */
	function footnote_close() {

		// recover footnote into the stack and restore old content
		$footnote = $this->doc;
		$this->doc = $this->store;
		$this->store = '';

		// check to see if this footnote has been seen before
		$i = array_search($footnote, $this->footnotes);

		if ($i === false) {
			// its a new footnote, add it to the $footnotes array
			$id = count($this->footnotes)+1;
			$this->footnotes[count($this->footnotes)] = $footnote;
		} else {
			// seen this one before, translate the index to an id and save a placeholder
			$i++;
			$id = count($this->footnotes)+1;
			$this->footnotes[count($this->footnotes)] = "@@FNT".($i);
		}

		// output the footnote reference and link
		$this->doc .= ' '.$id.')';
	}

	/** List Element Styles **/
    function listu_open() {
		$this->listStyle[] = 'u';
    }

	function listu_close() {
		$this->doc .= DOKU_LF;
		array_pop($this->listStyle);
	}

    function listo_open() {
		$this->listStyle[] = 1;
    }

    function listo_close() {
		$this->doc .= DOKU_LF;
		array_pop($this->listStyle);
    }
	/** List Element Styles **/

	function listcontent_close() {
		$this->doc .= DOKU_LF;
	}

    function listitem_open($level, $node = false) {
		$this->doc .= str_repeat(' ', $level);
    	
    	if ( $this->listStyle[count($this->listStyle)-1] == 'u' ) {
    		$this->doc .= '*';
    	} else {
    		$this->doc .= intval($this->listStyle[count($this->listStyle)-1]) . '.';
    	}
    }

    function listitem_close() {}

	function unformatted($text) {
		$this->doc .= $this->_xmlEntities($text);
	}
    
    /**
    * Execute PHP code if allowed
    *
    * @param  string $text      PHP code that is either executed or printed
    * @param  string $wrapper   html element to wrap result if $conf['phpok'] is okff
    *
    * @author Andreas Gohr <andi@splitbrain.org>
    */
    public function php($text, $wrapper = 'code') {
        global $conf;
        if($conf['phpok']) {
            ob_start();
            eval($text);
            $this->doc .= ob_get_contents();
            ob_end_clean();
        } else {
            $this->doc .= p_xhtml_cached_geshi($text, 'php', $wrapper);
        }
    }
    
    /**
    * Output block level PHP code
    *
    * If $conf['phpok'] is true this should evaluate the given code and append the result
    * to $doc
    *
    * @param string $text The PHP code
    */
    public function phpblock($text) {
        $this->php($text, 'pre');
    }

	function html($text) {
		$this->doc .= $this->_xmlEntities(PhpString::strip_tags($text));
	}

	function htmlblock($text) {
		$this->html($text);
	}

	function preformatted($text) {
		$code = $this->_xmlEntities(PhpString::trim($text));
		$code = preg_replace("/\n/", "\n> ", $code);
		$this->doc .=  "> " . $code . DOKU_LF . DOKU_LF;
	}

	function file($text, $lang = null, $file = null) {
		$this->preformatted($text);
	}

	function code($text, $lang = null, $file = null) {
		$this->preformatted($text);
	}

	function rss($url, $params) {
		$this->preformatted($url);
	}

	function acronym($acronym) {
		$this->doc .= $acronym;
	}

	function smiley($smiley) {
		$this->doc .= $smiley;
	}

	function entity($entity) {
		if ( array_key_exists($entity, $this->entities) ) {
			$this->doc .= $this->entities[$entity];
		} else {
			$this->doc .= $entity;
		}
	}

	function multiplyentity($x, $y) {
		$this->doc .= $x.'x'.$y;
	}

	function singlequoteopening() {
		global $lang;
		$this->doc .= $lang['singlequoteopening'];
	}

	function singlequoteclosing() {
		global $lang;
		$this->doc .= $lang['singlequoteclosing'];
	}

	function apoutf8_strophe() {
		global $lang;
		$this->doc .= $lang['apoutf8_strophe'];
	}

	function doublequoteopening() {
		global $lang;
		$this->doc .= $lang['doublequoteopening'];
	}

	function doublequoteclosing() {
		global $lang;
		$this->doc .= $lang['doublequoteclosing'];
	}

	function camelcaselink($link) {
		$this->internallink($link,$link);
	}

	function locallink($hash, $name = NULL){
		$name  = $this->_getLinkTitle($name, $hash, $isImage);
		$this->doc .= ' ' . $name . ' ';
	}

	function internallink($id, $name = NULL, $search=NULL,$returnonly=false) {
		global $ID;
		// default name is based on $id as given
		$default = $this->_simpleTitle($id);
		resolve_pageid(getNS($ID),$id,$exists);
		$this->doc .= ' ' . $this->_getLinkTitle($name, $default, $isImage, $id) . ' ';
	}

	function externallink($url, $name = NULL) {
		$this->doc .= ' ' . $this->_getLinkTitle($name, $url, $isImage) . ' [' . $url . ']';
	}

	function interwikilink($match, $name = NULL, $wikiName, $wikiUri) {
		$this->doc .= ' ' . $this->_getLinkTitle($name, $wikiUri, $isImage) . ' ';
	}

	function windowssharelink($url, $name = NULL) {
		$this->doc .= ' ' . $this->_getLinkTitle($name, $url, $isImage) . ' [' . $url . ']';
	}

	function emaillink($address, $name = NULL) {
		$name = $this->_getLinkTitle($name, '', $isImage);
		$address = html_entity_decode(obfuscate($address),ENT_QUOTES,'UTF-8');
		if(empty($name)){
			$name = $address;
		} else if ( $name != $address) {
		    $name .= ' [' . $address . ']';
		}
		
		$this->doc .= ' ' . $name . ' ';
	}

	function internalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
	$height=NULL, $cache=NULL, $linking=NULL) {
		$this->doc .= ' ' . $title . ' ';
	}

	function externalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
	$height=NULL, $cache=NULL, $linking=NULL) {
		$this->doc .= ' ' . $title . ' ';
	}

	function table_close($pos = null){

		// Do all the Magic
		$lengths = array();

		for( $i=0; $i < count($this->table); $i++ ) {
			// Go though rows;
			for ( $ii=0; $ii < count($this->table[$i]['cells']); $ii++) {
				// Go Through Columns;
				//print "Row: $ii; Len: " . $this->_counter['rowlencount'][$ii];
				$text = $this->table[$i]['cells'][$ii]['text'];

				switch( $this->table[$i]['cells'][$ii]['align'] ) {
					case 'center'  :	$LEFT = $RIGHT = floor($this->_counter['rowlencount'][$ii] - PhpString::strlen($text) / 2);
										if ( $LEFT + $RIGHT + PhpString::strlen($text) < $this->_counter['rowlencount'][$ii]) {
											$RIGHT += $this->_counter['rowlencount'][$ii] - $LEFT + $RIGHT + PhpString::strlen($text);
										}
										break;
					case 'right'   :	$RIGHT = 1;
										$LEFT = $this->_counter['rowlencount'][$ii] - PhpString::strlen($text);
										break;
					default        : 	$LEFT = 1;
										$RIGHT = $this->_counter['rowlencount'][$ii] - PhpString::strlen($text);
				}
				
				if ( $LEFT < 0 ) $LEFT = 1;
				if ( $RIGHT < 0 ) $RIGHT = 1;
				if ( $ii == count($this->table[$i]['cells'])-1 ) $RIGHT = 0; // Last Element needs no spaces
				
				$this->doc .= str_repeat(' ', $LEFT). $this->_xmlEntities($text). str_repeat(' ', $RIGHT);
			}
				
			$this->doc .= DOKU_LF;
		}

		$this->doc .= DOKU_LF;
		$this->table = array();
	}

	function tablerow_open(){
		$this->table[] = array();
		$this->_counter['rowstart'] = PhpString::strlen($this->doc);
		$this->table[count($this->table)-1] = array();

		$this->_counter['tmp_cellcount'] = 0;
	}

	function tablerow_close() {

		// Cell count per row
		unset($this->_counter['rowstart']);
		$this->doc .= DOKU_LF;
	}

	function tableheader_open($colspan = 1, $align = NULL, $rowspan = 1){
		$this->tablecell_open($colspan, $align, $rowspan, 1);
	}

	function tableheader_close(){
		$this->tablecell_close();
	}

	function tablecell_open($colspan = 1, $align = NULL, $rowspan = 1, $isHeader=false){

		$this->_counter['cellstart'] = PhpString::strlen($this->doc);
		$cell = array	(	'colspan' => $colspan,
							'rowspan' => $rowspan,
							'align' => $align,
							'isHeader' => $isHeader
		);

		$this->table[count($this->table)-1]['cellcount'] += $colspan;
		$this->table[count($this->table)-1]['cells'][] = $cell;
	}

	function tablecell_close(){
		if ( $this->table[count($this->table)-1]['cellcount'] > $this->_counter['maxcellcount'] )
			$this->_counter['maxcellcount'] = $this->table[count($this->table)-1]['cellcount'];

		$text = PhpString::trim(PhpString::substr($this->doc, $this->_counter['cellstart']-1));
		if ( PhpString::strlen($text) +2 > $this->_counter['rowlencount'][count($this->table[count($this->table)-1]['cells'])-1] )
			$this->_counter['rowlencount'][count($this->table[count($this->table)-1]['cells'])-1] = PhpString::strlen($text) +2;

		$this->table[count($this->table)-1]['cells'][count($this->table[count($this->table)-1]['cells'])-1]['text'] = $this->_xmlEntities($text);
		$this->doc = PhpString::substr($this->doc, 0, $this->_counter['cellstart']);
		unset($this->_counter['cellstart']);
	}

	/**
	 * Creates a linkid from a headline
	 *
	 * @param PhpString::string  $title   The headline title
	 * @param boolean $create  Create a new unique ID?
	 * @author Andreas Gohr <andi@splitbrain.org>
	 */
	function _headerToLink($title,$create=false) {
		$title = str_replace(':','',cleanID($title));
		$title = PhpString::ltrim($title,'0123456789._-');
		if(empty($title)) $title='section';

		return $title;
	}

	function _getLinkTitle($title, $default, & $isImage, $id=NULL) {
		global $conf;

		$isImage = false;
		if ( is_null($title) ) {
			if ($conf['useheading'] && $id) {
				$heading = p_get_first_heading($id,true);
				if ($heading) {
					return $heading;
				}
			}
			return $this->_xmlEntities($default);
		} else if ( is_array($title) ) {
			return $this->_xmlEntities($title['title']);
		} else {
			return $this->_xmlEntities($title);
		}
	}

	function _xmlEntities($utf8_string) {
	 	return $utf8_string;
	}
	
	function _formatLink($link){
		if(!empty($link['name']))
		return $link['name'];
		elseif(!empty($link['title']))
		return $link['title'];
		return $link['url'];
	}

   function strSplit($text, $split = 1)
    {
        if (!is_string($text)) return false;
        if (!is_numeric($split) && $split < 1) return false;

        $len = strlen($text);

        $array = array();

        $i = 0;

        while ($i < $len)
        {
            $key = NULL;

            for ($j = 0; $j < $split; $j += 1)
            {
                $key .= $text[$i];

                $i += 1;
            }

            $array[] = $key;
        }

        return $array;
    }

    function UTF8ToHTML($str)
    {
        $search = array();
        $search[] = "/([\\xC0-\\xF7]{1,1}[\\x80-\\xBF]+)/e";
        $search[] = "/&#228;/";
        $search[] = "/&#246;/";
        $search[] = "/&#252;/";
        $search[] = "/&#196;/";
        $search[] = "/&#214;/";
        $search[] = "/&#220;/";
        $search[] = "/&#223;/";

        $replace = array();
        $replace[] = '$this->_UTF8ToHTML("\\1")';
        $replace[] = "ae";
        $replace[] = "oe";
        $replace[] = "ue";
        $replace[] = "Ae";
        $replace[] = "Oe";
        $replace[] = "Ue";
        $replace[] = "ss";

        $str = preg_replace($search, $replace, $str);

        return $str;
    }

    function _UTF8ToHTML($str)
    {
        $ret = 0;

        foreach(($this->strSplit(strrev(chr((ord($str[0]) % 252 % 248 % 240 % 224 % 192) + 128).substr($str, 1)))) as $k => $v)
            $ret += (ord($v) % 128) * pow(64, $k);
        return "&#".$ret.";";
    }
}