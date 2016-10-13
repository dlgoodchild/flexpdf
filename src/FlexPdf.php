<?php declare( strict_types = 1 );

namespace DLGoodchild;

/**
 * This class was originally based off the tFPDF project on github (https://github.com/rev42/tfpdf)
 * The main goals are to have a fluid interface, simpler usage and parameters per method, stricter
 * typing through use of PHP7+ features.
 *
 * As I release newer versions I'll not be making any effort for it to be backwards compatible as
 * this will only hold back progress.
 *
 * Requires: PHP 7.0+
 *
 * Class FlexPdf
 * @package DLGoodchild
 */
class FlexPdf {

	private $unifontSubset;

	/**
	 * @var int
	 */
	private $nPageNumber;               // current page number

	private $nObjectNumber;                  // current object number

	private $aObjectOffsets;            // array of object offsets

	private $sBuffer;             // buffer holding in-memory PDF

	private $aPages;              // array containing pages

	/**
	 * 0 = uninitialised
	 * 1 = initialised/open
	 * 2 = page opened
	 * 3 = closed/finished
	 * @var int
	 */
	private $sDocState;              // current document state

	private $bCompress;           // compression flag

	private $nScaleFactor;                  // scale factor (number of points in user unit)

	private $sDefaultOrientation;     // default orientation

	private $sCurrentOrientation;     // current orientation

	private $aStandardPageSizes;       // standard page sizes

	private $sDefaultPageSize;        // default page size

	private $nCurrentPageSize;        // current page size

	private $PageSizes;          // used for pages with non default sizes or orientations

	private $wPt;

	private $hPt;          // dimensions of current page in points

	private $w;

	private $h;              // dimensions of current page in user unit

	private $nMarginLeft;            // left margin

	private $nMarginTop;            // top margin

	private $nMarginRight;            // right margin

	private $bMarginPageBreak;            // page break margin

	private $bMarginCell;            // cell margin

	private $nPosX;

	private $nPosY;              // current position in user unit

	private $lasth;              // height of last printed cell

	private $LineWidth;          // line width in user unit

	private $fontpath;           // path containing fonts

	private $CoreFonts;          // array of core font names

	private $fonts;              // array of used fonts

	private $FontFiles;          // array of font files

	private $diffs;              // array of encoding differences

	private $FontFamily;         // current font family

	private $FontStyle;          // current font style

	private $underline;          // underlining flag

	private $CurrentFont;        // current font info

	private $FontSizePt;         // current font size in points

	private $FontSize;           // current font size in user unit

	private $DrawColor;          // commands for drawing color

	private $FillColor;          // commands for filling color

	private $TextColor;          // commands for text color

	private $ColorFlag;          // indicates whether fill and text colors are different

	private $ws;                 // word spacing

	private $images;             // array of used images

	private $PageLinks;          // array of links in pages

	private $links;              // array of internal links

	private $bAutoPageBreak;      // automatic page breaking

	private $nPageBreakTrigger;   // threshold used to trigger page breaks

	private $InHeader;           // flag set when processing header

	private $InFooter;           // flag set when processing footer

	private $ZoomMode;           // zoom display mode

	private $LayoutMode;         // layout display mode

	private $title;              // title

	private $subject;            // subject

	private $author;             // author

	private $keywords;           // keywords

	private $creator;            // creator

	private $AliasNbPages;       // alias for total number of pages

	private $PDFVersion;         // PDF version number

	/**
	 * @param string $sOrientation
	 * @param string $sUnit
	 * @param string $sSize
	 */
	function __construct( string $sOrientation = 'P', string $sUnit = 'mm', string $sSize = 'A4' ) {
		if ( !function_exists( 'mb_strlen' ) ) {
			$this->error( 'mbstring extension is not available' );
		}
		if ( ini_get( 'mbstring.func_overload' ) & 2 ) {
			$this->error( 'mbstring overloading must be disabled' );
		}

		$this->nPageNumber = 0;
		// Initialization of properties
		$this->nObjectNumber = 2;
		$this->sBuffer = '';
		$this->aPages = array();
		$this->PageSizes = array();
		$this->sDocState = 0;
		$this->fonts = array();
		$this->FontFiles = array();
		$this->diffs = array();
		$this->images = array();
		$this->links = array();
		$this->InHeader = false;
		$this->InFooter = false;
		$this->lasth = 0;
		$this->FontFamily = '';
		$this->FontStyle = '';
		$this->FontSizePt = 12;
		$this->underline = false;
		$this->DrawColor = '0 G';
		$this->FillColor = '0 g';
		$this->TextColor = '0 g';
		$this->ColorFlag = false;
		$this->ws = 0;

		// Font path
		if ( defined( 'FPDF_FONTPATH' ) ) {
			$this->fontpath = FPDF_FONTPATH;
			if ( substr( $this->fontpath, -1 ) != '/' && substr( $this->fontpath, -1 ) != '\\' ) {
				$this->fontpath .= '/';
			}
		}
		else if ( is_dir( __DIR__.'/../font' ) ) {
			$this->fontpath = __DIR__ . '/../font/';
		}
		else {
			$this->fontpath = '';
		}

		// Core fonts
		$this->CoreFonts = array( 'courier', 'helvetica', 'times', 'symbol', 'zapfdingbats' );

		// Scale factor
		if ( $sUnit == 'pt' ) {
			$this->nScaleFactor = 1;
		}
		else if ( $sUnit == 'mm' ) {
			$this->nScaleFactor = 72 / 25.4;
		}
		else if ( $sUnit == 'cm' ) {
			$this->nScaleFactor = 72 / 2.54;
		}
		else if ( $sUnit == 'in' ) {
			$this->nScaleFactor = 72;
		}
		else {
			$this->error( 'Incorrect unit: '.$sUnit );
		}

		// Page sizes
		$this->aStandardPageSizes = array(
			'a3' => array( 841.89, 1190.55 ),
			'a4' => array( 595.28, 841.89 ),
			'a5' => array( 420.94, 595.28 ),
			'letter' => array( 612, 792 ),
			'legal' => array( 612, 1008 )
		);
		$sSize = $this->_getpagesize( $sSize );
		$this->sDefaultPageSize = $sSize;
		$this->nCurrentPageSize = $sSize;

		// Page orientation
		$sOrientation = strtolower( $sOrientation );
		if ( $sOrientation == 'p' || $sOrientation == 'portrait' ) {
			$this->sDefaultOrientation = 'P';
			$this->w = $sSize[0];
			$this->h = $sSize[1];
		}
		else if ( $sOrientation=='l' || $sOrientation=='landscape') {
			$this->sDefaultOrientation = 'L';
			$this->w = $sSize[1];
			$this->h = $sSize[0];
		}
		else {
			$this->error( 'Incorrect orientation: ' . $sOrientation );
		}

		$this->sCurrentOrientation = $this->sDefaultOrientation;
		$this->wPt = $this->w*$this->nScaleFactor;
		$this->hPt = $this->h*$this->nScaleFactor;

		// Page margins (1 cm)
		$margin = ( 28.35 / $this->nScaleFactor );
		$this->setMargins( $margin, $margin );

		// Interior cell margin (1 mm)
		$this->bMarginCell = $margin/10;

		// Line width (0.2 mm)
		$this->LineWidth = ( .567 / $this->nScaleFactor );

		// Automatic page break
		$this
			->setAutoPageBreak( true, 2*$margin )
			->setDisplayMode( 'default' )
			->setCompression( true );

		// Set default PDF version number
		$this->PDFVersion = '1.3';
	}

	/**
	 * @param $left
	 * @param $top
	 * @param null $right
	 * @return FlexPdf
	 */
	public function setMargins( $left, $top, $right = null ): FlexPdf {
		$this->nMarginLeft = $left;
		$this->nMarginTop = $top;
		if ( $right === null ) {
			$right = $left;
		}
		$this->nMarginRight = $right;
		return $this;
	}

	/**
	 * @param $margin
	 * @return FlexPdf
	 */
	public function setLeftMargin( $margin ): FlexPdf {
		$this->nMarginLeft = $margin;
		if ( $this->nPageNumber > 0 && $this->nPosX < $margin ) {
			$this->nPosX = $margin;
		}
		return $this;
	}

	/**
	 * @param $margin
	 * @return FlexPdf
	 */
	public function setTopMargin( $margin ): FlexPdf {
		$this->nMarginTop = $margin;
		return $this;
	}

	/**
	 * @param $margin
	 * @return FlexPdf
	 */
	public function setRightMargin( $margin ): FlexPdf {
		$this->nMarginRight = $margin;
		return $this;
	}

	/**
	 * @param $auto
	 * @param int $margin
	 * @return FlexPdf
	 */
	public function setAutoPageBreak( $auto, $margin = 0 ): FlexPdf {
		$this->bAutoPageBreak = $auto;
		$this->bMarginPageBreak = $margin;
		$this->nPageBreakTrigger = ( $this->h - $margin );
		return $this;
	}

	/**
	 * @param $zoom
	 * @param string $sLayout
	 * @return FlexPdf
	 */
	function setDisplayMode( $zoom, string $sLayout = 'default' ): FlexPdf {
		if ( $zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string( $zoom ) ) {
			$this->ZoomMode = $zoom;
		}
		else {
			$this->error( 'Incorrect zoom display mode: ' . $zoom );
		}

		if ( $sLayout=='single' || $sLayout=='continuous' || $sLayout=='two' || $sLayout=='default' ) {
			$this->LayoutMode = $sLayout;
		}
		else {
			$this->error( 'Incorrect layout display mode: '.$sLayout );
		}
		return $this;
	}

	/**
	 * @param bool $bCompress
	 * @return FlexPdf
	 */
	public function setCompression( bool $bCompress ): FlexPdf {
		$this->bCompress = ( function_exists( 'gzcompress' )? $bCompress: false );
		return $this;
	}

	/**
	 * @param string $sTitle
	 * @param bool $bUtf8
	 * @return FlexPdf
	 */
	public function setTitle( string $sTitle, bool $bUtf8 = false ): FlexPdf {
		$this->title = ( $bUtf8? $this->_UTF8toUTF16( $sTitle ): $sTitle );
		return $this;
	}

	/**
	 * @param string $sSubject
	 * @param bool $bUtf8
	 * @return FlexPdf
	 */
	public function setSubject( string $sSubject, bool $bUtf8 = false ): FlexPdf {
		$this->subject = ( $bUtf8? $this->_UTF8toUTF16( $sSubject ): $sSubject );
		return $this;
	}

	/**
	 * @param string $sAuthor
	 * @param bool $bUtf8
	 * @return FlexPdf
	 */
	public function setAuthor( string $sAuthor, bool $bUtf8 = false ): FlexPdf {
		$this->author = ( $bUtf8? $this->_UTF8toUTF16( $sAuthor ): $sAuthor );
		return $this;
	}

