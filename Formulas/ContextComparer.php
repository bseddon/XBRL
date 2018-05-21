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
 * @Copyright (C) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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

use lyquidity\XPath2\TreeComparer;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\Value\DateTimeValue;
use lyquidity\XPath2\Value\DayTimeDurationValue;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\Value\DateValue;

/**
 * Implements a comparer that does not care about the order of sub-elements
 */
class ContextComparer extends TreeComparer
{
	/**
	 * Constructor
	 * @param XPath2Context $context
	 * @param string $collation
	 */
	public function __construct( $context, $collation = null )
	{
		parent::__construct( $context, $collation );
	}

	/**
	 * DeepEqualByIterator Alternative way to iterate over nodes in order-sensitive manner
	 * @param XPath2NodeIterator $iter1
	 * @param XPath2NodeIterator $iter2
	 * @param bool $elementsOnly
	 * @return bool
	 */
	public function DeepEqualByIterator( $iter1, $iter2, $elementsOnly = false )
	{
		$iter1 = $iter1->CloneInstance();
		$iter2 = $iter2->CloneInstance();

		$flag1 = $iter1->MoveNext();

		while ( $flag1 )
		{

			// Find the element in $iter2 with the same name
			$iter1Current = $iter1->getCurrent();
			$localName1 = $iter1Current->getLocalName();
			$namespace1 = $iter1Current->getNamespaceURI();
			$found = false;

			$iter2->Reset();
			$flag2 = $iter2->MoveNext();

			while ( $flag2 )
			{
				$iter2Current = $iter2->getCurrent();
				$localName2 = $iter2Current->getLocalName();
				$namespace2 = $iter2Current->getNamespaceURI();

				if ( $localName1 == $localName2 && $namespace1 == $namespace2 )
				{
					// If one of the iterators is a ForIterator and it returns an ExprIterator handle it
					if ( $iter1Current instanceof ExprIterator || $iter2Current instanceof ExprIterator )
					{
						if ( ! $iter1Current instanceof ExprIterator ) $iter1Current = $iter1;
						if ( ! $iter2Current instanceof ExprIterator ) $iter2Current = $iter2;
						$found = $this->DeepEqualByIterator( $iter1Current->CloneInstance(), $iter2Current->CloneInstance() );
					}
					else if ( $iter1Current->getIsNode() == $iter2Current->getIsNode() )
					{
						if ( $iter1Current->getIsNode() && $iter2Current->getIsNode() )
						{
							$found = $this->NodeEqual( $iter1Current, $iter2Current );
						}
						else
						{
							$found = $this->ItemEqual( $iter1Current, $iter2Current );
						}
					}
				}

				if ( $found ) break;
				$flag2 = $iter2->MoveNext();
			}

			if ( ! $found )
			{
				return false;
			}

			$flag1 = $iter1->MoveNext();

		}

		return true;
	}

	/**
	 * Test if two items are equal
	 * Overridden so dates can be compared taking into account there relationship with the period end
	 * @param XPathItem $item1
	 * @param XPathItem $item2
	 * @return bool
	 */
	public function ItemEqual( $item1, $item2 )
	{
		if ( $item1 instanceof DOMXPathNavigator &&
			 $item1->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] &&
			 ( $item1->getLocalName() == 'instant' || $item1->getLocalName() == 'endDate' ) )
		{
			// Parent MUST be period
			$parent = $item1->CloneInstance();
			if ( $parent->MoveToParent() )
			{
				if ( $parent->getLocalName() == 'period' )
				{
					// OK, got two dates.  If one has the format <date>T<time> and the other does not then compare them carefully.
					$item1Value = trim( $item1->getValue() );
					$item2Value = trim( $item2->getValue() );
					if ( ( strpos( $item1Value, "T" ) !== false && strpos( $item2Value, "T" ) === false ) ||
						 ( strpos( $item2Value, "T" ) !== false && strpos( $item1Value, "T" ) === false )
					)
					{
						$item1 = $item1->getTypedValue();
						$item2 = $item2->getTypedValue();

						// One of the values has a 'T' format.  If one of them has 00:00:00 then create a revised date
						if ( strpos( $item1Value, "00:00:00" ) !== false )
						{
							$item1 = DateTimeValue::AddDayTimeDuration( $item1, DayTimeDurationValue::Parse("-P1D") );
							$item1 = DateValue::fromDate( $item1->S, $item1->getValue(), ! $item1->IsLocal );
						}
						else if ( strpos( $item2Value, "00:00:00" ) !== false )
						{
							$item2 = DateTimeValue::AddDayTimeDuration( $item2, DayTimeDurationValue::Parse("-P1D") );
							$item2 = DateValue::fromDate( $item2->S, $item2->getValue(), ! $item2->IsLocal );
						}

						$item1 = XPath2Item::fromValue( $item1 );
						$item2 = XPath2Item::fromValue( $item2 );
					}
				}
			}
		}

		return parent::ItemEqual( $item1, $item2 );
	}
}