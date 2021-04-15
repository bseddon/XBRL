<?php

/**
 * XBRL Inline transformation functions
 * Supports v1, v2, v3 and v4
 *
 * Based on the implementation in Arelle
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2021 Lyquidity Solutions Limited
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

namespace lyquidity\ixt;

use lyquidity\ixbrl\IXBRLInvalidNamespaceException;

#region Exceptions

class TransformationException extends \Exception
{
	/**
	 * Records the index of the argument causing a match issue
	 *
	 * @var integer
	 */
	public $argIndex = 0;

	/**
	 * Constructor
	 *
	 * @param int $argIndex The index of the argument causing a match issue
	 * @param string $message The result type expected
	 */
	public function __construct( $argIndex, $message )
	{
		$this->argIndex = $argIndex;
		parent::__construct( $message );
	}

}

#endregion
	
class IXBRL_Transforms
{
	/**
	 * Singleton instance
	 * @var IXBRL_Transforms
	 */
	private static $instance = null;

	/**
	 * Returns the singleton
	 * @return IXBRL_Transforms
	 */
	public static function getInstance()
	{
		if ( ! self::$instance )
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	#region Regular expression patterns

	private $dateslashPattern = '^[ \t\n\r]*(\d+)/(\d+)/(\d+)[ \t\n\r]*$';
	private $daymonthslashPattern = '^[ \t\n\r]*([0-9]{1,2})/([0-9]{1,2})[ \t\n\r]*$';
	private $monthdayslashPattern = '^[ \t\n\r]*([0-9]{1,2})/([0-9]{1,2})[ \t\n\r]*$';
	private $datedotPattern = '^[ \t\n\r]*(\d+)\.(\d+)\.(\d+)[ \t\n\r]*$';
	private $daymonthPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[ \t\n\r]*$';
	private $monthdayPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[A-Za-z]*[ \t\n\r]*$';
	private $daymonthyearPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$';
	private $monthdayyearPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$';

	private $dateUsPattern = '^[ \t\n\r]*(\w+)\s+(\d+),\s+(\d+)[ \t\n\r]*$';
	private $dateEuPattern = '^[ \t\n\r]*(\d+)\s+(\w+)\s+(\d+)[ \t\n\r]*$';
	private $daymonthBgPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthCsPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ledna|února|unora|března|brezna|dubna|května|kvetna|června|cervna|července|cervence|srpna|září|zari|října|rijna|listopadu|prosince|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDNA|ÚNORA|UNORA|BŘEZNA|BREZNA|DUBNA|KVĚTNA|KVETNA|ČERVNA|CERVNA|ČERVENCE|CERVENCE|SRPNA|ZÁŘÍ|ZARI|ŘÍJNA|RIJNA|LISTOPADU|PROSINCE|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Ledna|Února|Unora|Března|Brezna|Dubna|Května|Kvetna|Června|Cervna|Července|Cervence|Srpna|Září|Zari|Října|Rijna|Listopadu|Prosince|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)\.?[ \t\n\r]*$';
	private $daymonthDePattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthDkPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[ \t\n\r]*$'; //, re.IGNORECASE;
	private $daymonthElPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΥΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]{0,8}[ \t\n\r]*$';
	private $daymonthEnPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[ \t\n\r]*$';
	private $monthdayEnPattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{1,2})[A-Za-z]{0,2}[ \t\n\r]*$';
	private $daymonthEsPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]{0,7}[ \t\n\r]*$';
	private $daymonthEtPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]{0,5}[ \t\n\r]*$';
	private $daymonthFiPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]{0,8}[ \t\n\r]*$';
	private $daymonthFrPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]{0,5}[ \t\n\r]*$';
	private $daymonthHrPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]{0,6}[ \t\n\r]*$';
	private $monthdayHuPattern = '^[ \t\n\r]*(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]{0,7}[^0-9]+([0-9]{1,2})[ \t\n\r]*$';
	private $daymonthItPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]{0,6}[ \t\n\r]*$';
	private $monthdayLtPattern = '^[ \t\n\r]*(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]{0,6}[^0-9]+([0-9]{1,2})[^0-9]*[ \t\n\r]*$';
	private $daymonthLvPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthNlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthNoPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthPlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]{0,9}s*$';
	private $daymonthPtPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthRomanPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^XVIxvi]((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[ \t\n\r]*$';
	private $daymonthRoPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$';
	private $daymonthSePattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthSkPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthSlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$';
	private $daymonthyearBgPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[A-Za-z]*[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$';
	private $daymonthyearCsPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ledna|února|unora|března|brezna|dubna|května|kvetna|června|cervna|července|cervence|srpna|září|zari|října|rijna|listopadu|prosince|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDNA|ÚNORA|UNORA|BŘEZNA|BREZNA|DUBNA|KVĚTNA|KVETNA|ČERVNA|CERVNA|ČERVENCE|CERVENCE|SRPNA|ZÁŘÍ|ZARI|ŘÍJNA|RIJNA|LISTOPADU|PROSINCE|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Ledna|Února|Unora|Března|Brezna|Dubna|Května|Kvetna|Června|Cervna|Července|Cervence|Srpna|Září|Zari|Října|Rijna|Listopadu|Prosince|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)[^0-9a-zA-Z]+[^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearDePattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearDkPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[^0-9]*([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$'; // re.IGNORECASE;
	private $daymonthyearElPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΎΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearEnPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$';
	private $monthdayyearEnPattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]+)[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$';
	private $daymonthyearEsPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearEtPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearFiPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearFrPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearHrPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $yearmonthdayHuPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]+([0-9]{1,2})[ \t\n\r]*$';
	private $daymonthyearInPatternTR4 = '^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर)[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$';
	private $daymonthyearInPatternTR3 = '^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर|[०-९]{1,2})[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$';
	private $daymonthyearInIndPattern = '^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+((C\S*ait|चैत्र)|(Vai|वैशाख|बैसाख)|(Jy|ज्येष्ठ)|(dha|ḍha|आषाढ|आषाढ़)|(vana|Śrāvaṇa|श्रावण|सावन)|(Bh\S+dra|Proṣṭhapada|भाद्रपद|भादो)|(in|आश्विन)|(K\S+rti|कार्तिक)|(M\S+rga|Agra|मार्गशीर्ष|अगहन)|(Pau|पौष)|(M\S+gh|माघ)|(Ph\S+lg|फाल्गुन))[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$';
	private $daymonthyearItPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $yearmonthdayLtPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]*[^0-9a-zA-Z]+(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]+([0-9]{1,2})[^0-9]*[ \t\n\r]*$';
	private $yeardaymonthLvPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+([0-9]{1,2})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]*[ \t\n\r]*$';
	private $daymonthyearNlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearNoPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearPlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$';
	private $daymonthyearPtPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearRomanPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^XVIxvi]((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[^XVIxvi][^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearSePattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearRoPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearSkPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $daymonthyearSlPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearBgPattern = '^[ \t\n\r]*(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$';
	private $monthyearCsPattern = '^[ \t\n\r]*(leden|ledna|lednu|únor|unor|února|unora|únoru|unoru|březen|brezen|března|brezna|březnu|breznu|duben|dubna|dubnu|květen|kveten|května|kvetna|květnu|kvetnu|červen|cerven|června|cervna|červnu|cervnu|červenec|cervenec|července|cervence|červenci|cervenci|srpen|srpna|srpnu|září|zari|říjen|rijen|října|rijna|říjnu|rijnu|listopad|listopadu|prosinec|prosince|prosinci|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDEN|LEDNA|LEDNU|ÚNOR|UNOR|ÚNORA|UNORA|ÚNORU|UNORU|BŘEZEN|BREZEN|BŘEZNA|BREZNA|BŘEZNU|BREZNU|DUBEN|DUBNA|DUBNU|KVĚTEN|KVETEN|KVĚTNA|KVETNA|KVĚTNU|KVETNU|ČERVEN|CERVEN|ČERVNA|CERVNA|ČERVNU|CERVNU|ČERVENEC|CERVENEC|ČERVENCE|CERVENCE|ČERVENCI|CERVENCI|SRPEN|SRPNA|SRPNU|ZÁŘÍ|ZARI|ŘÍJEN|RIJEN|ŘÍJNA|RIJNA|ŘÍJNU|RIJNU|LISTOPAD|LISTOPADU|PROSINEC|PROSINCE|PROSINCI|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Leden|Ledna|Lednu|Únor|Unor|Února|Unora|Únoru|Unoru|Březen|Brezen|Března|Brezna|Březnu|Breznu|Duben|Dubna|Dubnu|Květen|Kveten|Května|Kvetna|Květnu|Kvetnu|Červen|Cerven|Června|Cervna|Červnu|Cervnu|Červenec|Cervenec|Července|Cervence|Červenci|Cervenci|Srpen|Srpna|Srpnu|Září|Zari|Říjen|Rijen|Října|Rijna|Říjnu|Rijnu|Listopad|Listopadu|Prosinec|Prosince|Prosinci|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)[^0-9a-zA-Z]+[^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearDePattern = '^[ \t\n\r]*(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearDkPattern = '^[ \t\n\r]*(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[^0-9]*([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$'; // re.IGNORECASE;
	private $monthyearElPattern = '^[ \t\n\r]*(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΎΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearEnPattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $yearmonthEnPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[ \t\n\r]*$';
	private $monthyearEsPattern = '^[ \t\n\r]*(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearEtPattern = '^[ \t\n\r]*(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearFiPattern = '^[ \t\n\r]*(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearFrPattern = '^[ \t\n\r]*(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearHrPattern = '^[ \t\n\r]*(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $yearmonthHuPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$';
	private $monthyearItPattern = '^[ \t\n\r]*(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearInPattern = '^[ \t\n\r]*(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर)[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$';
	private $yearmonthLtPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]*[^0-9a-zA-Z]+(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]*[ \t\n\r]*$';
	private $yearmonthLvPattern = '^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$';
	private $monthyearNlPattern = '^[ \t\n\r]*(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearNoPattern = '^[ \t\n\r]*(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearPlPattern = '^[ \t\n\r]*(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$';
	private $monthyearPtPattern = '^[ \t\n\r]*(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearRomanPattern = '^[ \t\n\r]*((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[^XVIxvi][^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearRoPattern = '^[ \t\n\r]*(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearSePattern = '^[ \t\n\r]*(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearSkPattern = '^[ \t\n\r]*(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearSlPattern = '^[ \t\n\r]*(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearLvPattern = '^[ \t\n\r]*(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$';

	# TR1-only patterns, only allow space separators, no all-CAPS month name, only 2 or 4 digit years
	private $dateLongUkTR1Pattern = '^[ \t\n\r]*(\d|\d{2,2}) (January|February|March|April|May|June|July|August|September|October|November|December) (\d{2,2}|\d{4,4})[ \t\n\r]*$';
	private $dateShortUkTR1Pattern = '^[ \t\n\r]*(\d|\d{2,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{2,2}|\d{4,4})[ \t\n\r]*$';
	private $dateLongUsTR1Pattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December) (\d|\d{2,2}), (\d{2,2}|\d{4,4})[ \t\n\r]*$';
	private $dateShortUsTR1Pattern = '^[ \t\n\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d|\d{2,2}), (\d{2,2}|\d{4,4})[ \t\n\r]*$';
	private $daymonthLongEnTR1Pattern = '^[ \t\n\r]*(\d|\d{2,2}) (January|February|March|April|May|June|July|August|September|October|November|December)[ \t\n\r]*$';
	private $daymonthShortEnTR1Pattern = '^[ \t\n\r]*([0-9]{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ \t\n\r]*$';
	private $monthdayLongEnTR1Pattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December) (\d|\d{2,2})[ \t\n\r]*$';
	private $monthdayShortEnTR1Pattern = '^[ \t\n\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+([0-9]{1,2})[A-Za-z]{0,2}[ \t\n\r]*$';
	private $monthyearShortEnTR1Pattern = '^[ \t\n\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+([0-9]{2}|[0-9]{4})[ \t\n\r]*$';
	private $monthyearLongEnTR1Pattern = '^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December)\s+([0-9]{2}|[0-9]{4})[ \t\n\r]*$';
	private $yearmonthShortEnTR1Pattern = '^[ \t\n\r]*([0-9]{2}|[0-9]{4})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ \t\n\r]*$';
	private $yearmonthLongEnTR1Pattern = '^[ \t\n\r]*([0-9]{2}|[0-9]{4})\s+(January|February|March|April|May|June|July|August|September|October|November|December)[ \t\n\r]*$';

	private $erayearmonthjpPattern = '^[\s ]*(明治|明|大正|大|昭和|昭|平成|平|令和|令)[\s ]*([0-9０-９]{1,2}|元)[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*$';
	private $erayearmonthdayjpPattern = '^[\s ]*(明治|明|大正|大|昭和|昭|平成|平|令和|令)[\s ]*([0-9０-９]{1,2}|元)[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*([0-9０-９]{1,2})[\s ]*(日)[\s ]*$';
	private $yearmonthcjkPattern = '^[\s ]*([0-9０-９]{1,2}|[0-9０-９]{4})[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*$';
	private $yearmonthdaycjkPattern = '^[\s ]*([0-9０-９]{1,2}|[0-9０-９]{4})[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*([0-9０-９]{1,2})[\s ]*(日)[\s ]*$';

	private $monthyearPattern = '^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$';
	private $yearmonthPattern = '^[ \t\n\r]*([0-9]{4}|[0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]*$';
	private $yearmonthdayPattern = '^[ \t\n\r]*([0-9]{4}|[0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]*$';

	private $zeroDashPattern = '^[ \t\n\r]*([-]|\u002D|\u002D|\u058A|\u05BE|\u2010|\u2011|\u2012|\u2013|\u2014|\u2015|\uFE58|\uFE63|\uFF0D)[ \t\n\r]*$';
	private $numDotDecimalPattern = '^[ \t\n\r]*[0-9]{1,3}([, \xA0]?[0-9]{3})*(\.[0-9]+)?[ \t\n\r]*$';
	private $numDotDecimalTR4Pattern = '^[ \t\n\r]*[, \xA00-9]*(\.[ \xA00-9]+)?[ \t\n\r]*$';
	private $numDotDecimalInPattern = '^((([0-9]{1,2}[, \xA0])?([0-9]{2}[, \xA0])*[0-9]{3})|([0-9]{1,3}))([.][0-9]+)?$|^([0-9]+)([.][0-9]+)?$';
	private $numCommaDecimalPattern = '^[ \t\n\r]*[0-9]{1,3}([. \xA0]?[0-9]{3})*(,[0-9]+)?[ \t\n\r]*$';
	private $numCommaDecimalTR4Pattern = '^[ \t\n\r]*[\. \xA00-9]*(,[ \xA00-9]+)?[ \t\n\r]*$';
	private $numUnitDecimalPattern = '^([0]|([1-9][0-9]{0,2}([.,\uFF0C\uFF0E]?[0-9]{3})*))[^0-9,.\uFF0C\uFF0E]+([0-9]{1,2})[^0-9,.\uFF0C\uFF0E]*$';
	private $numUnitDecimalInPattern = '^[ \t\n\r]*((([0-9]{1,2}[, \xA0])?([0-9]{2}[, \xA0])*[0-9]{3})|([0-9]{1,3}))([^0-9]+)([0-9]{1,2})([^0-9]*)$|^([0-9]+)([^0-9]+)([0-9]{1,2})([^0-9]*)[ \t\n\r]*$';
	private $numUnitDecimalTR4Pattern = '^([0-9０-９\.,，]+)([^0-9０-９\.,，][^0-9０-９]*)([0-9０-９]{1,2})[^0-9０-９]*$';
	private $numCommaPattern = '^[ \t\n\r]*[0-9]+(,[0-9]+)?[ \t\n\r]*$';
	private $numCommaDotPattern = '^[ \t\n\r]*[0-9]{1,3}(,[0-9]{3,3})*([.][0-9]+)?[ \t\n\r]*$';
	private $numDashPattern = '^[ \t\n\r]*-[ \t\n\r]*$';
	private $numDotCommaPattern = '^[ \t\n\r]*[0-9]{1,3}([.][0-9]{3,3})*(,[0-9]+)?[ \t\n\r]*$';
	private $numSpaceDotPattern = '^[ \t\n\r]*[0-9]{1,3}([ \xA0][0-9]{3,3})*([.][0-9]+)?[ \t\n\r]*$';
	private $numSpaceCommaPattern = '^[ \t\n\r]*[0-9]{1,3}([ \xA0][0-9]{3,3})*(,[0-9]+)?[ \t\n\r]*$';

	private $numCanonicalizationPattern = '^[ \t\n\r]*0*([1-9][0-9]*)?(([.]0*)[ \t\n\r]*$|([.][0-9]*[1-9])0*[ \t\n\r]*$|[ \t\n\r]*$)';

	#endregion

	#region Month/day indexes

	private $monthnumber = null;

	private $monthnumbercs = array(
		'ledna' => 1, 'leden' => 1, 'lednu' => 1, 'února' => 2, 'unora' => 2, 'únoru' => 2, 'unoru' => 2, 'únor' => 2, 'unor' => 2, 
		'března' => 3, 'brezna' => 3, 'březen' => 3, 'brezen' => 3, 'březnu' => 3, 'breznu' => 3, 'dubna' => 4, 'duben' => 4, 'dubnu' => 4, 
		'května' => 5, 'kvetna' => 5, 'květen' => 5, 'kveten' => 5, 'květnu' => 5, 'kvetnu' => 5,
		'června' => 6, 'cervna' => 6, 'červnu' => 6, 'cervnu' => 6, 'července' => 7, 'cervence' => 7, 
		'červen' => 6, 'cerven' => 6, 'červenec' => 7, 'cervenec' => 7, 'červenci' => 7, 'cervenci' => 7,
		'srpna' => 8, 'srpen' => 8, 'srpnu' => 8, 'září' => 9, 'zari' => 9, 
		'října' => 10, 'rijna' => 10, 'říjnu' => 10, 'rijnu' => 10, 'říjen' => 10, 'rijen' => 10, 'listopadu' => 11, 'listopad' => 11, 
		'prosince' => 12, 'prosinec' => 12, 'prosinci' => 12,
		'led' => 1, 'úno' => 2, 'uno' => 2, 'bře' => 3, 'bre' => 3, 'dub' => 4, 'kvě' => 5, 'kve' => 5,
		'čvn' => 6, 'cvn' => 6, 'čvc' => 7, 'cvc' => 7, 'srp' => 8, 'zář' => 9, 'zar' => 9,
		'říj' => 10, 'rij' => 10, 'lis' => 11, 'pro' => 12
	);

	private $monthnumberfi = array( 'tam' => 1, 'hel' => 2, 'maa' => 3, 'huh' => 4, 'tou' => 5, 'kes' => 6, 'hei' => 7, 'elo' => 8, 'syy' => 9, 'lok' => 10, 'mar' => 11, 'jou' => 12 );

	private $monthnumberhr = array( 'sij' => 1, 'velj' => 2, 'ožu' => 3, 'ozu' => 3, 'tra' => 4, 'svi' => 5, 'lip' => 6, 'srp' => 7, 'kol' => 8, 'ruj' => 9, 'lis' => 10, 'stu' => 11, 'pro' => 12 );

	private $monthnumberlt = array( 'sau' => 1, 'vas' => 2, 'kov' => 3, 'bal' => 4, 'geg' => 5, 'bir' => 6, 'lie' => 7, 'rugp' => 8, 'rgp' => 8, 'rugs' => 9, 'rgs' => 9, 'spa' => 10, 'spl' => 10, 'lap' => 11, 'gru' => 12, 'grd' => 12 );

	private $monthnumberpl = array( 'sty' => 1, 'lut' => 2, 'mar' => 3, 'kwi' => 4, 'maj' => 5, 'cze' => 6, 'lip' => 7, 'sie' => 8, 'wrz' => 9, 'paź' => 10, 'paz' => 10, 'lis' => 11, 'gru' => 12 );

	private $monthnumberroman = array( 'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12 );

	private $maxDayInMo = array(
		'01' => '31', '02' => '29', '03' => '31', '04' => '30', '05' => '31', '06' => '30',
		'07' => '31', '08' => '31', '09' => '30', '10' => '31', '11' => '30', '12' => '31',
		1 => '31', 2 => '29', 3 => '31', 4 => '30', 5 => '31', 6 => '30',
		7 => '31', 8 => '31', 9 => '30', 10 => '31', 11 => '30', 12 => '31'
	);
	private $gLastMoDay = [31,28,31,30,31,30,31,31,30,31,30,31];

	private $jpDigitsTrTable = array();

	private $eraStart = array(
		"令和" => 2018,
		"令" => 2018,
		"\u{5E73}\u{6210}" => 1988, 
		"\u{5E73}" => 1988,
		"\u{660E}\u{6CBB}" => 1867,
		"\u{660E}" => 1867,
		"\u{5927}\u{6B63}" => 1911,
		"\u{5927}" => 1911,
		"\u{662D}\u{548C}" => 1925,
		"\u{662D}" => 1925
	);

	private $devanagariDigitsTrTable = null;

	private $gregorianHindiMonthNumber = array(
		"\u{091C}\u{0928}\u{0935}\u{0930}\u{0940}" => "01",
		"\u{092B}\u{0930}\u{0935}\u{0930}\u{0940}" => "02", 
		"\u{092E}\u{093E}\u{0930}\u{094D}\u{091A}" => "03", 
		"\u{0905}\u{092A}\u{094D}\u{0930}\u{0948}\u{0932}" => "04",
		"\u{092E}\u{0908}" => "05", 
		"\u{091C}\u{0942}\u{0928}" => "06",
		"\u{091C}\u{0941}\u{0932}\u{093E}\u{0908}" => "07", 
		"\u{0905}\u{0917}\u{0938}\u{094D}\u{0924}" => "08",
		"\u{0938}\u{093F}\u{0924}\u{0902}\u{092C}\u{0930}" => "09",
		"\u{0905}\u{0915}\u{094D}\u{0924}\u{0942}\u{092C}\u{0930}" => "10",
		"\u{0928}\u{0935}\u{092E}\u{094D}\u{092C}\u{0930}" => "11",
		"\u{0926}\u{093F}\u{0938}\u{092E}\u{094D}\u{092C}\u{0930}" => "12"
	);

	private $sakaMonthNumber = array(
		"Chaitra" =>1, "\u{091A}\u{0948}\u{0924}\u{094D}\u{0930}" =>1,
		"Vaisakha" =>2, "Vaishakh" =>2, "Vai\u{015B}\u{0101}kha" =>2, "\u{0935}\u{0948}\u{0936}\u{093E}\u{0916}" =>2, "\u{092C}\u{0948}\u{0938}\u{093E}\u{0916}" =>2,
		"Jyaishta" =>3, "Jyaishtha" =>3, "Jyaistha" =>3, "Jye\u{1E63}\u{1E6D}ha" =>3, "\u{091C}\u{094D}\u{092F}\u{0947}\u{0937}\u{094D}\u{0920}" =>3,
		"Asadha" =>4, "Ashadha" =>4, "\u{0100}\u{1E63}\u{0101}\u{1E0D}ha" =>4, "\u{0906}\u{0937}\u{093E}\u{0922}" =>4, "\u{0906}\u{0937}\u{093E}\u{0922}\u{093C}" =>4,
		"Sravana" =>5, "Shravana" =>5, "\u{015A}r\u{0101}va\u{1E47}a" =>5, "\u{0936}\u{094D}\u{0930}\u{093E}\u{0935}\u{0923}" =>5, "\u{0938}\u{093E}\u{0935}\u{0928}" =>5,
		"Bhadra" =>6, "Bhadrapad" =>6, "Bh\u{0101}drapada" =>6, "Bh\u{0101}dra" =>6, "Pro\u{1E63}\u{1E6D}hapada" =>6, "\u{092D}\u{093E}\u{0926}\u{094D}\u{0930}\u{092A}\u{0926}" =>6, "\u{092D}\u{093E}\u{0926}\u{094B}" =>6,
		"Aswina" =>7, "Ashwin" =>7, "Asvina" =>7, "\u{0100}\u{015B}vina" =>7, "\u{0906}\u{0936}\u{094D}\u{0935}\u{093F}\u{0928}" =>7, 
		"Kartiak" =>8, "Kartik" =>8, "Kartika" =>8, "K\u{0101}rtika" =>8, "\u{0915}\u{093E}\u{0930}\u{094D}\u{0924}\u{093F}\u{0915}" =>8, 
		"Agrahayana" =>9,"Agrah\u{0101}ya\u{1E47}a" =>9,"Margashirsha" =>9, "M\u{0101}rga\u{015B}\u{012B}r\u{1E63}a" =>9, "\u{092E}\u{093E}\u{0930}\u{094D}\u{0917}\u{0936}\u{0940}\u{0930}\u{094D}\u{0937}" =>9, "\u{0905}\u{0917}\u{0939}\u{0928}" =>9,
		"Pausa" =>10, "Pausha" =>10, "Pau\u{1E63}a" =>10, "\u{092A}\u{094C}\u{0937}" =>10,
		"Magha" =>11, "Magh" =>11, "M\u{0101}gha" =>11, "\u{092E}\u{093E}\u{0918}" =>11,
		"Phalguna" =>12, "Phalgun" =>12, "Ph\u{0101}lguna" =>12, "\u{092B}\u{093E}\u{0932}\u{094D}\u{0917}\u{0941}\u{0928}" =>12,
	);

	private $sakaMonthPattern = 
		"(C\S*ait|\u{091A}\u{0948}\u{0924}\u{094D}\u{0930})|" .
		"(Vai|\u{0935}\u{0948}\u{0936}\u{093E}\u{0916}|\u{092C}\u{0948}\u{0938}\u{093E}\u{0916})|" .
		"(Jy|\u{091C}\u{094D}\u{092F}\u{0947}\u{0937}\u{094D}\u{0920})|" .
		"(dha|\u{1E0D}ha|\u{0906}\u{0937}\u{093E}\u{0922}|\u{0906}\u{0937}\u{093E}\u{0922}\u{093C})|" .
		"(vana|\u{015A}r\u{0101}va\u{1E47}a|\u{0936}\u{094D}\u{0930}\u{093E}\u{0935}\u{0923}|\u{0938}\u{093E}\u{0935}\u{0928})|" .
		"(Bh\S+dra|Pro\u{1E63}\u{1E6D}hapada|\u{092D}\u{093E}\u{0926}\u{094D}\u{0930}\u{092A}\u{0926}|\u{092D}\u{093E}\u{0926}\u{094B})|" .
		"(in|\u{0906}\u{0936}\u{094D}\u{0935}\u{093F}\u{0928})|" .
		"(K\S+rti|\u{0915}\u{093E}\u{0930}\u{094D}\u{0924}\u{093F}\u{0915})|" .
		"(M\S+rga|Agra|\u{092E}\u{093E}\u{0930}\u{094D}\u{0917}\u{0936}\u{0940}\u{0930}\u{094D}\u{0937}|\u{0905}\u{0917}\u{0939}\u{0928})|" .
		"(Pau|\u{092A}\u{094C}\u{0937})|" .
		"(M\S+gh|\u{092E}\u{093E}\u{0918})|" .
		"(Ph\S+lg|\u{092B}\u{093E}\u{0932}\u{094D}\u{0917}\u{0941}\u{0928})"
	;

	private $sakaMonthLength = array(30,31,31,31,31,31,30,30,30,30,30,30); # Chaitra has 31 days in Gregorian leap year
	private $sakaMonthOffset = array( 
		array(3,22,0), 
		array(4,21,0),
		array(5,22,0),
		array(6,22,0),
		array(7,23,0),
		array(8,23,0),
		array(9,23,0),
		array(10,23,0),
		array(11,22,0),
		array(12,22,0),
		array(1,21,1),
		array(2,20,1)
	);

	#endregion

	#region Private array variables

	/**
	 * Old namespace
	 * @var [type]
	 */
	private $deprecatedNamespaceURI = 'http://www.xbrl.org/2008/inlineXBRL/transformation'; // the CR/PR pre-REC namespace

	/**
	 * Version 1 function names array
	 * @var string[]
	 */
	private $tr1Functions = null;

	/**
	 * Version 2 function names array
	 * @var string[]
	 */
	private $tr2Functions = null;

	/**
	 * Version 3 function names array
	 * @var string[]
	 */
	private $tr3Functions = null;

	/**
	 * Version 4 function names array
	 * @var string[]
	 */
	private $tr4Functions = null;

	/**
	 * Format function names arrays indexed by TRR namespaces
	 * @var string[]
	 */
	private $namespaceFunctions = null;

	/**
	 * The most recently set namespace
	 * @var string
	 */
	private $transformNamespace = null;

	/**
	 * The set of functions for the set namespace
	 * @var string[]
	 */
	private $transformFunctions = null;

	#endregion

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		// Initialized here because of theGreek requirement
		$this->monthnumber = array(
			# english
			'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 
			'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12, 
			'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 
			'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12, 
			# bulgarian
			'ян' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'маи' => 5, 'юни' => 6,
			'юли' => 7, 'авг' => 8, 'сеп' => 9, 'окт' => 10, 'ное' => 11, 'дек' => 12,
			# danish
			'jan' => 1, 'feb' => 2, 'mar' =>  3, 'apr' => 4, 'maj' => 5, 'jun' => 6,
			'jul' => 7, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11, 'dec' => 12,
			# de: german
			'jan' => 1, 'jän' => 1, 'jaen' => 1, 'feb' => 2, 'mär' => 3, 'maer' => 3, 'mar' => 3,'apr' => 4, 
			'mai' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11, 'dez' => 12,
			# el: greek
			'ιαν' => 1, 'ίαν' => 1, 'iαν' => 1, 'φεβ' => 2, 'μάρ' => 3, 'μαρ' => 3, 
			'απρ' => 4, 'άπρ' => 4, 'απρ' => 4, 'aπρ' => 4, 'αρίλ' => 4, 'άρίλ' => 4, 'αριλ' => 4, 'άριλ' => 4, 'άριλ' => 4, 'αριλ' => 4, 'aρίλ' => 4, 'aριλ' => 4, 
			'μαΐ' => 5, 'μαι' => 5, 'μάι' => 5, 'μαϊ' => 5, 'μάϊ' => 5, strtolower( 'ΜΑΪ́' ) => 5, # ΜΑΪ́ has combining diacritical marks not on lower case pattern 
			'ιούν' => 6, 'ίούν' => 6, 'ίουν' => 6, 'ιουν' => 6, 'ιουν' => 6, 'ιουν' => 6, 'iούν' => 6, 'iουν' => 6, 
			'ιούλ' => 7, 'ίούλ' => 7, 'ίουλ' => 7, 'ίουλ' => 7, 'ιουλ' => 7, 'iούλ' => 7, 'iουλ' => 7, 
			'αύγ' => 8, 'αυγ' => 8, 
			'σεπ' => 9, 'οκτ' => 10, 'όκτ' => 10, 'oκτ' => 10, 'νοέ' => 11, 'νοε' => 11, 'δεκ' => 12,
			# es: spanish (differences)
			'ene' => 1, 'abr' => 4, 'ago' => 8, 'dic' => 12,
			# et: estonian (differences)
			'jaan' => 1, 'veebr' => 2, 'märts' => 3, 'marts' => 3, 'mai' => 5, 'juuni' => 6, 'juuli' => 7, 'sept' => 9, 'okt' => 10, 'dets' => 12,
			# fr: french (differences)
			'janv' => 1, 'févr' => 2, 'fevr' => 2, 'mars' => 3, 'avr' => 4, 'mai' => 5, 'juin' => 6, 'juil' => 7, 'août' => 8, 'aout' => 8, 'déc' => 12, 
			# hu: hungary (differences)
			'márc' => 3, 'marc' => 3, 'ápr' => 4, 'máj' => 5, 'maj' => 5, 'jún' => 6, 'jun' => 6, 'júl' => 7, 'jul' => 7, 'szept' => 9, 'okt' => 10, 
			# it: italy (differences)
			'gen' => 1, 'mag' => 5, 'giu' => 6, 'lug' => 7, 'ago' => 8, 'set' => 9, 'ott' => 10, 'dic' => 12, 
			# lv: latvian (differences)
			'janv' => 1, 'febr' => 2, 'marts' => 3, 'maijs' => 5, 'jūn' => 6, 'jūl' => 7, 'okt' => 10,
			# nl: dutch (differences)
			'maa' => 3, 'mrt' => 3, 'mei' => 5, 
			# no: norway
			'mai' => 5, 'des' => 12, 
			# pt: portugese (differences)
			'fev' => 2, 'ago' => 8, 'set' => 9, 'out' => 10, 'dez' => 12, 
			# ro: romanian (differences)
			'ian' => 1, 'iun' => 6, 'iul' => 7, 'noi' => 11,
			# sk: skovak (differences)
			'máj' => 5, 'maj' => 5, 'jún' => 6, 'júl' => 7, 
			# sl: sloveniabn
			'avg' => 8, 
		);

		#region Groups

		$this->tr1Functions = array(
			// 2010-04-20 functions
			'dateslashus' => 'dateslashus',
			'dateslasheu' => 'dateslasheu',
			'datedotus' => 'datedotus',
			'datedoteu' => 'datedoteu',
			'datelongus' => 'datelongusTR1',
			'dateshortus' => 'dateshortusTR1',
			'datelonguk' => 'datelongukTR1',
			'dateshortuk' => 'dateshortukTR1',
			'numcommadot' => 'numcommadot',
			'numdash' => 'numdash',
			'numspacedot' => 'numspacedot',
			'numdotcomma' => 'numdotcomma',
			'numcomma' => 'numcomma',
			'numspacecomma' => 'numspacecomma',
			'datelongdaymonthuk' => 'datedaymonthLongEnTR1',
			'dateshortdaymonthuk' => 'datedaymonthShortEnTR1',
			'datelongmonthdayus' => 'datemonthdayLongEnTR1',
			'dateshortmonthdayus' => 'datemonthdayShortEnTR1',
			'dateslashdaymontheu' => 'datedaymonthSlashTR1',
			'dateslashmonthdayus' => 'datemonthdaySlashTR1',
			'datelongyearmonth' => 'dateyearmonthLongEnTR1',
			'dateshortyearmonth' => 'dateyearmonthShortEnTR1',
			'datelongmonthyear' => 'datemonthyearLongEnTR1',
			'dateshortmonthyear' => 'datemonthyearShortEnTR1'
		);

		$this->tr2Functions = array(
			// 2011-07-31 functions
			'booleanfalse' => 'booleanfalse',
			'booleantrue' => 'booleantrue',
			'datedaymonth' => 'datedaymonthTR2',
			'datedaymonthen' => 'datedaymonthen',
			'datedaymonthyear' => 'datedaymonthyearTR2',
			'datedaymonthyearen' => 'datedaymonthyearen',
			'dateerayearmonthdayjp' => 'dateerayearmonthdayjp',
			'dateerayearmonthjp' => 'dateerayearmonthjp',
			'datemonthday' => 'datemonthday',
			'datemonthdayen' => 'datemonthdayen',
			'datemonthdayyear' => 'datemonthdayyear',
			'datemonthdayyearen' => 'datemonthdayyearen',
			'datemonthyearen' => 'datemonthyearen',
			'dateyearmonthdaycjk' => 'dateyearmonthdaycjk',
			'dateyearmonthen' => 'dateyearmonthen',
			'dateyearmonthcjk' => 'dateyearmonthcjk',
			'nocontent' => 'nocontent',
			'numcommadecimal' => 'numcommadecimal',
			'zerodash' => 'zerodash',
			'numdotdecimal' => 'numdotdecimal',
			'numunitdecimal' => 'numunitdecimal'
		);
	
		// transformation registry v-3 functions
		// tr3 starts with tr2 and adds more functions
		$this->tr3Functions = array_merge(
			$this->tr2Functions,
			array(
				'calindaymonthyear' => 'calindaymonthyear',
				'datedaymonthdk' => 'datedaymonthdk',
				'datedaymonthyeardk' => 'datedaymonthyeardk',
				'datedaymonthyearin' => 'datedaymonthyearinTR3',
				'datemonthyear' => 'datemonthyearTR3',
				'datemonthyeardk' => 'datemonthyeardk',
				'datemonthyearin' => 'datemonthyearin',
				'dateyearmonthday' => 'dateyearmonthday', // (Y)Y(YY)*MM*DD allowing kanji full-width numerals
				'numdotdecimalin' => 'numdotdecimalin',
				'numunitdecimalin' => 'numunitdecimalin',
			)
		);

		// transformation registry v-4 functions
		// tr4 starts with tr3 and adds more functions

		$this->tr4Functions = array_merge(
			$this->tr3Functions,
			array(
				'date-day-month' => 'datedaymonthTR2',
				'date-day-monthname-bg' => 'datedaymonthbg',
				'date-day-monthname-cs' => 'datedaymonthcs',
				'date-day-monthname-da' => 'datedaymonthdk',
				'date-day-monthname-de' => 'datedaymonthde',
				'date-day-monthname-el' => 'datedaymonthel',
				'date-day-monthname-en' => 'datedaymonthen',
				'date-day-monthname-es' => 'datedaymonthes',
				'date-day-monthname-et' => 'datedaymonthet',
				'date-day-monthname-fi' => 'datedaymonthfi',
				'date-day-monthname-fr' => 'datedaymonthfr',
				'date-day-monthname-hr' => 'datedaymonthhr',
				'date-day-monthname-it' => 'datedaymonthit',
				'date-day-monthname-lv' => 'datedaymonthlv',
				'date-day-monthname-nl' => 'datedaymonthnl',
				'date-day-monthname-no' => 'datedaymonthno',
				'date-day-monthname-pl' => 'datedaymonthpl',
				'date-day-monthname-pt' => 'datedaymonthpt',
				'date-day-monthname-ro' => 'datedaymonthro',
				'date-day-monthname-se' => 'datedaymonthse',
				'date-day-monthname-sk' => 'datedaymonthsk',
				'date-day-monthname-sl' => 'datedaymonthsl',
				'date-day-monthname-sv' => 'datedaymonthdk',
				'date-day-monthroman' => 'datedaymonthroman',
				'date-day-month-year' => 'datedaymonthyearTR4',
				'date-day-monthname-year-bg' => 'datedaymonthyearbg',
				'date-day-monthname-year-cs' => 'datedaymonthyearcs',
				'date-day-monthname-year-da' => 'datedaymonthyeardk',
				'date-day-monthname-year-de' => 'datedaymonthyearde',
				'date-day-monthname-year-el' => 'datedaymonthyearel',
				'date-day-monthname-year-en' => 'datedaymonthyearen',
				'date-day-monthname-year-es' => 'datedaymonthyeares',
				'date-day-monthname-year-et' => 'datedaymonthyearet',
				'date-day-monthname-year-fi' => 'datedaymonthyearfi',
				'date-day-monthname-year-fr' => 'datedaymonthyearfr',
				'date-day-monthname-year-hi' => 'datedaymonthyearinTR4',
				'date-day-monthname-year-hr' => 'datedaymonthyearhr',
				'date-day-monthname-year-it' => 'datedaymonthyearit',
				'date-day-monthname-year-nl' => 'datedaymonthyearnl',
				'date-day-monthname-year-no' => 'datedaymonthyearno',
				'date-day-monthname-year-pl' => 'datedaymonthyearpl',
				'date-day-monthname-year-pt' => 'datedaymonthyearpt',
				'date-day-monthname-year-ro' => 'datedaymonthyearro',
				'date-day-monthname-year-se' => 'datedaymonthyearse',
				'date-day-monthname-year-sk' => 'datedaymonthyearsk',
				'date-day-monthname-year-sl' => 'datedaymonthyearsl',
				'date-day-monthname-year-dk' => 'datedaymonthyeardk',
				'date-day-monthroman-year' => 'datedaymonthyearroman',
				'date-ind-day-monthname-year-hi' => 'calindaymonthyear',
				'date-jpn-era-year-month-day' => 'dateerayearmonthdayjp',
				'date-jpn-era-year-month' => 'dateerayearmonthjp',
				'date-monthname-day-en' => 'datemonthdayen',
				'date-monthname-day-hu' => 'datemonthdayhu',
				'date-monthname-day-lt' => 'datemonthdaylt',
				'date-monthname-day-year-en' => 'datemonthdayyearen',
				'date-month-day' => 'datemonthday',
				'date-month-day-year' => 'datemonthdayyear',
				'date-month-year' => 'datemonthyearTR4',
				'date-monthname-year-bg' => 'datemonthyearbg',
				'date-monthname-year-cs' => 'datemonthyearcs',
				'date-monthname-year-da' => 'datemonthyeardk',
				'date-monthname-year-de' => 'datemonthyearde',
				'date-monthname-year-el' => 'datemonthyearel',
				'date-monthname-year-en' => 'datemonthyearen',
				'date-monthname-year-es' => 'datemonthyeares',
				'date-monthname-year-et' => 'datemonthyearet',
				'date-monthname-year-fi' => 'datemonthyearfi',
				'date-monthname-year-fr' => 'datemonthyearfr',
				'date-monthname-year-hi' => 'datemonthyearin',
				'date-monthname-year-hr' => 'datemonthyearhr',
				'date-monthname-year-it' => 'datemonthyearit',
				'date-monthname-year-nl' => 'datemonthyearnl',
				'date-monthname-year-no' => 'datemonthyearno',
				'date-monthname-year-pl' => 'datemonthyearpl',
				'date-monthname-year-pt' => 'datemonthyearpt',
				'date-monthname-year-ro' => 'datemonthyearro',
				'date-monthname-year-se' => 'datemonthyearse',
				'date-monthname-year-sk' => 'datemonthyearsk',
				'date-monthname-year-sl' => 'datemonthyearsl',
				'date-monthname-year-dk' => 'datemonthyeardk',
				'date-monthname-year-lv' => 'datemonthyearlv',
				'date-monthroman-year' => 'datemonthyearroman',
				'date-year-day-monthname-lv' => 'dateyeardaymonthlv',
				'date-year-month' => 'dateyearmonthTR4',
				'date-year-month-day' => 'dateyearmonthday', // (Y)Y(YY)*MM*DD allowing kanji full-width numerals
				'date-year-monthname-en' => 'dateyearmonthen',
				'date-year-monthname-hu' => 'dateyearmonthhu',
				'date-year-monthname-day-hu' => 'dateyearmonthdayhu',
				'date-year-monthname-day-lt' => 'dateyearmonthdaylt',
				'date-year-monthname-lt' => 'dateyearmonthlt',
				'date-year-monthname-lv' => 'dateyearmonthlv',
				'fixed-empty' => 'nocontent',
				'fixed-false' => 'booleanfalse',
				'fixed-true' => 'booleantrue',
				'fixed-zero' => 'fixedzero',
				'num-comma-decimal' => 'numcommadecimalTR4',
				'num-dot-decimal' => 'numdotdecimalTR4', // relax requirement for 0 before decimal
				'numdotdecimalin' => 'numdotdecimalinTR4',
				'num-unit-decimal' => 'numunitdecimalTR4',
			)
		);

		$this->namespaceFunctions = array(
			\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV1 ] => $this->tr1Functions, // transformation registry v1,
			\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV2 ] => $this->tr2Functions, // transformation registry v2,
			\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV3 ] => $this->tr3Functions, // transformation registry v3,
			\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV4 ] => $this->tr4Functions, // transformation registry v4,
			'http://www.xbrl.org/inlineXBRL/transformation/WGWD/YYYY-MM-DD' => $this->tr4Functions, // transformation registry v4 draft
			'http://www.xbrl.org/2008/inlineXBRL/transformation' => $this->tr1Functions // the CR/PR pre-REC namespace
		);

		#endregion

		foreach( range(0, 9 ) as $i )
		{
			$this->jpDigitsTrTable[ 0xFF10 + $i ] = ord("0") + $i;
		}

		foreach( range(0, 9 ) as $i )
		{
			$this->devanagariDigitsTrTable[ 0x966 + $i ] = ord("0") + $i;
		}	
	}

	#region Utility functions

	/**
	 * Pad year to 4 digit accounting for the centruy
	 * @param string $arg
	 * @return string
	 */
	private function year4( $arg )
	{
		return str_pad ( $arg, 4, '2000', STR_PAD_LEFT );
	}

	/**
	 * General function to parse a date string
	 * @param string $arg The value to transform
	 * @param string $pattern The pattern to apply
	 * @param stringp[] $options An array of option with defaults:
	 * 		'day' => 1, 'month' => 2, 'year' => 3, 'count' => 4, 'moTbl' => null
	 * 		$moTbl A table of month offsets
	 * 		$day Offset of the parsed day value
	 * 		$month Offset of the parsed month value
	 * 		$year Offset of the parsed year value
	 * 		$count
	 * @return string
	 */
	private function datedaymonthyear( $arg, $pattern, $options = array() )
	{
		$options = array_merge( array( 'day' => 1, 'month' => 2, 'year' => 3, 'count' => 4, 'moTbl' => null ), $options );
		$match = preg_match( "~{$pattern}~", $arg, $matches );

		if ( $match && count( $matches ) == $options['count'] )
		{
			$_year = $this->year4( $matches[ $options['year'] ] );
			$_day = $matches[ $options['day'] ];
			$_month = $matches[ $options['month'] ];
			if ( ! $options['moTbl'] ) $options['moTbl'] = $this->monthnumber;
			$_month = $options['moTbl'][ mb_strtolower( $_month ) ] ?? $_month;
			if ( $this->checkDate( $_year, $_month, $_day ) )
				return sprintf( '%s-%02d-%02d', $this->year4( $_year ), $_month, $_day );
		}

		throw new TransformationException( 0, 'xs:date' );	
	}

	/**
	 * General function to parse a month/day string
	 * @param string $arg The value to transform
	 * @param string $pattern The pattern to apply
	 * @param stringp[] $options An array of option with defaults:
	 * 		'day' => 1, 'month' => 2, 'count' => 3, 'moTbl' => null
	 * 		$moTbl A table of month offsets
	 * 		$day Offset of the parsed day value
	 * 		$month Offset of the parsed month value
	 * 		$count
	 * @return string
	 */
	private function datedaymonth( $arg, $pattern, $options = array() )
	{
		$options = array_merge( array( 'day' => 1, 'month' => 2, 'count' => 3, 'moTbl' => null, 'reOption' => '' ), $options );
		$match = preg_match( "~{$pattern}~{$options['reOption']}", $arg, $matches );

		if ( $match && count( $matches ) == $options['count'] )
		{
			$_day = $matches[ $options['day'] ];
			$_month = $matches[ $options['month'] ];
			if ( ! $options['moTbl'] ) $options['moTbl'] = $this->monthnumber;
			$_month = $options['moTbl'][ mb_strtolower( $_month ) ] ?? $_month;
			if ( $_day <= $this->maxDayInMo[ $_month ] ?? '00' )
				return sprintf( '--%02d-%02d', $_month, $_day );
			}

		throw new TransformationException( 0, 'xs:gMonthDay' );	
	}

	/**
	 * General function to parse a month/year string
	 * @param string $arg The value to transform
	 * @param string $pattern The pattern to apply
	 * @param stringp[] $options An array of option with defaults:
	 * 		'day' => 1, 'month' => 2, 'count' => 3, 'moTbl' => null
	 * 		$moTbl A table of month offsets
	 * 		$year Offset of the parsed yeat value
	 * 		$month Offset of the parsed month value
	 * 		$count
	 * @return string
	 */
	private function datemonthyear( $arg, $pattern, $options = array() )
	{
		$options = array_merge( array( 'year' => 1, 'month' => 2, 'count' => 3, 'moTbl' => null ), $options );
		$match = preg_match( "~{$pattern}~", $arg, $matches );

		if ( $match && count( $matches ) == $options['count'] )
		{
			$_year = $this->year4( $matches[ $options['year'] ] );
			$_month = $matches[ $options['month'] ];
			if ( ! $options['moTbl'] ) $options['moTbl'] = $this->monthnumber;
			$_month = $options['moTbl'][ mb_strtolower( $_month ) ] ?? $_month;
			return sprintf( '%s-%02d', $_year, $_month );
		}
		throw new TransformationException( 0, 'xs:gYearMonth' );	
	}

	/**
	 * Ensure the collected values represent a valid date
	 * @param int $y
	 * @param int $m
	 * @param int $d
	 * @return void
	 */
	private function checkDate( $y, $m, $d) 
	{
        if ( ( $time = mktime( 0, 0, 0, intval( $m ), intval( $d ), intval( $y ) ) ) === false )
			return false;
		// This checks in the input date is the same as $time which will not be the case for 30th Feb
		return date('Y', $time ) == $y && date('m', $time ) == $m && date('d', $time ) == $d;
	}

	/**
	 * Translate JP digits
	 * @param string $digit
	 * @return string
	 */
	private function jpDigitsToNormal( $digits )
	{
		return $this->translate( $digits, $this->jpDigitsTrTable );
	}

	/**
	 * Translate IN digits
	 * @param string $digit
	 * @return string
	 */
	private function devanagariDigitsToNormal( $devanagariDigits )
	{
		return $this->translate( $devanagariDigits, $this->devanagariDigitsTrTable );
	}

	/**
	 * Translate text using an array of codepoint mappings
	 *
	 * @param [type] $text
	 * @param [type] $table
	 * @return void
	 */
	function translate( $text, $table )
	{
		$chars = mb_str_split( $text );
		foreach( $chars as $index => $char )
		{
			$ord = mb_ord( $char );
			$chars[ $index ] = mb_chr( $table[ $ord ] ?? $ord );
		}
		return implode('', $chars );
	}

	# see: http://www.i18nguy.com/l10n/emperor-date.html        

	/**
	 * Return the year for the era
	 * @param string $era
	 * @param string $year
	 * @return string
	 */
	private function eraYear( $era, $year )
	{
		return $this->eraStart[ $era ] + ($year == '元' ? 1 : intval( $year ) );
	}

	/**
	 * Concert an Indian calendar date to gregorian
	 * @param string $sYear
	 * @param string $sMonth
	 * @param string $sDay
	 * @return string[]
	 */
	private function sakaToGregorian( $sYear, $sMonth, $sDay)
	{
		$sYear = $sYear + 78;  # offset from Saka to Gregorian year
		$sStartsInLeapYr = $sYear % 4 == 0 && ( $sYear % 100 != 0 || $sYear % 400 == 0 ); // Saka yr starts in leap year

		if ( $sYear < 0 )
			throw new \Exception( sprintf( "Saka calendar year not supported: %s %s %s ", $sYear, $sMonth, $sDay ) );
		if ( $sMonth < 1 || $sMonth > 12 )
			throw new \Exception( sprintf( "Saka calendar month error: %s %s %s ", $sYear, $sMonth, $sDay ) );

		$sMonthLength = $this->sakaMonthLength[ $sMonth - 1 ];
		if ( $sStartsInLeapYr && $sMonth == 1 )
			$sMonthLength += 1; // Chaitra has 1 extra day when starting in gregorian leap years

		if ( $sDay < 1 || $sDay > $sMonthLength )
			throw new \Exception( sprintf( "Saka calendar day error: %s %s %s ", $sYear, $sMonth, $sDay ) );

		list( $gMonth, $gDayOffset, $sYearOffset ) = $this->sakaMonthOffset[ $sMonth - 1 ]; // offset Saka to Gregorian by Saka month

		if ( $sStartsInLeapYr && $sMonth == 1 )
			$gDayOffset -= 1; // Chaitra starts 1 day earlier when starting in Gregorian leap years

		$sYear += $sYearOffset; // later Saka months offset into next Gregorian year

		$gMonthLength = $this->gLastMoDay[ $gMonth - 1 ]; // month length (days in month)
		if ( $gMonth == 2 && $sYear % 4 == 0 && ( $sYear % 100 != 0 || $sYear % 400 == 0 ) ) // does Phalguna (Feb) end in a Gregorian leap year?
			$gMonthLength += 1; // Phalguna (Feb) is in a Gregorian leap year (Feb has 29 days)

		$gDay = $gDayOffset + $sDay - 1;
		if ( $gDay > $gMonthLength ) // overflow from Gregorial month of start of Saka month to next Gregorian month
		{
			$gDay -= $gMonthLength;
			$gMonth += 1;
			if ( $gMonth == 13 )  // overflow from Gregorian year of start of Saka year to following Gregorian year
			{
				$gMonth = 1;
				$sYear += 1;
			}
		}
		return array($sYear, $gMonth, $gDay);
	}

	# zero pad to 4 digits
	private function yearIN( $arg, $_month, $_day )
	{
		if ( $arg && strlen( $arg ) == 2 )
		{
			return ( $arg > '21' || ( $arg == '21' && $_month >= 10 && $_day >= 11 ) ? '19' : '20' ) . $arg;
		}
		return $arg;
	}

	/**
	 * Core function for TR3 nd TR4 IN date functions
	 * @param string $arg
	 * @param string $daymonthyearInPattern
	 * @return void
	 */
	private function datedaymonthyearin( $arg, $daymonthyearInPattern )
	{
    	$match = preg_match( "~$daymonthyearInPattern~u", $arg, $matches );
		if ( $match )
		{
			$_year = $this->year4( $this->devanagariDigitsToNormal( $matches[3] ) );
			$_month = $this->gregorianHindiMonthNumber[ $matches[2] ] ?? $this->devanagariDigitsToNormal( $matches[2] );
			$_day = $this->devanagariDigitsToNormal( $matches[1] );
			if ( $this->checkDate( $_year, $_month, $_day ) )
				return sprintf( "%s-%02d-%02d", $_year, $_month, $_day );
		}
		throw new TransformationException( 0, 'xs:date' );	
	}

	private function canonicalNumber( $arg )
	{
		$match = preg_match( $this->numCanonicalizationPattern, $arg, $matches );
		if ( $matches )
        	return ( $matches[1] ?? "0" ) . ( $matches[4] ?? "");
    	return $arg;
	}

	#endregion

	#region Functions

	/**
	 * Sets the TRR namespace and selects the correct format functions array
	 * @param string $namespace
	 * @return void
	 * @throws \Exception If the namespace is not recognised
	 */
	public function setTransformVersion( $namespace )
	{
		$this->transformNamespace = null;
		$this->transformFunctions = $this->namespaceFunctions[ $namespace ] ?? null;
		if ( ! $this->transformFunctions )
			throw new \Exception("The namespace '$namespace' is not valid, it must one of the TRR namespaces: " . join( ', ', array_keys( $this->namespaceFunctions ) ) );
		$this->transformNamespace = $namespace;
	}

	/**
	 * Transform a value in $arg using the TRR format in $format
	 * @param string $format The TRR format to apply
	 * @param string $arg The value to which the format will be applied
	 * @return string
	 * @throws \Exception If a TRR namespace is not set or the format is not valid for the namespace selected
	 */
	public function transform( $format, $arg )
	{
		if ( ! $this->transformFunctions )
			throw new \Exception('The transformation cannot proceed because a TRR namespace has not been set.');

		$formatFunction = $this->transformFunctions[ $format ] ?? null;
		if ( ! $formatFunction )
			throw new IXBRLInvalidNamespaceException("The format '$format' is not valid for the TRR namespace '{$this->transformNamespace}'");

		return $this->$formatFunction( $arg );
	}

	/**
	 * Reformats US-style slash-separated dates into XSD format
	 * @param string $arg The value to parse
	 * @return string
	 * @throws TransformationException
	 */
	public function dateslashus( $arg )
	{
		$match = preg_match( "~{$this->dateslashPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 4 )
			return sprintf( '%s-%02d-%02d', $this->year4( $matches[3] ), $matches[1], $matches[2] );
		throw new TransformationException( 0, 'xs:date' );
	}

	/**
	 * Reformats EU-style slash-separated dates into XSD format
	 * @param string $arg The value to parse
	 * @return string
	 * @throws TransformationException
	 */
	public function dateslasheu( $arg )
	{
		$match = preg_match( "~{$this->dateslashPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 4 )
			return sprintf( '%s-%02d-%02d', $this->year4( $matches[3] ), $matches[2], $matches[1] );
		throw new TransformationException( 0, 'xs:date' );
	}

	/**
	 * Date in format DD.MM.YY(YY). Will also accept single digits for D, M, Y. Does not check for valid day or month. e.g. accepts 30.02.2008 40.40.2008
	 * @param string $arg The value to parse
	 * @return string
	 * @throws TransformationException
	 */
	private function datedotus( $arg )
	{
		$match = preg_match( "~{$this->datedotPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 4 )
			return sprintf( '%s-%02d-%02d', $this->year4( $matches[3] ), $matches[1], $matches[2] );
		throw new TransformationException( 0, 'xs:date' );
	}

	/**
	 * Reformats EU-style dot-separated dates into XSD format
	 * @param string $arg The value to parse
	 * @return string
	 * @throws TransformationException
	 */
	private function datedoteu( $arg )
	{
		$match = preg_match( "~{$this->datedotPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 4 )
			return sprintf( '%s-%02d-%02d', $this->year4( $matches[3] ), $matches[2], $matches[1] );
		throw new TransformationException( 0, 'xs:date' );
	}

	/**
	 * Reformats US-style long dates into XSD format
	 * @param string $arg Date in the format Month DD, (YY)YY
	 * @return string
	 * @throws TransformationException
	 */
	private function datelongusTR1( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->dateLongUsTR1Pattern, array( 'day' => 2, 'month' => 1, 'year' => 3 ) );
	}
 
	/**
	 * Reformats US-style short dates into XSD format
	 * @param string $arg Date in the format Mon DD, (YY)YY
	 * @return string
	 * @throws TransformationException
	 */
	private function dateshortusTR1( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->dateShortUsTR1Pattern, array( 'day' => 2, 'month' => 1, 'year' => 3 ) );
	}

	/**
	 * Reformats UK-style long dates into XSD format
	 * @param string $arg Date in the abbreviated month format DD Month (YY)YY
	 * @return string
	 * @throws TransformationException
	 */
	private function datelongukTR1( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->dateLongUkTR1Pattern, array( 'day' => 1, 'month' => 2, 'year' => 3 ) );
	}

	/**
	 * Reformats UK-style short dates into XSD format
	 * @param string $arg Date in the abbreviated month format DD Mon (YY)YY
	 * @return string
	 * @throws TransformationException
	 */
	private function dateshortukTR1( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->dateShortUkTR1Pattern, array( 'day' => 1, 'month' => 2, 'year' => 3 ) );
	}

	/**
	 * Reformats "human readable" numbers using commas (,) as a thousands separator into XSD format floating point value
	 * @param string $arg The numeric value with comma thousands separator.
	 * @return string
	 * @throws TransformationException
	 */
	private function numcommadot( $arg )
	{
		$match = preg_match( "~{$this->numCommaDotPattern}~", $arg );
		if ( $match )
			return str_replace( ',', '', $arg );
		throw new TransformationException( 0, 'ixt:numcommadot' );	
	}

	/**
	 * Reformats accountant-friendly '-' as a zero.
	 * @param string $arg The dash used to denote nothing.
	 * @return string
	 * @throws TransformationException
	 */
	private function numdash( $arg )
	{
		$match = preg_match( "~{$this->numDashPattern}~", $arg );
		if ( $match )
			return str_replace( '-', '0', $arg );
		throw new TransformationException( 0, 'ixt:numdash' );	
	}

	/**
	 * Reformats "human readable" numbers using space (" ") as a thousands separator into XSD format floating
	 * @param string $arg The numeric value with space thousands separator.
	 * @return string
	 * @throws TransformationException
	 */
	private function numspacedot( $arg )
	{
		$match = preg_match( "~{$this->numSpaceDotPattern}~", $arg );
		if ( $match )
			return str_replace( ' ', '', $arg );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );		
	}

	/**
	 * Reformats "human readable" numbers using dot (.) as a thousands separator and comma (,) as fraction separator into XSD format floating point value
	 * @param string $arg The numeric value with dot thousands separator and comma fraction separator.
	 * @return string
	 * @throws TransformationException
	 */
	private function numdotcomma( $arg )
	{
		$match = preg_match( "~{$this->numDotCommaPattern}~", $arg );
		if ( $match )
			return str_replace( ',', '.', str_replace( '.', '', $arg ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );		
	}

	/**
	 * Reformats "human readable" numbers using comma (,) as fraction separator into XSD format floating point
	 * @param string $arg The numeric value with comma fraction separator.
	 * @return string
	 * @throws TransformationException
	 */
	private function numcomma( $arg )
	{
		$match = preg_match( "~{$this->numCommaPattern}~", $arg );
		if ( $match )
			return str_replace( ',', '.', $arg );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );		
	}

	/**
	 * Reformats "human readable" numbers using space (" ") as a thousands separator into XSD format floating point value
	 * @param string $arg The numeric value as an XSD type ixt:nonNegativeDecimalType.
	 * @return string
	 * @throws TransformationException
	 */
	private function numspacecomma( $arg )
	{
		$match = preg_match( "~{$this->numSpaceCommaPattern}~", $arg );
		if ( $match )
			return str_replace( ',', '.', str_replace( ' ', '', $arg ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );		
	}

	/**
	 * Reformats recurring date in format "DD Month" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datedaymonthLongEnTR1( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthLongEnTR1Pattern, array( 'day' => 1, 'month' => 2 ) );
	}

	/**
	 * Reformats recurring date in format "DD Mon" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datedaymonthShortEnTR1( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthShortEnTR1Pattern, array( 'day' => 1, 'month' => 2 ) );
	}

	/**
	 * Reformats recurring date in format "Month DD" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datemonthdayLongEnTR1( $arg )
	{
		return $this->datedaymonth( $arg, $this->monthdayLongEnTR1Pattern, array( 'day' => 2, 'month' => 1 ) );
	}

	/**
	 * Reformats recurring date in format "Mon DD" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datemonthdayShortEnTR1( $arg )
	{
		return $this->datedaymonth( $arg, $this->monthdayShortEnTR1Pattern, array( 'day' => 2, 'month' => 1 ) );
	}

	/**
	 * Reformats recurring date "DD/MM" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datedaymonthSlashTR1( $arg )
	{
		$match = preg_match( "~{$this->daymonthslashPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 3 )
			return sprintf( '--%02d-%02d', $matches[2], $matches[1] );
		throw new TransformationException( 0, 'xs:gMonthDay' );
	}

	/**
	 * Reformats recurring date "MM/DD" into W3C/ISO recurring date standard --MM-DD
	 * @param string $arg The numeric value as an XSD type xs:gMonthDay.
	 * @return string
	 * @throws TransformationException
	 */
	private function datemonthdaySlashTR1( $arg )
	{
		$match = preg_match( "~{$this->monthdayslashPattern}~", $arg, $matches );
		if ( $match && count( $matches ) == 3 )
			return sprintf( '--%02d-%02d', $matches[1], $matches[2] );
		throw new TransformationException( 0, 'xs:gMonthDay' );
	}

	/**
	 * Reformats date in format "(YY)YY Month" into W3C/ISO date standard YYYY-MM
	 * @param string $arg Date in format "(YY)YY Month"
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthLongEnTR1( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthLongEnTR1Pattern, array( 'month' => 2, 'year' => 1 ) );
	}

	/**
	 * Reformats date in format "(YY)YY Mon" into W3C/ISO date standard YYYY-MM
	 * @param string $arg Date in format "(YY)YY Month"
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthShortEnTR1( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthShortEnTR1Pattern, array( 'month' => 2, 'year' => 1 ) );
	}

	/**
	 * Reformats date in format "Month (YY)YY" into W3C/ISO date standard YYYY-MM
	 * @param string $arg Date in format "Month (YY)YY"
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearLongEnTR1( $arg )
	{
		return $this->datemonthyear( $arg, $this->monthyearLongEnTR1Pattern, array( 'month' => 1, 'year' => 2 ) );
	}

	/**
	 * Reformats date in format "Mon (YY)YY" into W3C/ISO date standard YYYY-MM
	 * @param string $arg Date in format "Mon (YY)YY"
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearShortEnTR1( $arg )
	{
		return $this->datemonthyear( $arg, $this->monthyearShortEnTR1Pattern, array( 'month' => 1, 'year' => 2 ) );
	}

	/**
	 * Transforms free-form string into boolean false.
	 * @param string $arg Any text
	 * @return string ixt:booleanfalseType
	 * @throws TransformationException
	 */
	private function booleanfalse( $arg = null )
	{
		return 'false';
	}

	/**
	 * Transforms free-form string into boolean true.
	 * @param string $arg Any text
	 * @return string ixt:booleantrueType
	 * @throws TransformationException
	 */
	private function booleantrue( $arg = null )
	{
		return 'true';
	}

	/**
	 * Transforms numeric date in the format "(D)D*(M)M", with non-numeric separator, 
	 * into W3C/ISO recurring date standard "--MM-DD" format. The result must be a 
	 * valid xs:gMonthDay, so for example, "30/02" is not permitted.
	 * @param string $arg Numeric date in the format "(D)D*(M)M", with non-numeric separato
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datedaymonthTR2( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthPattern, array( 'day' => 1, 'month' => 2 ) );
	}

	/**
	 * Transforms English date in the format "(D)D*Mon(th)" into W3C/ISO recurring date 
	 * standard "--MM-DD" format. When a date contains several month names (e.g. "30th 
	 * day of January, March and April"), the transform must match the last occurrence. 
	 * The result must be a valid xs:gMonthDay, so for example, "30th February" is not permitted.
	 * @param string $arg Numeric date in the format "(D)D*(M)M", with non-numeric separator.
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datedaymonthen( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthEnPattern );
	}

	/**
	 * Transforms numeric date in the format "(D)D*(M)M*(Y)Y(YY)", with non-numeric separators, 
	 * into W3C/ISO date standard "YYYY-MM-DD" format. Two-digit years are assumed to fall 
	 * between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result 
	 * must be a valid xs:date, so for example, "30.02.09" is not permitted.
	 * @param string $arg Numeric date in the format "(D)D*(M)M*(Y)Y(YY)", with non-numeric separators.
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearTR2( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearPattern );
	}

	/**
	 * Transforms English date in the format "(D)D*Mon(th)*(Y)Y(YY)" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * The result must be a valid xs:date, so for example, "30 February 2009" is not permitted. When a date contains
	 * several month names (e.g. "30th day of January, March and April, 1969"), the transform must match the last occurrence.
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearen( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearEnPattern );
	}

	/**
	 * Transforms Japanese date in the format "era year month day" (e.g. "平成元年5月31日") into XML Schema format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date, so for example, "平成元年2月30日" is not permitted
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateerayearmonthdayjp( $arg )
	{
		$match = preg_match( "~{$this->erayearmonthdayjpPattern}~", $this->jpDigitsToNormal( $arg ), $matches );
		if ( $match && count( $matches ) == 8 )
		{
			$_year = $this->eraYear( $matches[1], $matches[2] );
			$_month = $matches[4];
			$_day = $matches[6];
			if ( $this->checkDate( $_year, $_month, $_day ) )
				return sprintf( "%s-%02d-%02d", $this->year4( $_year ), $_month, $_day );
		}
		throw new TransformationException( 0, 'xs:date' );		
	}

	/**
	 * Transforms Japanese date in the format "era year month" (e.g. "平成元年5月") into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result 
	 * must be a valid xs:gYearMonth, so for example, "平成元年13月" is not permitted.
	 * @param string $arg Japanese date in the format "era year month day" (e.g. "平成元年5月31日").
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateerayearmonthjp( $arg )
	{
		$match = preg_match( "~{$this->erayearmonthjpPattern}~", $this->jpDigitsToNormal( $arg ), $matches );
		if ( $match && count( $matches ) == 6 )
		{
			$_year = $this->eraYear( $matches[1], $matches[2] );
			$_month = $matches[4];
			if ( "01" <= $_month && $_month <= "12" )
				return sprintf( "%s-%02d", $this->year4( $_year ), $_month );
		}
		throw new TransformationException( 0, 'xs:date' );
	}

	/**
	 * Transforms numeric date in the format "(M)M*(D)D", with non-numeric separator, into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay, so for example, "02/30" is not permitted.
	 * @param string $arg Japanese date in the format "era year month" (e.g. "平成元年5月").
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datemonthday( $arg )
	{
		return $this->datedaymonth( $arg, $this->monthdayPattern, array( 'month' => 1, 'day' => 2, 'count' => 3 ) );
	}

	/**
	 * Transforms English date in the format "Mon(th)*(D)D(Ordinal)" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * Accepts single digits for D. Accepts months in full or abbreviated form, with non-numeric separator. 
	 * Any ordinal of one or two letters is accepted. The result must be a valid xs:gMonthDay, so for example, 
	 * "February 30" is not permitted. When a date contains several month names (e.g. January, March and April 30"), 
	 * the transform must match the first occurrence.
	 * @param string $arg English date in the format "Mon(th)*(D)D".
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datemonthdayen( $arg )
	{
		return $this->datedaymonth( $arg, $this->monthdayEnPattern, array( 'month' => 1, 'day' => 2, 'count' => 3 ) );
	}

	/**
	 * Transforms numeric date in the format "(M)M*(D)D*(Y)Y(YY)", with non-numeric separators, into W3C/ISO date standard 
	 * "YYYY-MM-DD" format. Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 
	 * 2000 and 2009. The result must be a valid xs:date, so for example, "02.30.09" is not permitted.
	 * @param string $arg Numeric date in the format "(M)M*(D)D*(Y)Y(YY)", with non-numeric separators.
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datemonthdayyear( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->monthdayyearPattern, array( 'month' => 1, 'day' => 2, 'year' => 3 ) );
	}

	/**
	 * Transforms English date in the format "Mon(th)*(D)D*(Y)Y(YY)" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date, so for example, "February 30th, 2009" is not permitted. When a date 
	 * contains several month names (e.g. "January, March and April the 30th, 1969"), the transform must match the first occurrence.
	 * @param string $arg English date in the format "Mon(th)*(D)D*(Y)Y(YY)".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datemonthdayyearen( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->monthdayyearEnPattern, array( 'month' => 1, 'day' => 2, 'year' => 3 ) );
	}

	/**
	 * Transforms English date in the format "Mon(th)*(Y)Y(YY)" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 
	 * 2009. When a date contains several month names (e.g. "January, March and April, 1969"), the transform 
	 * must match the first occurrence. 1969"), the transform must match the first occurrence.
	 * @param string $arg English date in the format "Mon(th)*(Y)Y(YY)".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearen( $arg )
	{
		return $this->datemonthyear( $arg, $this->monthdayEnPattern, array( 'month' => 1, 'year' => 2, 'count' => 3 ) );
	}

	/**
	 * Transforms Japanese, Chinese or Korean date in the format "year month day" (e.g. "2010年5月31日") into W3C/ISO 
	 * date standard "YYYY-MM-DD" format. Two-digit years are assumed to fall between 2000 and 2099 and one-digit years 
	 * to fall between 2000 and 2009. The result must be a valid xs:date, so for example, "2010年2月30日" is not permitted.
	 * @param string $arg Japanese, Chinese or Korean date in the format "year month day" (e.g. "2010年5月31日").
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateyearmonthdaycjk( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->yearmonthdaycjkPattern, array( 'month' => 3, 'day' => 5, 'year' => 1, 'count' => 7 ) );
	}

	/**
	 * TTransforms English date in the format "(Y)Y(YY)*Mon(th)" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * When a date contains several month names (e.g. "1969, January, March and April"), the transform must match the last occurrence.permitted.
	 * @param string $arg English date in the format "(Y)Y(YY)*Mon(th)".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthen( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthEnPattern, array( 'month' => 2, 'year' => 1, 'count' => 3 ) );
	}

	/**
	 * Transforms Japanese, Chinese or Korean date in the format "year month" (e.g. "2010年5月") into W3C/ISO date 
	 * standard "YYYY-MM" format. Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to 
	 * fall between 2000 and 2009. The result must be a valid xs:gYearMonth, so for example, "2010年13月" is not permitted.
	 * @param string $arg Japanese, Chinese or Korean date in the format "year month" (e.g. "2010年5月")
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthcjk( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthcjkPattern, array( 'month' => 3, 'year' => 1, 'count' => 5 ) );
	}

	/**
	 * This transformation allows a selection of free-form data to be associated with an empty XBRL concept. 
	 * It is used in cases where, for instance, an empty concept is defined as a flag but it is desirable to 
	 * tie the use of that flag to information displayed on the face of the Inline XBRL document.
	 * @param string $arg Any text
	 * @return string ixt:nocontentType
	 * @throws TransformationException
	 */
	private function nocontent( $arg )
	{
		return '';
	}

	/**
	 * Transforms number with comma (",") fraction separator and optional thousands separators into 
	 * non-negative number based on schema-defined decimal format.
	 * @param string $arg The numeric value with comma decimal separator.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numcommadecimal( $arg )
	{
		$match = preg_match( "~{$this->numCommaDecimalPattern}~", $arg );
		if ( $match )
			return str_replace( "\u{00A0}", '', str_replace( ' ', '', str_replace( ',', '.', str_replace( '.', '', $arg ) ) ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );	
	}

	/**
	 * Reformats various unicode dashes as a zero.
	 * @param string $arg The dash used to denote nothing.
	 * @return string ixt:zeroIntegerType
	 * @throws TransformationException
	 */
	private function zerodash( $arg )
	{
		$match = preg_match( "~{$this->zeroDashPattern}~", $arg );
		if ( $match )
			return '0';
		throw new TransformationException( 0, 'ixt:zeroIntegerType' );	
	}

	/**
	 * Transforms number with dot (".") fraction separator and optional thousands separators 
	 * into non-negative number based on schema-defined decimal format.
	 * @param string $arg The numeric value with dot decimal separator.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numdotdecimal( $arg )
	{
		$match = preg_match( "~{$this->numDotDecimalPattern}~", $arg );
		if ( $match )
			return str_replace( "\u{00A0}", '', str_replace( ' ', '', str_replace( ',', '', $arg ) ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );	
	}

	/**
	 * Transforms mixed string monetary value with string unit indicators and optional thousands 
	 * separators into non-negative number based on schema-defined decimal format. Supports 
	 * single- and double-byte characters.
	 * @param string $arg Monetary format with one or more unit string suffixes, with either single or double-byte characters.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numunitdecimal( $arg )
	{
		$match = preg_match( "~{$this->numUnitDecimalPattern}~", $this->jpDigitsToNormal( $arg ), $matches );
		if ( $match  && count( $matches) > 1 )
			return str_replace("\{uFF0E}",'', str_replace('\uFF0C','', str_replace(',','', str_replace('.','', $matches[1] ) ) ) ) . '.' . str_pad( $matches[ count( $matches ) - 1 ], STR_PAD_LEFT );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );	
	}

	/**
	 * Transforms an Indian date based on the Indian National Calendar in the order "day month year" 
	 * (using Hindi names for Saka months, or the equivalent Latin transliteration; e.g. "11 पौष 1921" 
	 * or "11 Pausha 1921"; and either Arabic or Devanagari numerals; e.g. ११ पौष १९२१) into the 
	 * Gregorian Calendar using W3C/ISO date standard "YYYY-MM-DD" format. Accepts double digits for year. 
	 * Two-digit years are assumed to fall between 2000 and 2099 in the Gregorian Calendar.
	 * @param string $arg Indian date based on the Indian National Calendar in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function calindaymonthyear( $arg )
	{
		$match = preg_match( "~{$this->daymonthyearInIndPattern}~u", $arg, $matches );
		if ( ! $match )
			throw new TransformationException( 0, 'xs:date' );
		if ( ! preg_match( "~{$this->sakaMonthPattern}~", $matches[2], $monthMatches ) )
		{
			throw new TransformationException( 0, 'calindaymonthyear' );
		}
		$_month = count( $monthMatches ) - 1;
		$_day = intval( $this->devanagariDigitsToNormal( $matches[1] ) );
		$_year = intval( $this->devanagariDigitsToNormal( $this->yearIN( $matches[15], $_month, $_day ) ) );
		$gregorianDate = $this->sakaToGregorian( $_year, $_month, $_day ); 
		return sprintf( "%s-%02d-%02d", $gregorianDate[0], $gregorianDate[1], $gregorianDate[2] );
	}

	/**
	 * Transforms Danish date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. februar" is not permitted.
	 * @param string $arg Danish date in the order "day month".
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datedaymonthdk( $arg )
	{
		$match = preg_match( "~{$this->daymonthDkPattern}~i", $arg, $matches );
		if ( $match && count( $matches ) == 4 )
		{
			$day = $matches[1];
			$monthName = strtolower( $matches[2] );
			$monEnd = $matches[3];
			$monPer = $matches[4]; 
			if ( ( $month = $this->monthnumber[ $monthName ] ?? false ) && 
				 ( ! $monEnd || ! $monPer ) && 
				 ( '01' <= $day && $day <= $this->maxDayInMo[ $month ] ?? "00" )
			)
			{
				return sprintf( "--%02d-%02d", $month, $day );
			}
		}
		throw new TransformationException(0, 'datedaymonthdk');
	}

	/**
	 * Transforms Danish date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. Two-digit years 
	 * are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result must be a 
	 * valid xs:date so, for example, "30. februar 2009" is not permitted.
	 * @param string $arg Danish date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyeardk( $arg )
	{
		$match = preg_match( "~{$this->daymonthyearDkPattern}~i", $arg, $matches );
		if ( $match && count( $matches ) == 6 )
		{
			$year = $matches[5];
			$day = $matches[1];
			$monthName = strtolower( $matches[2] );
			$monEnd = $matches[3];
			$monPer = $matches[4]; 
			if ( ( $month = $this->monthnumber[ $monthName ] ?? false ) && 
				 ( ! $monEnd || ! $monPer ) && 
				 ( '01' <= $day && $day <= $this->maxDayInMo[ $month ] ?? "00" )
			)
			{
				return sprintf( "%s-%02d-%02d", $this->year4( $year ), $month, $day );
			}
		}
		throw new TransformationException(0, 'datedaymonthyeardk');
	}

	/**
	 * Transforms Indian date based on the Gregorian Calendar in the order "day month year" (using Hindi names for Gregorian months; 
	 * e.g. "19 सितंबर 2012"; either Arabic or Devanagari numerals for day and year; e.g. "१९ सितंबर २०१२"; or using Devanagari numerals 
	 * throughout; e.g. "१९ ०९ २०१२") into W3C/ISO date standard "YYYY-MM-DD" format. Accepts single digits for month and double digits for year.
	 * @param string $arg Indian date based on Gregorian Calendar in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearinTR3( $arg )
	{
		return $this->datedaymonthyearin( $arg, $this->daymonthyearInPatternTR3 );
	}

	/**
	 * Transforms numeric date in the order "month year", with non-numeric separator, into W3C/ISO date standard "YYYY-MM" format.
	 * @param string $arg Numeric date in the order "month year".
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datemonthyearTR3( $arg )
	{
		$match = preg_match( "~{$this->monthyearPattern}~", $arg, $matches ); // "(M)M*(Y)Y(YY)", with non-numeric separator,
		if ( $match && count( $matches ) == 3 )
		{
			$year = $this->year4( $matches[2] );
			$month = $matches[1];
			if ( "01" <= $month && $month <= "12" )
				return sprintf( "%s-%02d", $year, $month );
		}
	}

	/**
	 * Transforms Danish date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. Two-digit years are 
	 * assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Danish date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyeardk( $arg )
	{
		$match = preg_match( "~{$this->monthyearDkPattern}~i", $arg, $matches );
		if ( $match && count( $matches ) == 5 )
		{
			$year = $matches[4];
			$monthName = strtolower( $matches[1] );
			$monEnd = $matches[2];
			$monPer = $matches[3]; 
			if ( ( $month = $this->monthnumber[ $monthName ] ?? false ) && 
				 ( ! $monEnd || ! $monPer )
			)
			{
				return sprintf( "%s-%02d", $this->year4( $year ), $month );
			}
		}
		throw new TransformationException(0, 'datedaymonthyeardk');
	}

	/**
	 * Transforms Indian date based on the Gregorian Calendar in the order "month year" (using Hindi names for Gregorian months; 
	 * e.g. सितंबर 2012) and either Arabic or Devanagari numerals; e.g. सितंबर २०१२) into W3C/ISO date standard "YYYY-MM" format. 
	 * Accepts double digits for year.
	 * @param string $arg Indian date based on the Gregorian Calendar in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearin( $arg )
	{
		$match = preg_match( "~{$this->monthyearInPattern}~u", $arg, $matches );
		if ( $match && count( $matches ) == 3 )
			return sprintf( "%s-%02d", $this->year4( $this->devanagariDigitsToNormal( $matches[2] ) ), $this->gregorianHindiMonthNumber[ $matches[1] ] );

	   throw new TransformationException(0, 'datedaymonthyeardk');
	}

	/**
	 * Transforms numeric date in the order "year month day", with non-numeric separators, into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result must be a 
	 * valid xs:date, so for example, "09.02.30" is not permitted.
	 * @param string $arg Numeric date in the order "year month day".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateyearmonthday( $arg ) 
	{
		$digits = $this->jpDigitsToNormal( $arg );
		$digits = $this->devanagariDigitsToNormal( $digits );
		$match = preg_match( "~{$this->yearmonthdayPattern}~u", $digits, $matches );
		if ( $match && count( $matches ) == 4 )
		{
			$year = $this->year4( $matches[1] );
			$month = $matches[2];
			$day = $matches[3];
			if ( $this->checkDate( $year, $month, $day ) )
				return sprintf( "%s-%02d-%02d", $year, $month, $day );
		}

		throw new TransformationException(0, 'dateyearmonthday');
	}

	/**
	 * Transforms Indian number with dot (".") fraction separator and a comma after first 3 digits (after thousand) and then comma after 
	 * each 2 digits (e.g. "1,00,00,000") into non-negative number based on schema-defined decimal format.
	 * @param string $arg Indian numeric value with a comma after first 3 digits (after thousand) and then comma after each 2 digits.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numdotdecimalin( $arg )
	{
		$match = preg_match( "~{$this->numDotDecimalInPattern}~", $arg, $matches ); // (Y)Y(YY)*MM*DD with kangu full-width numerals
		if ( $match )
		{
			$fract = strpos( $matches[ count( $matches ) - 1 ], '.' ) === 0
				? $matches[ count( $matches ) - 1 ]
				: '';
			return str_replace( "\u{a0}", '', str_replace( ' ', '', str_replace( ',', '', $matches[1] ) ) ) . $fract;
		}

		throw new TransformationException(0, 'numdotdecimalin');
	}

	/**
	 * Transforms Indian mixed string monetary value with string unit indicators and with a comma after first 3 digits (after thousand) and 
	 * then comma after each 2 digits (e.g. "1,000 rupees 50 paise") into non-negative number based on schema-defined decimal format.
	 * @param string $arg Indian Monetary format with one or more unit string suffixes.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numunitdecimalin( $arg )
	{
		$match = preg_match( "~{$this->numUnitDecimalInPattern}~", $arg, $matches );
		if ( $match )
		{
			return str_replace( "\u{a0}", '', str_replace( ' ', '', str_replace( ',', '', $matches[1] ) ) );
		}

		throw new TransformationException(0, 'numunitdecimalin');
	}

	/**
	 * Transforms Bulgarian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 февруари" is not permitted.
	 * @param string $arg Bulgarian date in the order "day month".
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datedaymonthbg( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthBgPattern, array( 'reOption' => 'u' ) );
	}

	/**
	 * Transforms Czech date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. února" is not permitted.
	 * @param string $arg Czech date in the order "day month".
	 * @return string xs:gMonthDay
	 * @throws TransformationException
	 */
	private function datedaymonthcs( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthCsPattern, array( 'reOption' => 'u', 'moTbl' => $this->monthnumbercs ) );
	}

	/**
	 * Transforms German date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. Februar" is not permitted.
	 * @param string $arg German date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthde( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthDePattern );
	}

	/**
	 * Transforms Greek date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 Φεβρουαρίου" is not permitted.
	 * @param string $arg Greek date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthel( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthElPattern, array( 'reOption' => 'u', 'moTbl' => $this->monthnumber ) );
	}

	/**
	 * Transforms Spanish date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 de febrero" is not permitted.
	 * @param string $arg Spanish date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthes( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthEsPattern );
	}

	/**
	 * Transforms Estonian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. veebruar" is not permitted.
	 * @param string $arg 
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthet( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthEtPattern );
	}

	/**
	 * Transforms Finnish date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. helmikuuta" is not permitted.
	 * @param string $arg Finnish date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthfi( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthFiPattern, array( 'moTbl' => $this->monthnumberfi ) );
	}

	/**
	 * Transforms French date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 février" is not permitted.
	 * @param string $arg French date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthfr( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthFrPattern );
	}

	/**
	 * Transforms Croatian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. veljače" is not permitted.
	 * @param string $arg Croatian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthhr( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthHrPattern, array( 'moTbl' => $this->monthnumberhr ) );
	}

	/**
	 * Transforms Italian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 febbraio" is not permitted.
	 * @param string $arg Italian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthit( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthItPattern );
	}

	/**
	 * Transforms Latvian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. februāris" is not permitted.
	 * @param string $arg Latvian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthlv( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthLvPattern );
	}

	/**
	 * Transforms Dutch date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 februari" is not permitted.
	 * @param string $arg Dutch date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthnl( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthNlPattern );
	}

	/**
	 * Transforms Norwegian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. februar" is not permitted.
	 * @param string $arg Norwegian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthno( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthNoPattern );
	}

	/**
	 * Transforms Polish date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. lutego" is not permitted.
	 * @param string $arg Polish date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthpl( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthPlPattern, array( 'moTbl' => $this->monthnumberpl ) );		
	}

	/**
	 * Transforms Portuguese date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 de fevereiro" is not permitted.
	 * @param string $arg Portuguese date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthpt( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthPtPattern );		
	}

	/**
	 * Transforms Romanian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 februarie" is not permitted.
	 * @param string $arg Romanian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthro( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthRoPattern );
	}

	/**
	 * Transforms Swedish date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 februari" is not permitted.
	 * @param string $arg Romanian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthse( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthSePattern );
	}

	/**
	 * Transforms Slovak date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. februára" is not permitted.
	 * @param string $arg Slovak date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthsk( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthSkPattern );
	}

	/**
	 * Transforms Slovenian date in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30. februar" is not permitted.
	 * @param string $arg Slovenian date in the order "day month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthsl( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthSlPattern );
	}

	/**
	 * Transforms date using Roman numerals for month in the order "day month" into W3C/ISO recurring date standard "--MM-DD" format. 
	 * The result must be a valid xs:gMonthDay so, for example, "30 II" is not permitted.
	 * @param string $arg Date in the order "day month" using Roman numerals for month.
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datedaymonthroman( $arg )
	{
		return $this->datedaymonth( $arg, $this->daymonthRomanPattern, array( 'count' => 5, 'moTbl' => $this->monthnumberroman ) );
	}

	/**
	 * Transforms numeric date in the order "day month year", with non-numeric separators, into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result must be a 
	 * valid xs:date so, for example, "30.02.09" is not permitted.
	 * @param string $arg Numeric date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearTR4( $arg )
	{
		$digits = $this->jpDigitsToNormal( $arg );
		$digits = $this->devanagariDigitsToNormal( $digits );

		return $this->datedaymonthyear( $digits, $this->daymonthyearPattern );
	}

	/**
	 * Transforms Bulgarian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. @REVIEW Two-digit years are 
	 * assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. The result must be a valid xs:date so, f
	 * or example, "30 февруари 2008 г." is not permitted.
	 * @param string $arg Bulgarian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearbg( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearCsPattern, array( 'moTbl' => $this->monthnumbercs ) );		
	}

	/**
	 * Transforms Czech date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 
	 * 2000 and 2009. The result must be a valid xs:date so, for example, "30. února 2008" is not permitted.
	 * @param string $arg Czech date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearcs( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearCsPattern, array( 'moTbl' => $this->monthnumbercs ) );		
	}

	/**
	 * Transforms German date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. Februar 2008" is not permitted.
	 * @param string $arg German date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearde( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearDePattern );		
	}

	/**
	 * Transforms Greek date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 
	 * 2000 and 2009. The result must be a valid xs:date so, for example, "30 Φεβρουαρίου 2008" is not permitted.
	 * @param string $arg Greek date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearel( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearElPattern );		
	}

	/**
	 * Transforms Spanish date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 de febrero de 2008" is not permitted.
	 * @param string $arg 
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyeares( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearEsPattern );		
	}

	/**
	 * Transforms Estonian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. veebruar 2008" is not permitted.
	 * @param string $arg Estonian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearet( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearEtPattern );		
	}

	/**
	 * Transforms Finnish date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. helmikuuta 2008" is not permitted.
	 * @param string $arg Finnish date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearfi( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearFiPattern, array( 'moTbl' => $this->monthnumberfi ) );		
	}

	/**
	 * Transforms French date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 février 2008" is not permitted.
	 * @param string $arg French date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearfr( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearFrPattern );		
	}

	/**
	 * Transforms Indian date based on the Gregorian Calendar in the order "day month year" (using Hindi names for Gregorian months; 
	 * e.g. "19 सितंबर 2012"; either Arabic or Devanagari numerals for day and year; e.g. "१९ सितंबर २०१२"; or using Devanagari numerals 
	 * throughout; e.g. "१९ ०९ २०१२") into W3C/ISO date standard "YYYY-MM-DD" format. Accepts single digits for month and double digits for year.
	 * @param string $arg Indian date based on Gregorian Calendar in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearinTR4( $arg )
	{
		return $this->datedaymonthyearin( $arg, $this->daymonthyearInPatternTR4 );
	}

	/**
	 * Transforms Croatian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. veljače 2008" is not permitted.
	 * @param string $arg Croatian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearhr( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearHrPattern, array( 'moTbl' => $this->monthnumberhr ) );		
	}

	/**
	 * Transforms Italian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 febbraio 2008" is not permitted.
	 * @param string $arg Italian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearit( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearItPattern );		
	}

	/**
	 * Transforms Dutch date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 februari 2008" is not permitted.
	 * @param string $arg Dutch date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearnl( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearNlPattern );		
	}

	/**
	 * Transforms Norwegian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. februar 2008" is not permitted.
	 * @param string $arg Norwegian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearno( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearNoPattern );		
	}

	/**
	 * Transforms Polish date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. lutego 2008 r" is not permitted.
	 * @param string $arg Polish date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearpl( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearPlPattern, array( 'moTbl' => $this->monthnumberpl ) );		
	}

	/**
	 * Transforms Portuguese date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 de fevereiro de 2008" is not permitted.
	 * @param string $arg Portuguese date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearpt( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearPtPattern );		
	}

	/**
	 * Transforms Romanian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 februarie 2008" is not permitted.
	 * @param string $arg Romanian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearro( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearRoPattern );		
	}

	/**
	 * Transforms Swedish date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 februari 2009" is not permitted.
	 * @param string $arg Swedish date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearse( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearSePattern );		
	}

	/**
	 * Transforms Slovak date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. februára 2008" is not permitted.
	 * @param string $arg Slovak date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearsk( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearSkPattern );		
	}

	/**
	 * Transforms Slovenian date in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30. februar 2008" is not permitted.
	 * @param string $arg Slovenian date in the order "day month year".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearsl( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearSlPattern );
	}

	/**
	 * Transforms date using Roman numerals in the order "day month year" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "30 II 2008" is not permitted.
	 * @param string $arg Date in the order "day month year" using Roman numerals for month.
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datedaymonthyearroman( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->daymonthyearRomanPattern, array( 'count' => 8, 'year' => 8, 'moTbl' => $this->monthnumberroman ) );		
	}

	/**
	 * Not specified
	 * @param string $arg 
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datemonthdayhu( $arg )
	{
		throw new TransformationException(0, 'This transform is not specified');
	}

	/**
	 * Not specified
	 * @param string $arg 
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function datemonthdaylt( $arg )
	{
		// return $this->datemonthyear( $arg, $this->daymonthyearLtPattern, array( 'moTbl' => $this->monthnumberlt ) );
		throw new TransformationException(0, 'This transform is not specified');
	}

	/**
	 * Transforms numeric date in the order "month year", with non-numeric separator, into W3C/ISO date standard "YYYY-MM" format.
	 * @param string $arg Numeric date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearTR4( $arg, $pattern = null, $table = null )
	{
		$digits = $this->jpDigitsToNormal( $arg );
		$digits = $this->devanagariDigitsToNormal( $digits );

		return $this->datemonthyear( $digits, $pattern ? $pattern :$this->monthyearPattern, array( 'year' => 2, 'month' => 1, 'moTbl' => $table ? $table : $this->monthnumber ) );
	}

	/**
	 * Utility funtion for TR4 month name/year transforms
	 * @param string $arg
	 * @param string [$pattern]
	 * @param string [$table]
	 * @return void
	 */
	private function tr4MonthnameYear( $arg, $pattern = null, $table = null )
	{
		return $this->datemonthyear( $arg, $pattern ? $pattern :$this->monthyearPattern, array( 'year' => 2, 'month' => 1, 'moTbl' => $table ? $table : $this->monthnumber ) );
	}

	/**
	 * Transforms Bulgarian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Bulgarian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearbg( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearBgPattern );
	}

	/**
	 * Transforms Czech date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Czech date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearcs( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearCsPattern, $this->monthnumbercs );
	}

	/**
	 * Transforms German date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg German date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearde( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearDePattern );
	}

	/**
	 * Transforms Greek date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Greek date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearel( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearElPattern );
	}

	/**
	 * Transforms Spanish date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Spanish date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyeares( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearEsPattern );
	}

	/**
	 * Transforms Estonian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Estonian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearet( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearEtPattern );
	}

	/**
	 * Transforms Finnish date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Finnish date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearfi( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearFiPattern, $this->monthnumberfi );
	}

	/**
	 * Transforms French date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg French date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearfr( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearFrPattern );
	}

	/**
	 * Transforms Croatian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Croation date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearhr( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearHrPattern,  $this->monthnumberhr );
	}

	/**
	 * Transforms Italian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Italian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearit( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearItPattern );
	}

	/**
	 * Transforms Dutch date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Dutch date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearnl( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearNlPattern );
	}

	/**
	 * Transforms Norwegian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Norwegian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearno( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearNoPattern );
	}

	/**
	 * Transforms Polish date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Polish date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearpl( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearPlPattern, $this->monthnumberpl );
	}

	/**
	 * Transforms Portuguese date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Portugese date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearpt( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearPtPattern );
	}

	/**
	 * Transforms Romanian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Romanian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearro( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearRoPattern );
	}

	/**
	 * Transforms Swedish date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Slovakian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearse( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearSePattern );
	}

	/**
	 * Transforms Slovak date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Slovakian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearsk( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearSkPattern );
	}

	/**
	 * Transforms Slovenian date in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Slovinian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearsl( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearSlPattern );
	}

	/**
	 * Transforms date using Roman numerals in the order "month year" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Roman date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearroman( $arg )
	{
		return $this->datemonthyear( $arg, $this->monthyearRomanPattern, array( 'year' => 2, 'month' => 1, 'count' => 7, 'moTbl' => $this->monthnumberroman ) );
	}

	/**
	 * Transforms Latvian date in the order "year day month" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "2008. gada 30. februāris" is not permitted.
	 * @param string $arg Latvian date in the order "month year".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function datemonthyearlv( $arg )
	{
		return $this->tr4MonthnameYear( $arg, $this->monthyearLvPattern );
	}

	/**
	 * 
	 * @param string $arg 
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthTR4( $arg )
	{
		$digits = $this->jpDigitsToNormal( $arg );
		$digits = $this->devanagariDigitsToNormal( $digits );

		return $this->datemonthyear( $digits, $this->yearmonthPattern );
	}

	/**
	 * Transforms Latvian date in the order "year day month" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 
	 * 2000 and 2009. The result must be a valid xs:date so, for example, "2008. gada 30. februāris" is not permitted.
	 * @param string $arg Hungarian date in the order "year month".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateyeardaymonthlv( $arg )
	{
		$this->datedaymonthyear( $arg, $this->yeardaymonthLvPattern, array( 'year' => 1, 'day' => 2, 'month' => 3, 'count' => 4 ) );
	}

	/**
	 * Transforms Hungarian date in the order "year month" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg 
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthhu( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthHuPattern );
	}

	/**
	 * Transforms Hungarian date in the order "year month day" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "2008. február 30" is not permitted.
	 * @param string $arg 
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateyearmonthdayhu( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->yearmonthdayHuPattern, array( 'year' => 1, 'day' => 3, 'month' => 2 ) );
	}

	/**
	 * Transforms Lithuanian date in the order "year month day" into W3C/ISO date standard "YYYY-MM-DD" format. 
	 * @REVIEW Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009. 
	 * The result must be a valid xs:date so, for example, "2008 m. Vasaris 30 d" is not permitted.
	 * @param string $arg Lithuanian date in the order "year month day".
	 * @return string xs:date
	 * @throws TransformationException
	 */
	private function dateyearmonthdaylt( $arg )
	{
		return $this->datedaymonthyear( $arg, $this->yearmonthdayLtPattern, array( 'year' => 1, 'day' => 3, 'month' => 2, 'moTbl' => $this->monthnumberlt ) );
	}

	/**
	 * Transforms Lithuanian date in the order "year month" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg 
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthlt( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthLtPattern, array( 'year' => 1, 'month' => 2, 'moTbl' => $this->monthnumberlt ) );
	}

	/**
	 * Transforms Latvian date in the order "year month" into W3C/ISO date standard "YYYY-MM" format. 
	 * Two-digit years are assumed to fall between 2000 and 2099 and one-digit years to fall between 2000 and 2009.
	 * @param string $arg Latvian date in the order "year month".
	 * @return string xs:gYearMonth
	 * @throws TransformationException
	 */
	private function dateyearmonthlv( $arg )
	{
		return $this->datemonthyear( $arg, $this->yearmonthLvPattern, array( 'year' => 1, 'month' => 2 ) );
	}

	/**
	 * @param string $arg 
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function fixedzero( $arg )
	{
		return '0';
	}

	/**
	 * Transforms number with comma (",") fraction separator and optional thousands separators into non-negative number based on schema-defined decimal format.
	 * @param string $arg The numeric value with comma decimal separator.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numcommadecimalTR4( $arg )
	{
		$match = preg_match( "~{$this->numCommaDecimalTR4Pattern}~", $arg );
		if ( $match )
			return str_replace( "\u{00A0}", '', str_replace( ' ', '', str_replace( ',', '.', str_replace( '.', '', $arg ) ) ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType (numcommadecimalTR4)' );
	}

	/**
	 * Transforms number with dot (".") fraction separator and optional thousands separators into non-negative number based on schema-defined decimal format.
	 * @param string $arg The numeric value with dot decimal separator.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numdotdecimalTR4( $arg ) // relax requirement for 0 before decimal
	{
		$match = preg_match( "~{$this->numDotDecimalTR4Pattern}~", $arg );
		if ( $match )
			return str_replace( "\u{00A0}", '', str_replace( ' ', '', str_replace( ',', '', $arg ) ) );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType (numdotdecimalTR4)' );		
	}

	/**
	 * Transforms Indian number with dot (".") fraction separator and a comma after first 3 digits (after thousand) and 
	 * then comma after each 2 digits (e.g. "1,00,00,000") into non-negative number based on schema-defined decimal format.
	 * @param string $arg 
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numdotdecimalinTR4( $arg )
	{
		return $this->canonicalNumber( $this->numdotdecimalin( $arg ) );
	}

	/**
	 * Transforms mixed string monetary value with string unit indicators and optional thousands separators 
	 * into non-negative number based on schema-defined decimal format. Supports halfwidth and fullwidth forms.
	 * @param string $arg Monetary format with one or more unit string suffixes, with either halfwidth and fullwidth forms.
	 * @return string ixt:nonNegativeDecimalType
	 * @throws TransformationException
	 */
	private function numunitdecimalTR4( $arg )
	{
		$match = preg_match( "~{$this->numUnitDecimalTR4Pattern}~u", $this->jpDigitsToNormal( $arg ), $matches );
		if ( $match  && count( $matches) > 1 )
			return str_replace("\{uFF0E}",'', str_replace('\uFF0C','', str_replace(',','', str_replace('.','', $matches[1] ) ) ) ) . '.' . str_pad( $matches[ count( $matches ) - 1 ], STR_PAD_LEFT );
		throw new TransformationException( 0, 'ixt:nonNegativeDecimalType' );		
	}

	#endregion

	/**
	 * Convenience function to run the transform tests
	 * @return void
	 */
	public static function Tests()
	{
		tests();
	}
}

/**
 * Runs the transform tests
 * @return void
 */
function tests()
{
	/**
	 * @var IXBRL_Transforms
	 */
	$transformInstance = IXBRL_Transforms::getInstance();

	#Region TR1

	$transformInstance->setTransformVersion(\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV1 ] );
	// $x = $transformInstance->transform('dateslashus', '12/31/09');
	// $x = $transformInstance->transform('dateslasheu', '31/12/2009');
	// $x = $transformInstance->transform('datedotus', '02.30.2008');
	// $x = $transformInstance->transform('datedoteu', '30.02.2008');
	// $x = $transformInstance->transform('datelongus', 'February 28, 2008');
	// $x = $transformInstance->transform('datelonguk', '31 January 2008');
	// $x = $transformInstance->transform('dateshortus', 'Feb 28, 2008');
	// $x = $transformInstance->transform('dateshortuk', '31 Jan 2008');
	// $x = $transformInstance->transform('numcommadot', '300,000,000.12');
	// $x = $transformInstance->transform('numdash', '-');
	// $x = $transformInstance->transform('numspacedot', '300 000 000.12');
	// $x = $transformInstance->transform('numcomma', '300000000,12');
	// $x = $transformInstance->transform('numdotcomma', '300.000.000,12');
	// $x = $transformInstance->transform('numspacecomma', '300 000 000,12');
	// $x = $transformInstance->transform('datelongdaymonthuk', '31 January');
	// $x = $transformInstance->transform('dateshortdaymonthuk', '31 Jan');
	// $x = $transformInstance->transform('datelongmonthdayus', 'January 31');
	// $x = $transformInstance->transform('dateshortmonthdayus', 'Jan 31');
	// $x = $transformInstance->transform('dateslashdaymontheu', '31/01');
	// $x = $transformInstance->transform('dateslashmonthdayus', '01/31');
	// $x = $transformInstance->transform('datelongyearmonth', '2009 February');
	// $x = $transformInstance->transform('dateshortyearmonth', '2009 Feb');
	// $x = $transformInstance->transform('datelongmonthyear', 'February 2009');
	// $x = $transformInstance->transform('dateshortmonthyear', 'Feb 2009');

	#endregion

	#region TR2

	$transformInstance->setTransformVersion(\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV2 ] );
	// $x = $transformInstance->transform('booleantrue', 1 );
	// $x = $transformInstance->transform('booleanfalse', 0 );
	// $x = $transformInstance->transform('datedaymonth', '31x01' );
	// $x = $transformInstance->transform('datedaymonthen', '31 January' );
	// $x = $transformInstance->transform('datedaymonthen', '31st January' );
	// $x = $transformInstance->transform('datedaymonthen', '28th January February' );
	// $x = $transformInstance->transform('datedaymonthen', '28th Jan' );
	// $x = $transformInstance->transform('datedaymonthyear', '28x01x19' );
	// $x = $transformInstance->transform('datedaymonthyearen', '30 January 2009' );
	// $x = $transformInstance->transform('datedaymonthyearen', '30th January 2009' );
	// $x = $transformInstance->transform('datedaymonthyearen', '29 Feb 2008' );
	// $x = $transformInstance->transform('datedaymonthyearen', '30 January 1999' );
	// $x = $transformInstance->transform('datedaymonthyearen', '30th day of January, March and April, 1969' );
	// $x = $transformInstance->transform('datedaymonthyearen', '30th Feb 2009' ); // Fails
	// $x = $transformInstance->transform('dateerayearmonthdayjp', '平成元年5月31日' );
	// $x = $transformInstance->transform('dateerayearmonthdayjp', '平成元年2月30日' ); // Fails
	// $x = $transformInstance->transform('dateerayearmonthjp', '平成元年5月' ); 
	// $x = $transformInstance->transform('dateerayearmonthjp', '平成元年13月' ); // Fails
	// $x = $transformInstance->transform('datemonthday', '10x30' );
	// $x = $transformInstance->transform('datemonthdayen', 'Janx30' );
	// $x = $transformInstance->transform('datemonthdayyear', '2x28x18' );
	// $x = $transformInstance->transform('datemonthdayyearen', 'Febx28x18' );
	// $x = $transformInstance->transform('datemonthyearen', 'Febx18' );
	// $x = $transformInstance->transform('dateyearmonthdaycjk', '2010年5月31日' );
	// $x = $transformInstance->transform('dateyearmonthen', '2010xAug' );
	// $x = $transformInstance->transform('dateyearmonthcjk', '2010年5月' );
	// $x = $transformInstance->transform('nocontent', 'xxx' );
	// $x = $transformInstance->transform('numcommadecimal', '300 000,12' );
	// $x = $transformInstance->transform('numdotdecimal', '300 000.12' );
	// $x = $transformInstance->transform('zerodash', '-' );
	// $x = $transformInstance->transform('numunitdecimal', '12 Euro 43 Cent' ); // Don't understand this one

	#endregion

	#region TR3

	$transformInstance->setTransformVersion(\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV3 ] );
	// $x = $transformInstance->transform('calindaymonthyear', '11 पौष 1921' );
	// $x = $transformInstance->transform('calindaymonthyear', '११ पौष १९२१' ); 
	// $x = $transformInstance->transform('calindaymonthyear', '11 Pausha 1921' ); 
	// $x = $transformInstance->transform('datedaymonthdk', '28. februar' ); 
	// $x = $transformInstance->transform('datedaymonthyeardk', '28. februar 2009' ); 
	// $x = $transformInstance->transform('datedaymonthyearin', '19 सितंबर 2012' );
	// $x = $transformInstance->transform('datedaymonthyearin', '१९ सितंबर २०१२' ); 
	// $x = $transformInstance->transform('datedaymonthyearin', '१९ ०९ २०१२' ); 
	// $x = $transformInstance->transform('datemonthyear', '03z2019' ); 
	// $x = $transformInstance->transform('datemonthyeardk', 'Jan. 2019' ); 
	// $x = $transformInstance->transform('datemonthyearin', 'सितंबर 2012' );
	// $x = $transformInstance->transform('datemonthyearin', 'सितंबर २०१२' );
	// $x = $transformInstance->transform('dateyearmonthday', '2019 09 01' );
	// $x = $transformInstance->transform('dateyearmonthday', '2019 ०९ 01' );
	// $x = $transformInstance->transform('numdotdecimalin', '1,00,00,000.21' );
	// $x = $transformInstance->transform('numdotdecimalin', '1,000.21' );
	// $x = $transformInstance->transform('numdotdecimalin', '1,000' );
	// $x = $transformInstance->transform('numdotdecimalin', '10' );
	// $x = $transformInstance->transform('numdotdecimalin', '10.21' );
	// $x = $transformInstance->transform('numunitdecimalin', '1,000 rupees 50 paise' );
	// $x = $transformInstance->transform('numunitdecimalin', '1,00,000 rupees 50 paise' );
	// $x = $transformInstance->transform('numunitdecimalin', '10 rupees 50 paise' );

	#endregion

	#region TR4

	$transformInstance->setTransformVersion(\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXTV4 ] );
	// $x = $transformInstance->transform('date-day-monthname-bg', '28 февруари' );
	// $x = $transformInstance->transform('date-day-monthname-bg', '30 февруари' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-cs', '28. února' );
	// $x = $transformInstance->transform('date-day-monthname-cs', '30. února' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-de', '28. Februar' ); 
	// $x = $transformInstance->transform('date-day-monthname-de', '30. Februar' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-el', '28 Φεβρουαρίου' ); 
	// $x = $transformInstance->transform('date-day-monthname-el', '30 Φεβρουαρίου' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-es', '28 de febrero' ); 
	// $x = $transformInstance->transform('date-day-monthname-es', '30 de febrero' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-et', '28 veebruar' ); 
	// $x = $transformInstance->transform('date-day-monthname-et', '30 veebruar' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-fi', '28. helmikuuta' ); 
	// $x = $transformInstance->transform('date-day-monthname-fi', '30. helmikuuta' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-fr', '28 février' ); 
	// $x = $transformInstance->transform('date-day-monthname-fr', '30 février' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-hr', '28. veljače' ); 
	// $x = $transformInstance->transform('date-day-monthname-fr', '30. veljače' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-it', '28 febbraio' ); 
	// $x = $transformInstance->transform('date-day-monthname-it', '30 febbraio' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-lv', '28. februāris' ); 
	// $x = $transformInstance->transform('date-day-monthname-lv', '30. februāris' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-nl', '28 februari' ); 
	// $x = $transformInstance->transform('date-day-monthname-nl', '30 februari' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-no', '28. februar' ); 
	// $x = $transformInstance->transform('date-day-monthname-no', '30. februar' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-pl', '28. lutego' ); 
	// $x = $transformInstance->transform('date-day-monthname-pl', '30. lutego' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-pt', '28 de fevereiro' ); 
	// $x = $transformInstance->transform('date-day-monthname-pt', '30 de fevereiro' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-ro', '28 februarie' ); 
	// $x = $transformInstance->transform('date-day-monthname-ro', '30 februarie' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-se', '28 februari' ); 
	// $x = $transformInstance->transform('date-day-monthname-se', '30 februari' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-sk', '28. februára' ); 
	// $x = $transformInstance->transform('date-day-monthname-sk', '30. februára' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-sl', '28. februar' ); 
	// $x = $transformInstance->transform('date-day-monthname-sl', '30. februar' ); // Fail
	// $x = $transformInstance->transform('date-day-monthroman', '28 II' ); 
	// $x = $transformInstance->transform('date-day-monthroman', '30 II' ); // Fail
	// $x = $transformInstance->transform('date-day-month-year', '28.02.09' ); 
	// $x = $transformInstance->transform('date-day-month-year', '30.02.09' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-bg', '28 февруари 2008 г.' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-bg', '30 февруари 2008 г.' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-cs', '28. února 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-cs', '30. února 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-de', '28. Februar 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-de', '30. Februar 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-el', '28 Φεβρουαρίου 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-el', '30 Φεβρουαρίου 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-es', '28 de febrero de 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-es', '30 de febrero de 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-et', '28. veebruar 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-et', '30. veebruar 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-fi', '28. helmikuuta 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-fi', '30. helmikuuta 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-fr', '28 février 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-fr', '30 février 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-hr', '28. veljače 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-hr', '30. veljače 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-it', '28 febbraio 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-it', '30 febbraio 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-nl', '28 februari 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-nl', '30 februari 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-no', '28. februar  2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-no', '30. februar  2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-pl', '28. lutego 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-pl', '30. lutego 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-pt', '28 de fevereiro de 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-pt', '30 de fevereiro de 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-ro', '28 februarie 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-ro', '30 februarie 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-se', '28 februari 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-se', '30 februari 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-sk', '28. februára 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-sk', '30. februára 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthname-year-sl', '28. februar 2008' ); 
	// $x = $transformInstance->transform('date-day-monthname-year-sl', '30. februar 2008' ); // Fail
	// $x = $transformInstance->transform('date-day-monthroman-year', '28 II 2008' ); 
	// $x = $transformInstance->transform('date-day-monthroman-year', '30 II 2008' ); // Fail
	// $x = $transformInstance->transform('date-month-year', '02 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-bg', 'февруари 2008 г.' );
	// $x = $transformInstance->transform('date-monthname-year-cs', 'února 2008' );
	// $x = $transformInstance->transform('date-monthname-year-de', 'Februar 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-dk', 'Februar 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-el', 'Φεβρουαρίου 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-es', 'febrero de 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-et', 'veebruar 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-fi', 'helmikuuta 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-fr', 'février 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-hr', 'veljače 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-it', 'febbraio 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-nl', 'februari 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-no', 'februar  2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-pl', 'lutego 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-pt', 'fevereiro de 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-ro', 'februarie 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-se', 'februari 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-sk', 'februára 2008' ); 
	// $x = $transformInstance->transform('date-monthname-year-sl', 'februar 2008' );
	// $x = $transformInstance->transform('date-monthname-year-lv', 'februar 2008' );
	// $x = $transformInstance->transform('date-monthroman-year', 'II 2008' ); 

	// $x = $transformInstance->transform('date-year-month', '2008 02' );
	// $x = $transformInstance->transform('date-year-day-monthname-lv', '2008. gada 28. februāris' );
	// $x = $transformInstance->transform('date-year-day-monthname-lv', '2008. gada 30. februāris' ); // Fail
	// $x = $transformInstance->transform('date-year-month-day', '2009 10 31' );
	// $x = $transformInstance->transform('date-year-monthname-hu', '2009 február' );
	// $x = $transformInstance->transform('date-year-monthname-day-hu', '2009 február 28' );
	// $x = $transformInstance->transform('date-year-monthname-en', '2008 February' );
	// $x = $transformInstance->transform('date-year-monthname-day-lt', '2008 m. Vasaris 28 d' );
	// $x = $transformInstance->transform('date-year-monthname-lt', '2008 m. Vasaris' );
	// $x = $transformInstance->transform('date-year-monthname-lv', '2008 februar' );
	// $x = $transformInstance->transform('fixed-empty', 'xxx' );
	// $x = $transformInstance->transform('fixed-false', 'xxxx' );
	// $x = $transformInstance->transform('fixed-true', 'xxxx' );
	// $x = $transformInstance->transform('fixed-zero', '' );
	// $x = $transformInstance->transform('num-comma-decimal', '123,14' );
	// $x = $transformInstance->transform('num-dot-decimal', '123.14' );
	// $x = $transformInstance->transform('numdotdecimalin', '1,00,00,000.12' );
	$x = $transformInstance->transform('num-unit-decimal', '3.000 euro 5 cent' );

	#endregion

	echo "$x\n";
}

function transforms()
{
	return IXBRL_Transforms::getInstance();
}
