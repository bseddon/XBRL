<?php

/**
 * XPath 2.0 for PHP
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

namespace XBRL\functions;

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\FactVariableBinding;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\FactValues;
use XBRL\Formulas\Exceptions\FormulasException;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns the specified uncovered aspect for use in XPath expressions of a consistency assertion,
 * value assertion, formula aspect rule, or generic message XPath expression. The function is not
 * applicable to variable-set variable evaluation and filter expressions.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPath2NodeIterator	Returns the uncovered aspect requested, or an empty sequence if not applicable.
 *
 * The function implemented by this function has two arguments:
 *
 * aspect		xs:token			The aspect value MUST be one of: concept entity-identifier location period unit
 * 									complete-segment complete-scenario non-XDT-segment non-XDT-scenario dimension
 * 									See output, below, for the value of the function for each aspect.
 *
 * dimension	xs:QName?			For a dimension aspect, this parameter MUST be provided and is the QName of the dimension.
 * 									For other aspects, this parameter may be omitted, or may be supplied as an empty sequence.
 *
 * @throws xffe:invalidFunctionUse	This error MUST be thrown if the this function is referenced in an XPath expression
 * 									invoked in variable-set variable evaluation, such as by a generalVariable or filter
 * 									XPath expression. A formula processor MAY detect this error as a static compilation-time
 * 									error if it this function is referenced in variable-set evaluation expressions
 * 									(by generalVariable or filters).
 * 									A formula processor MUST detect this error as a dynamic run-time error if it this
 * 									function is referenced in variable-set evaluation expressions (by generalVariable or
 * 									filters), such as during execution of an XPath or other language implementation of
 * 									custom functions that were invoked dynamically in variable-set evaluation expressions
 * 									(by generalVariable or filters).
 * 									This error MUST be thrown if the this function is referenced in an XPath expression
 * 									invoked in an XPath function related to an existence assertion, including if referenced
 * 									in an XPath expression from generic messages relating to an existence assertion.
 * 									This error MUST be thrown if implicit filtering is 'false'.
 *
 * The following paragraphs provide the output type for each aspect (where present).
 *
 * If the aspect value is 'concept' then the output MUST be of type xs:QName and is the uncovered concept aspect value.
 *
 * If the aspect value is 'entity-identifier' then the output MUST be of type element(xbrli:identifier) and is the
 * uncovered entity identifier element aspect value.
 *
 * If the aspect value is 'location' then the output MUST be of type element() and is the uncovered location aspect value.
 * This element() MUST be a fact item or tuple that is a descendant of the xbrli:xbrl instance element.
 *
 * If the aspect value is 'period' then the output MUST be of type element(xbrli:period) and is the uncovered period
 * element aspect value. If there is no such aspect then an empty sequence is output.
 *
 * If the aspect value is 'unit' then
 * the output MUST be of type element(xbrli:unit)? and is the uncovered unit element aspect value.  If there is no such
 * aspect then an empty sequence is output.
 *
 * If the aspect value is 'complete-segment' then the output MUST be of type element(xbrli:segment)? and is the uncovered
 * complete-segment element aspect value, in which the elements and their descendant nodes have the appropriate type based
 * on the Post Schema Validation Infoset. If there is no such aspect then an empty sequence is output.
 *
 * If the aspect value is 'complete-scenario' then the output MUST be of type element(xbrli:scenario)? and is the uncovered
 * complete-scenario element aspect value, in which the elements and their descendant nodes have the appropriate type based
 * on the Post Schema Validation Infoset. If there is no such aspect then an empty sequence is output.
 *
 * If the aspect value is 'non-XDT-segment' then the output MUST be of type element()* and is the uncovered non-XDT-segment
 * element nodes, in which those non-XDT segment element nodes and their descendant nodes have the appropriate type based on
 * the Post Schema Validation Infoset. Note that complete-segment returns the segment node and non-XDT-segment returns a
 * collection of non-XDT child nodes of the segment. If there is no such aspect then an empty sequence is output.
 *
 * If the aspect value is 'non-XDT-scenario' then the output MUST be of type element()* and is the uncovered non-XDT-scenario
 * element nodes, in which those non-XDT scenario element nodes and their descendant nodes have the appropriate type based
 * on the Post Schema Validation Infoset. Note that complete-scenario returns the scenario node and non-XDT-scenario returns
 * a collection of non-XDT child nodes of the scenario. If there is no such aspect then an empty sequence is output.
 *
 * If the aspect value is 'dimension', and the dimension is an explicit dimension, then the output MUST be of type xs:QName
 * and is the member of the uncovered explicit dimension aspect value. If the aspect value is 'dimension', and the dimension
 * is a typed dimension, then the output MUST be of type element(xbrldi:typedMember)? and is the child element of the segment
 * or scenario that contains the typed dimension value if there is a value for the dimension in either the segment or scenario
 * bound to the uncovered aspect and returns the empty sequence otherwise. The data type of the child and its descendant
 * elements have the appropriate type based on the Post Schema Validation Infoset.
 *
 * If the aspect value is 'dimension' and either there are no dimensional aspects, or the dimension QName input is an empty
 * sequence, or it does not represent a dimension aspect that is uncovered, then an empty sequence is output.
 */
