<?php

// !!!! Do not edit this File with TextMate. Saving will corrupt one Unicode character (U+1D11E).

require_once(dirname(__FILE__).'/../CSSParser.php');

class CSSParserTest extends PHPUnit_Framework_TestCase {
	function testCssFiles() {
		
		$sDirectory = dirname(__FILE__).DIRECTORY_SEPARATOR.'files';
		if($rHandle = opendir($sDirectory)) {
			/* This is the correct way to loop over the directory. */
			while (false !== ($sFileName = readdir($rHandle))) {
				if(strpos($sFileName, '.') === 0) {
					continue;
				}
				if(strrpos($sFileName, '.css') !== strlen($sFileName)-strlen('.css')) {
					continue;
				}
				if(strpos($sFileName, '-') === 0) {
					//Either a file which SHOULD fail or a future test of a as-of-now missing feature
					continue;
				}
				$file = $sDirectory.DIRECTORY_SEPARATOR.$sFileName;
				$oParser = new CSSParser();
        $oDoc = $oParser->parseString($file);
				try {
					$this->assertNotEquals('', $oDoc->__toString());
				} catch(Exception $e) {
					$this->fail($e);
				}
			}
			closedir($rHandle);
		}
	}
	
	/**
	* @depends testCssFiles
	*/
	function testColorParsing() {
		$oDoc = $this->parsedStructureForFile('colortest');
    $this->assertSame(
      "#mine {color: rgb(255,0,0);border-color: rgba(10,100,230,0.3);border-left-color: rgb(128,204,26);outline-color: rgb(34,34,34);background-color: rgb(35,35,35);}#yours {background-color: rgb(255,255,255);color: notacolor;outline-color: rgba(0,0,0,0);border-color: rgb(255,0,255);}",
      $oDoc->__toString()
    );
    foreach($oDoc->getAllDeclarationBlocks() as $oDeclaration)
    {
      foreach($oDeclaration->getRules() as $oRule)
      {
        foreach($oRule->getValues() as $aValues)
        {
          if($aValues[0] instanceof CSSColor)
          {
            $aValues[0]->toHSL();
          }  
        }
      }
    }
    $this->assertSame(
      "#mine {color: hsl(0,100%,50%);border-color: hsla(215,92%,47%,0.3);border-left-color: hsl(86,77%,45%);outline-color: hsl(0,0%,13%);background-color: hsl(0,0%,14%);}#yours {background-color: hsl(0,0%,100%);color: notacolor;outline-color: hsla(0,0%,0%,0);border-color: hsl(300,100%,50%);}",
      $oDoc->__toString()
    );
	}
	
	function testUnicodeParsing() {
		$oDoc = $this->parsedStructureForFile('unicode');
		foreach($oDoc->getAllDeclarationBlocks() as $oRuleSet) {
			$sSelector = $oRuleSet->getSelectors();
			$sSelector = $sSelector[0]->getSelector();
			if(substr($sSelector, 0, strlen('.test-')) !== '.test-') {
				continue;
			}
			$aContentRules = $oRuleSet->getRules('content');
			$aContents = $aContentRules['content']->getValues();
			$sCssString = $aContents[0][0]->__toString();
			if($sSelector == '.test-1') {
				$this->assertSame('" "', $sCssString);
			}
			if($sSelector == '.test-2') {
				$this->assertSame('"é"', $sCssString);
			}
			if($sSelector == '.test-3') {
				$this->assertSame('" "', $sCssString);
			}
			if($sSelector == '.test-4') {
				$this->assertSame('"𝄞"', $sCssString);
			}
			if($sSelector == '.test-5') {
				$this->assertSame('"水"', $sCssString);
			}
			if($sSelector == '.test-6') {
				$this->assertSame('"¥"', $sCssString);
			}
			if($sSelector == '.test-7') {
				$this->assertSame('"\A"', $sCssString);
			}
			if($sSelector == '.test-8') {
				$this->assertSame('"\"\""', $sCssString);
			}
			if($sSelector == '.test-9') {
				$this->assertSame('"\"\\\'"', $sCssString);
			}
			if($sSelector == '.test-10') {
				$this->assertSame('"\\\'\\\\"', $sCssString);
			}
			if($sSelector == '.test-11') {
				$this->assertSame('"test"', $sCssString);
			}
		}
	}

