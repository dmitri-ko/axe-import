<?php
/**
 * Post type Admin API file.
 *
 * @package Axe Import/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin API class.
 */
class Axe_Import_SimpleXMLElement extends SimpleXMLElement
{
    /**
     * Add SimpleXMLElement code into a SimpleXMLElement
     *
     * @param SimpleXMLElement $append
     */
    public function appendXML(  $append, $node_name = '' )
    {
        if ($append) {
            if (strlen(trim((string)$append)) == 0) {
				if ($node_name){
					$xml = $this->addChild($node_name);
				} else {
					$xml = $this->addChild($append->getName());
				}
               
            } else {
                $xml = $this->addChild($append->getName(), htmlspecialchars( (string)$append) );
            }

            foreach ($append->children() as $child) {
                $xml->appendXML($child);
            }

            foreach ($append->attributes() as $n => $v) {
                $xml->addAttribute($n, $v);
            }
        }
    }
}