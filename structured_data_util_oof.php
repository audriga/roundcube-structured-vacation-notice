<?php

/**
 * A utility class for working with structured data
 */
class structured_data_util_oof
{

	/**
	 * Extract JSON-LD (if any) from an HTML source
	 *
	 * @param string $html The HTML that we want to extract JSON-LD from
	 *
	 * @return string|null The JSON-LD as a string (if there's any). Otherwise null
	 */
	public static function html2jsonld_oof($html)
	{
	    $jsonLd = null;
	    $startScriptTagPos = strpos($html, '<script type="application/ld+json">');

	    if ($startScriptTagPos !== false) {
	        /**
	         * Remove anything before '<script type="application/ld+json">'
	         * from the HTML
	         *
	         * This ensures that we don't extract anything based on other
	         * script tags, placed before the one, containing the JSON-LD
	        */
	        $htmlMod = substr($html, $startScriptTagPos);

	        // Calculate the start and end positions of the script tag
	        $startScriptTagPos = strpos(
	            $htmlMod,
	            '<script type="application/ld+json">'
	        );
	        $endScriptTagPos = strpos($htmlMod, '</script>');

	        // Extract the JSON-LD by searching for it in the HTML string
	        $jsonLd = substr(
	            $htmlMod,
	            $startScriptTagPos + strlen('<script type="application/ld+json">'),
	            $endScriptTagPos - ($startScriptTagPos + strlen(
	                '<script type="application/ld+json">'
	            ))
	        );

	        // If we coulnd't extract anything, then set $jsonLd to null
	        if (!isset($jsonLd) || empty($jsonLd)) {
	            $jsonLd = null;
	        }
	    }
	    return $jsonLd;
	}

	/**
     * A utility function to extract the type of "context"
     * that a JSON-LD with structured data has
     *
     * @param string $jsonLd The JSON-LD as a string
     *
     * @return string The context type
     */
	public static function get_context_type($jsonLd)
    {
        // Return an empty context type if the JSON-LD string is not set or empty
        if (!isset($jsonLd) || empty($jsonLd)) {
            return '';
        }

        // Decode the JSON-LD
        $jsonLd = json_decode($jsonLd, true);
    
        // Get the JSON-LD's @type property
        $jsonLdType = $jsonLd['@type'];

        // Return an empty context type if the JSON-LD's @type is not set or empty
        if (!isset($jsonLdType) || empty($jsonLdType)) {
            return '';
        }

        // Return the appropriate context type based on @type's value
        switch ($jsonLdType) {
            case 'OutOfOffice':
                return 'outOfOffice';
            
            default:
                return '';
        }
    }	
}