function getUncoveredAspect( $context, $provider, $args )
{
	try
	{
		/**
		 * @var string $token
		 */
		$token = CoreFuncs::CastArg( $context, $args[0], SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::Token, XmlTypeCardinality::One ) );

		if ( count( $args ) == 2 )
		{
			/**
			 * @var QNameValue $qname
			 */
			$qname = CoreFuncs::CastArg( $context, $args[1], SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::QName, XmlTypeCardinality::One ) );
		}

		$aspect = null;

		switch ( $token )
		{
			case "concept":
				$aspect = ASPECT_CONCEPT;
				break;

			case "entity-identifier":
				$aspect = ASPECT_ENTITY_IDENTIFIER;
				break;

			case "location":
				$aspect = ASPECT_LOCATION;
				break;

			case "period":
				$aspect = ASPECT_PERIOD;
				break;

			case "unit":
				$aspect = ASPECT_UNIT;
				break;

			case "complete-segment":
				$aspect = ASPECT_COMPLETE_SEGMENT;
				break;

			case "complete-scenario":
				$aspect = ASPECT_COMPLETE_SCENARIO;
				break;

			case "non-XDT-segment":
				$aspect = ASPECT_NON_XDT_SEGMENT;
				break;

			case "non-XDT-scenario":
				$aspect = ASPECT_NON_XDT_SCENARIO;
				break;

			case "dimension":
				$aspect = ASPECT_DIMENSIONS;
				break;

			default:
				throw XPath2Exception::asDefault( "The aspect token '$token' is not valid", null );
		}

		// To use this function implicit filtering MUST be enabled
		if ( ! isset( $context->variableSet ) )
		{
			\XBRL_Log::getInstance()->formula_validation( "uncovered-aspect", "The uncovered-aspects function can only be used consistency/value assertion or formula result or aspect rule expression.",
				array(
					'error' => 'xffe:invalidFunctionUse'
				) );
		}

		/**
		 * @var VariableSet $variableSet
		 */
		$variableSet = $context->variableSet;

		// To use this function implicit filtering MUST be enabled
		if ( ! $variableSet->implicitFiltering )
		{
			\XBRL_Log::getInstance()->formula_validation( "uncovered-aspect", "Implicit filtering MUST be enabled to use the 'xff:uncovered-aspect' function.",
				array(
					'error' => 'xffe:invalidFunctionUse'
				) );
		}

		if ( $aspect == ASPECT_DIMENSIONS )
		{
			// Check the QName really is a dimension

		}
		else
		{
			foreach ( $variableSet->factVariableBindings as $qname => /** @var FactVariableBinding $binding */ $binding )
			{
				if ( $binding->isFallback || ! in_array( $aspect, $binding->aspectsDefined ) || in_array( $aspect, $binding->aspectsCovered ) ) continue;
				return aspectValue( $context, $provider, $binding->uncoveredAspectFacts[ $aspect ], $aspect );
			}

			return Undefined::getValue();
		}
	}
	catch ( FormulasException $ex)
	{
		if ( $ex->error == "xffe:invalidFunctionUse" )
		{
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure, $ex );

}