	function testSpecificity() {
		$oDoc = $this->parsedStructureForFile('specificity');
		$oDeclarationBlock = $oDoc->getAllDeclarationBlocks();
		$oDeclarationBlock = $oDeclarationBlock[0];
		$aSelectors = $oDeclarationBlock->getSelectors();
		foreach($aSelectors as $oSelector) {
			switch($oSelector->getSelector()) {
				case "#test .help":
					$this->assertSame(110, $oSelector->getSpecificity());
				break;
				case "#file":
					$this->assertSame(100, $oSelector->getSpecificity());
				break;
				case ".help:hover":
					$this->assertSame(20, $oSelector->getSpecificity());
				break;
				case "ol li::before":
					$this->assertSame(3, $oSelector->getSpecificity());
				break;
				case "li.green":
					$this->assertSame(11, $oSelector->getSpecificity());
				break;
				default:
					$this->fail("specificity: untested selector ".$oSelector->getSelector());
			}
		}
		$this->assertEquals(array(new CSSSelector('#test .help', true)), $oDoc->getSelectorsBySpecificity('> 100'));
	}

	function testManipulation() {
		$oDoc = $this->parsedStructureForFile('atrules');
		$this->assertSame('@charset "utf-8";@font-face {font-family: "CrassRoots";src: url("../media/cr.ttf");}html, body {font-size: 1.6em;}', $oDoc->__toString());
		foreach($oDoc->getAllDeclarationBlocks() as $oBlock) {
			foreach($oBlock->getSelectors() as $oSelector) {
				//Loop over all selector parts (the comma-separated strings in a selector) and prepend the id
				$oSelector->setSelector('#my_id '.$oSelector->getSelector());
			}
		}
		$this->assertSame('@charset "utf-8";@font-face {font-family: "CrassRoots";src: url("../media/cr.ttf");}#my_id html, #my_id body {font-size: 1.6em;}', $oDoc->__toString());

		$oDoc = $this->parsedStructureForFile('values');
		$this->assertSame('#header {margin: 10px 2em 1cm 2%;font-family: Verdana,Helvetica,"Gill Sans",sans-serif;font-size: 10px;color: rgb(255,0,0) !important;}body {color: rgb(0,128,0);font: 75% "Lucida Grande","Trebuchet MS",Verdana,sans-serif;}', $oDoc->__toString());
		foreach($oDoc->getAllRuleSets() as $oRuleSet) {
			$oRuleSet->removeRule('font-');
		}
		$this->assertSame('#header {margin: 10px 2em 1cm 2%;color: rgb(255,0,0) !important;}body {color: rgb(0,128,0);}', $oDoc->__toString());
	}

	function testSlashedValues() {
		$oDoc = $this->parsedStructureForFile('slashed');
		$this->assertSame('.test {font: 12px/1.5 Verdana,Arial,sans-serif;border-radius: 5px 10px 5px 10px/10px 5px 10px 5px;}', $oDoc->__toString());
		foreach($oDoc->getAllValues(null) as $mValue) {
			if($mValue instanceof CSSSize && $mValue->isSize() && !$mValue->isRelative()) {
				$mValue->setSize($mValue->getSize()*3);
			}
		}
		foreach($oDoc->getAllDeclarationBlocks() as $oBlock) {
			$oRule = $oBlock->getRules('font');
			$oRule = $oRule['font'];
			$oSpaceList = $oRule->getValue();
			$this->assertEquals(' ', $oSpaceList->getListSeparator());
			$oSlashList = $oSpaceList->getListComponents();
			$oCommaList = $oSlashList[1];
			$oSlashList = $oSlashList[0];
			$this->assertEquals(',', $oCommaList->getListSeparator());
			$this->assertEquals('/', $oSlashList->getListSeparator());
			$oRule = $oBlock->getRules('border-radius');
			$oRule = $oRule['border-radius'];
			$oSlashList = $oRule->getValue();
			$this->assertEquals('/', $oSlashList->getListSeparator());
			$oSpaceList1 = $oSlashList->getListComponents();
			$oSpaceList2 = $oSpaceList1[1];
			$oSpaceList1 = $oSpaceList1[0];
			$this->assertEquals(' ', $oSpaceList1->getListSeparator());
			$this->assertEquals(' ', $oSpaceList2->getListSeparator());
		}
		$this->assertSame('.test {font: 36px/1.5 Verdana,Arial,sans-serif;border-radius: 15px 30px 15px 30px/30px 15px 30px 15px;}', $oDoc->__toString());
	}

