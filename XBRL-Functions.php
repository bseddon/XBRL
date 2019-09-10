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

use lyquidity\XPath2\FunctionTable;
use lyquidity\XPath2\XPath2ResultType;
use lyquidity;

$functionTable = FunctionTable::getInstance();
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContext.php";
	return getContext( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "unit", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUnit.php";
	return getUnit( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "unit-numerator", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUnitNumerator.php";
	return getUnitNumerator( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "unit-denominator", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUnitDenominator.php";
	return getUnitDenominator( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "measure-name", 1, XPath2ResultType::QName, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getMeasureName.php";
	return getMeasureName( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "period", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPeriod.php";
	return getPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context-period", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContextPeriod.php";
	return getContextPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-start-end-period", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsStartEndPeriod.php";
	return getIsStartEndPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-forever-period", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsForeverPeriod.php";
	return getIsForeverPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-duration-period", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsDurationPeriod.php";
	return getIsDurationPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-instant-period", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsInstantPeriod.php";
	return getIsInstantPeriod( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "period-start", 1, XPath2ResultType::DateTime, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPeriodStart.php";
	return getPeriodStart( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "period-end", 1, XPath2ResultType::DateTime, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPeriodEnd.php";
	return getPeriodEnd( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "period-instant", 1, XPath2ResultType::DateTime, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPeriodInstant.php";
	return getPeriodInstant( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "entity", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getEntity.php";
	return getEntity( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context-entity", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContextEntity.php";
	return getContextEntity( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "identifier", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIdentifier.php";
	return getIdentifier( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context-identifier", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContextIdentifier.php";
	return getContextIdentifier( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "entity-identifier", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getEntityIdentifier.php";
	return getEntityIdentifier( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "identifier-value", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIdentifierValue.php";
	return getIdentifierValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "identifier-scheme", 1, XPath2ResultType::AnyUri, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIdentifierSchemeValue.php";
	return getIdentifierSchemeValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "segment", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getSegment.php";
	return getSegment( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "entity-segment", 1, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getEntitySegment.php";
	return getEntitySegment( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context-segment", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContextSegment.php";
	return getContextSegment( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "scenario", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getScenario.php";
	return getScenario( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "context-scenario", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getContextScenario.php";
	return getContextScenario( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-identifier-value", 1, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactIdentifierValue.php";
	return getFactIdentifierValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-identifier-scheme", 1, XPath2ResultType::AnyUri, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactIdentifierScheme.php";
	return getFactIdentifierScheme( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-non-numeric", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsNonNumeric.php";
	return getIsNonNumeric( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-numeric", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsNumeric.php";
	return getIsNumeric( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "is-fraction", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIsFraction.php";
	return getIsFraction( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "precision", 1, XPath2ResultType::Any, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPrecision.php";
	return getPrecision( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "decimals", 1, XPath2ResultType::Any, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getDecimals.php";
	return getDecimals( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_FORMULA ], "uncovered-aspect", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUncoveredAspect.php";
	return getUncoveredAspect( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_FORMULA ], "uncovered-aspect", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUncoveredAspect.php";
	return getUncoveredAspect( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_FORMULA ], "has-fallback-value", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getHasFallbackValue.php";
	return getHasFallbackValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_FORMULA ], "uncovered-non-dimensional-aspects", 0, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUncoveredNonDimensionalAspects.php";
	return getUncoveredNonDimensionalAspects( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_FORMULA ], "uncovered-dimensional-aspects", 0, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUncoveredDimensionalAspects.php";
	return getUncoveredDimensionalAspects( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "identical-nodes", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIdenticalNodes.php";
	return getIdenticalNodes( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "s-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getSEqual.php";
	return getSEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "u-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUEqual.php";
	return getUEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "v-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getVEqual.php";
	return getVEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "c-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getCEqual.php";
	return getCEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "identical-node-set", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getIdenticalNodesSet.php";
	return getIdenticalNodesSet( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "s-equal-set", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getSEqualSet.php";
	return getSEqualSet( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "v-equal-set", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getVEqualSet.php";
	return getVEqualSet( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "c-equal-set", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getCEqualSet.php";
	return getCEqualSet( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "u-equal-set", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getUEqualSet.php";
	return getUEqualSet( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "x-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getXEqual.php";
	return getXEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "duplicate-item", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getDuplicateItem.php";
	return getDuplicateItem( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "duplicate-tuple", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getDuplicateTuple.php";
	return getDuplicateTuple( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "p-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPEqual.php";
	return getPEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "cu-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getCUEqual.php";
	return getCUEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "pc-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPCEqual.php";
	return getPCEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "pcu-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getPCUEqual.php";
	return getPCUEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "start-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getStartEqual.php";
	return getStartEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "end-equal", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getEndEqual.php";
	return getEndEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "nodes-correspond", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getNodesCorrespond.php";
	return getNodesCorrespond( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "facts-in-instance", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactsInInstance.php";
	return getFactsInInstance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "items-in-instance", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getItemsInInstance.php";
	return getItemsInInstance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "tuples-in-instance", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getTuplesInInstance.php";
	return getTuplesInInstance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "items-in-tuple", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getItemsInTuple.php";
	return getItemsInTuple( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "tuples-in-tuple", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getTuplesInTuple.php";
	return getTuplesInTuple( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "non-nil-facts-in-instance", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getNonNilFactsInInstance.php";
	return getNonNilFactsInInstance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-balance", 1, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptBalance.php";
	return getConceptBalance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-period-type", 1, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptPeriodType.php";
	return getConceptPeriodType( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-custom-attribute", 2, XPath2ResultType::Any, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptCustomAttribute.php";
	return getConceptCustomAttribute( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-data-type", 1, XPath2ResultType::QName, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptDataType.php";
	return getConceptDataType( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-data-type-derived-from", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptDataTypeDerivedFrom.php";
	return getConceptDataTypeDerivedFrom( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-substitutions", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptSubstitutions.php";
	return getConceptSubstitutions( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "filter-member-network-selection", 5, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/filterMemberNetworkSelection.php";
	return filterMemberNetworkSelection( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "filter-member-DRS-selection", 5, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/filterMemberDRSSelection.php";
	return filterMemberDRSSelection( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-segment-remainder", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactSegmentRemainder.php";
	return getFactSegmentRemainder( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-scenario-remainder", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactScenarioRemainder.php";
	return getFactScenarioRemainder( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-has-explicit-dimension", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactHasExplicitDimension.php";
	return getFactHasExplicitDimension( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-has-typed-dimension", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactHasTypedDimension.php";
	return getFactHasTypedDimension( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-has-explicit-dimension-value", 3, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactHasExplicitDimensionValue.php";
	return getFactHasExplicitDimensionValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-explicit-scenario-dimension-value", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactExplicitScenarioDimensionValue.php";
	return getFactExplicitScenarioDimensionValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-explicit-dimension-value", 2, XPath2ResultType::QName, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactExplicitDimensionValue.php";
	return getFactExplicitDimensionValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-typed-dimension-value", 2, XPath2ResultType::Navigator, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactTypedDimensionValue.php";
	return getFactTypedDimensionValue( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-explicit-dimensions", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactExplicitDimensions.php";
	return getFactExplicitDimensions( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-typed-dimensions", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactTypedDimensions.php";
	return getFactTypedDimensions( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-dimensions", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactDimensions.php";
	return getFactDimensions( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-dimension-s-equal2", 3, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactDimensionSEqual.php";
	return getFactDimensionSEqual( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "linkbase-link-roles", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getLinkbaseRoles.php";
	return getLinkbaseRoles( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "linkbase-link-roles", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getLinkbaseRoles2.php";
	return getLinkbaseRoles2( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "linkbase-link-roles", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPath functions/.php";
	return getLinkbaseRoles2( $context, $provider, $args );
});
$functionTable->Add( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "navigate-relationships", XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/navigateRelationships.php";
	return navigateRelationships( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-label", 4, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptLabel.php";
	return getConceptLabel( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "arcrole-definition", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getArcroleDefinition.php";
	return getArcroleDefinition( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "role-definition", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getRoleDefinition.php";
	return getRoleDefinition( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "fact-footnotes", 5, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getFactFootnotes.php";
	return getFactFootnotes( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-relationships", 4, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptRelationships.php";
	return getConceptRelationships( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-relationships", 5, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptRelationships.php";
	return getConceptRelationships( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-relationships", 6, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptRelationships.php";
	return getConceptRelationships( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "concept-relationships", 7, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getConceptRelationships.php";
	return getConceptRelationships( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-from-concept", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/relationshipFromConcept.php";
	return relationshipFromConcept( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-to-concept", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/relationshipToConcept.php";
	return relationshipToConcept( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "distinct-nonAbstract-parent-concepts", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getDistinctNonAbstractParentConcepts.php";
	return getDistinctNonAbstractParentConcepts( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-attribute", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getRelationshipAttribute.php";
	return getRelationshipAttribute( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-link-attribute", 2, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/getRelationshipLinkAttribute.php";
	return getRelationshipLinkAttribute( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-name", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/relationshipName.php";
	return relationshipName( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "relationship-link-name", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/relationshipLinkName.php";
	return relationshipLinkName( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "xbrl-instance", 1, XPath2ResultType::NodeSet, function( $context, $provider, $args )
{
	require_once "XPathFunctions/xbrlInstance.php";
	return xbrlInstance( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ], "format-number", 2, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once "XPathFunctions/formatNumber.php";
	return formatNumber( $context, $provider, $args );
});

/** Additional Lyquidity functions */

$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "is-item", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/getIsItem.php";
	return \XBRL\functions\lyquidity\getIsItem( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "is-tuple", 1, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/getIsTuple.php";
	return \XBRL\functions\lyquidity\getIsTuple( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "context-reference", 1, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/getContextRef.php";
	return \XBRL\functions\lyquidity\getContextRef( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "non-XDT-scenario-aspect-test", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/getNonXDTScenarioAspectTest.php";
	return \XBRL\functions\lyquidity\getNonXDTScenarioAspectTest( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "non-XDT-segment-aspect-test", 2, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/getNonXDTSegmentAspectTest.php";
	return \XBRL\functions\lyquidity\getNonXDTSegmentAspectTest( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "aspectMatch", 5, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/aspectMatch.php";
	return \XBRL\functions\lyquidity\aspectMatch( $context, $provider, $args );
});
$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "aspectMatch", 6, XPath2ResultType::Boolean, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/aspectMatch.php";
	return \XBRL\functions\lyquidity\aspectMatch( $context, $provider, $args );
});

$functionTable->AddWithArity( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ], "minimized-date-string", 2, XPath2ResultType::String, function( $context, $provider, $args )
{
	require_once __DIR__ . "/XPathFunctions/lyquidity/minimizedDateString.php";
	return \XBRL\functions\lyquidity\getMinimizedDateString( $context, $provider, $args );
});

/**
 * A class to convey relationship information
 */
class conceptRelationship
{
	/**
	 * A QName representing a concept
	 * @var QNameValue $type
	 */
	public $type;

	/**
	 * A QName representing a concept
	 * @var QNameValue $parent
	 */
	public $parent;

	/**
	 * A QName representing the arc type of the relationship
	 * @var QNameValue $arcType
	 */
	public $arcType;

	/**
	 * A list pf attributes
	 * @var array $attributes
	 */
	public $attributes = null;

	/**
	 * The uri of the containing link
	 * @var string $roleUri
	 */
	public $roleUri = null;

	/**
	 * A reference to an array containing the query parameters
	 * @var array
	 */
	public $query = null;

	/**
	 * True if the relationship is generic
	 * @var bool $isGeneric
	 */
	public $isGeneric = false;
}