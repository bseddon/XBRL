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
	private static $instance = null;

	public static function getInstance()
	{
		if ( ! self::$instance )
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	#region Regular expression patterns

	private $dateslashPattern = "^[ \\t\\n\\r]*(\\d+)/(\\d+)/(\\d+)[ \\t\\n\\r]*$";
	private $daymonthslashPattern = "^[ \t\n\r]*([0-9]{1,2})/([0-9]{1,2})[ \t\n\r]*$";
	private $monthdayslashPattern = "^[ \t\n\r]*([0-9]{1,2})/([0-9]{1,2})[ \t\n\r]*$";
	private $datedotPattern = "^[ \\t\\n\\r]*(\\d+)\\.(\\d+)\.(\\d+)[ \\t\\n\\r]*$";
	private $daymonthPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[ \t\n\r]*$";
	private $monthdayPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[A-Za-z]*[ \t\n\r]*$";
	private $daymonthyearPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$";
	private $monthdayyearPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$";

	private $dateUsPattern = "^[ \t\n\r]*(\w+)\s+(\d+),\s+(\d+)[ \t\n\r]*$";
	private $dateEuPattern = "^[ \t\n\r]*(\d+)\s+(\w+)\s+(\d+)[ \t\n\r]*$";
	private $daymonthBgPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthCsPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ledna|února|unora|března|brezna|dubna|května|kvetna|června|cervna|července|cervence|srpna|září|zari|října|rijna|listopadu|prosince|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDNA|ÚNORA|UNORA|BŘEZNA|BREZNA|DUBNA|KVĚTNA|KVETNA|ČERVNA|CERVNA|ČERVENCE|CERVENCE|SRPNA|ZÁŘÍ|ZARI|ŘÍJNA|RIJNA|LISTOPADU|PROSINCE|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Ledna|Února|Unora|Března|Brezna|Dubna|Května|Kvetna|Června|Cervna|Července|Cervence|Srpna|Září|Zari|Října|Rijna|Listopadu|Prosince|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)\.?[ \t\n\r]*$";
	private $daymonthDePattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthDkPatternI = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[ \t\n\r]*$"; //, re.IGNORECASE;
	private $daymonthElPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΥΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]{0,8}[ \t\n\r]*$";
	private $daymonthEnPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[ \t\n\r]*$";
	private $monthdayEnPattern = "^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{1,2})[A-Za-z]{0,2}[ \t\n\r]*$";
	private $daymonthEsPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]{0,7}[ \t\n\r]*$";
	private $daymonthEtPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]{0,5}[ \t\n\r]*$";
	private $daymonthFiPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]{0,8}[ \t\n\r]*$";
	private $daymonthFrPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]{0,5}[ \t\n\r]*$";
	private $daymonthHrPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]{0,6}[ \t\n\r]*$";
	private $monthdayHuPattern = "^[ \t\n\r]*(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]{0,7}[^0-9]+([0-9]{1,2})[ \t\n\r]*$";
	private $daymonthItPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]{0,6}[ \t\n\r]*$";
	private $monthdayLtPattern = "^[ \t\n\r]*(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]{0,6}[^0-9]+([0-9]{1,2})[^0-9]*[ \t\n\r]*$";
	private $daymonthLvPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthNlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthNoPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthPlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]{0,9}s*$";
	private $daymonthPtPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthRomanPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^XVIxvi]((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[ \t\n\r]*$";
	private $daymonthRoPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$";
	private $daymonthSkPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthSlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]{0,6}[ \t\n\r]*$";
	private $daymonthyearBgPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[A-Za-z]*[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$";
	private $daymonthyearCsPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ledna|února|unora|března|brezna|dubna|května|kvetna|června|cervna|července|cervence|srpna|září|zari|října|rijna|listopadu|prosince|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDNA|ÚNORA|UNORA|BŘEZNA|BREZNA|DUBNA|KVĚTNA|KVETNA|ČERVNA|CERVNA|ČERVENCE|CERVENCE|SRPNA|ZÁŘÍ|ZARI|ŘÍJNA|RIJNA|LISTOPADU|PROSINCE|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Ledna|Února|Unora|Března|Brezna|Dubna|Května|Kvetna|Června|Cervna|Července|Cervence|Srpna|Září|Zari|Října|Rijna|Listopadu|Prosince|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)[^0-9a-zA-Z]+[^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearDePattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearDkPatternI = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[^0-9]*([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$"; // re.IGNORECASE;
	private $daymonthyearElPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΎΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearEnPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$";
	private $monthdayyearEnPattern = "^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]+)[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$";
	private $daymonthyearEsPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearEtPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearFiPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearFrPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearHrPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $yearmonthdayHuPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]+([0-9]{1,2})[ \t\n\r]*$";
	private $daymonthyearInPatternTR4 = "^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर)[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$";
	private $daymonthyearInPatternTR3 = "^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर|[०-९]{1,2})[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$";
	private $daymonthyearInIndPattern = "^[ \t\n\r]*([0-9]{1,2}|[०-९]{1,2})[^0-9०-९]+((C\S*ait|चैत्र)|(Vai|वैशाख|बैसाख)|(Jy|ज्येष्ठ)|(dha|ḍha|आषाढ|आषाढ़)|(vana|Śrāvaṇa|श्रावण|सावन)|(Bh\S+dra|Proṣṭhapada|भाद्रपद|भादो)|(in|आश्विन)|(K\S+rti|कार्तिक)|(M\S+rga|Agra|मार्गशीर्ष|अगहन)|(Pau|पौष)|(M\S+gh|माघ)|(Ph\S+lg|फाल्गुन))[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$";
	private $daymonthyearItPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $yearmonthdayLtPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]*[^0-9a-zA-Z]+(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]+([0-9]{1,2})[^0-9]*[ \t\n\r]*$";
	private $yeardaymonthLvPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+([0-9]{1,2})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]*[ \t\n\r]*$";
	private $daymonthyearNlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearNoPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearPlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^0-9a-zA-Z]+(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$";
	private $daymonthyearPtPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearRomanPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]*[^XVIxvi]((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[^XVIxvi][^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearRoPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearSkPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $daymonthyearSlPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearBgPattern = "^[ \t\n\r]*(ян|фев|мар|апр|май|маи|юни|юли|авг|сеп|окт|ное|дек|ЯН|ФЕВ|МАР|АПР|МАЙ|МАИ|ЮНИ|ЮЛИ|АВГ|СЕП|ОКТ|НОЕ|ДЕК|Ян|Фев|Мар|Апр|Май|Маи|Юни|Юли|Авг|Сеп|Окт|Ное|Дек)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$";
	private $monthyearCsPattern = "^[ \t\n\r]*(leden|ledna|lednu|únor|unor|února|unora|únoru|unoru|březen|brezen|března|brezna|březnu|breznu|duben|dubna|dubnu|květen|kveten|května|kvetna|květnu|kvetnu|červen|cerven|června|cervna|červnu|cervnu|červenec|cervenec|července|cervence|červenci|cervenci|srpen|srpna|srpnu|září|zari|říjen|rijen|října|rijna|říjnu|rijnu|listopad|listopadu|prosinec|prosince|prosinci|led|úno|uno|bře|bre|dub|kvě|kve|čvn|cvn|čvc|cvc|srp|zář|zar|říj|rij|lis|pro|LEDEN|LEDNA|LEDNU|ÚNOR|UNOR|ÚNORA|UNORA|ÚNORU|UNORU|BŘEZEN|BREZEN|BŘEZNA|BREZNA|BŘEZNU|BREZNU|DUBEN|DUBNA|DUBNU|KVĚTEN|KVETEN|KVĚTNA|KVETNA|KVĚTNU|KVETNU|ČERVEN|CERVEN|ČERVNA|CERVNA|ČERVNU|CERVNU|ČERVENEC|CERVENEC|ČERVENCE|CERVENCE|ČERVENCI|CERVENCI|SRPEN|SRPNA|SRPNU|ZÁŘÍ|ZARI|ŘÍJEN|RIJEN|ŘÍJNA|RIJNA|ŘÍJNU|RIJNU|LISTOPAD|LISTOPADU|PROSINEC|PROSINCE|PROSINCI|LED|ÚNO|UNO|BŘE|BRE|DUB|KVĚ|KVE|ČVN|CVN|ČVC|CVC|SRP|ZÁŘ|ZAR|ŘÍJ|RIJ|LIS|PRO|Leden|Ledna|Lednu|Únor|Unor|Února|Unora|Únoru|Unoru|Březen|Brezen|Března|Brezna|Březnu|Breznu|Duben|Dubna|Dubnu|Květen|Kveten|Května|Kvetna|Květnu|Kvetnu|Červen|Cerven|Června|Cervna|Červnu|Cervnu|Červenec|Cervenec|Července|Cervence|Červenci|Cervenci|Srpen|Srpna|Srpnu|Září|Zari|Říjen|Rijen|Října|Rijna|Říjnu|Rijnu|Listopad|Listopadu|Prosinec|Prosince|Prosinci|Led|Úno|Uno|Bře|Bre|Dub|Kvě|Kve|Čvn|Cvn|Čvc|Cvc|Srp|Zář|Zar|Říj|Rij|Lis|Pro)[^0-9a-zA-Z]+[^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearDePattern = "^[ \t\n\r]*(jan|jän|jaen|feb|mär|maer|mar|apr|mai|jun|jul|aug|sep|okt|nov|dez|JAN|JÄN|JAEN|FEB|MÄR|MAER|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DEZ|Jan|Jän|Jaen|Feb|Mär|Maer|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearDkPatternI = "^[ \t\n\r]*(jan|feb|mar|apr|maj|jun|jul|aug|sep|okt|nov|dec)([A-Za-z]*)([.]*)[^0-9]*([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$"; // re.IGNORECASE;
	private $monthyearElPattern = "^[ \t\n\r]*(ιαν|ίαν|φεβ|μάρ|μαρ|απρ|άπρ|αρίλ|άρίλ|αριλ|άριλ|μαΐ|μαι|μάι|μαϊ|μάϊ|ιούν|ίούν|ίουν|ιουν|ιούλ|ίούλ|ίουλ|ίουλ|ιουλ|αύγ|αυγ|σεπ|οκτ|όκτ|νοέ|νοε|δεκ|ΙΑΝ|ΊΑΝ|IΑΝ|ΦΕΒ|ΜΆΡ|ΜΑΡ|ΑΠΡ|ΆΠΡ|AΠΡ|AΡΙΛ|ΆΡΙΛ|ΑΡΙΛ|ΜΑΪ́|ΜΑΙ|ΜΆΙ|ΜΑΪ|ΜΆΪ|ΙΟΎΝ|ΊΟΎΝ|ΊΟΥΝ|IΟΎΝ|ΙΟΥΝ|IΟΥΝ|ΙΟΎΛ|ΊΟΎΛ|ΊΟΥΛ|IΟΎΛ|ΙΟΥΛ|IΟΥΛ|ΑΎΓ|ΑΥΓ|ΣΕΠ|ΟΚΤ|ΌΚΤ|OΚΤ|ΝΟΈ|ΝΟΕ|ΔΕΚ|Ιαν|Ίαν|Iαν|Φεβ|Μάρ|Μαρ|Απρ|Άπρ|Aπρ|Αρίλ|Άρίλ|Aρίλ|Aριλ|Άριλ|Αριλ|Μαΐ|Μαι|Μάι|Μαϊ|Μάϊ|Ιούν|Ίούν|Ίουν|Iούν|Ιουν|Iουν|Ιούλ|Ίούλ|Ίουλ|Iούλ|Ιουλ|Iουλ|Αύγ|Αυγ|Σεπ|Οκτ|Όκτ|Oκτ|Νοέ|Νοε|Δεκ)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearEnPattern = "^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $yearmonthEnPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)[ \t\n\r]*$";
	private $monthyearEsPattern = "^[ \t\n\r]*(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC|Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearEtPattern = "^[ \t\n\r]*(jaan|veebr|märts|marts|apr|mai|juuni|juuli|aug|sept|okt|nov|dets|JAAN|VEEBR|MÄRTS|MARTS|APR|MAI|JUUNI|JUULI|AUG|SEPT|OKT|NOV|DETS|Jaan|Veebr|Märts|Marts|Apr|Mai|Juuni|Juuli|Aug|Sept|Okt|Nov|Dets)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearFiPattern = "^[ \t\n\r]*(tam|hel|maa|huh|tou|kes|hei|elo|syy|lok|mar|jou|TAM|HEL|MAA|HUH|TOU|KES|HEI|ELO|SYY|LOK|MAR|JOU|Tam|Hel|Maa|Huh|Tou|Kes|Hei|Elo|Syy|Lok|Mar|Jou)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearFrPattern = "^[ \t\n\r]*(janv|févr|fevr|mars|avr|mai|juin|juil|août|aout|sept|oct|nov|déc|dec|JANV|FÉVR|FEVR|MARS|AVR|MAI|JUIN|JUIL|AOÛT|AOUT|SEPT|OCT|NOV|DÉC|DEC|Janv|Févr|Fevr|Mars|Avr|Mai|Juin|Juil|Août|Aout|Sept|Oct|Nov|Déc|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearHrPattern = "^[ \t\n\r]*(sij|velj|ožu|ozu|tra|svi|lip|srp|kol|ruj|lis|stu|pro|SIJ|VELJ|OŽU|OZU|TRA|SVI|LIP|SRP|KOL|RUJ|LIS|STU|PRO|Sij|Velj|Ožu|Ozu|Tra|Svi|Lip|Srp|Kol|Ruj|Lis|Stu|Pro)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $yearmonthHuPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(jan|feb|márc|marc|ápr|apr|máj|maj|jún|jun|júl|jul|aug|szept|okt|nov|dec|JAN|FEB|MÁRC|MARC|ÁPR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SZEPT|OKT|NOV|DEC|Jan|Feb|Márc|Marc|Ápr|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Szept|Okt|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$";
	private $monthyearItPattern = "^[ \t\n\r]*(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|GEN|FEB|MAR|APR|MAG|GIU|LUG|AGO|SET|OTT|NOV|DIC|Gen|Feb|Mar|Apr|Mag|Giu|Lug|Ago|Set|Ott|Nov|Dic)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearInPattern = "^[ \t\n\r]*(जनवरी|फरवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर)[^0-9०-९]+([0-9]{2}|[0-9]{4}|[०-९]{2}|[०-९]{4})[ \t\n\r]*$";
	private $yearmonthLtPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]*[^0-9a-zA-Z]+(sau|vas|kov|bal|geg|bir|lie|rugp|rgp|rugs|rgs|spa|spl|lap|gru|grd|SAU|VAS|KOV|BAL|GEG|BIR|LIE|RUGP|RGP|RUGS|RGS|SPA|SPL|LAP|GRU|GRD|Sau|Vas|Kov|Bal|Geg|Bir|Lie|Rugp|Rgp|Rugs|Rgs|Spa|Spl|Lap|Gru|Grd)[^0-9]*[ \t\n\r]*$";
	private $yearmonthLvPattern = "^[ \t\n\r]*([0-9]{1,2}|[0-9]{4})[^0-9]+(janv|febr|marts|apr|maijs|jūn|jun|jūl|jul|aug|sept|okt|nov|dec|JANV|FEBR|MARTS|APR|MAIJS|JŪN|JUN|JŪL|JUL|AUG|SEPT|OKT|NOV|DEC|Janv|Febr|Marts|Apr|Maijs|Jūn|Jun|Jūl|Jul|Aug|Sept|Okt|Nov|Dec)[^0-9]{0,7}[ \t\n\r]*$";
	private $monthyearNlPattern = "^[ \t\n\r]*(jan|feb|maa|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec|JAN|FEB|MAA|MRT|APR|MEI|JUN|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Maa|Mrt|Apr|Mei|Jun|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearNoPattern = "^[ \t\n\r]*(jan|feb|mar|apr|mai|jun|jul|aug|sep|okt|nov|des|JAN|FEB|MAR|APR|MAI|JUN|JUL|AUG|SEP|OKT|NOV|DES|Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Sep|Okt|Nov|Des)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearPlPattern = "^[ \t\n\r]*(sty|lut|mar|kwi|maj|cze|lip|sie|wrz|paź|paz|lis|gru|STY|LUT|MAR|KWI|MAJ|CZE|LIP|SIE|WRZ|PAŹ|PAZ|LIS|GRU|Sty|Lut|Mar|Kwi|Maj|Cze|Lip|Sie|Wrz|Paź|Paz|Lis|Gru)[^0-9]+([0-9]{1,2}|[0-9]{4})[^0-9]*[ \t\n\r]*$";
	private $monthyearPtPattern = "^[ \t\n\r]*(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ|Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearRomanPattern = "^[ \t\n\r]*((I?(X|V|I)I{0,3})|(i?(x|v|i)i{0,3}))[^XVIxvi][^0-9]*([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearRoPattern = "^[ \t\n\r]*(ian|feb|mar|apr|mai|iun|iul|aug|sep|oct|noi|nov|dec|IAN|FEB|MAR|APR|MAI|IUN|IUL|AUG|SEP|OCT|NOI|NOV|DEC|Ian|Feb|Mar|Apr|Mai|Iun|Iul|Aug|Sep|Oct|Noi|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearSkPattern = "^[ \t\n\r]*(jan|feb|mar|apr|máj|maj|jún|jun|júl|jul|aug|sep|okt|nov|dec|JAN|FEB|MAR|APR|MÁJ|MAJ|JÚN|JUN|JÚL|JUL|AUG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Máj|Maj|Jún|Jun|Júl|Jul|Aug|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearSlPattern = "^[ \t\n\r]*(jan|feb|mar|apr|maj|jun|jul|avg|sep|okt|nov|dec|JAN|FEB|MAR|APR|MAJ|JUN|JUL|AVG|SEP|OKT|NOV|DEC|Jan|Feb|Mar|Apr|Maj|Jun|Jul|Avg|Sep|Okt|Nov|Dec)[^0-9]+([0-9]{1,2}|[0-9]{4})[ \t\n\r]*$";

	# TR1-only patterns, only allow space separators, no all-CAPS month name, only 2 or 4 digit years
	private $dateLongUkTR1Pattern = "^[ \\t\\n\\r]*(\\d|\\d{2,2}) (January|February|March|April|May|June|July|August|September|October|November|December) (\\d{2,2}|\\d{4,4})[ \\t\\n\\r]*$";
	private $dateShortUkTR1Pattern = "^[ \\t\\n\\r]*(\\d|\\d{2,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\\d{2,2}|\\d{4,4})[ \\t\\n\\r]*$";
	private $dateLongUsTR1Pattern = "^[ \\t\\n\\r]*(January|February|March|April|May|June|July|August|September|October|November|December) (\\d|\\d{2,2}), (\\d{2,2}|\\d{4,4})[ \\t\\n\\r]*$";
	private $dateShortUsTR1Pattern = "^[ \\t\\n\\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\\d|\\d{2,2}), (\\d{2,2}|\\d{4,4})[ \\t\\n\\r]*$";
	private $daymonthLongEnTR1Pattern = "^[ \t\n\r]*(\d|\d{2,2}) (January|February|March|April|May|June|July|August|September|October|November|December)[ \t\n\r]*$";
	private $daymonthShortEnTR1Pattern = "^[ \t\n\r]*([0-9]{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ \t\n\r]*$";
	private $monthdayLongEnTR1Pattern = "^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December) (\d|\d{2,2})[ \t\n\r]*$";
	private $monthdayShortEnTR1Pattern = "^[ \t\n\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+([0-9]{1,2})[A-Za-z]{0,2}[ \t\n\r]*$";
	private $monthyearShortEnTR1Pattern = "^[ \t\n\r]*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+([0-9]{2}|[0-9]{4})[ \t\n\r]*$";
	private $monthyearLongEnTR1Pattern = "^[ \t\n\r]*(January|February|March|April|May|June|July|August|September|October|November|December)\s+([0-9]{2}|[0-9]{4})[ \t\n\r]*$";
	private $yearmonthShortEnTR1Pattern = "^[ \t\n\r]*([0-9]{2}|[0-9]{4})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ \t\n\r]*$";
	private $yearmonthLongEnTR1Pattern = "^[ \t\n\r]*([0-9]{2}|[0-9]{4})\s+(January|February|March|April|May|June|July|August|September|October|November|December)[ \t\n\r]*$";

	private $erayearmonthjpPattern = "^[\s ]*(明治|明|大正|大|昭和|昭|平成|平|令和|令)[\s ]*([0-9０-９]{1,2}|元)[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*$";
	private $erayearmonthdayjpPattern = "^[\s ]*(明治|明|大正|大|昭和|昭|平成|平|令和|令)[\s ]*([0-9０-９]{1,2}|元)[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*([0-9０-９]{1,2})[\s ]*(日)[\s ]*$";
	private $yearmonthcjkPattern = "^[\s ]*([0-9０-９]{1,2}|[0-9０-９]{4})[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*$";
	private $yearmonthdaycjkPattern = "^[\s ]*([0-9０-９]{1,2}|[0-9０-９]{4})[\s ]*(年)[\s ]*([0-9０-９]{1,2})[\s ]*(月)[\s ]*([0-9０-９]{1,2})[\s ]*(日)[\s ]*$";

	private $monthyearPattern = "^[ \t\n\r]*([0-9]{1,2})[^0-9]+([0-9]{4}|[0-9]{1,2})[ \t\n\r]*$";
	private $yearmonthPattern = "^[ \t\n\r]*([0-9]{4}|[0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]*$";
	private $yearmonthdayPattern = "^[ \t\n\r]*([0-9]{4}|[0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]+([0-9]{1,2})[^0-9]*$";

	private $zeroDashPattern = "^[ \t\n\r]*([-]|\u002D|\u002D|\u058A|\u05BE|\u2010|\u2011|\u2012|\u2013|\u2014|\u2015|\uFE58|\uFE63|\uFF0D)[ \t\n\r]*$";
	private $numDotDecimalPattern = "^[ \t\n\r]*[0-9]{1,3}([, \xA0]?[0-9]{3})*(\.[0-9]+)?[ \t\n\r]*$";
	private $numDotDecimalTR4Pattern = "^[ \t\n\r]*[, \xA00-9]*(\.[ \xA00-9]+)?[ \t\n\r]*$";
	private $numDotDecimalInPattern = "^(([0-9]{1,2}[, \xA0])?([0-9]{2}[, \xA0])*[0-9]{3})([.][0-9]+)?$|^([0-9]+)([.][0-9]+)?$";
	private $numCommaDecimalPattern = "^[ \t\n\r]*[0-9]{1,3}([. \xA0]?[0-9]{3})*(,[0-9]+)?[ \t\n\r]*$";
	private $numCommaDecimalTR4Pattern = "^[ \t\n\r]*[\. \xA00-9]*(,[ \xA00-9]+)?[ \t\n\r]*$";
	private $numUnitDecimalPattern = "^([0]|([1-9][0-9]{0,2}([.,\uFF0C\uFF0E]?[0-9]{3})*))[^0-9,.\uFF0C\uFF0E]+([0-9]{1,2})[^0-9,.\uFF0C\uFF0E]*$";
	private $numUnitDecimalInPattern = "^(([0-9]{1,2}[, \xA0])?([0-9]{2}[, \xA0])*[0-9]{3})([^0-9]+)([0-9]{1,2})([^0-9]*)$|^([0-9]+)([^0-9]+)([0-9]{1,2})([^0-9]*)$";
	private $numUnitDecimalTR4Pattern = "^([0-9０-９\.,，]+)([^0-9０-９\.,，][^0-9０-９]*)([0-9０-９]{1,2})[^0-9０-９]*$";
	private $numCommaPattern = "^[ \t\n\r]*[0-9]+(,[0-9]+)?[ \t\n\r]*$";
	private $numCommaDotPattern = "^[ \\t\\n\\r]*[0-9]{1,3}(,[0-9]{3,3})*([.][0-9]+)?[ \\t\\n\\r]*$";
	private $numDashPattern = "^[ \\t\\n\\r]*-[ \\t\\n\\r]*$";
	private $numDotCommaPattern = "^[ \t\n\r]*[0-9]{1,3}([.][0-9]{3,3})*(,[0-9]+)?[ \t\n\r]*$";
	private $numSpaceDotPattern = "^[ \t\n\r]*[0-9]{1,3}([ \xA0][0-9]{3,3})*([.][0-9]+)?[ \t\n\r]*$";
	private $numSpaceCommaPattern = "^[ \t\n\r]*[0-9]{1,3}([ \xA0][0-9]{3,3})*(,[0-9]+)?[ \t\n\r]*$";

	private $numCanonicalizationPattern = "^[ \t\n\r]*0*([1-9][0-9]*)?(([.]0*)[ \t\n\r]*$|([.][0-9]*[1-9])0*[ \t\n\r]*$|[ \t\n\r]*$)";

	#endregion

	#region Month/day indexes

	private $monthnumber = null;

	private $monthnumbercs = array(
		"ledna" => 1, "leden" => 1, "lednu" => 1, "února" => 2, "unora" => 2, "únoru" => 2, "unoru" => 2, "únor" => 2, "unor" => 2, 
		"března" => 3, "brezna" => 3, "březen" => 3, "brezen" => 3, "březnu" => 3, "breznu" => 3, "dubna" => 4, "duben" => 4, "dubnu" => 4, 
		"května" => 5, "kvetna" => 5, "květen" => 5, "kveten" => 5, "květnu" => 5, "kvetnu" => 5,
		"června" => 6, "cervna" => 6, "červnu" => 6, "cervnu" => 6, "července" => 7, "cervence" => 7, 
		"červen" => 6, "cerven" => 6, "červenec" => 7, "cervenec" => 7, "červenci" => 7, "cervenci" => 7,
		"srpna" => 8, "srpen" => 8, "srpnu" => 8, "září" => 9, "zari" => 9, 
		"října" => 10, "rijna" => 10, "říjnu" => 10, "rijnu" => 10, "říjen" => 10, "rijen" => 10, "listopadu" => 11, "listopad" => 11, 
		"prosince" => 12, "prosinec" => 12, "prosinci" => 12,
		"led" => 1, "úno" => 2, "uno" => 2, "bře" => 3, "bre" => 3, "dub" => 4, "kvě" => 5, "kve" => 5,
		"čvn" => 6, "cvn" => 6, "čvc" => 7, "cvc" => 7, "srp" => 8, "zář" => 9, "zar" => 9,
		"říj" => 10, "rij" => 10, "lis" => 11, "pro" => 12
	);

	private $monthnumberfi = array( "tam" => 1, "hel" => 2, "maa" => 3, "huh" => 4, "tou" => 5, "kes" => 6, "hei" => 7, "elo" => 8, "syy" => 9, "lok" => 10, "mar" => 11, "jou" => 12 );

	private $monthnumberhr = array( "sij" => 1, "velj" => 2, "ožu" => 3, "ozu" => 3, "tra" => 4, "svi" => 5, "lip" => 6, "srp" => 7, "kol" => 8, "ruj" => 9, "lis" => 10, "stu" => 11, "pro" => 12 );

	private $monthnumberlt = array( "sau" => 1, "vas" => 2, "kov" => 3, "bal" => 4, "geg" => 5, "bir" => 6, "lie" => 7, "rugp" => 8, "rgp" => 8, "rugs" => 9, "rgs" => 9, "spa" => 10, "spl" => 10, "lap" => 11, "gru" => 12, "grd" => 12 );

	private $monthnumberpl = array( "sty" => 1, "lut" => 2, "mar" => 3, "kwi" => 4, "maj" => 5, "cze" => 6, "lip" => 7, "sie" => 8, "wrz" => 9, "paź" => 10, "paz" => 10, "lis" => 11, "gru" => 12 );

	private $monthnumberroman = array( "i" => 1, "ii" => 2, "iii" => 3, "iv" => 4, "v" => 5, "vi" => 6, "vii" => 7, "viii" => 8, "ix" => 9, "x" => 10, "xi" => 11, "xii" => 12 );

	private $maxDayInMo = array(
		"01" => "31", "02" => "29", "03" => "31", "04" => "30", "05" => "31", "06" => "30",
		"07" => "31", "08" => "31", "09" => "30", "10" => "31", "11" => "30", "12" => "31",
		1 => "31", 2 => "29", 3 => "31", 4 => "30", 5 => "31", 6 => "30",
		7 => "31", 8 => "31", 9 => "30", 10 => "31", 11 => "30", 12 => "31"
	);
	private $gLastMoDay = [31,28,31,30,31,30,31,31,30,31,30,31];

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
			"january" => 1, "february" => 2, "march" => 3, "april" => 4, "may" => 5, "june" => 6, 
			"july" => 7, "august" => 8, "september" => 9, "october" => 10, "november" => 11, "december" => 12, 
			"jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6, 
			"jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12, 
			# bulgarian
			"ян" => 1, "фев" => 2, "мар" => 3, "апр" => 4, "май" => 5, "маи" => 5, "юни" => 6,
			"юли" => 7, "авг" => 8, "сеп" => 9, "окт" => 10, "ное" => 11, "дек" => 12,
			# danish
			"jan" => 1, "feb" => 2, "mar" =>  3, "apr" => 4, "maj" => 5, "jun" => 6,
			"jul" => 7, "aug" => 8, "sep" => 9, "okt" => 10, "nov" => 11, "dec" => 12,
			# de: german
			"jan" => 1, "jän" => 1, "jaen" => 1, "feb" => 2, "mär" => 3, "maer" => 3, "mar" => 3,"apr" => 4, 
			"mai" => 5, "jun" => 6, "jul" => 7, "aug" => 8, "sep" => 9, "okt" => 10, "nov" => 11, "dez" => 12,
			# el: greek
			"ιαν" => 1, "ίαν" => 1, "iαν" => 1, "φεβ" => 2, "μάρ" => 3, "μαρ" => 3, 
			"απρ" => 4, "άπρ" => 4, "απρ" => 4, "aπρ" => 4, "αρίλ" => 4, "άρίλ" => 4, "αριλ" => 4, "άριλ" => 4, "άριλ" => 4, "αριλ" => 4, "aρίλ" => 4, "aριλ" => 4, 
			"μαΐ" => 5, "μαι" => 5, "μάι" => 5, "μαϊ" => 5, "μάϊ" => 5, strtolower( "ΜΑΪ́" ) => 5, # ΜΑΪ́ has combining diacritical marks not on lower case pattern 
			"ιούν" => 6, "ίούν" => 6, "ίουν" => 6, "ιουν" => 6, "ιουν" => 6, "ιουν" => 6, "iούν" => 6, "iουν" => 6, 
			"ιούλ" => 7, "ίούλ" => 7, "ίουλ" => 7, "ίουλ" => 7, "ιουλ" => 7, "iούλ" => 7, "iουλ" => 7, 
			"αύγ" => 8, "αυγ" => 8, 
			"σεπ" => 9, "οκτ" => 10, "όκτ" => 10, "oκτ" => 10, "νοέ" => 11, "νοε" => 11, "δεκ" => 12,
			# es: spanish (differences)
			"ene" => 1, "abr" => 4, "ago" => 8, "dic" => 12,
			# et: estonian (differences)
			"jaan" => 1, "veebr" => 2, "märts" => 3, "marts" => 3, "mai" => 5, "juuni" => 6, "juuli" => 7, "sept" => 9, "okt" => 10, "dets" => 12,
			# fr: french (differences)
			"janv" => 1, "févr" => 2, "fevr" => 2, "mars" => 3, "avr" => 4, "mai" => 5, "juin" => 6, "juil" => 7, "août" => 8, "aout" => 8, "déc" => 12, 
			# hu: hungary (differences)
			"márc" => 3, "marc" => 3, "ápr" => 4, "máj" => 5, "maj" => 5, "jún" => 6, "jun" => 6, "júl" => 7, "jul" => 7, "szept" => 9, "okt" => 10, 
			# it: italy (differences)
			"gen" => 1, "mag" => 5, "giu" => 6, "lug" => 7, "ago" => 8, "set" => 9, "ott" => 10, "dic" => 12, 
			# lv: latvian (differences)
			"janv" => 1, "febr" => 2, "marts" => 3, "maijs" => 5, "jūn" => 6, "jūl" => 7, "okt" => 10,
			# nl: dutch (differences)
			"maa" => 3, "mrt" => 3, "mei" => 5, 
			# no: norway
			"mai" => 5, "des" => 12, 
			# pt: portugese (differences)
			"fev" => 2, "ago" => 8, "set" => 9, "out" => 10, "dez" => 12, 
			# ro: romanian (differences)
			"ian" => 1, "iun" => 6, "iul" => 7, "noi" => 11,
			# sk: skovak (differences)
			"máj" => 5, "maj" => 5, "jún" => 6, "júl" => 7, 
			# sl: sloveniabn
			"avg" => 8, 
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
				'date-day-monthname-year-sk' => 'datedaymonthyearsk',
				'date-day-monthname-year-sl' => 'datedaymonthyearsl',
				'date-day-monthname-year-sv' => 'datedaymonthyeardk',
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
				'date-monthname-year-sk' => 'datemonthyearsk',
				'date-monthname-year-sl' => 'datemonthyearsl',
				'date-monthname-year-sv' => 'datemonthyeardk',
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
			"http://www.xbrl.org/inlineXBRL/transformation/WGWD/YYYY-MM-DD" => $this->tr4Functions, // transformation registry v4 draft
			'http://www.xbrl.org/2008/inlineXBRL/transformation' => $this->tr1Functions // the CR/PR pre-REC namespace
		);

		#endregion

	}

	#region Utility functions

	/**
	 * Pad year to 4 digit accounting for the centruy
	 * @param string $arg
	 * @return string
	 */
	private function year4( $arg )
	{
		return str_pad ( $arg, 4, "2000", STR_PAD_LEFT );
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
	private function datedaymonthyear( $arg, $pattern, $options )
	{
		$options = array_merge( array( 'day' => 1, 'month' => 2, 'year' => 3, 'count' => 4, 'moTbl' => null ), $options );
		$match = preg_match( "~{$pattern}~", $arg, $matches );

		if ( $match && count( $matches ) == $options['count'] )
		{
			$_year = $this->year4( $matches[ $options['year'] ] );
			$_day = $matches[ $options['day'] ];
			$_month = $matches[ $options['month'] ];
			if ( ! $options['moTbl'] ) $options['moTbl'] = $this->monthnumber;
			$_month = $options['moTbl'][ strtolower( $_month ) ] ?? $_month;
			if ( $this->checkDate( $_year, $_month, $_day ) )
				return sprintf( "%s-%02d-%02d", $this->year4( $_year ), $_month, $_day );
			}

		throw new TransformationException( 0, "xs:date" );	
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
        return mktime( 0, 0, 0, intval( $m ), intval( $d ), intval( $y ) ) !== false;
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
			throw new \Exception("The format '$format' is not valid for the TRR namespace '{$this->transformNamespace}'");

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
			return sprintf( "%s-%02d-%02d", $this->year4( $matches[3] ), $matches[1], $matches[2] );
		throw new TransformationException( 0, "xs:date" );
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
			return sprintf( "%s-%02d-%02d", $this->year4( $matches[3] ), $matches[2], $matches[1] );
		throw new TransformationException( 0, "xs:date" );
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
			return sprintf( "%s-%02d-%02d", $this->year4( $matches[3] ), $matches[1], $matches[2] );
		throw new TransformationException( 0, "xs:date" );
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
			return sprintf( "%s-%02d-%02d", $this->year4( $matches[3] ), $matches[2], $matches[1] );
		throw new TransformationException( 0, "xs:date" );
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
		throw new TransformationException( 0, "ixt:numcommadot" );	
	}

	/**
	 * Reformats accountant-friendly "-" as a zero.
	 * @param string $arg The dash used to denote nothing.
	 * @return string
	 * @throws TransformationException
	 */
	private function numdash( $arg )
	{
		$match = preg_match( "~{$this->numDashPattern}~", $arg );
		if ( $match )
			return str_replace( '-', '0', $arg );
		throw new TransformationException( 0, "ixt:numdash" );	
	}

	private function numspacedot( $arg )
	{}

	private function numdotcomma( $arg )
	{}

	private function numcomma( $arg )
	{}

	private function numspacecomma( $arg )
	{}

	private function datedaymonthLongEnTR1( $arg )
	{}

	private function datedaymonthShortEnTR1( $arg )
	{}

	private function datemonthdayLongEnTR1( $arg )
	{}

	private function datemonthdayShortEnTR1( $arg )
	{}

	private function datedaymonthSlashTR1( $arg )
	{}

	private function datemonthdaySlashTR1( $arg )
	{}

	private function dateyearmonthLongEnTR1( $arg )
	{}

	private function dateyearmonthShortEnTR1( $arg )
	{}

	private function datemonthyearLongEnTR1( $arg )
	{}

	private function datemonthyearShortEnTR( $arg )
	{}

	private function booleanfalse( $arg )
	{
		return 'false';
	}

	private function booleantrue( $arg )
	{
		return 'true';
	}

	private function datedaymonthTR2( $arg )
	{}

	private function datedaymonthen( $arg )
	{}

	private function datedaymonthyearTR2( $arg )
	{}

	private function datedaymonthyearen( $arg )
	{}

	private function dateerayearmonthdayjp( $arg )
	{}

	private function dateerayearmonthjp( $arg )
	{}

	private function datemonthday( $arg )
	{}

	private function datemonthdayen( $arg )
	{}

	private function datemonthdayyear( $arg )
	{}

	private function datemonthdayyearen( $arg )
	{}

	private function datemonthyearen( $arg )
	{}

	private function dateyearmonthdaycjk( $arg )
	{}

	private function dateyearmonthen( $arg )
	{}

	private function dateyearmonthcjk( $arg )
	{}

	private function nocontent( $arg )
	{}

	private function numcommadecimal( $arg )
	{}

	private function zerodash( $arg )
	{}

	private function numdotdecimal( $arg )
	{}

	private function numunitdecimal( $arg )
	{}

	private function calindaymonthyear( $arg )
	{}

	private function datedaymonthdk( $arg )
	{}

	private function datedaymonthyeardk( $arg )
	{}

	private function datedaymonthyearinTR3( $arg )
	{}

	private function datemonthyearTR3( $arg )
	{}

	private function datemonthyeardk( $arg )
	{}

	private function datemonthyearin( $arg )
	{}

	private function dateyearmonthday( $arg ) // (Y)Y(YY)*MM*DD allowing kanji full-width numeral 
	{}

	private function numdotdecimalin( $arg )
	{}

	private function numunitdecimalin( $arg )
	{}

	private function datedaymonthbg( $arg )
	{}

	private function datedaymonthcs( $arg )
	{}

	private function datedaymonthde( $arg )
	{}

	private function datedaymonthel( $arg )
	{}

	private function datedaymonthes( $arg )
	{}

	private function datedaymonthet( $arg )
	{}

	private function datedaymonthfi( $arg )
	{}

	private function datedaymonthfr( $arg )
	{}

	private function datedaymonthhr( $arg )
	{}

	private function datedaymonthit( $arg )
	{}

	private function datedaymonthlv( $arg )
	{}

	private function datedaymonthnl( $arg )
	{}

	private function datedaymonthno( $arg )
	{}

	private function datedaymonthpl( $arg )
	{}

	private function datedaymonthpt( $arg )
	{}

	private function datedaymonthro( $arg )
	{}

	private function datedaymonthsk( $arg )
	{}

	private function datedaymonthsl( $arg )
	{}

	private function datedaymonthroman( $arg )
	{}

	private function datedaymonthyearTR4( $arg )
	{}

	private function datedaymonthyearbg( $arg )
	{}

	private function datedaymonthyearcs( $arg )
	{}

	private function datedaymonthyearde( $arg )
	{}

	private function datedaymonthyearel( $arg )
	{}

	private function datedaymonthyeares( $arg )
	{}

	private function datedaymonthyearet( $arg )
	{}

	private function datedaymonthyearfi( $arg )
	{}

	private function datedaymonthyearfr( $arg )
	{}

	private function datedaymonthyearinTR4( $arg )
	{}

	private function datedaymonthyearhr( $arg )
	{}

	private function datedaymonthyearit( $arg )
	{}

	private function datedaymonthyearnl( $arg )
	{}

	private function datedaymonthyearno( $arg )
	{}

	private function datedaymonthyearpl( $arg )
	{}

	private function datedaymonthyearpt( $arg )
	{}

	private function datedaymonthyearro( $arg )
	{}

	private function datedaymonthyearsk( $arg )
	{}

	private function datedaymonthyearsl( $arg )
	{}

	private function datedaymonthyearroman( $arg )
	{}

	private function datemonthdayhu( $arg )
	{}

	private function datemonthdaylt( $arg )
	{}

	private function datemonthyearTR4( $arg )
	{}

	private function datemonthyearbg( $arg )
	{}

	private function datemonthyearcs( $arg )
	{}

	private function datemonthyearde( $arg )
	{}

	private function datemonthyearel( $arg )
	{}

	private function datemonthyeares( $arg )
	{}

	private function datemonthyearet( $arg )
	{}

	private function datemonthyearfi( $arg )
	{}

	private function datemonthyearfr( $arg )
	{}

	private function datemonthyearhr( $arg )
	{}

	private function datemonthyearit( $arg )
	{}

	private function datemonthyearnl( $arg )
	{}

	private function datemonthyearno( $arg )
	{}

	private function datemonthyearpl( $arg )
	{}

	private function datemonthyearpt( $arg )
	{}

	private function datemonthyearro( $arg )
	{}

	private function datemonthyearsk( $arg )
	{}

	private function datemonthyearsl( $arg )
	{}

	private function datemonthyearroman( $arg )
	{}

	private function dateyeardaymonthlv( $arg )
	{}

	private function dateyearmonthTR4( $arg )
	{}

	private function dateyearmonthhu( $arg )
	{}

	private function dateyearmonthdayhu( $arg )
	{}

	private function dateyearmonthdaylt( $arg )
	{}

	private function dateyearmonthlt( $arg )
	{}

	private function dateyearmonthlv( $arg )
	{}

	private function fixedzero( $arg )
	{}

	private function numcommadecimalTR4( $arg )
	{}

	private function numdotdecimalTR4( $arg ) // relax requirement for 0 before decimal
	{}

	private function numdotdecimalinTR4( $arg )
	{}

	private function numunitdecimalTR4( $arg )
	{}

	#endregion

}

function transforms()
{
	return IXBRL_Transforms::getInstance();
}