	function testFunctionSyntax() {
		$oDoc = $this->parsedStructureForFile('functions');
		$sExpected = 'div.main {background-image: linear-gradient(rgb(0,0,0),rgb(255,255,255));}.collapser::before, .collapser::-moz-before, .collapser::-webkit-before {content: "»";font-size: 1.2em;margin-right: 0.2em;-moz-transition-property: -moz-transform;-moz-transition-duration: 0.2s;-moz-transform-origin: center 60%;}.collapser.expanded::before, .collapser.expanded::-moz-before, .collapser.expanded::-webkit-before {-moz-transform: rotate(90deg);}.collapser + * {height: 0;overflow: hidden;-moz-transition-property: height;-moz-transition-duration: 0.3s;}.collapser.expanded + * {height: auto;}';
		$this->assertSame($sExpected, $oDoc->__toString());

		foreach($oDoc->getAllValues(null, true) as $mValue) {
			if($mValue instanceof CSSSize && $mValue->isSize()) {
				$mValue->setSize($mValue->getSize()*3);
			}
		}
		$sExpected = str_replace(array('1.2em', '0.2em', '60%'), array('3.6em', '0.6em', '180%'), $sExpected);
		$this->assertSame($sExpected, $oDoc->__toString());
		
		foreach($oDoc->getAllValues(null, true) as $mValue) {
			if($mValue instanceof CSSSize && !$mValue->isRelative() && !$mValue->isColorComponent()) {
				$mValue->setSize($mValue->getSize()*2);
			}
		}
		$sExpected = str_replace(array('0.2s', '0.3s', '90deg'), array('0.4s', '0.6s', '180deg'), $sExpected);
		$this->assertSame($sExpected, $oDoc->__toString());
	}

  function testExpandShorthands() {
		$oDoc = $this->parsedStructureForFile('expand-shorthands');
		$sExpected = 'body {font: italic 500 14px/1.618 "Trebuchet MS",Georgia,serif;border: 2px solid rgb(255,0,255);background: rgb(204,204,204) url("/images/foo.png") no-repeat left top;margin: 1em !important;padding: 2px 6px 3px;}';
		$this->assertSame($sExpected, $oDoc->__toString());
    $oDoc->expandShorthands();
    $sExpected = 'body {margin-top: 1em !important;margin-right: 1em !important;margin-bottom: 1em !important;margin-left: 1em !important;padding-top: 2px;padding-right: 6px;padding-bottom: 3px;padding-left: 6px;border-top-color: rgb(255,0,255);border-right-color: rgb(255,0,255);border-bottom-color: rgb(255,0,255);border-left-color: rgb(255,0,255);border-top-style: solid;border-right-style: solid;border-bottom-style: solid;border-left-style: solid;border-top-width: 2px;border-right-width: 2px;border-bottom-width: 2px;border-left-width: 2px;font-style: italic;font-variant: normal;font-weight: 500;font-size: 14px;line-height: 1.618;font-family: "Trebuchet MS",Georgia,serif;background-color: rgb(204,204,204);background-image: url("/images/foo.png");background-repeat: no-repeat;background-attachment: scroll;background-position: left top;}';
		$this->assertSame($sExpected, $oDoc->__toString());
  }
	
  function testCreateShorthands() {
		$oDoc = $this->parsedStructureForFile('create-shorthands');
		$sExpected = 'body {font-size: 2em;font-family: Helvetica,Arial,sans-serif;font-weight: bold;border-width: 2px;border-color: rgb(153,153,153);border-style: dotted;background-color: rgb(255,255,255);background-image: url("foobar.png");background-repeat: repeat-y;margin-top: 2px;margin-right: 3px;margin-bottom: 4px;margin-left: 5px;}';
		$this->assertSame($sExpected, $oDoc->__toString());
    $oDoc->createShorthands();
    $sExpected = 'body {background: rgb(255,255,255) url("foobar.png") repeat-y;margin: 2px 5px 4px 3px;border: 2px dotted rgb(153,153,153);font: bold 2em Helvetica,Arial,sans-serif;}';
		$this->assertSame($sExpected, $oDoc->__toString());
  }

	function parsedStructureForFile($sFileName) {
		$sFile = dirname(__FILE__).DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR."$sFileName.css";
		$oParser = new CSSParser();
		return $oParser->parseString(file_get_contents($sFile));
	}
}
