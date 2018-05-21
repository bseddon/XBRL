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

namespace XBRL\Formulas\Resources;

use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\XPath2Expression;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\xpath\XPathNodeType;
use XBRL\Formulas\Resources\Assertions\ExistenceAssertion;

/**
 * Base class for all resources
 */
class Resource
{
	/**
	 * Populate an instance with values from an array
	 * @param array $source An array of values to add to the class
	 * @return Resource
	 */
	public static function fromArray( $source )
	{
		try
		{
			$classname = get_called_class();

			if ( ! class_exists( $classname ) )
			{
				\XBRL_Log::getInstance()->err( "The class '$classname' does not exist" );
				return null;
			}

			$instance = new $classname();

			if ( ! $instance instanceof Resource )
			{
				\XBRL_Log::getInstance()->err( "The class instance being created MUST inherit from 'XBRL\\Formulas\\Resources\\Resources'" );
				return null;
			}

			$has = get_object_vars( $instance );
			foreach ( $has as $name => $oldValue )
			{
	        	if ( ! isset( $source[ $name ] ) ) continue;
	        	$instance->$name = $source[ $name ];
			}

			return $instance;
		}
		catch( \Exception $ex )
		{
			return null;
		}
	}

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
		// throw new InvalidOperationException( "The processResource method of the base resource class should not be called" );

		$attributes = $node->attributes();

		$result = array( 'label' => $label );

		if ( property_exists( $attributes, "id" ) )
		{
			$result['id'] = trim( $attributes->id );
		}

		return $result;
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array();
	}

	/**
	 * Allow resources to be validated
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		return true;
	}

	/**
	 * Allows a resource to return a list of the qnames of any variable references they contain
	 * @return array[\QName]
	 */
	public function getVariableRefs()
	{
		return array();
	}

	/**
	 * Convert an instance to an array
	 * @throws InvalidOperationException
	 */
	public function toArray()
	{
		return get_object_vars( $this );
	}

	/**
	 * Evaluate an expression and return a node iterator
	 * @param VariableSet $variableSet
	 * @param string $expression
	 * @param array $vars (optional)
	 * @param array $contextItems (optional) A list of objects to add to the context
	 * @param XPath2ItemProvider $provider (optional) A specific provider to use.  ExistenceAssertion passes one.
	 * @return XPath2NodeIterator
	 */
	public function evaluateXPath( $variableSet, $expression, $vars = array(), $contextItems = array(), $provider = null )
	{
		$expression = XPath2Expression::Compile( $expression, $variableSet->nsMgr );
		return $this->evaluateXPathExpression( $variableSet, $expression, $vars, $contextItems, $provider );
	}

	/**
	 * Evaluate an expression and return a node iterator
	 * @param VariableSet $variableSet
	 * @param XPath2Expression $xpathExpression
	 * @param array $vars (optional)
	 * @param array $contextItems (optional) A list of objects to add to the context
	 * @param XPath2ItemProvider $provider (optional) A specific provider to use.  ExistenceAssertion passes one.
	 * @return XPath2NodeIterator
	 */
	public function evaluateXPathExpression( $variableSet, $xpathExpression, $vars = array(), $contextItems = array(), $provider = null )
	{
		$xbrlInstance = $variableSet->xbrlInstance;

		if ( $xbrlInstance && is_file( $xbrlInstance->getDocumentName() ) && ! $variableSet->changedDirectory )
		{
			chdir( dirname( $xbrlInstance->getDocumentName() ) );
			$variableSet->changedDirectory = true;
		}

		$nav = null;
		if ( $xbrlInstance )
		{
			$dom = dom_import_simplexml( $xbrlInstance->getInstanceXml() );
			$nav = new DOMXPathNavigator( $dom );
			$nav->MoveToRoot();
			$nav->MoveToChild( XPathNodeType::Element );

			if ( ! $provider )
			{
				$provider = new NodeProvider( $nav );
			}
		}

		$xpathExpression->AddToContext( "xbrlInstance", $xbrlInstance );
		$xpathExpression->AddToContext( "xbrlTaxonomy", $variableSet->xbrlTaxonomy );
		$xpathExpression->AddToContext( "base", $variableSet->base );
		if ( $contextItems )
		foreach ( $contextItems as $contextItemName => $contextItem )
		{
			$xpathExpression->AddToContext( $contextItemName, $contextItem );
		}
		$result = $xpathExpression->EvaluateWithVars( $provider, $vars );

		return $result;
	}
}