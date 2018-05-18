<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace XBRL\Formulas\Resources\Variables;

use lyquidity\xml\MS\XmlNamespaceManager;

/**
 * A class to process a general variable definitions
 */
class Signature extends Variable
{
	/**
	 * A list of the defined inputs
	 * @var array $inputs
	 */
	public $inputs = array();

	/**
	 * The name of the function stored as an array with localname, prefix and namespace
	 * @var array $name
	 */
	public $name = array();

	/**
	 * The type of the value produced
	 * @var string $output
	 */
	public $output = "xs:anyType";

	/**
	 * A list of implementations for this signature
	 * @var array $implementations
	 */
	public $implementations = array();

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$result['type'] = 'customfunction';
		$result['customfunctionType'] = 'signature';

		$this->inputs = array();
		$inputInstance = new Input();
		foreach ( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_VARIABLE ] )->input as $input )
		{
			$this->inputs[] = $inputInstance->process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $input, null, $log );
		}

		$result['inputs'] = $this->inputs;

		$attributes = $node->attributes();
		$namespaces = $node->getDocNamespaces( true );

		if ( property_exists( $attributes, "name" ) )
		{
			$name = trim( $attributes->name );
			$qName = strpos( $name, ":" )
				? qname( $name, $namespaces )
				: new QName( "", null, $name );

			$this->name = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);

			if ( $this->name['namespace'] == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ] )
			{
				\XBRL_Log::getInstance()->formula_validation( 'Custom function', 'Custom function name has a namespace reserved for functions in the function registry (xfi)', array(
					'signature' => $this->name['originalPrefix'] . ":" . $this->name['name'],
					'error' => 'xbrlve:noProhibitedNamespaceForCustomFunction'
				) );
			}
		}

		$result['name'] = $this->name;

		if ( property_exists( $attributes, "output" ) )
		{
			$this->output = trim( $attributes->output );
		}

		$result["output"] = $this->output;

		return $result;
	}

	/**
	 * Validate the signature values
	 * @param XmlNamespaceManager $nsMgr
	 * @return bool
	 */
	public function validate( $variableSet, $nsMgr )
	{
		if ( ! $this->name )
		{
			\XBRL_Log::getInstance()->formula_validation( 'Custom function', 'Custom function name is not defined', array(
				'error' => 'xbrlve:noNameForCustomFunction'
			) );
			return false;
		}

		$qname = $this->getQName();

		if ( $qname->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ] )
		{
			\XBRL_Log::getInstance()->formula_validation( 'Custom function', 'Custom function name has a namespace reserved for functions in the function registry (xfi)', array(
				'signature' => $qname->prefix . ":" . $qname->localName,
				'error' => 'xbrlve:noProhibitedNamespaceForCustomFunction'
			) );
		}

		if ( $this->output )
		{
			$outputQName = qname( $this->output, $nsMgr->getNamespaces() );
			if ( $outputQName && ! \XBRL::startsWith( $this->output, "xs:" ) && $outputQName->namespaceURI != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] )
			{
				\XBRL_Log::getInstance()->formula_validation( 'Custom function', 'Custom function outut type is not valid', array(
					'type' => $this->output ? $this->output : "missing",
					'error' => 'xbrlve:invalidDatatypeInCustomFunctionSignature'
				) );
			}
		}

		return true;
	}
}
