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
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\ExtFuncs;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\FalseValue;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getIsNumeric.php";
require_once "getIsFraction.php";
require_once "getPrecision.php";
require_once "getDecimals.php";
require_once "getUEqual.php";
require_once "checkIsNotTuple.php";
require_once "getCEqual.php";

/**
 * Returns true if two item sequences are v-equal.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	Returns true if two item sequences are v-equal and false otherwise.
 *
 * @throws xfie:NodeIsNotXbrlItem	A dynamic error must be raised if any input element is not derived from xbrli:item.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getVEqual( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator
		// There shold be the same count in each node.

		if ( ! $args[0] instanceof XPath2NodeIterator || ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

		if ( $args[0]->getCount() != $args[1]->getCount() )
		{
			return CoreFuncs::$False;
		}

		do
		{
			$flag1 = $args[0]->MoveNext();
			$flag2 = $args[1]->MoveNext();

			if ( $flag1 != $flag2 )
			{
				return CoreFuncs::$False;
			}

			if ( ! $flag1 )
			{
				break;
			}

			// TODO Should make this a function and have all the functions use it (e.g. is-numeric, is-not-numeric)
			$checkIsNotTuple = function( $qn )
			{
				// Look up the type
				$types = \XBRL_Types::getInstance();
				$prefix = $types->getPrefixForNamespace( $qn->NamespaceUri );
				if ( ! $prefix )
				{
					throw new \InvalidArgumentException();
				}

				$element = $types->getElement( $qn->LocalName, $prefix );
				if ( ! $element )
				{
					throw new \InvalidArgumentException();
				}

				if ( isset( $element['substitutionGroup'] ) && $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( \XBRL_Constants::$xbrliTuple ) ) )
				{
					throw XPath2Exception::withErrorCode( "xfie:NodeIsNotXbrlItem", "The item is a tuple" );
				}
			};

			/**
			 * @var XPathNavigator $current1
			 */
			$current1 = $args[0]->getCurrent();
			$qn1 = ExtFuncs::GetNodeName( $context, $current1 );
			$checkIsNotTuple( $qn1 );

			$isNumeric1 = getIsNumeric( $context, $provider, array( $qn1 ) );

			/**
			 * @var XPathNavigator $current2
			 */
			$current2 = $args[1]->getCurrent();
			$qn2 = ExtFuncs::GetNodeName( $context, $current2 );
			$checkIsNotTuple( $qn2 );
			$isNumeric2 = getIsNumeric( $context, $provider, array( $qn2 ) );

			// They must either both be numeric or non-numeric
			if ( $isNumeric1 != $isNumeric2 )
			{
				return CoreFuncs::$False;
			}

			if ( $isNumeric1 instanceof TrueValue )
			{
				// Items must c-equal and u-equal
				// TODO add c-equal
				$doni1 = DocumentOrderNodeIterator::fromItemset( array( $current1->CloneInstance() ) );
				$doni2 = DocumentOrderNodeIterator::fromItemset( array( $current2->CloneInstance() ) );

				$result = getCEqual( $context, $provider, array( $doni1, $doni2 ) );
				if ( $result instanceof FalseValue )
				{
					return CoreFuncs::$False;
				}

				$doni1->Reset();
				$doni2->Reset();

				$result = getUEqual( $context, $provider, array( $doni1, $doni2 ) );
				if ( $result instanceof FalseValue )
				{
					return CoreFuncs::$False;
				}

				$isFraction1 = getIsFraction( $context, $provider, array( $qn1 ) );
				$isFraction2 = getIsFraction( $context, $provider, array( $qn2 ) );

				if ( $isFraction1 != $isFraction1 )
				{
					return CoreFuncs::$False;
				}

				if ( $isFraction1 instanceof CoreFuncs::$True )
				{
					$float2Fraction = function( $n, $tolerance = 1.e-6 )
					{
						$h1=1; $h2=0;
						$k1=0; $k2=1;
						$b = 1/$n;
						do {
							$b = 1/$b;
							$a = floor($b);
							$aux = $h1; $h1 = $a*$h1+$h2; $h2 = $aux;
							$aux = $k1; $k1 = $a*$k1+$k2; $k2 = $aux;
							$b = $b-$a;
						} while (abs($n-$h1/$k1) > $n*$tolerance);

						return array( 'numerator' => $h1, 'denominator' => $k1 );
					};

					$parts1 = $float2Fraction( $current1->getValue() );
					$parts2 = $float2Fraction( $current2->getValue() );

					if ( $parts1['numerator'] != $parts1['numerator'] || $parts1['denominator'] != $parts1['denominator'] )
					{
						return CoreFuncs::$False;
					}
				}
				else
				{
					// $precision1 = getPrecision( $context, $provider, array( $current1->CloneInstance() ) );
					// $precision2 = getPrecision( $context, $provider, array( $current2->CloneInstance() ) );

					$decimals1 = getDecimals( $context, $provider, array( $current1->CloneInstance() ) );
					$decimals1 = $decimals1 == "INF"
						? INF
						: $decimals1->getValue();

					$decimals2 = getDecimals( $context, $provider, array( $current2->CloneInstance() ) );
					$decimals2 = $decimals2 == "INF"
						? INF
						: $decimals2->getValue();

					$decimals = min( $decimals1, $decimals2 );
					if ( round( $current1->getValue(), $decimals ) != round( $current2->getValue(), $decimals ) )
					{
						return CoreFuncs::$False;
					}
				}
			}
			else
			{
				// TODO add c-equal
				if ( CoreFuncs::NormalizeSpace( $current1->getValue() ) != CoreFuncs::NormalizeSpace( $current2->getValue() ) )
				{
					return CoreFuncs::$False;
				}
			}

		}
		while( true );

		return CoreFuncs::$True;
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xfie:NodeIsNotXbrlItem" )
		{
			throw $ex;
		}
	}
	catch( \InvalidArgumentException $ex )
	{
		// Do nothing
	}
	catch ( \Exception $ex)
	{
		throw XPath2Exception::withErrorCode( "xfie:NodeIsNotXbrlItem", "argument is " );
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
