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
 * @version 0.9
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

use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\XPath2Exception;

 /**
  * A class to process a fact variable definitions
  */
class Parameter extends Variable
{
	/**
	 * Parameter name
	 * @var string $name
	 */
	public $name = array();

	/**
	 * An optional expression that generates a value
	 * @var string $select
	 */
	public $select;

	/**
	 * Flag indicating the parameter is required
	 * @var bool $required
	 */
	public $required = false;

	/**
	 * The type of the parameter
	 * @var string $as
	 */
	public $as = null;

	/**
	 * A compiled version of the 'select' property
	 * @var XPath2Expression $expression
	 */
	public $expression;

	/**
	 * The result of the expression evaluation
	 * @var mixed $result
	 */
	public $result;

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

		$attributes = $node->attributes();

		if ( property_exists( $attributes, "name" ) )
		{
			$docNamespaces = $node->getDocNamespaces( true );
			$localNamespaces = $node->getDocNamespaces( true, false );

			$name = trim( $attributes->name );
			// $qName = strpos( $name, ":" )
			// 	? qname( $name, array_merge( $docNamespaces, $localNamespaces ) )
			// 	: new QName( "", null, $name );
			$qName = qname( $name, array_merge( $docNamespaces, $localNamespaces ) );

			$this->name = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);
		}

		$result['name'] = $this->name;

		if ( property_exists( $attributes, "select" ) ) // Optional
		{
			$this->select = trim( $attributes->select );
			$result["select"] = $this->select;
		}

		if ( property_exists( $attributes, "required" ) ) // Optional
		{
			$this->required = filter_var( $attributes->required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}

		$result["required"] = $this->required;

		if ( property_exists( $attributes, "as" ) )
		{
			$this->as = trim( $attributes->as );
		}

		$result["as"] = $this->as;

		$result = parent::storeVariable( $result, $localName );

		return $result;
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		if ( isset( $this->as ) && strlen( $this->as ) )
		{
			$asQname = qname( $this->as, $nsMgr->getNamespaces() );
			// if ( is_null( $asQname ) || \XBRL::startsWith( $this->as, "xs:" ) || $asQname->namespaceURI != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] )
			if ( $asQname && ! \XBRL::startsWith( $this->as, "xs:" ) && $asQname->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] )
			{
				\XBRL_Log::getInstance()->formula_validation( 'Variable parameter', 'Parameter type has namespace reserved for functions in the function registry (xfi)', array(
					'parameter' => $this->getQName()->clarkNotation(),
					'as' => $this->as,
					'error' => 'xbrlve:parameterTypeMismatch'
				) );

				return false;
			}
		}

		if ( isset( $this->select ) && strlen( $this->select ) )
		{
			try
			{
				$expression = XPath2Expression::Compile( $this->getSelectAsExpression(), $nsMgr );
				$this->expression = $expression;
			}
			catch( XPath2Exception $ex )
			{
				throw $ex;
			}
		}

		return true;
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return isset( $this->expression )
			? $this->expression->getParameterQNames()
			: array();
	}

	/**
	 * Return the select statement as a casted expression if an 'as' value is provided
	 */
	public function getSelectAsExpression()
	{
		return $this->select . ( ! empty( $this->as ) ? " cast as " . $this->as : "" );
	}
}
