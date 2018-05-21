<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *		 |___/	  |_|					 |___/
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

namespace XBRL\Formulas;

use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\Resources\Variables\VariableSet;

/**
 * A collection of static functions to return values for a DOMXPathNavigator
 */
class FactValues
{

	/**
	 * Get the context ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return string|bool
	 * @throws \Exception
	 */
	public static function getContextRef( $fact )
	{
		// return false;

		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::getContextRef" );
		}

		return $contextRef = $fact->GetAttribute( "contextRef", null );

		// $fact = $fact->CloneInstance();
		// if ( $fact->MoveToAttribute( "contextRef", null ) )
		// {
		// 	return $fact->getValue();
		// }
		//
		// return false;
	}

	/**
	 * Get the unit ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return string|bool
	 * @throws \Exception
	 */
	public static function getUnitRef( $fact )
	{
		// return false;

		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::getUnitRef" );
		}

		return $contextRef = $fact->GetAttribute( "unitRef", null );

		// $fact = $fact->CloneInstance();
		// if ( $fact->MoveToAttribute( "unitRef", null ) )
		// {
		// 	return $fact->getValue();
		// }
        //
		// return false;
	}

	/**
	 * Get the decimals ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return string|bool
	 * @throws \Exception
	 */
	public static function getDecimals( $fact )
	{
		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::getDecimals" );
		}

		$fact = $fact->CloneInstance();
		if ( $fact->MoveToAttribute( "decimals", null ) )
		{
			return $fact->getValue();
		}

		return false;
	}

	/**
	 * Get the precision ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return string|bool
	 * @throws \Exception
	 */
	public static function getPrecision( $fact )
	{
		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::getPrecision" );
		}

		$fact = $fact->CloneInstance();
		if ( $fact->MoveToAttribute( "precision", null ) )
		{
			return $fact->getValue();
		}

		return false;
	}

	/**
	 * Get flag indicating whether $fact is numeric or not
	 * @param DOMXPathNavigator $fact
	 * @return bool
	 * @throws \Exception
	 */
	public static function isNumeric( $fact )
	{
		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::isNumeric" );
		}

		$schemaType = $fact->getSchemaType();
		$type = "{$schemaType->QualifiedName->prefix}:{$schemaType->QualifiedName->localName}";
		// BMS 2018-04-09 Extra test candidate not required any more but it doesn't hurt.
		$result = \XBRL_Types::getInstance()->resolvesToBaseType( $type, array( 'xs:decimal', 'xsd:decimal' ) );

		return $result;
	}

	/**
	 * Get flag indicating whether $fact is numeric or not
	 * @param DOMXPathNavigator $fact
	 * @return bool
	 * @throws \Exception
	 */
	public static function isNull( $fact )
	{
		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::isNull" );
		}

		if ( ! $fact->getHasAttributes() ) return false;

		$attributes = $fact->CloneInstance();
		return $attributes->MoveToAttribute( "nil", \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_INSTANCE ] );
	}

	/**
	 * Get the precision ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return bool
	 * @throws \Exception
	 */
	public static function isItem( $fact )
	{
		$prefix = \XBRL_Types::getInstance()->getPrefixForNamespace( $fact->getNamespaceURI() );
		$name = "{$prefix}:{$fact->getLocalName()}";
		$element = \XBRL_Types::getInstance()->getElement( $name);
		if ( ! $element || ! isset( $element['substitutionGroup'] ) ) return false;

		$result = \XBRL_Types::getInstance()->resolveToSubstitutionGroup( $element['substitutionGroup'] , array( "xbrli:item" ) );
		return $result;
	}

	/**
	 * Get the precision ref from XBRL instance
	 * @param DOMXPathNavigator $fact
	 * @return bool
	 * @throws \Exception
	 */
	public static function isTuple( $fact )
	{
		$prefix = \XBRL_Types::getInstance()->getPrefixForNamespace( $fact->getNamespaceURI() );
		$name = "{$prefix}:{$fact->getLocalName()}";
		$element = \XBRL_Types::getInstance()->getElement( $name);
		if ( ! $element || ! isset( $element['substitutionGroup'] ) ) return false;

		$result = \XBRL_Types::getInstance()->resolveToSubstitutionGroup( $element['substitutionGroup'] , array( "xbrli:tuple" ) );
		return $result;
	}

	/**
	 * Get the schema type for the fact
	 * @param DOMXPathNavigator $fact
	 * @return string|bool
	 * @throws \Exception
	 */
	public static function getType( $fact )
	{
		if ( ! $fact instanceof DOMXPathNavigator )
		{
			throw new \Exception( "Invalid fact variable passed to FactValues::isItem" );
		}

		return \XBRL_Types::getInstance()->getTypeForDOMNode( $fact->getUnderlyingObject() );
	}

	/**
	 * Return the dimensions associated with the fact.  This will look up the context ref.
	 * @param VariableSet $variableSet
	 * @param DOMXPathNavigator $fact
	 * @return array An array of the dimension (keys) and members (values)
	 */
	public static function getDimensionsForFact( $variableSet, $fact )
	{
		$contextRef = FactValues::getContextRef( $fact );
		return FactValues::getDimensionsInContextRef( $variableSet, $contextRef );
	}

	/**
	 * Return a list of dimensions list in the segment or scenarios of the context or element
	 * @param VariableSet $variableSet
	 * @param array $contextRef
	 * @return array An array of the dimension (keys) and members (values)
	 */
	public static function getDimensionsInContextRef( $variableSet, $contextRef )
	{
		if ( ! $contextRef ) return array();
		$context = $variableSet->xbrlInstance->getContext( $contextRef );
		if ( ! $context ) return array();
		return FactValues::getDimensionsInContext( $variableSet, $context );
	}

	/**
	 * Return a list of dimensions list in the segment or scenarios of the context or element
	 * @param VariableSet $variableSet
	 * @param array $context
	 * @return array An array of the dimension (keys) and members (values)
	 */
	public static function getDimensionsInContext( $variableSet, $context )
	{
		$result = array();

		if ( isset( $context['segment'] ) )
		{
			$result = array_merge( $result, FactValues::processSegmentForExplicitDimensions( $variableSet, $context['segment'] ) );
			$result = array_merge( $result, FactValues::processSegmentForTypedDimensions( $variableSet, $context['segment'] ) );
		}

		if ( isset( $context['scenario'] ) )
		{
			$result = array_merge( $result, FactValues::processSegmentForExplicitDimensions( $variableSet, $context['scenario'] ) );
			$result = array_merge( $result, FactValues::processSegmentForTypedDimensions( $variableSet, $context['scenario'] ) );
		}

		$entity = $context['entity'];

		if ( isset( $entity['segment'] ) )
		{
			$result = array_merge( $result, FactValues::processSegmentForExplicitDimensions( $variableSet, $entity['segment'] ) );
			$result = array_merge( $result, FactValues::processSegmentForTypedDimensions( $variableSet, $entity['segment'] ) );
		}

		if ( isset( $entity['scenario'] ) )
		{
			$result = array_merge( $result, FactValues::processSegmentForExplicitDimensions( $variableSet, $entity['scenario'] ) );
			$result = array_merge( $result, FactValues::processSegmentForTypedDimensions( $variableSet, $entity['scenario'] ) );
		}

		return $result;
	}

	/**
	 * Get the explicit dimension(s) for a segment
	 * @param VariableSet $variableSet
	 * @param array $segment
	 * @return array An array of the dimension (keys) and members (values)
	 */
	private static function processSegmentForExplicitDimensions( $variableSet, $segment )
	{
		$results = array();

		if ( isset( $segment['explicitMember'] ) )
		{
			foreach ( $segment['explicitMember'] as $explicitMember )
			{
				$dimension = $explicitMember['dimension'];
				$member = $explicitMember['member'];

				$dimensionQName = qname( $dimension, $variableSet->xbrlInstance->getInstanceNamespaces() );
				$memberQName = qname( $member, $variableSet->xbrlInstance->getInstanceNamespaces() );

				$results[ $dimensionQName->clarkNotation() ][] = $memberQName->clarkNotation();
			}
		}

		return $results;
	}

	/**
	 * Get the typed dimension(s) for a segment
	 * @param VariableSet $variableSet
	 * @param array $segment
	 * @return array An array of the dimension (keys) and members (values)
	 */
	private static function processSegmentForTypedDimensions( $variableSet, $segment )
	{
		$results = array();

		if ( isset( $segment['typedMember'] ) )
		{
			foreach ( $segment['typedMember'] as $typedMember )
			{
				$dimension = $typedMember['dimension'];
				$member = $typedMember['member'];

				$dimensionQName = qname( $dimension, $variableSet->xbrlInstance->getInstanceNamespaces() );
				$memberQName = qname( $member, $variableSet->xbrlInstance->getInstanceNamespaces() );

				$results[ $dimensionQName->clarkNotation() ] = array();
			}
		}

		return $results;
	}

}