	/**
	 * @param string $sKeywords
	 * @param bool $bUtf8
	 * @return FlexPdf
	 */
	function setKeywords( string $sKeywords, bool $bUtf8 = false ): FlexPdf {
		$this->keywords = ( $bUtf8? $this->_UTF8toUTF16( $sKeywords ): $sKeywords );
		return $this;
	}

	/**
	 * @param string $sCreator
	 * @param bool $bUtf8
	 * @return FlexPdf
	 */
	function setCreator( string $sCreator, bool $bUtf8 = false ): FlexPdf {
		$this->creator = ( $bUtf8? $this->_UTF8toUTF16( $sCreator ): $sCreator );
		return $this;
	}

	function aliasNbPages( string $alias = '{nb}' ) {
		// Define an alias for total number of pages
		$this->AliasNbPages = $alias;
		return $this;
	}

	/**
	 * @param string $sErrorMessage
	 * @throws \Exception
	 */
	private function error( string $sErrorMessage ) {
		throw new \Exception( sprintf( 'FlexPDF error: %s ', $sErrorMessage ) );
	}

	/**
	 * @return FlexPdf
	 */
	public function open(): FlexPdf {
		$this->sDocState = 1;
		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	public function close(): FlexPdf {
		// Terminate document
		if ( $this->sDocState == 3 ) {
			return $this;
		}

		if ( $this->nPageNumber == 0 ) {
			$this->addPage();
		}

		// Page footer
		$this->InFooter = true;
		// todo call footer callback
		$this->InFooter = false;

		// Close page
		$this->_endpage();

		// Close document
		$this->_enddoc();

		return $this;
	}

	public function addPage( $orientation = '', $size = '' ) {
		// Start a new page
		if ( $this->sDocState == 0 ) {
			$this->open();
		}

		$family = $this->FontFamily;
		$style = $this->FontStyle.($this->underline ? 'U' : '');
		$fontsize = $this->FontSizePt;
		$lw = $this->LineWidth;
		$dc = $this->DrawColor;
		$fc = $this->FillColor;
		$tc = $this->TextColor;
		$cf = $this->ColorFlag;
		if ( $this->nPageNumber > 0 ) {
			// Page footer
			$this->InFooter = true;
			// todo call footer callback
			$this->InFooter = false;

			// Close page
			$this->_endpage();
		}

		// Start new page
		$this->_beginpage( $orientation, $size );

		// Set line cap style to square
		$this->_out('2 J');

		// Set line width
		$this->LineWidth = $lw;
		$this->_out( sprintf( '%.2F w', $lw * $this->nScaleFactor ) );

		// Set font
		if ( $family ) {
			$this->setFont( $family, $style, $fontsize );
		}

		// Set colors
		$this->DrawColor = $dc;
		if ( $dc != '0 G' ) {
			$this->_out( $dc );
		}

		$this->FillColor = $fc;
		if ( $fc != '0 g' ) {
			$this->_out( $fc );
		}
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;

		// Page header
		$this->InHeader = true;
		// todo: call header callback
		$this->InHeader = false;

		// Restore line width
		if ( $this->LineWidth != $lw ) {
			$this->LineWidth = $lw;
			$this->_out( sprintf( '%.2F w', $lw * $this->nScaleFactor ) );
		}

		// Restore font
		if ( $family ) {
			$this->setFont( $family, $style, $fontsize );
		}

		// Restore colors
		if ( $this->DrawColor != $dc ) {
			$this->DrawColor = $dc;
			$this->_out( $dc );
		}

		if ( $this->FillColor != $fc ) {
			$this->FillColor = $fc;
			$this->_out($fc);
		}

		$this->TextColor = $tc;
		$this->ColorFlag = $cf;

		return $this;
	}

	public function pageNo(): int {
		// Get current page number
		return $this->nPageNumber;
	}

	/**
	 * @param int $nRed
	 * @param int $nGreen
	 * @param int $nBlue
	 * @return FlexPdf
	 */
	public function setDrawColor( $nRed, $nGreen = null, $nBlue = null ): FlexPdf {
		// Set color for all stroking operations
		if ( ( $nRed == 0 && $nGreen == 0 && $nBlue == 0 ) || $nGreen === null) {
			$this->DrawColor = sprintf( '%.3F G', $nRed / 255 );
		}
		else {
			$this->DrawColor = sprintf( '%.3F %.3F %.3F RG', $nRed / 255, $nGreen / 255, $nBlue / 255 );
		}

		if ( $this->nPageNumber > 0 ) {
			$this->_out( $this->DrawColor );
		}

		return $this;
	}

	public function setFillColor($r, $g=null, $b=null): FlexPdf {
		// Set color for all filling operations
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->FillColor = sprintf('%.3F g',$r/255);
		else
			$this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
		if($this->nPageNumber>0)
			$this->_out($this->FillColor);
		return $this;
	}

	public function setTextColor($r, $g=null, $b=null): FlexPdf {
		// Set color for text
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->TextColor = sprintf('%.3F g',$r/255);
		else
			$this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
		return $this;
	}

	/**
	 * @param string $s
	 * @return float
	 */
	public function getStringWidth( string $s ): float {
		// Get width of a string in the current font
		$s = (string)$s;

		$cw = &$this->CurrentFont['cw'];
		$w=0;

		if ( $this->unifontSubset ) {
			$unicode = $this->UTF8StringToArray( $s );
			foreach ( $unicode as $char ) {
				if ( isset( $cw[$char] ) ) {
					$w += (ord($cw[2*$char])<<8) + ord($cw[2*$char+1]);
				}
				else if ( $char > 0 && $char < 128 && isset( $cw[chr( $char )] ) ) {
					$w += $cw[chr( $char )];
				}
				else if ( isset( $this->CurrentFont['desc']['MissingWidth'] ) ) {
					$w += $this->CurrentFont['desc']['MissingWidth'];
				}
				else if ( isset( $this->CurrentFont['MissingWidth'] ) ) {
					$w += $this->CurrentFont['MissingWidth'];
				}
				else {
					$w += 500;
				}
			}
		}
		else {
			$l = strlen( $s );
			for ( $i=0; $i < $l; $i++ ) {
				$w += $cw[$s[$i]];
			}
		}
		return ( $w * $this->FontSize / 1000 );
	}

	/**
	 * @param int $nWidth
	 * @return $this
	 */
	public function setLineWidth( int $nWidth ): FlexPdf {
		// Set line width
		$this->LineWidth = $nWidth;
		if ( $this->nPageNumber > 0 ) {
			$this->_out( sprintf( '%.2F w', $nWidth * $this->nScaleFactor ) );
		}

		return $this;
	}

	/**
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @return $this
	 */
	public function line( int $x1, int $y1, int $x2, int $y2 ): FlexPdf {
		$this->_out(
			sprintf( '%.2F %.2F m %.2F %.2F l S',
				$x1 * $this->nScaleFactor,
				($this->h - $y1) * $this->nScaleFactor,
				$x2 * $this->nScaleFactor,
				($this->h - $y2) * $this->nScaleFactor
			)
		);
		return $this;
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $w
	 * @param int $h
	 * @param string $style
	 * @return $this
	 */
	public function rect( int $x, int $y, int $w, int $h, string $style = '' ): FlexPdf {
		if ( $style == 'F' ) {
			$op = 'f';
		}
		else if ( $style == 'FD' || $style == 'DF' ) {
			$op = 'B';
		}
		else {
			$op = 'S';
		}
		$this->_out(
			sprintf( '%.2F %.2F %.2F %.2F re %s',
				$x * $this->nScaleFactor,
				($this->h - $y) * $this->nScaleFactor,
				$w * $this->nScaleFactor,
				-$h * $this->nScaleFactor,
				$op
			)
		);
		return $this;
	}

	public function addFont( string $family, string $style = '', string $file = '', $uni = false ): FlexPdf {
		// Add a TrueType, OpenType or Type1 font
		$family = strtolower( $family );
		$style = strtoupper( $style );

		if ( $style == 'IB' ) {
			$style = 'BI';
		}

		if ( $file == '' ) {
			$file = str_replace( ' ', '', $family ).strtolower( $style ).( $uni? '.ttf': '.php' );
		}

		$fontkey = $family.$style;
		if ( isset( $this->fonts[$fontkey] ) ) {
			return $this;
		}

		if ( $uni ) {
			if (defined("_SYSTEM_TTFONTS") && file_exists(_SYSTEM_TTFONTS.$file )) {
				$ttffilename = _SYSTEM_TTFONTS.$file;
			}
			else {
				$ttffilename = $this->_getfontpath().'unifont/'.$file;
			}

			$unifilename = $this->_getfontpath().'unifont/'.strtolower(substr($file ,0,(strpos($file ,'.'))));
			$name = '';
			$originalsize = 0;

			$ttfstat = stat( $ttffilename );
			if ( file_exists( $unifilename.'.mtx.php' ) ) {
				include( $unifilename.'.mtx.php' );
			}

			if ( !isset( $type ) || !isset( $name ) || $originalsize != $ttfstat['size']) {
				$ttffile = $ttffilename;
				require_once( $this->_getfontpath().'unifont/ttfonts.php');

				$ttf = new \TTFontFile();
				$ttf->getMetrics($ttffile);
				$cw = $ttf->charWidths;
				$name = preg_replace('/[ ()]/','',$ttf->fullName);

				$desc= array(
					'Ascent'=>round($ttf->ascent),
					'Descent'=>round($ttf->descent),
					'CapHeight'=>round($ttf->capHeight),
					'Flags'=>$ttf->flags,
					'FontBBox'=>'['.round($ttf->bbox[0])." ".round($ttf->bbox[1])." ".round($ttf->bbox[2])." ".round($ttf->bbox[3]).']',
					'ItalicAngle'=>$ttf->italicAngle,
					'StemV'=>round($ttf->stemV),
					'MissingWidth'=>round($ttf->defaultWidth)
				);

				$up = round($ttf->underlinePosition);
				$ut = round($ttf->underlineThickness);
				$originalsize = $ttfstat['size']+0;
				$type = 'TTF';
				// Generate metrics .php file
				$s='<?php'."\n";
				$s.='$name=\''.$name."';\n";
				$s.='$type=\''.$type."';\n";
				$s.='$desc='.var_export($desc,true).";\n";
				$s.='$up='.$up.";\n";
				$s.='$ut='.$ut.";\n";
				$s.='$ttffile=\''.$ttffile."';\n"; // todo: apply a relative path
				$s.='$originalsize='.$originalsize.";\n";
				$s.='$fontkey=\''.$fontkey."';\n";
				$s.="?>";

				if ( is_writable( dirname( $this->_getfontpath().'unifont/'.'x' ) ) ) {
					$fh = fopen($unifilename.'.mtx.php',"w");
					fwrite($fh,$s,strlen($s));
					fclose($fh);
					$fh = fopen($unifilename.'.cw.dat',"wb");
					fwrite($fh,$cw,strlen($cw));
					fclose($fh);
					@unlink($unifilename.'.cw127.php');
				}
				unset($ttf);
			}
			else {
				$cw = @file_get_contents($unifilename.'.cw.dat');
			}
			$i = count($this->fonts)+1;
			if(!empty($this->AliasNbPages)) {
				$sbarr = range( 0, 57 );
			}
			else {
				$sbarr = range( 0, 32 );
			}
			$this->fonts[$fontkey] = array('i'=>$i, 'type'=>$type, 'name'=>$name, 'desc'=>$desc, 'up'=>$up, 'ut'=>$ut, 'cw'=>$cw, 'ttffile'=>$ttffile, 'fontkey'=>$fontkey, 'subset'=>$sbarr, 'unifilename'=>$unifilename);

			$this->FontFiles[$fontkey]=array('length1'=>$originalsize, 'type'=>"TTF", 'ttffile'=>$ttffile);
			$this->FontFiles[$file]=array('type'=>"TTF");
			unset($cw);
		}
		else {
			$info = $this->_loadfont( $file );
			$info['i'] = count($this->fonts)+1;
			if ( !empty( $info['diff'] ) ) {
				// Search existing encodings
				$n = array_search( $info['diff'], $this->diffs );
				if ( !$n ) {
					$n = count( $this->diffs ) + 1;
					$this->diffs[$n] = $info['diff'];
				}
				$info['diffn'] = $n;
			}

			if ( !empty( $info['file'] ) ) {
				// Embedded font
				if ( $info['type']=='TrueType') {
					$this->FontFiles[$info['file']] = array( 'length1' => $info['originalsize'] );
				}
				else {
					$this->FontFiles[$info['file']] = array( 'length1' => $info['size1'], 'length2' => $info['size2'] );
				}
			}
			$this->fonts[$fontkey] = $info;
		}
		return $this;
	}

	public function setFont( string $family, string $style='', int $size = 0 ): FlexPdf {
		// Select a font; size given in points
		if($family=='') {
			$family = $this->FontFamily;
		}
		else {
			$family = strtolower( $family );
		}

		$style = strtoupper($style);
		if(strpos($style,'U')!==false) {
			$this->underline = true;
			$style = str_replace('U','',$style);
		}
		else {
			$this->underline = false;
		}

		if ( $style == 'IB' ) {
			$style = 'BI';
		}

		if ( $size == 0 ) {
			$size = $this->FontSizePt;
		}
		// Test if font is already selected
		if ( $this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size ) {
			return $this;
		}

		// Test if font is already loaded
		$fontkey = $family.$style;
		if ( !isset( $this->fonts[$fontkey] ) ) {
			// Test if one of the core fonts
			if ( $family == 'arial' ) {
				$family = 'helvetica';
			}

			if (in_array($family,$this->CoreFonts)) {
				if($family=='symbol' || $family=='zapfdingbats') {
					$style = '';
				}
				$fontkey = $family.$style;
				if(!isset($this->fonts[$fontkey])) {
					$this->addFont( $family, $style );
				}
			}
			else {
				$this->error( 'Undefined font: ' . $family . ' ' . $style );
			}
		}

		// Select it
		$this->FontFamily = $family;
		$this->FontStyle = $style;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->nScaleFactor;
		$this->CurrentFont = &$this->fonts[$fontkey];
		$this->unifontSubset = ( $this->fonts[$fontkey]['type']=='TTF');

		if ( $this->nPageNumber > 0 ) {
			$this->_out( sprintf( 'BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt ) );
		}

		return $this;
	}

	/**
	 * @param int $size
	 * @return FlexPDF
	 */
	public function setFontSize( int $size ): FlexPdf {
		if ( $this->FontSizePt == $size ) {
			return $this;
		}

		$this->FontSizePt = $size;
		$this->FontSize = ( $size / $this->nScaleFactor );
		if ( $this->nPageNumber > 0 ) {
			$this->_out( sprintf( 'BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt ) );
		}
		return $this;
	}

	/**
	 * Create a new internal link
	 * @return int
	 */
	public function addLink(): int {
		$nLinkIndex = count( $this->links ) + 1;
		$this->links[$nLinkIndex] = array( 0, 0 );
		return $nLinkIndex;
	}

	/**
	 * @param int $nLinkIndex
	 * @param int $nPosY
	 * @param int $nPage
	 * @return FlexPdf
	 */
	public function setLink( $nLinkIndex, $nPosY = 0, $nPage = -1 ): FlexPdf {
		// Set destination of internal link
		if ( $nPosY == -1 ) {
			$nPosY = $this->nPosY;
		}

		if ( $nPage == -1 ) {
			$nPage = $this->nPageNumber;
		}
		$this->links[$nLinkIndex] = array( $nPage, $nPosY );

		return $this;
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param float $w
	 * @param int $h
	 * @param $sLink
	 * @return FlexPdf
	 */
	public function link( int $x, int $y, float $w, int $h, $sLink ): FlexPdf {
		$this->PageLinks[$this->nPageNumber][] = array(
			$x * $this->nScaleFactor,
			$this->hPt - $y * $this->nScaleFactor,
			$w * $this->nScaleFactor,
			$h * $this->nScaleFactor,
			$sLink
		);
		return $this;
	}

	/**
	 * @param $x
	 * @param $y
	 * @param $sText
	 * @return FlexPdf
	 */
	public function text( $x, $y, string $sText ): FlexPdf {
		// Output a string
		if ( $this->unifontSubset ) {
			$txt2 = '('.$this->_escape( $this->UTF8ToUTF16BE( $sText, false ) ).')';
			foreach ( $this->UTF8StringToArray( $sText ) as $uni ) {
				$this->CurrentFont['subset'][$uni] = $uni;
			}
		}
		else {
			$txt2 = '(' . $this->_escape( $sText ) . ')';
		}
		$s = sprintf(
			'BT %.2F %.2F Td %s Tj ET',
			$x * $this->nScaleFactor,
			($this->h-$y) * $this->nScaleFactor,
			$txt2
		);

		if ( $this->underline && $sText != '' ) {
			$s .= ' ' . $this->_dounderline( $x, $y, $sText );
		}
		if ( $this->ColorFlag ) {
			$s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
		}
		$this->_out( $s );
		return $this;
	}

	public function AcceptPageBreak() {
		// Accept automatic page break or not
		return $this->bAutoPageBreak;
	}

	public function cell( $nWidth, $h = 0, string $txt = '', $border = 0, $ln = 0, string $align = '', bool $fill = false, string $link = '' ) {
		$k = $this->nScaleFactor;
		if ( ( $this->nPosY + $h > $this->nPageBreakTrigger ) && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak() ) {
			// Automatic page break
			$x = $this->nPosX;
			$ws = $this->ws;
			if ( $ws > 0 ) {
				$this->ws = 0;
				$this->writeLineBreak();
			}
			$this->addPage( $this->sCurrentOrientation, $this->nCurrentPageSize );
			$this->nPosX = $x;
			if ( $ws > 0 ) {
				$this->ws = $ws;
				// todo: writeLineBreak with numeric param
				$this->_out(sprintf('%.3F Tw',$ws*$k));
			}
		}
		if ( $nWidth == 0 ) {
			$nWidth = $this->w - $this->nMarginRight - $this->nPosX;
		}

		$s = '';
		if ( $fill || $border==1 ) {
			if ( $fill ) {
				$op = ( $border == 1 )? 'B': 'f';
			}
			else {
				$op = 'S';
			}
			$s = sprintf( '%.2F %.2F %.2F %.2F re %s ',
				$this->nPosX * $k,
				( $this->h - $this->nPosY ) * $k,
				$nWidth * $k,
				-$h * $k,
				$op
			);
		}

		// todo: draw border
		if ( is_string( $border ) ) {
			$x = $this->nPosX;
			$y = $this->nPosY;

			if ( strpos( $border,'L' ) !== false ) {
				$s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - $y ) * $k, $x * $k, ( $this->h - ( $y + $h ) ) * $k );
			}
			if ( strpos( $border,'T' ) !== false ) {
				$s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - $y ) * $k, ( $x + $nWidth ) * $k, ( $this->h - $y ) * $k );
			}
			if ( strpos( $border,'R') !== false ) {
				$s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', ( $x + $nWidth ) * $k, ( $this->h - $y ) * $k, ( $x + $nWidth ) * $k, ( $this->h - ( $y + $h ) ) * $k );
			}
			if ( strpos( $border,'B' ) !== false ) {
				$s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - ( $y + $h ) ) * $k, ( $x + $nWidth ) * $k, ( $this->h - ( $y + $h ) ) * $k );
			}
		}

		if ( $txt !== '' ) {
			if($align=='R') {
				$dx = $nWidth - $this->bMarginCell - $this->getStringWidth( $txt );
			}
			else if ( $align == 'C' ) {
				$dx = ( $nWidth - $this->getStringWidth( $txt ) ) / 2;
			}
			else {
				$dx = $this->bMarginCell;
			}

			if ( $this->ColorFlag) {
				$s .= 'q ' . $this->TextColor . ' ';
			}

			// If multibyte, Tw has no effect - do word spacing using an adjustment before each space
			if ($this->ws && $this->unifontSubset) {
				foreach($this->UTF8StringToArray($txt) as $uni) {
					$this->CurrentFont['subset'][$uni] = $uni;
				}

				$space = $this->_escape($this->UTF8ToUTF16BE(' ', false));
				$s .= sprintf('BT 0 Tw %.2F %.2F Td [',($this->nPosX+$dx)*$k,($this->h-($this->nPosY+.5*$h+.3*$this->FontSize))*$k);
				$t = explode(' ',$txt);
				$numt = count($t);
				for ( $i = 0; $i < $numt; $i++ ) {
					$tx = $t[$i];
					$tx = '('.$this->_escape($this->UTF8ToUTF16BE($tx, false)).')';
					$s .= sprintf('%s ',$tx);
					if (($i+1)<$numt) {
						$adj = -($this->ws*$this->nScaleFactor)*1000/$this->FontSizePt;
						$s .= sprintf('%d(%s) ',$adj,$space);
					}
				}
				$s .= '] TJ';
				$s .= ' ET';
			}
			else {
				if ($this->unifontSubset) {
					$txt2 = '('.$this->_escape($this->UTF8ToUTF16BE($txt, false)).')';
					foreach($this->UTF8StringToArray($txt) as $uni) {
						$this->CurrentFont['subset'][$uni] = $uni;
					}
				}
				else
					$txt2='('.str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$txt))).')';
				$s .= sprintf('BT %.2F %.2F Td %s Tj ET',($this->nPosX+$dx)*$k,($this->h-($this->nPosY+.5*$h+.3*$this->FontSize))*$k,$txt2);
			}
			if ( $this->underline ) {
				$s .= ' ' . $this->_dounderline( $this->nPosX + $dx, $this->nPosY + .5 * $h + .3 * $this->FontSize, $txt );
			}
			if ( $this->ColorFlag ) {
				$s .= ' Q';
			}
			if ( $link ) {
				$this->link( $this->nPosX + $dx, $this->nPosY + .5 * $h - .5 * $this->FontSize, $this->getStringWidth( $txt ), $this->FontSize, $link );
			}
		}
		if ( $s ) {
			$this->_out( $s );
		}
		$this->lasth = $h;
		if ( $ln > 0 ) {
			// Go to next line
			$this->nPosY += $h;
			if($ln==1) {
				$this->nPosX = $this->nMarginLeft;
			}
		}
		else {
			$this->nPosX += $nWidth;
		}

		return $this;
	}

	public function multiCell( $w, $h, $txt, $border = 0, $align = 'J', $fill = false ) {
		// Output text with automatic or explicit line breaks
		$cw = &$this->CurrentFont['cw'];
		if($w==0) {
			$w = $this->w - $this->nMarginRight - $this->nPosX;
		}
		$wmax = ($w-2*$this->bMarginCell);
		$s = str_replace("\r",'',$txt);
		if ($this->unifontSubset) {
			$nb=mb_strlen($s, 'utf-8');
			while($nb>0 && mb_substr($s,$nb-1,1,'utf-8')=="\n")	$nb--;
		}
		else {
			$nb = strlen($s);
			if($nb>0 && $s[$nb-1]=="\n") {
				$nb--;
			}
		}
		$b = 0;
		if ( $border ) {
			if ( $border == 1 ) {
				$border = 'LTRB';
				$b = 'LRT';
				$b2 = 'LR';
			}
			else {
				$b2 = '';
				if ( strpos( $border, 'L' ) !== false ) {
					$b2 .= 'L';
				}
				if ( strpos( $border, 'R' ) !== false ) {
					$b2 .= 'R';
				}
				$b = ( strpos( $border, 'T' ) !== false )? $b2.'T': $b2;
			}
		}
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$ns = 0;
		$nl = 1;
		while ( $i < $nb ) {
			// Get next character
			if ($this->unifontSubset) {
				$c = mb_substr($s,$i,1,'UTF-8');
			}
			else {
				$c=$s[$i];
			}

			if ( $c == "\n" ) {
				// Explicit line break
				if($this->ws>0) {
					$this->ws = 0;
					$this->_out('0 Tw');
				}

				$this->cell( $w, $h, $this->subString( $s, $j, $i-$j ), $b, 2, $align, $fill );

				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if ( $border && $nl == 2 ) {
					$b = $b2;
				}
				continue;
			}
			if ( $c == ' ' ) {
				$sep = $i;
				$ls = $l;
				$ns++;
			}

			if ($this->unifontSubset) {
				$l += $this->getStringWidth($c);
			}
			else {
				$l += $cw[$c]*$this->FontSize/1000;
			}

			if ( $l > $wmax ) {
				// Automatic line break
				if ( $sep == -1 ) {
					if ( $i == $j ) {
						$i++;
					}

					if ( $this->ws > 0 ) {
						$this->ws = 0;
						$this->_out('0 Tw');
					}
					$this->cell( $w, $h, $this->subString( $s, $j, $i-$j ), $b, 2, $align, $fill );
				}
				else {
					if ( $align == 'J' ) {
						$this->ws = ($ns>1) ? ($wmax-$ls)/($ns-1) : 0;
						$this->_out(sprintf('%.3F Tw',$this->ws*$this->nScaleFactor));
					}

					$this->cell( $w, $h, $this->subString( $s, $j, $sep-$j ), $b, 2, $align, $fill );
					$i = $sep+1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if($border && $nl==2) {
					$b = $b2;
				}
			}
			else {
				$i++;
			}
		}

		// Last chunk
		if ( $this->ws > 0 ) {
			$this->ws = 0;
			$this->writeLineBreak();
		}

		if ( $border && strpos( $border, 'B' ) !== false ) {
			$b .= 'B';
		}

		$this->cell( $w, $h, $this->subString( $s, $j, $i-$j ), $b, 2, $align, $fill );

		$this->nPosX = $this->nMarginLeft;
		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	private function writeLineBreak(): FlexPdf {
		return $this->_out( '0 Tw' );
	}

	/**
	 * @param int $nHeight
	 * @param string $sText
	 * @param string $sLink
	 */
	function write( $nHeight, $sText, $sLink = '' ) {
		// Output text in flowing mode
		$cw = &$this->CurrentFont['cw'];
		$w = $this->w-$this->nMarginRight-$this->nPosX;

		$wmax = ( $w - 2 * $this->bMarginCell );
		$s = str_replace( "\r", '', $sText );
		if ( $this->unifontSubset ) {
			$nb = mb_strlen($s, 'UTF-8');
			if ( $nb==1 && $s==" ") {
				$this->nPosX += $this->getStringWidth( $s );
				return;
			}
		}
		else {
			$nb = strlen( $s );
		}

		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;

		while ( $i < $nb ) {
			// Get next character
			if ( $this->unifontSubset ) {
				$c = mb_substr( $s,$i, 1, 'UTF-8' );
			}
			else {
				$c = $s[$i];
			}

			if ( $c == "\n" ) {
				// Explicit line break
				$this->cell( $w, $nHeight, $this->subString( $s, $j, $i-$j ), 0, 2, '', false, $sLink );

				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				if ( $nl == 1 ) {
					$this->nPosX = $this->nMarginLeft;
					$w = $this->w-$this->nMarginRight-$this->nPosX;
					$wmax = ($w-2*$this->bMarginCell);
				}
				$nl++;
				continue;
			}

			if ( $c == ' ' ) {
				$sep = $i;
			}

			if ( $this->unifontSubset) {
				$l += $this->getStringWidth( $c );
			}
			else {
				$l += ( $cw[$c] * $this->FontSize/1000 );
			}

			if ( $l > $wmax ) {
				// Automatic line break
				if ( $sep == -1 ) {
					if ( $this->nPosX > $this->nMarginLeft ) {
						// Move to next line
						$this->nPosX = $this->nMarginLeft;
						$this->nPosY += $nHeight;
						$w = $this->w-$this->nMarginRight-$this->nPosX;
						$wmax = ($w-2*$this->bMarginCell);
						$i++;
						$nl++;
						continue;
					}

					if ( $i == $j ) {
						$i++;
					}

					$this->cell( $w, $nHeight, $this->subString( $s, $j, $i-$j ), 0, 2, '', false, $sLink );
				}
				else {
					$this->cell( $w, $nHeight, $this->subString( $s, $j, $sep-$j ), 0, 2, '', false, $sLink );
					$i = $sep+1;
				}

				$sep = -1;
				$j = $i;
				$l = 0;
				if ( $nl == 1 ) {
					$this->nPosX = $this->nMarginLeft;
					$w = $this->w-$this->nMarginRight-$this->nPosX;
					$wmax = ($w-2*$this->bMarginCell);
				}
				$nl++;
			}
			else {
				$i++;
			}
		}
		// Last chunk
		if ( $i != $j ) {
			if ( $this->unifontSubset ) {
				$this->cell( $l, $nHeight, mb_substr( $s, $j, $i-$j, 'UTF-8' ), 0, 0, '', false, $sLink );
			}
			else {
				$this->cell( $l, $nHeight, substr( $s, $j ), 0, 0, '', false, $sLink );
			}
		}
	}

	/**
	 * @param string $sText
	 * @param int $nStart
	 * @param int $nLength
	 * @return string
	 */
	public function subString( string $sText, int $nStart, int $nLength ): string {
		return (
			$this->unifontSubset?
				mb_substr( $sText, $nStart, $nLength, 'UTF-8' ):
				   substr( $sText, $nStart, $nLength )
			);
	}

	/**
	 * @param int $nLineHeight
	 * @return FlexPdf
	 */
	public function linefeed( $nLineHeight = null ): FlexPdf {
		// Line feed; default value is last cell height
		$this->nPosX = $this->nMarginLeft;
		if ( $nLineHeight === null ) {
			$this->nPosY += $this->lasth;
		}
		else {
			$this->nPosY += $nLineHeight;
		}
		return $this;
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	public function isImageFileAdded( string $sFile ): bool {
		return ( isset( $this->images[$sFile] ) );
	}

	/**
	 * @param string $sFile
	 * @param string $sType
	 * @return array
	 * @throws \Exception
	 */
	public function getImageInfo( string $sFile, string $sType = null ) {
		if ( empty( $sType ) ) {
			$nDotPos = strrpos( $sFile, '.' );
			if ( $nDotPos === false ) {
				throw new \Exception( sprintf( 'Image file has no extension and no type was specified: %s', $sFile ) );
			}
			$sType = substr( $sFile, $nDotPos + 1 );
		}

		$sType = strtolower( $sType );
		if ( $sType == 'jpeg' ) {
			$sType = 'jpg';
		}

		$sInfoMethod = sprintf( '_parse%s', $sType );
		if ( !method_exists( $this, $sInfoMethod ) ) {
			throw new \Exception( sprintf( 'Unsupported image type: %s', $sType ) );
		}

		$aFileInfo = $this->{$sInfoMethod}( $sFile );
		$aFileInfo['i'] = count( $this->images ) + 1;
		$aFileInfo['index'] = count( $this->images ) + 1;

		return $aFileInfo;
	}

	/**
	 * @param string $sFile
	 * @param int $nPosX
	 * @param int $nPosY
	 * @param int $nWidth
	 * @param int $nHeight
	 * @param string $sType
	 * @param string $sLink
	 * @return $this
	 */
	public function image( string $sFile, $nPosX = null, $nPosY = null, $nWidth = 0, $nHeight = 0, $sType = '', $sLink = '' ) {
		// Put an image on the page
		if ( !$this->isImageFileAdded( $sFile ) ) {
			$this->images[$sFile] = $this->getImageInfo( $sFile, $sType );
		}

		$aFileInfo = $this->images[$sFile];

		// Automatic width and height calculation if needed. Put image at 96 dpi
		if ( $nWidth == 0 && $nHeight == 0 ) {
			$nWidth = -96;
			$nHeight = -96;
		}

		if ( $nWidth < 0 ) {
			$nWidth = -$aFileInfo['w'] * 72 / $nWidth / $this->nScaleFactor;
		}

		if ( $nHeight < 0 ) {
			$nHeight = -$aFileInfo['h'] * 72 / $nHeight / $this->nScaleFactor;
		}

		if ( $nWidth == 0 ) {
			$nWidth = $nHeight * $aFileInfo['w'] / $aFileInfo['h'];
		}

		if ( $nHeight == 0 ) {
			$nHeight = $nWidth * $aFileInfo['h'] / $aFileInfo['w'];
		}

		// Flowing mode
		if ( $nPosY === null ) {
			if ( $this->nPosY+$nHeight>$this->nPageBreakTrigger && !$this->InHeader
				&& !$this->InFooter && $this->AcceptPageBreak() ) {
				// Automatic page break
				$x2 = $this->nPosX;
				$this->addPage( $this->sCurrentOrientation, $this->nCurrentPageSize );
				$this->nPosX = $x2;
			}
			$nPosY = $this->nPosY;
			$this->nPosY += $nHeight;
		}

		if ( $nPosX === null ) {
			$nPosX = $this->nPosX;
		}

		$this->_out(
			sprintf( 'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
				$nWidth * $this->nScaleFactor,
				$nHeight * $this->nScaleFactor,
				$nPosX * $this->nScaleFactor,
				($this->h - ($nPosY + $nHeight ) ) * $this->nScaleFactor,
				$aFileInfo['index']
			)
		);

		if ( !empty( $sLink ) ) {
			$this->link( $nPosX, $nPosY, $nWidth, $nHeight, $sLink );
		}
		return $this;
	}

	/**
	 * @return int
	 */
	public function getX() {
		return $this->nPosX;
	}

	/**
	 * @param int $x
	 * @return $this
	 */
	public function setX( $x ) {
		if ( $x >= 0 ) {
			$this->nPosX = $x;
		}
		else {
			$this->nPosX = $this->w + $x;
		}
		return $this;
	}

	/**
	 * @return int
	 */
	public function getY() {
		return $this->nPosY;
	}

	/**
	 * @param int $nPosY
	 * @param bool $bResetX
	 * @return $this
	 */
	public function setY( $nPosY, $bResetX = true ) {
		// Set y position and reset x
		if ( $bResetX ) {
			$this->nPosX = $this->nMarginLeft;
		}
		if ( $nPosY >= 0 ) {
			$this->nPosY = $nPosY;
		}
		else {
			$this->nPosY = $this->h + $nPosY;
		}
		return $this;
	}

	/**
	 * @param int $nPosX
	 * @param int $nPosY
	 * @return $this
	 */
	public function setXY( $nPosX, $nPosY ) {
		return $this
			->setY( $nPosY )
			->setX( $nPosX );
	}

	/**
	 * @return string
	 */
	public function getBuffer() {
		return $this->sBuffer;
	}

	/**
	 * @return string
	 */
	public function raw() {
		$this->close();
		return $this->getBuffer();
	}

	public function output( $name = '', $dest = '' ) {
		// Output PDF to some destination
		if ( $this->sDocState < 3 ) {
			$this->close();
		}

		$dest = strtoupper($dest);
		if ( $dest == '' ) {
			if ( $name == '' ) {
				$name = 'doc.pdf';
				$dest = 'I';
			}
			else {
				$dest = 'F';
			}
		}

		switch ( $dest ) {
			case 'I':
				// Send to standard output
				$this->_checkoutput();
				if ( PHP_SAPI != 'cli' ) {
					// We send to a browser
					header( 'Content-Type: application/pdf' );
					header( 'Content-Disposition: inline; filename="'.$name.'"' );
					header( 'Cache-Control: private, max-age=0, must-revalidate' );
					header( 'Pragma: public' );
				}
				echo $this->sBuffer;
				break;
			case 'D':
				// Download file
				$this->_checkoutput();
				header('Content-Type: application/x-download');
				header('Content-Disposition: attachment; filename="'.$name.'"');
				header('Cache-Control: private, max-age=0, must-revalidate');
				header('Pragma: public');
				echo $this->sBuffer;
				break;
			case 'F':
				// Save to local file
				$f = fopen($name,'wb');
				if(!$f) {
					$this->error( 'Unable to create output file: ' . $name );
				}
				fwrite($f,$this->sBuffer,strlen($this->sBuffer));
				fclose($f);
				break;
			case 'S':
				// Return as a string
				return $this->sBuffer;
			default:
				$this->error('Incorrect output destination: '.$dest);
		}
		return '';
	}

	private function _getfontpath() {
		return $this->fontpath;
	}

	private function _checkoutput() {
		if ( PHP_SAPI != 'cli' ) {
			if ( headers_sent( $file, $line ) ) {
				$this->error( "Some data has already been output, can't send PDF file (output started at $file:$line)" );
			}
		}

		if ( ob_get_length() ) {
			// The output buffer is not empty
			if ( preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents() ) ) {
				// It contains only a UTF-8 BOM and/or whitespace, let's clean it
				ob_clean();
			}
			else {
				$this->error( "Some data has already been output, can't send PDF file" );
			}
		}
	}

	private function _getpagesize($size) {
		if ( is_string( $size ) ) {
			$size = strtolower($size);
			if(!isset($this->aStandardPageSizes[$size])) {
				$this->error( 'Unknown page size: ' . $size );
			}
			$a = $this->aStandardPageSizes[$size];
			return array($a[0]/$this->nScaleFactor, $a[1]/$this->nScaleFactor);
		}
		else {
			return ( $size[0] > $size[1] )? array( $size[1], $size[0] ): $size;
		}
	}

	/**
	 * @param $orientation
	 * @param $size
	 * @return $this
	 */
	private function _beginpage( $orientation, $size ) {
		$this->nPageNumber++;
		$this->aPages[$this->nPageNumber] = '';
		$this->sDocState = 2;
		$this->nPosX = $this->nMarginLeft;
		$this->nPosY = $this->nMarginTop;
		$this->FontFamily = '';

		// Check page size and orientation
		if($orientation=='')
			$orientation = $this->sDefaultOrientation;
		else
			$orientation = strtoupper($orientation[0]);

		if($size=='')
			$size = $this->sDefaultPageSize;
		else
			$size = $this->_getpagesize($size);

		if($orientation!=$this->sCurrentOrientation || $size[0]!=$this->nCurrentPageSize[0] || $size[1]!=$this->nCurrentPageSize[1]) {
			// New size or orientation
			if($orientation=='P') {
				$this->w = $size[0];
				$this->h = $size[1];
			}
			else {
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w*$this->nScaleFactor;
			$this->hPt = $this->h*$this->nScaleFactor;
			$this->nPageBreakTrigger = $this->h-$this->bMarginPageBreak;
			$this->sCurrentOrientation = $orientation;
			$this->nCurrentPageSize = $size;
		}
		if($orientation!=$this->sDefaultOrientation || $size[0]!=$this->sDefaultPageSize[0] || $size[1]!=$this->sDefaultPageSize[1])
			$this->PageSizes[$this->nPageNumber] = array($this->wPt, $this->hPt);

		return $this;
	}

	private function _endpage() {
		$this->sDocState = 1;
		return $this;
	}

	/**
	 * @param string $sFont
	 * @return array
	 * @throws \Exception
	 */
	private function _loadfont( string $sFont ) {
		$sFontFile = rtrim( $this->fontpath, '/' ).'/'.$sFont;
		if ( !is_file( $sFontFile ) ) {
			throw new \Exception( sprintf( 'Font file "%s" does not exist', $sFontFile ) );
		}

		include( $sFontFile );

		$a = get_defined_vars();
		//var_dump($a);
		//die();
		if ( !isset( $a['name'] ) ) {
			$this->error( 'Could not include font definition file' );
		}
		return $a;
	}

	private function _escape( $sString ) {
		// Escape special characters in strings
		$sString = str_replace( '\\', '\\\\', $sString );
		$sString = str_replace( '(', '\\(', $sString );
		$sString = str_replace( ')', '\\)', $sString );
		$sString = str_replace( "\r", '\\r', $sString );
		return $sString;
	}

	private function _textstring( string $sString ): string {
		// Format a text string
		return '('.$this->_escape( $sString ).')';
	}

	private function _UTF8toUTF16( string $s ): string {
		// Convert UTF-8 to UTF-16BE with BOM
		$res = "\xFE\xFF";
		$nb = strlen( $s );
		$i = 0;
		while ( $i < $nb ) {
			$c1 = ord($s[$i++]);
			if($c1>=224)
			{
				// 3-byte character
				$c2 = ord($s[$i++]);
				$c3 = ord($s[$i++]);
				$res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
				$res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
			}
			elseif($c1>=192) {
				// 2-byte character
				$c2 = ord($s[$i++]);
				$res .= chr(($c1 & 0x1C)>>2);
				$res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
			}
			else {
				// Single-byte character
				$res .= "\0".chr($c1);
			}
		}
		return $res;
	}

	private function _dounderline($x, $y, $txt) {
		// Underline text
		$up = $this->CurrentFont['up'];
		$ut = $this->CurrentFont['ut'];
		$w = $this->getStringWidth($txt)+$this->ws*substr_count($txt,' ');
		return sprintf(
			'%.2F %.2F %.2F %.2F re f',
			$x*$this->nScaleFactor,
			($this->h-($y-$up/1000*$this->FontSize))*$this->nScaleFactor,
			$w*$this->nScaleFactor,
			-$ut/1000*$this->FontSizePt
		);
	}

	private function _parsejpg($file) {
		// Extract info from a JPEG file
		$a = getimagesize($file);
		if(!$a) {
			$this->error( 'Missing or incorrect image file: ' . $file );
		}

		if($a[2]!=2) {
			$this->error( 'Not a JPEG file: ' . $file );
		}

		if(!isset($a['channels']) || $a['channels']==3) {
			$colspace = 'DeviceRGB';
		}
		else if($a['channels']==4) {
			$colspace = 'DeviceCMYK';
		}
		else {
			$colspace = 'DeviceGray';
		}
		$bpc = isset($a['bits']) ? $a['bits'] : 8;
		$data = file_get_contents($file);
		return array('w'=>$a[0], 'h'=>$a[1], 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'DCTDecode', 'data'=>$data);
	}

	private function _parsepng($file) {
		// Extract info from a PNG file
		$f = fopen($file,'rb');
		if( !$f ) {
			$this->error( 'Can\'t open image file: ' . $file );
		}
		$info = $this->_parsepngstream($f,$file);
		fclose($f);
		return $info;
	}

	private function _parsepngstream($f, $file) {
		// Check signature
		if($this->_readstream($f,8)!=chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10)) {
			$this->error( 'Not a PNG file: ' . $file );
		}

		// Read header chunk
		$this->_readstream($f,4);
		if($this->_readstream($f,4)!='IHDR')
			$this->Error('Incorrect PNG file: '.$file);
		$w = $this->_readint($f);
		$h = $this->_readint($f);
		$bpc = ord($this->_readstream($f,1));
		if($bpc>8)
			$this->Error('16-bit depth not supported: '.$file);
		$ct = ord($this->_readstream($f,1));
		if($ct==0 || $ct==4)
			$colspace = 'DeviceGray';
		elseif($ct==2 || $ct==6)
			$colspace = 'DeviceRGB';
		elseif($ct==3)
			$colspace = 'Indexed';
		else
			$this->Error('Unknown color type: '.$file);
		if(ord($this->_readstream($f,1))!=0)
			$this->Error('Unknown compression method: '.$file);
		if(ord($this->_readstream($f,1))!=0)
			$this->Error('Unknown filter method: '.$file);
		if(ord($this->_readstream($f,1))!=0)
			$this->Error('Interlacing not supported: '.$file);
		$this->_readstream($f,4);
		$dp = '/Predictor 15 /Colors '.($colspace=='DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;

		// Scan chunks looking for palette, transparency and image data
		$pal = '';
		$trns = '';
		$data = '';
		do {
			$n = $this->_readint($f);
			$type = $this->_readstream($f,4);
			if($type=='PLTE') {
				// Read palette
				$pal = $this->_readstream($f,$n);
				$this->_readstream($f,4);
			}
			else if($type=='tRNS') {
				// Read transparency info
				$t = $this->_readstream($f,$n);
				if ($ct==0) {
					$trns = array( ord( substr( $t, 1, 1 ) ) );
				}
				else if($ct==2) {
					$trns = array( ord( substr( $t, 1, 1 ) ), ord( substr( $t, 3, 1 ) ), ord( substr( $t, 5, 1 ) ) );
				}
				else {
					$pos = strpos($t,chr(0));
					if($pos!==false)
						$trns = array($pos);
				}
				$this->_readstream($f,4);
			}
			elseif($type=='IDAT') {
				// Read image data block
				$data .= $this->_readstream($f,$n);
				$this->_readstream($f,4);
			}
			elseif($type=='IEND') {
				break;
			}
			else {
				$this->_readstream( $f, $n + 4 );
			}
		}
		while($n);

		if($colspace=='Indexed' && empty($pal)) {
			$this->error( 'Missing palette in ' . $file );
		}
		$info = array('w'=>$w, 'h'=>$h, 'cs'=>$colspace, 'bpc'=>$bpc, 'f'=>'FlateDecode', 'dp'=>$dp, 'pal'=>$pal, 'trns'=>$trns);
		if($ct>=4) {
			// Extract alpha channel
			if(!function_exists('gzuncompress')) {
				$this->error( 'Zlib not available, can\'t handle alpha channel: ' . $file );
			}
			$data = gzuncompress($data);
			$color = '';
			$alpha = '';
			if($ct==4) {
				// Gray image
				$len = 2*$w;
				for($i=0;$i<$h;$i++) {
					$pos = (1+$len)*$i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data,$pos+1,$len);
					$color .= preg_replace('/(.)./s','$1',$line);
					$alpha .= preg_replace('/.(.)/s','$1',$line);
				}
			}
			else {
				// RGB image
				$len = 4*$w;
				for($i=0;$i<$h;$i++) {
					$pos = (1+$len)*$i;
					$color .= $data[$pos];
					$alpha .= $data[$pos];
					$line = substr($data,$pos+1,$len);
					$color .= preg_replace('/(.{3})./s','$1',$line);
					$alpha .= preg_replace('/.{3}(.)/s','$1',$line);
				}
			}
			unset($data);
			$data = gzcompress($color);
			$info['smask'] = gzcompress($alpha);
			if($this->PDFVersion<'1.4') {
				$this->PDFVersion = '1.4';
			}
		}
		$info['data'] = $data;
		return $info;
	}

	private function _readstream($f, $n) {
		// Read n bytes from stream
		$res = '';
		while($n>0 && !feof($f)) {
			$s = fread($f,$n);
			if($s===false) {
				$this->error( 'Error while reading stream' );
			}
			$n -= strlen($s);
			$res .= $s;
		}
		if($n>0) {
			$this->error( 'Unexpected end of stream' );
		}
		return $res;
	}

	private function _readint( $f ) {
		// Read a 4-byte integer from stream
		$a = unpack('Ni',$this->_readstream($f,4));
		return $a['i'];
	}

	private function _parsegif( $file ) {
		// Extract info from a GIF file (via PNG conversion)
		if ( !function_exists('imagepng')) {
			$this->error( 'GD extension is required for GIF support' );
		}
		if ( !function_exists('imagecreatefromgif')) {
			$this->error( 'GD has no GIF read support' );
		}
		$im = imagecreatefromgif($file);
		if ( !$im ) {
			$this->error( 'Missing or incorrect image file: ' . $file );
		}
		imageinterlace($im,0);
		$f = @fopen('php://temp','rb+');
		if($f) {
			// Perform conversion in memory
			ob_start();
			imagepng($im);
			$data = ob_get_clean();
			imagedestroy($im);
			fwrite($f,$data);
			rewind($f);
			$info = $this->_parsepngstream($f,$file);
			fclose($f);
		}
		else {
			// Use temporary file
			$tmp = tempnam( '.','gif' );
			if ( !$tmp ) {
				$this->error( 'Unable to create a temporary file' );
			}
			if ( !imagepng($im,$tmp)) {
				$this->Error( 'Error while saving to temporary file' );
			}
			imagedestroy($im);
			$info = $this->_parsepng($tmp);
			unlink($tmp);
		}
		return $info;
	}

	/**
	 * Begin a new object within the document.
	 * @return FlexPdf
	 */
	private function _newobj(): FlexPdf {
		$this->nObjectNumber++;
		$this->aObjectOffsets[$this->nObjectNumber] = strlen( $this->sBuffer );

		$this->_out( $this->nObjectNumber.' 0 obj' );
		return $this;
	}

	/**
	 * @param string $sString
	 * @return FlexPdf
	 */
	private function _putstream( string $sString ): FlexPdf {
		return $this
			->_out( 'stream' )
			->_out( $sString )
			->_out( 'endstream' );
	}

	/**
	 * Adds a line to the document, always appended with a newline
	 * @param string $sString
	 * @return FlexPdf
	 */
	private function _out( string $sString ): FlexPdf {
		if ( $this->sDocState == 2 ) {
			$this->aPages[$this->nPageNumber] .= $sString . "\n";
		}
		else {
			$this->sBuffer .= $sString . "\n";
		}
		return $this;
	}

	/**
	 *
	 */
	private function _putpages() {
		$nb = $this->nPageNumber;
		if ( !empty( $this->AliasNbPages ) ) {
			// Replace number of pages in fonts using subsets
			$alias = $this->UTF8ToUTF16BE($this->AliasNbPages, false);
			$r = $this->UTF8ToUTF16BE("$nb", false);
			for ( $n=1;$n<=$nb;$n++ ) {
				$this->aPages[$n] = str_replace( $alias, $r, $this->aPages[$n] );
			}
			// Now repeat for no pages in non-subset fonts
			for ( $n=1;$n<=$nb;$n++) {
				$this->aPages[$n] = str_replace( $this->AliasNbPages, $nb, $this->aPages[$n] );
			}
		}

		if ( $this->sDefaultOrientation=='P' ) {
			$wPt = $this->sDefaultPageSize[0]*$this->nScaleFactor;
			$hPt = $this->sDefaultPageSize[1]*$this->nScaleFactor;
		}
		else {
			$wPt = $this->sDefaultPageSize[1]*$this->nScaleFactor;
			$hPt = $this->sDefaultPageSize[0]*$this->nScaleFactor;
		}
		$filter = ($this->bCompress) ? '/Filter /FlateDecode ' : '';

		for ( $n = 1; $n <= $nb; $n++ ) {
			// Page
			$this->_newobj();
			$this->_out('<</Type /Page');
			$this->_out('/Parent 1 0 R');
			if ( isset($this->PageSizes[$n] ) ) {
				$this->_out( sprintf( '/MediaBox [0 0 %.2F %.2F]', $this->PageSizes[$n][0], $this->PageSizes[$n][1] ) );
			}
			$this->_out('/Resources 2 0 R');
			if(isset($this->PageLinks[$n])) {
				// Links
				$annots = '/Annots [';
				foreach($this->PageLinks[$n] as $pl ) {
					$rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
					$annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
					if ( is_string($pl[4])) {
						$annots .= '/A <</S /URI /URI ' . $this->_textstring( $pl[4] ) . '>>>>';
					}
					else {
						$l = $this->links[$pl[4]];
						$h = isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
						$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',1+2*$l[0],$h-$l[1]*$this->nScaleFactor);
					}
				}
				$this->_out($annots.']');
			}

			if ( $this->PDFVersion > '1.3' ) {
				$this->_out( '/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>' );
			}
			$this->_out( '/Contents '.( $this->nObjectNumber + 1 ).' 0 R>>' );
			$this->_out( 'endobj' );

			// Page content
			$p = ($this->bCompress) ? gzcompress($this->aPages[$n]) : $this->aPages[$n];

			$this
				->_newobj()
				->_out( '<<'.$filter.'/Length '.strlen( $p ).'>>' )
				->_putstream( $p )
				->_out( 'endobj' );
		}
		// Pages root
		$this->aObjectOffsets[1] = strlen( $this->sBuffer );
		$this->_out( '1 0 obj' );
		$this->_out( '<</Type /Pages' );
		$kids = '/Kids [';
		for ( $i = 0; $i < $nb; $i++ ) {
			$kids .= ( 3 + 2 * $i ) . ' 0 R ';
		}
		$this
			->_out( $kids.']' )
			->_out( '/Count '.$nb )
			->_out( sprintf( '/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt ) )
			->_out( '>>' )
			->_out( 'endobj' );

		return $this;
	}

	private function _putfonts() {
		$nf=$this->nObjectNumber;
		foreach($this->diffs as $diff) {
			// Encodings
			$this->_newobj();
			$this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$diff.']>>');
			$this->_out('endobj');
		}

		foreach($this->FontFiles as $file=>$info) {
			if (!isset($info['type']) || $info['type']!='TTF') {
				// Font file embedding
				$this->_newobj();
				$this->FontFiles[$file]['n']=$this->nObjectNumber;
				$font='';
				$f=fopen($this->_getfontpath().$file,'rb',1);
				if(!$f)
					$this->Error('Font file not found');
				while(!feof($f))
					$font.=fread($f,8192);
				fclose($f);
				$compressed=(substr($file,-2)=='.z');
				if(!$compressed && isset($info['length2'])) {
					$header=(ord($font[0])==128);
					if($header) {
						// Strip first binary header
						$font=substr($font,6);
					}

					if ( $header && ord( $font[$info['length1']] ) == 128 ) {
						// Strip second binary header
						$font=substr($font,0,$info['length1']).substr($font,$info['length1']+6);
					}
				}

				$this->_out('<</Length '.strlen($font));
				if($compressed) {
					$this->_out( '/Filter /FlateDecode' );
				}

				$this->_out('/Length1 '.$info['length1']);
				if(isset($info['length2'])) {
					$this->_out( '/Length2 ' . $info['length2'] . ' /Length3 0' );
				}
				$this->_out('>>');
				$this->_putstream($font);
				$this->_out('endobj');
			}
		}

		foreach ( $this->fonts as $k => $font ) {
			// Font objects
			//$this->fonts[$k]['n']=$this->n+1;
			$type = $font['type'];
			$name = $font['name'];

			if ( $type == 'Core' ) {
				// Standard font
				$this->fonts[$k]['n'] = $this->nObjectNumber+1;

				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_out('/BaseFont /'.$name);
				$this->_out('/Subtype /Type1');
				if ( $name!='Symbol' && $name!='ZapfDingbats') {
					$this->_out( '/Encoding /WinAnsiEncoding' );
				}
				$this->_out('>>');
				$this->_out('endobj');
			}
			else if ( $type=='Type1' || $type == 'TrueType' ) {
				// Additional Type1 or TrueType font
				$this->fonts[$k]['n']=$this->nObjectNumber+1;
				$this->_newobj();

				$this->_out( '<</Type /Font' );
				$this->_out( '/BaseFont /'.$name );
				$this->_out( '/Subtype /'.$type );
				$this->_out( '/FirstChar 32 /LastChar 255' );
				$this->_out( '/Widths '.($this->nObjectNumber+1).' 0 R' );
				$this->_out( '/FontDescriptor '.($this->nObjectNumber+2).' 0 R' );
				if ( $font['enc'] ) {
					if ( isset( $font['diff'] ) ) {
						$this->_out( '/Encoding ' . ( $nf + $font['diff'] ) . ' 0 R' );
					}
					else {
						$this->_out( '/Encoding /WinAnsiEncoding' );
					}
				}
				$this->_out('>>');
				$this->_out('endobj');
				// Widths
				$this->_newobj();
				$cw=&$font['cw'];
				$s='[';
				for ( $i = 32; $i <= 255; $i++ ) {
					$s .= $cw[chr( $i )] . ' ';
				}
				$this->_out($s.']');
				$this->_out('endobj');
				// Descriptor
				$this->_newobj();
				$s='<</Type /FontDescriptor /FontName /'.$name;
				foreach ( $font['desc'] as $k => $v ) {
					$s .= ' /' . $k . ' ' . $v;
				}
				$file = $font['file'];
				if ( $file ) {
					$s .= ' /FontFile' . ( $type == 'Type1'? '': '2' ) . ' ' . $this->FontFiles[$file]['n'] . ' 0 R';
				}
				$this->_out($s.'>>');
				$this->_out('endobj');
			}
			// TrueType embedded SUBSETS or FULL
			else if ( $type == 'TTF' ) {
				$this->fonts[$k]['n']=$this->nObjectNumber+1;
				require_once( $this->_getfontpath().'unifont/ttfonts.php' );
				$ttf = new \TTFontFile();
				$fontname = 'MPDFAA'.'+'.$font['name'];
				$subset = $font['subset'];
				unset( $subset[0] );
				$ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
				$ttfontsize = strlen($ttfontstream);
				$fontstream = gzcompress($ttfontstream);
				$codeToGlyph = $ttf->codeToGlyph;
				unset( $codeToGlyph[0] );

				// Type0 Font
				// A composite font - a font composed of other fonts, organized hierarchically
				$this
					->_newobj()
					->_out( '<</Type /Font' )
					->_out( '/Subtype /Type0' )
					->_out( '/BaseFont /'.$fontname.'' )
					->_out( '/Encoding /Identity-H' )
					->_out( '/DescendantFonts ['.($this->nObjectNumber + 1).' 0 R]' )
					->_out( '/ToUnicode '.($this->nObjectNumber + 2).' 0 R' )
					->_out( '>>' )
					->_out( 'endobj' );

				// CIDFontType2
				// A CIDFont whose glyph descriptions are based on TrueType font technology
				$this
					->_newobj()
					->_out( '<</Type /Font' )
					->_out( '/Subtype /CIDFontType2' )
					->_out( '/BaseFont /'.$fontname.'' )
					->_out( '/CIDSystemInfo '.($this->nObjectNumber + 2).' 0 R' )
					->_out( '/FontDescriptor '.($this->nObjectNumber + 3).' 0 R' );

				if ( isset( $font['desc']['MissingWidth'] ) ) {
					$this->_out( '/DW '.$font['desc']['MissingWidth'].'' );
				}

				$this->_putTTfontwidths( $font, $ttf->maxUni );

				$this
					->_out( '/CIDToGIDMap '.($this->nObjectNumber + 4).' 0 R' )
					->_out( '>>' )
					->_out( 'endobj' );

				// ToUnicode
				$this->_newobj();
				$toUni = "/CIDInit /ProcSet findresource begin\n";
				$toUni .= "12 dict begin\n";
				$toUni .= "begincmap\n";
				$toUni .= "/CIDSystemInfo\n";
				$toUni .= "<</Registry (Adobe)\n";
				$toUni .= "/Ordering (UCS)\n";
				$toUni .= "/Supplement 0\n";
				$toUni .= ">> def\n";
				$toUni .= "/CMapName /Adobe-Identity-UCS def\n";
				$toUni .= "/CMapType 2 def\n";
				$toUni .= "1 begincodespacerange\n";
				$toUni .= "<0000> <FFFF>\n";
				$toUni .= "endcodespacerange\n";
				$toUni .= "1 beginbfrange\n";
				$toUni .= "<0000> <FFFF> <0000>\n";
				$toUni .= "endbfrange\n";
				$toUni .= "endcmap\n";
				$toUni .= "CMapName currentdict /CMap defineresource pop\n";
				$toUni .= "end\n";
				$toUni .= "end";

				$this->_out('<</Length '.(strlen($toUni)).'>>');
				$this->_putstream($toUni);
				$this->_out('endobj');

				// CIDSystemInfo dictionary
				$this
					->_newobj()
					->_out( '<</Registry (Adobe)' )
					->_out( '/Ordering (UCS)' )
					->_out( '/Supplement 0' )
					->_out( '>>' )
					->_out( 'endobj' );

				// Font descriptor
				$this
					->_newobj()
					->_out( '<</Type /FontDescriptor' )
					->_out( '/FontName /'.$fontname );

				foreach ( $font['desc'] as $kd => $v ) {
					if ($kd == 'Flags') {
						$v = $v | 4;
						$v = $v & ~32;
					}
					// SYMBOLIC font flag
					$this->_out(' /'.$kd.' '.$v);
				}
				$this->_out( '/FontFile2 '.($this->nObjectNumber + 2).' 0 R' );
				$this->_out( '>>' );
				$this->_out( 'endobj' );

				// Embed CIDToGIDMap
				// A specification of the mapping from CIDs to glyph indices
				$cidtogidmap = '';
				$cidtogidmap = str_pad( '', 256 * 256 * 2, "\x00" );
				foreach ( $codeToGlyph as $cc => $glyph ) {
					$cidtogidmap[$cc*2] = chr( $glyph >> 8 );
					$cidtogidmap[$cc*2 + 1] = chr( $glyph & 0xFF );
				}

				$cidtogidmap = gzcompress($cidtogidmap);
				$this
					->_newobj()
					->_out( '<</Length '.strlen( $cidtogidmap ).'' )
					->_out( '/Filter /FlateDecode' )
					->_out( '>>' )
					->_putstream( $cidtogidmap )
					->_out( 'endobj' );

				//Font file
				$this
					->_newobj()
					->_out( '<</Length '.strlen( $fontstream ) )
					->_out( '/Filter /FlateDecode' )
					->_out( '/Length1 '.$ttfontsize )
					->_out( '>>' )
					->_putstream( $fontstream )
					->_out( 'endobj' );

				unset( $ttf );
			}
			else {
				// Allow for additional types
				$this->fonts[$k]['n'] = $this->nObjectNumber+1;
				$mtd = '_put'.strtolower( $type );
				if ( !method_exists( $this, $mtd ) ) {
					$this->error( 'Unsupported font type: '.$type );
				}
				$this->$mtd( $font );
			}
		}
	}

	function _putTTfontwidths( &$font, $maxUni ) {
		if ( file_exists( $font['unifilename'].'.cw127.php' ) ) {
			include( $font['unifilename'].'.cw127.php' );
			$startcid = 128;
		}
		else {
			$rangeid = 0;
			$range = array();
			$prevcid = -2;
			$prevwidth = -1;
			$interval = false;
			$startcid = 1;
		}
		$cwlen = $maxUni + 1;

		// for each character
		for ( $cid = $startcid; $cid < $cwlen; $cid++ ) {
			if ( $cid == 128 && ( !file_exists( $font['unifilename'].'.cw127.php' ) ) ) {
				if ( is_writable(dirname($this->_getfontpath().'unifont/x' ) ) ) {
					$fh = fopen( $font['unifilename'].'.cw127.php',"wb" );
					$cw127='<?php'."\n";
					$cw127.='$rangeid='.$rangeid.";\n";
					$cw127.='$prevcid='.$prevcid.";\n";
					$cw127.='$prevwidth='.$prevwidth.";\n";
					if ( $interval ) {
						$cw127 .= '$interval=true'.";\n";
					}
					else {
						$cw127 .= '$interval=false'.";\n";
					}

					$cw127 .= '$range='.var_export( $range, true ).";\n";
					$cw127 .= "?>";
					fwrite( $fh, $cw127, strlen( $cw127 ) );
					fclose( $fh );
				}
			}
			if ($font['cw'][$cid*2] == "\00" && $font['cw'][$cid*2+1] == "\00") {
				continue;
			}

			$width = (ord($font['cw'][$cid*2]) << 8) + ord($font['cw'][$cid*2+1]);
			if ( $width == 65535 ) {
				$width = 0;
			}

			if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
				continue;
			}
			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid == ($prevcid + 1)) {
					if ($width == $prevwidth) {
						if ($width == $range[$rangeid][0]) {
							$range[$rangeid][] = $width;
						}
						else {
							array_pop($range[$rangeid]);
							// new range
							$rangeid = $prevcid;
							$range[$rangeid] = array();
							$range[$rangeid][] = $prevwidth;
							$range[$rangeid][] = $width;
						}
						$interval = true;
						$range[$rangeid]['interval'] = true;
					}
					else {
						if ($interval) {
							// new range
							$rangeid = $cid;
							$range[$rangeid] = array();
							$range[$rangeid][] = $width;
						}
						else { $range[$rangeid][] = $width; }
						$interval = false;
					}
				}
				else {
					$rangeid = $cid;
					$range[$rangeid] = array();
					$range[$rangeid][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$prevk = -1;
		$nextk = -1;
		$prevint = false;
		foreach ( $range as $k => $ws ) {
			$cws = count($ws);
			if ( ( $k == $nextk ) && ( !$prevint ) && ( ( !isset( $ws['interval'] ) ) || ( $cws < 4 ) ) ) {
				if ( isset( $range[$k]['interval'] ) ) {
					unset( $range[$k]['interval'] );
				}
				$range[$prevk] = array_merge( $range[$prevk], $range[$k] );
				unset( $range[$k] );
			}
			else {
				$prevk = $k;
			}

			$nextk = $k + $cws;

			if ( isset( $ws['interval'] ) ) {
				if ( $cws > 3 ) {
					$prevint = true;
				}
				else {
					$prevint = false;
				}
				unset( $range[$k]['interval'] );
				--$nextk;
			}
			else {
				$prevint = false;
			}
		}

		$w = '';
		foreach ( $range as $k => $ws ) {
			if ( count( array_count_values( $ws ) ) == 1 ) {
				$w .= ' '.$k.' '.($k + count($ws) - 1).' '.$ws[0];
			}
			else {
				$w .= ' '.$k.' [ '.implode(' ', $ws).' ]' . "\n";
			}
		}
		$this->_out('/W ['.$w.' ]');
	}

	function _putimages() {
		foreach(array_keys($this->images) as $file) {
			$this->_putimage($this->images[$file]);
			unset($this->images[$file]['data']);
			unset($this->images[$file]['smask']);
		}
	}

	function _putimage(&$info) {
		$this->_newobj();
		$info['n'] = $this->nObjectNumber;
		$this->_out('<</Type /XObject');
		$this->_out('/Subtype /Image');
		$this->_out('/Width '.$info['w']);
		$this->_out('/Height '.$info['h']);
		if($info['cs']=='Indexed') {
			$this->_out( '/ColorSpace [/Indexed /DeviceRGB ' . ( strlen( $info['pal'] ) / 3 - 1 ) . ' ' . ( $this->nObjectNumber + 1 ) . ' 0 R]' );
		}
		else {
			$this->_out('/ColorSpace /'.$info['cs']);
			if($info['cs']=='DeviceCMYK')
				$this->_out('/Decode [1 0 1 0 1 0 1 0]');
		}
		$this->_out('/BitsPerComponent '.$info['bpc']);
		if(isset($info['f'])) {
			$this->_out( '/Filter /' . $info['f'] );
		}
		if(isset($info['dp'])) {
			$this->_out( '/DecodeParms <<' . $info['dp'] . '>>' );
		}
		if(isset($info['trns']) && is_array($info['trns'])) {
			$trns = '';
			for($i=0;$i<count($info['trns']);$i++)
				$trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
			$this->_out('/Mask ['.$trns.']');
		}
		if(isset($info['smask'])) {
			$this->_out( '/SMask ' . ( $this->nObjectNumber + 1 ) . ' 0 R' );
		}
		$this->_out('/Length '.strlen($info['data']).'>>');
		$this->_putstream($info['data']);
		$this->_out('endobj');
		// Soft mask
		if(isset($info['smask'])) {
			$dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
			$smask = array('w'=>$info['w'], 'h'=>$info['h'], 'cs'=>'DeviceGray', 'bpc'=>8, 'f'=>$info['f'], 'dp'=>$dp, 'data'=>$info['smask']);
			$this->_putimage($smask);
		}
		// Palette
		if($info['cs']=='Indexed') {
			$filter = ($this->bCompress) ? '/Filter /FlateDecode ' : '';
			$pal = ($this->bCompress) ? gzcompress($info['pal']) : $info['pal'];
			$this->_newobj();
			$this->_out('<<'.$filter.'/Length '.strlen($pal).'>>');
			$this->_putstream($pal);
			$this->_out('endobj');
		}
	}

	/**
	 * @return FlexPdf
	 */
	function _putxobjectdict(): FlexPdf {
		foreach($this->images as $image) {
			$this->_out( '/I' . $image['i'] . ' ' . $image['n'] . ' 0 R' );
		}
		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	function _putresourcedict(): FlexPdf {
		$this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
		$this->_out('/Font <<');
		foreach($this->fonts as $font) {
			$this->_out('/F'.$font['i'].' '.$font['n'].' 0 R');
		}
		$this->_out('>>');
		$this->_out('/XObject <<');
		$this->_putxobjectdict();
		$this->_out('>>');

		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	private function _putresources(): FlexPdf {
		$this->_putfonts();
		$this->_putimages();
		// Resource dictionary
		$this->aObjectOffsets[2] = strlen($this->sBuffer);
		$this
			->_out('2 0 obj')
			->_out('<<')
			->_putresourcedict()
			->_out('>>')
			->_out('endobj');

		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	private function _putinfo(): FlexPdf {
		$this->_out( '/Producer '.$this->_textstring( 'FlexPdf' ) );

		if ( !empty( $this->title ) ) {
			$this->_out( '/Title ' . $this->_textstring( $this->title ) );
		}
		if ( !empty( $this->subject ) ) {
			$this->_out( '/Subject '.$this->_textstring( $this->subject ) );
		}
		if ( !empty( $this->author ) ) {
			$this->_out( '/Author ' . $this->_textstring( $this->author ) );
		}
		if ( !empty( $this->keywords ) ) {
			$this->_out( '/Keywords ' . $this->_textstring( $this->keywords ) );
		}
		if ( !empty( $this->creator ) ) {
			$this->_out( '/Creator ' . $this->_textstring( $this->creator ) );
		}
		$this->_out( '/CreationDate '.$this->_textstring( 'D:'.@date( 'YmdHis' ) ) );

		return $this;
	}

	/**
	 * @return FlexPdf
	 */
	private function writeCatalog(): FlexPdf {
		$this->_out( '/Type /Catalog' );
		$this->_out( '/Pages 1 0 R' );

		if ( $this->ZoomMode == 'fullpage' ) {
			$this->_out( '/OpenAction [3 0 R /Fit]' );
		}
		else if ( $this->ZoomMode == 'fullwidth' ) {
			$this->_out( '/OpenAction [3 0 R /FitH null]' );
		}
		else if ( $this->ZoomMode == 'real' ) {
			$this->_out( '/OpenAction [3 0 R /XYZ null null 1]' );
		}
		else if ( !is_string( $this->ZoomMode ) ) {
			$this->_out( '/OpenAction [3 0 R /XYZ null null ' . sprintf( '%.2F', $this->ZoomMode / 100 ) . ']' );
		}

		if ( $this->LayoutMode == 'single' ) {
			$this->_out( '/PageLayout /SinglePage' );
		}
		else if ( $this->LayoutMode=='continuous' ) {
			$this->_out( '/PageLayout /OneColumn' );
		}
		else if ( $this->LayoutMode=='two') {
			$this->_out( '/PageLayout /TwoColumnLeft' );
		}
		return $this;
	}

	private function _putheader() {
		$this->_out( '%PDF-'.$this->PDFVersion );
		return $this;
	}

	private function _puttrailer() {
		$this
			->_out( 'trailer')
			->_out( '<<' )
			->_out( '/Size '.($this->nObjectNumber+1) )
			->_out( '/Root '.$this->nObjectNumber.' 0 R' )
			->_out( '/Info '.($this->nObjectNumber-1).' 0 R' )
			->_out( '>>' );
		return $this;
	}

	private function _enddoc() {
		$this->_putheader();
		$this->_putpages();
		$this->_putresources();
		// Info
		$this->_newobj()
			->_out('<<')
			->_putinfo()
			->_out('>>')
			->_out('endobj');

		// Catalog
		$this->_newobj()
			->_out('<<')
			->writeCatalog()
			->_out('>>')
			->_out('endobj');

		// Cross-ref
		$o = strlen($this->sBuffer);
		$this
			->_out( 'xref' )
			->_out( '0 '.($this->nObjectNumber+1) )
			->_out( '0000000000 65535 f ' );
		for ( $i = 1; $i <= $this->nObjectNumber; $i++ ) {
			$this->_out( sprintf( '%010d 00000 n ', $this->aObjectOffsets[$i] ) );
		}

		// Trailer
		$this
			->_puttrailer()
			->_out( 'startxref')
			->_out( ''.$o )
			->_out('%%EOF');

		$this->sDocState = 3;

		return $this;
	}

	// Converts UTF-8 strings to UTF16-BE.
	private function UTF8ToUTF16BE( $str, bool $setbom = true ): string {
		$outstr = '';
		if ( $setbom ) {
			$outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
		}
		$outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
		return $outstr;
	}

	// Converts UTF-8 strings to codepoints array
	private function UTF8StringToArray( string $str ): array {
		$out = array();
		$len = strlen( $str );
		for ($i = 0; $i < $len; $i++) {
			$uni = -1;
			$h = ord($str[$i]);
			if ( $h <= 0x7F ) {
				$uni = $h;
			}
			else if ( $h >= 0xC2 ) {
				if ( ($h <= 0xDF) && ($i < $len -1) ) {
					$uni = ( $h & 0x1F ) << 6 | ( ord( $str[++$i] ) & 0x3F );
				}
				else if ( ($h <= 0xEF) && ($i < $len -2) ) {
					$uni = ( $h & 0x0F ) << 12 | ( ord( $str[++$i] ) & 0x3F ) << 6
						| ( ord( $str[++$i] ) & 0x3F );
				}
				else if ( ($h <= 0xF4) && ($i < $len -3) ) {
					$uni = ( $h & 0x0F ) << 18 | ( ord( $str[++$i] ) & 0x3F ) << 12
						| ( ord( $str[++$i] ) & 0x3F ) << 6
						| ( ord( $str[++$i] ) & 0x3F );
				}
			}
			if ($uni >= 0) {
				$out[] = $uni;
			}
		}
		return $out;
	}
}