/**
 * Get the required value for the requested aspect
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param DOMXPathNavigator $fact
 * @param string $aspect
 */
function aspectValue( $context, $provider, $fact, $aspect )
{
	if ( is_null( $fact ) )
	{
	    return aspect == ASPECT_DIMENSIONS ? set() : null;
	}

	switch ( $aspect )
	{
		case ASPECT_CONCEPT:
			// Checked
			return QNameValue::fromXPathNavigator( $fact );

		case ASPECT_LOCATION:
			$parent = $fact->CloneInstance();
			$parent->MoveToParent();
			return $parent;
	}

	$contextRef = FactValues::getContextRef( $fact );

	if ( FactValues::isTuple( $fact ) || ! $contextRef )
	{
		return null;
	}

	switch ( $aspect )
	{
		case ASPECT_ENTITY_IDENTIFIER:
			require_once 'getEntityIdentifier.php';
			return getEntityIdentifier( $context, $provider, array( $fact ) );

		case ASPECT_PERIOD:
			require_once 'getPeriod.php';
			// Checked
			return getPeriod( $context, $provider, array( $fact ) );

		case ASPECT_UNIT:
			require_once 'getUnit.php';
			return getUnit( $context, $provider, array( $fact ) );

		case ASPECT_COMPLETE_SEGMENT:
			require_once 'getContextSegment.php';
			return getContextSegment( $context, $provider, array( $fact ) );

		case ASPECT_COMPLETE_SCENARIO:
			require_once 'getContextScenario.php';
			return getContextScenario( $context, $provider, array( $fact ) );

		case ASPECT_NON_XDT_SEGMENT:
			require_once 'getContextScenario.php';
			return getContextScenario( $context, $provider, array( $fact ) );

		case ASPECT_NON_XDT_SCENARIO:
			break;

		case ASPECT_DIMENSIONS:
			break;

		default:
			throw XPath2Exception::asDefault( "The aspect token '$token' is not valid", null );
	}

	// elif aspect == Aspect.LOCATION_RULE:
	//	return fact
	// elif fact.isTuple or fact.context is None:
	// 	return None     #subsequent aspects don't exist for tuples
//	# context is known to be not None after here
//	elif aspect == Aspect.PERIOD:
//		return fact.context.period
//	elif aspect == Aspect.PERIOD_TYPE:
//		if fact.context.isInstantPeriod: return "instant"
//		elif fact.context.isStartEndPeriod: return "duration"
//		elif fact.context.isForeverPeriod: return "forever"
//		return None
//	elif aspect == Aspect.INSTANT:
//		return fact.context.instantDatetime
//	elif aspect == Aspect.START:
//		return fact.context.startDatetime
//	elif aspect == Aspect.END:
//		return fact.context.endDatetime
//	elif aspect == Aspect.ENTITY_IDENTIFIER:
//		return fact.context.entityIdentifierElement
//	elif aspect == Aspect.SCHEME:
//		return fact.context.entityIdentifier[0]
//	elif aspect == Aspect.VALUE:
//		return fact.context.entityIdentifier[1]
//	elif aspect in (Aspect.COMPLETE_SEGMENT, Aspect.COMPLETE_SCENARIO,
//					Aspect.NON_XDT_SEGMENT, Aspect.NON_XDT_SCENARIO):
//		return fact.context.nonDimValues(aspect)
//	elif aspect == Aspect.DIMENSIONS:
//		return fact.context.dimAspects(self.xpCtx.defaultDimensionAspects)
//	elif isinstance(aspect, QName):
//		return fact.context.dimValue(aspect)
//	elif fact.unit is not None:
//		if aspect == Aspect.UNIT:
//			return fact.unit
//		elif aspect in (Aspect.UNIT_MEASURES, Aspect.MULTIPLY_BY, Aspect.DIVIDE_BY):
//			return fact.unit.measures
	return null;

}