<?php
/**
 * Extended SimpleXMLElement file.
 *
 * @package Axe Import/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extended SimpleXMLElement class
 *
 * @category Class
 */
class Axe_Import_SimpleXMLElement extends SimpleXMLElement {

	/**
	 * Add SimpleXMLElement code into a SimpleXMLElement
	 *
	 * @param SimpleXMLElement $append node to append.
	 * @param string           $node_name name of apending node.
	 */
	public function appendXML( $append, $node_name = '' ) {

		if ( $append ) {
			if ( strlen( trim( (string) $append ) ) === 0 ) {
				if ( $node_name ) {
					$xml = $this->addChild( $node_name );
				} else {
					$xml = $this->addChild( $append->getName() );
				}
			} else {
				$xml = $this->addChild( $append->getName(), htmlspecialchars( (string) $append ) );
			}

			foreach ( $append->children() as $child ) {
				/**
				 * Variable for storing current node.
				 *
				 * @var Axe_Import_SimpleXMLElement $xml current node.
				*/
				$xml->appendXML( $child );
			}

			foreach ( $append->attributes() as $n => $v ) {
				$xml->addAttribute( $n, $v );
			}
		}
	}
}
