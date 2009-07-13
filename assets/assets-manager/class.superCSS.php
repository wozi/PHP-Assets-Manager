<?php 
/**
 * Class superCSS
 * Adds functionalities to the CSS files by adding support of variables etc.
 * 
 * @author nico wozniak
 * @version 0.7.7.7.1 : support only @variables
 */
abstract class superCSS {
	
	/**
	 * parse ()
	 * Parse some CSS content and replace the variables etc
	 * @param $cssContent
	 * @return {Sring}
	 */
	public function parse ($cssContent) {
		$parser = new superCSSParser ();
		return $parser->parse ($cssContent);
	}
}

/**
 * Class superCSSParser
 * Parse a CSS file for @variables
 * 
 * @author nico
 * @version 0.7.7.7 : support only @variables
 */
class superCSSParser {
	
	public function __construct () {
	}
	
	/**
	 * parse ()
	 * Parse some CSS content and replace the variables etc
	 * @param $cssContent
	 * @return {Sring}
	 */
	public function parse ($cssContent) {
		
		// get the variables
		$cssContent = $this->convertVariables ($cssContent);
		
		// @todo Everything!
		
		return $cssContent;
	}
	
	/**
	 * convertVariables ()
	 * Get the variable definitions and replace their content by their values
	 * @param $content
	 * @return unknown_type
	 */
	private function convertVariables ($content) {
		// search variable definitions (@var = value; OR @var : value;)
		preg_match_all ("/(@[a-zA-Z0-9]+) ?[=:]+ ?(.+?);/i", $content, $variables);
	//	var_dump ($variables);
		
		// now that we have the variables, we'll search for their names and replace them by their values
		for ($i = 0; $i < sizeof ($variables [1]); $i++) {
			// remove the variable definition
			$content = preg_replace ("/".$variables [1] [$i]." ?[=:]+ ?.+?;/i", '', $content);
			
			// replace
			$content = preg_replace ("/(".$variables [1] [$i].")/i", $variables [2] [$i], $content);
		}
		
		return $content;
	}
}
?>