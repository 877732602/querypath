<?php
/**
 * Tests for the QueryPath library.
 *
 * @package Tests
 * @author M Butcher <matt@aleph-null.tv>
 * @license The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

use \QueryPath\QueryPath as QP;

/** */
require_once 'PHPUnit/Framework.php';
require_once 'src/QueryPath/QueryPath.php';

define('DATA_FILE', 'test/data.xml');
define('DATA_HTML_FILE', 'test/data.html');
define('NO_WRITE_FILE', 'test/no-write.xml');
define('MEDIUM_FILE', 'test/amplify.xml');

/**
 * Tests for DOM Query. Primarily, this is focused on the DomQueryImpl
 * class which is exposed through the DomQuery interface and the dq() 
 * factory function.
 */
class QueryPathTest extends PHPUnit_Framework_TestCase {
  
  public function testQueryPathConstructors() {
    
    // From XML file
    $file = DATA_FILE;
    $qp = qp($file);
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // From XML file with context
    $cxt = stream_context_create();
    $qp = qp($file, NULL, array('context' => $cxt));
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // From XML string
    $str = '<?xml version="1.0" ?><root><inner/></root>';
    $qp = qp($str);
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // From SimpleXML
    $str = '<?xml version="1.0" ?><root><inner/></root>';    
    $qp = qp(simplexml_load_string($str));
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // Test from DOMDocument
    $doc = new DOMDocument('1.0');
    $qp = qp($doc->loadXML($str));
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // Now with a selector:
    $qp = qp($file, '#head');
    $this->assertEquals(1, count($qp->get()));
    $this->assertEquals($qp->get(0)->tagName, 'head');
    
    // Test HTML:
    $htmlFile = DATA_HTML_FILE;
    $qp = qp($htmlFile);
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // Test with another QueryPath
    $qp = qp($qp);
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
    
    // Test from array of DOMNodes
    $array = $qp->get();
    $qp = qp($array);
    $this->assertEquals(1, count($qp->get()));
    $this->assertTrue($qp->get(0) instanceof DOMNode);
  }
  
  public function testOptionXMLEncoding() {
    $xml = qp(NULL, NULL, array('encoding' => 'iso-8859-1'))->append('<test/>')->xml();
    $iso_found = preg_match('/iso-8859-1/', $xml) == 1;
    
    $this->assertTrue($iso_found, 'Encoding should be iso-8859-1 in ' . $xml . 'Found ' . $iso_found);
    
    $iso_found = preg_match('/utf-8/', $xml) == 1;
    $this->assertFalse($iso_found, 'Encoding should not be utf-8 in ' . $xml);
    
    $xml = qp('<?xml version="1.0" encoding="utf-8"?><test/>', NULL, array('encoding' => 'iso-8859-1'))->xml();
    $iso_found = preg_match('/utf-8/', $xml) == 1;
    $this->assertTrue($iso_found, 'Encoding should be utf-8 in ' . $xml);
    
    $iso_found = preg_match('/iso-8859-1/', $xml) == 1;
    $this->assertFalse($iso_found, 'Encoding should not be utf-8 in ' . $xml);
    
  }
  
  public function testQPAbstractFactory() {
    $options = array('QueryPath_class' => 'QueryPathExtended');
    $qp = qp(NULL, NULL, $options);
    $this->assertTrue($qp instanceof QueryPathExtended, 'Is instance of extending class.');
    $this->assertTrue($qp->foonator(), 'Has special foonator() function.');
  }
  
  public function testQPAbstractFactoryIterating() {
    $xml = '<?xml version="1.0"?><l><i/><i/><i/><i/><i/></l>';
    $options = array('QueryPath_class' => 'QueryPathExtended');
    foreach(qp($xml, 'i', $options) as $item) {
      $this->assertTrue($item instanceof QueryPathExtended, 'Is instance of extending class.');
    }
    
  }
  
  /**
   * @expectedException QueryPathException
   */
  public function testFailedCall() {
    // This should hit __call() and then fail.
    qp()->fooMethod();
  }
  
  /**
   * @expectedException QueryPathException
   */
  public function testFailedObjectConstruction() {
    qp(new stdClass());
  }
  
  /**
   * @expectedException QueryPathParseException
   */
  public function testFailedHTTPLoad() {
    try {
      qp('http://localhost:8877/no_such_file.xml');
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  
  /**
   * @expectedException QueryPathParseException
   */
  public function testFailedHTTPLoadWithContext() {
    try {
      qp('http://localhost:8877/no_such_file.xml', NULL, array('foo' => 'bar'));
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  
  /**
   * @expectedException QueryPathParseException
   */
  public function testFailedParseHTMLElement() {
    try {
      qp('<foo>&foonator;</foo>', NULL);
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }

  /**
   * @expectedException QueryPathParseException
   */
  public function testFailedParseXMLElement() {
    try {
      qp('<?xml version="1.0"?><foo><bar>foonator;</foo>', NULL);
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  public function testIgnoreParserWarnings() {
    $qp = @qp('<html><body><b><i>BAD!</b></i></body>', NULL, array('ignore_parser_warnings' => TRUE));
    $this->assertTrue(strpos($qp->html(), '<i>BAD!</i>') !== FALSE);
    
    \QueryPath\Options::merge(array('ignore_parser_warnings' => TRUE));
    $qp = @qp('<html><body><b><i>BAD!</b></i></body>');
    $this->assertTrue(strpos($qp->html(), '<i>BAD!</i>') !== FALSE);
    
    $qp = @qp('<html><body><blarg>BAD!</blarg></body>');
    $this->assertTrue(strpos($qp->html(), '<blarg>BAD!</blarg>') !== FALSE, $qp->html());
    \QueryPath\Options::set(array()); // Reset to empty options.
  }
  /**
   * @expectedException QueryPathParseException
   */  
  public function testFailedParseNonMarkup() {
    try {
      qp('<23dfadf', NULL);
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  
  /**
   * @expectedException QueryPathParseException
   */
  public function testFailedParseEntity() {
    try {
      qp('<?xml version="1.0"?><foo>&foonator;</foo>', NULL);
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  
  public function testReplaceEntitiesOption() {
    $path = '<?xml version="1.0"?><root/>';
    $xml = qp($path, NULL, array('replace_entities' => TRUE))->xml('<foo>&</foo>')->xml();
    $this->assertTrue(strpos($xml, '<foo>&amp;</foo>') !== FALSE);
    
    $xml = qp($path, NULL, array('replace_entities' => TRUE))->html('<foo>&</foo>')->xml();
    $this->assertTrue(strpos($xml, '<foo>&amp;</foo>') !== FALSE);
    
    $xml = qp($path, NULL, array('replace_entities' => TRUE))->xhtml('<foo>&</foo>')->xml();
    $this->assertTrue(strpos($xml, '<foo>&amp;</foo>') !== FALSE);
    
    \QueryPath\Options::set(array('replace_entities' => TRUE));
    $this->assertTrue(strpos($xml, '<foo>&amp;</foo>') !== FALSE);
    \QueryPath\Options::set(array());
  }
  
  public function testFind() {
    $file = DATA_FILE;
    $qp = qp($file)->find('#head');
    $this->assertEquals(1, count($qp->get()));
    $this->assertEquals($qp->get(0)->tagName, 'head');
    
    $this->assertEquals('inner', qp($file)->find('.innerClass')->tag());
  }
  
  public function testTop() {
    $file = DATA_FILE;
    $qp = qp($file)->find('li');
    $this->assertGreaterThan(2, $qp->size());
    $this->assertEquals(1, $qp->top()->size());
    
    // Added for QP 2.0
    $xml = '<?xml version="1.0"?><root><u><l/><l/><l/></u><u/></root>';
    $qp = qp($xml, 'l');
    $this->assertEquals(3, $qp->size());
    $this->assertEquals(2, $qp->top('u')->size());
  }
  
  public function testAttr() {
    $file = DATA_FILE;
    
    $qp = qp($file)->find('#head');
    $this->assertEquals(1, $qp->size());
    $this->assertEquals($qp->get(0)->getAttribute('id'), $qp->attr('id'));
    
    $qp->attr('foo', 'bar');
    $this->assertEquals('bar', $qp->attr('foo'));
    
    $qp->attr(array('foo2' => 'bar', 'foo3' => 'baz'));
    $this->assertEquals('baz', $qp->attr('foo3'));
    
    // Check magic nodeType attribute:
    $this->assertEquals(XML_ELEMENT_NODE, qp($file)->find('#head')->attr('nodeType'));
    
  }
  
  public function testHasAttr() {
    $xml = '<?xml version="1.0"?><root><div foo="bar"/></root>';
    
    $this->assertFalse(qp($xml, 'root')->hasAttr('foo'));
    $this->assertTrue(qp($xml, 'div')->hasAttr('foo'));
    
    $xml = '<?xml version="1.0"?><root><div foo="bar"/><div foo="baz"></div></root>';
    $this->assertTrue(qp($xml, 'div')->hasAttr('foo'));
    
    $xml = '<?xml version="1.0"?><root><div bar="bar"/><div foo="baz"></div></root>';
    $this->assertFalse(qp($xml, 'div')->hasAttr('foo'));
    
    $xml = '<?xml version="1.0"?><root><div bar="bar"/><div bAZ="baz"></div></root>';
    $this->assertFalse(qp($xml, 'div')->hasAttr('foo'));
  }
  
  public function testVal() {
    $qp = qp('<?xml version="1.0"?><foo><bar value="test"/></foo>', 'bar');
    $this->assertEquals('test', $qp->val());
    
    $qp = qp('<?xml version="1.0"?><foo><bar/></foo>', 'bar')->val('test');
    $this->assertEquals('test', $qp->attr('value'));
  }
  
  public function testCss() {
    $file = DATA_FILE;
    $this->assertEquals('foo: bar', qp($file, 'unary')->css('foo', 'bar')->attr('style'));
    $this->assertEquals('foo: bar', qp($file, 'unary')->css('foo', 'bar')->css());
    $this->assertEquals('foo: bar', qp($file, 'unary')->css(array('foo' =>'bar'))->css()); 
  }
  
  public function testRemoveAttr() {
    $file = DATA_FILE;
    
    $qp = qp($file, 'inner')->removeAttr('class');
    $this->assertEquals(2, $qp->size());
    $this->assertFalse($qp->get(0)->hasAttribute('class'));
    
  }
  
  public function testEq() {
    $file = DATA_FILE;
    $qp = qp($file)->find('li')->eq(0);
    $this->assertEquals(1, $qp->size());
    $this->assertEquals($qp->attr('id'), 'one');
  }
  
  public function testIs() {
    $file = DATA_FILE;
    $this->assertTrue(qp($file)->find('#one')->is('#one'));
    $this->assertTrue(qp($file)->find('li')->is('#one'));
  }
  
  public function testIndex() {
    $xml = '<?xml version="1.0"?><foo><bar id="one"/><baz id="two"/></foo>';
    $qp = qp($xml, 'bar');
    $e1 = $qp->get(0);
    $this->assertEquals(0, $qp->find('bar')->index($e1));
    $this->assertFalse($qp->top()->find('#two')->index($e1));
  }
  
  public function testFilter() {
    $file = DATA_FILE;
    $this->assertEquals(1, qp($file)->filter('li')->size());
    $this->assertEquals(2, qp($file, 'inner')->filter('li')->size());
    $this->assertEquals('inner-two', qp($file, 'inner')->filter('li')->eq(1)->attr('id'));
  }
  
  public function testFilterLambda() {
    $file = DATA_FILE;
    // Get all evens:
    $l = 'return (($index + 1) % 2 == 0);';
    $this->assertEquals(2, qp($file, 'li')->filterLambda($l)->size());
  }
  
  public function filterCallbackFunction($index, $item) {
    return (($index + 1) % 2 == 0);
  }
  
  public function testFilterCallback() {
    $file = DATA_FILE;
    $cb = array($this, 'filterCallbackFunction');
    $this->assertEquals(2, qp($file, 'li')->filterCallback($cb)->size());
  }
  
  public function testFilterCallbackAnon() {
    // Test an anonymous function as a callback.
    $file = DATA_FILE;
    $cb = function ($index, $item) {return (($index + 1) % 2 == 0);};
    $this->assertEquals(2, qp($file, 'li')->filterCallback($cb)->size());
  }
  
  /**
   * @expectedException QueryPathException
   */
  public function testFailedFilterCallback() {
    $file = DATA_FILE;
    $cb = array($this, 'noSuchFunction');
    qp($file, 'li')->filterCallback($cb)->size();
  }

  /**
   * @expectedException QueryPathException
   */
  public function testFailedMapCallback() {
    $file = DATA_FILE;
    $cb = array($this, 'noSuchFunction');
    qp($file, 'li')->map($cb)->size();
  }

  
  public function testNot() {
    $file = DATA_FILE;
    
    // Test with selector
    $qp = qp($file, 'li:odd')->not('#one');
    $this->assertEquals(2, $qp->size());
    
    // Test with DOM Element
    $qp = qp($file, 'li');
    $el = $qp->branch()->find('#one')->get(0);
    $this->assertEquals(4, $qp->not($el)->size());
    
    // Test with array of DOM Elements
    $qp = qp($file, 'li');
    $arr = $qp->get();
    $this->assertEquals(count($arr), $qp->size());
    array_shift($arr);
    $this->assertEquals(1, $qp->not($arr)->size());
  }
  
  public function testSlice() {
    $file = DATA_FILE;
    // There are five <li> elements
    $qp = qp($file, 'li')->slice(1);
    $this->assertEquals(4, $qp->size());
    
    // The first item in the matches should be #two.
    $this->assertEquals('two', $qp->attr('id'));
    
    // THe last item should be #five
    $this->assertEquals('five', $qp->eq(3)->attr('id'));
    
    // This should not throw an error.
    $this->assertEquals(4, qp($file, 'li')->slice(1, 9)->size());
    
    $this->assertEquals(0, qp($file, 'li')->slice(9)->size());
    
    // The first item should be #two, the last #three
    $qp = qp($file, 'li')->slice(1, 2);
    $this->assertEquals(2, $qp->size());
    $this->assertEquals('two', $qp->attr('id'));
    $this->assertEquals('three', $qp->eq(1)->attr('id'));
  }
  
  public function mapCallbackFunction($index, $item) {
    if ($index == 1) {
      return FALSE;
    }
    if ($index == 2) {
      return array(1, 2, 3);
    }
    return $index;
  }
  
  public function testMap() {
    $file = DATA_FILE;
    $fn = 'mapCallbackFunction';
    $this->assertEquals(7, qp($file, 'li')->map(array($this, $fn))->size());
  }
  
  public function testMapAnon() {
    // Test using an anonymous map function.
    $file = DATA_FILE;
    $fn = function ($index, $item) {
      if ($index == 1) {
        return FALSE;
      }
      if ($index == 2) {
        return array(1, 2, 3);
      }
      return $index;
    };
    $this->assertEquals(7, qp($file, 'li')->map($fn)->size());
  }
  
  public function eachCallbackFunction($index, $item) {
    if ($index < 2) {
      qp($item)->attr('class', 'test');
    }
    else {
      return FALSE;
    }
  }
  
  public function testEach() {
    $file = DATA_FILE;
    $fn = 'eachCallbackFunction';
    $res = qp($file, 'li')->each(array($this, $fn));
    $this->assertEquals(5, $res->size());
    $this->assertFalse($res->get(4)->getAttribute('class') === NULL);
    $this->assertEquals('test', $res->eq(1)->attr('class'));
    
    // Test when each runs out of things to test before returning.
    $res = qp($file, '#one')->each(array($this, $fn));
    $this->assertEquals(1, $res->size());
  }
  
  public function testEachAnon() {
    // Test using anonymous functions in an each.
    $file = DATA_FILE;
    
    // Anonymous function.
    $fn = function ($index, $item) {
      if ($index < 2) {
        qp($item)->attr('class', 'test');
      }
      else {
        return FALSE;
      }
    };
    $res = qp($file, 'li')->each($fn);
    $this->assertEquals(5, $res->size());
    $this->assertFalse($res->get(4)->getAttribute('class') === NULL);
    $this->assertEquals('test', $res->eq(1)->attr('class'));
  }
  
  /**
   * @expectedException QueryPathException
   */
  public function testEachOnInvalidCallback() {
    $file = DATA_FILE;
    $fn = 'eachCallbackFunctionFake';
    $res = qp($file, 'li')->each(array($this, $fn));
  }
  
  public function testEachLambda() {
    $file = DATA_FILE;
    $fn = 'qp($item)->attr("class", "foo");';
    $res = qp($file, 'li')->eachLambda($fn);
    $this->assertEquals('foo', $res->eq(1)->attr('class'));
  }
  
  public function testDeepest() {
    $str = '<?xml version="1.0" ?>
    <root>
      <one/>
      <one><two/></one>
      <one><two><three/></two></one>
      <one><two><three><four/></three></two></one>
      <one/>
      <one><two><three><banana/></three></two></one>
    </root>';
    $deepest = qp($str)->deepest();
    $this->assertEquals(2, $deepest->size());
    $this->assertEquals('four', $deepest->get(0)->tagName);
    $this->assertEquals('banana', $deepest->get(1)->tagName);
    
    $deepest = qp($str, 'one')->deepest();
    $this->assertEquals(2, $deepest->size());
    $this->assertEquals('four', $deepest->get(0)->tagName);
    $this->assertEquals('banana', $deepest->get(1)->tagName);
    
    $str = '<?xml version="1.0" ?>
    <root>
      CDATA
    </root>';
    $this->assertEquals(1, qp($str)->deepest()->size());
  }
  
  public function testTag() {
    $file = DATA_FILE;
    $this->assertEquals('li', qp($file, 'li')->tag());
  }
  
  public function testAppend() {
    $file = DATA_FILE;
    $this->assertEquals(1, qp($file,'unary')->append('<test/>')->find(':root > unary > test')->size());
    $qp = qp($file,'#inner-one')->append('<li id="appended"/>');
    $this->assertEquals(1, $qp->find('#appended')->size());
    $this->assertNull($qp->get(0)->nextSibling);
    
    $this->assertEquals(2, qp($file, 'inner')->append('<test/>')->top()->find('test')->size());
    $this->assertEquals(2, qp($file, 'inner')->append(qp('<?xml version="1.0"?><test/>'))->top()->find('test')->size());
    
    // Issue #6: This seems to break on Debian Etch systems... no idea why.
    $this->assertEquals('test', qp()->append('<test/>')->top()->tag());
    
    // Issue #7: Failure issues warnings
    // This seems to be working as expected -- libxml emits
    // parse errors.
    //$this->assertEquals(NULL, qp()->append('<test'));
    
    // Test loading SimpleXML.
    $simp = simplexml_load_file($file);
    $qp = qp('<?xml version="1.0"?><foo/>')->append($simp);
    $this->assertEquals(1, $qp->find('root')->size());
    
    // Test with replace entities turned on:
    $qp = qp($file, 'root', array('replace_entities' => TRUE))->append('<p>&raquo;</p>');
    $this->assertEquals('<p>»</p>', $qp->find('p')->html());
    
    // Test with empty, mainly to make sure it doesn't explode.
    $this->assertTrue(qp($file)->append('') instanceof QueryPath);
  }
  
  /**
   * @expectedException QueryPathParseException
   */
  public function testAppendBadMarkup() {
    $file = DATA_FILE;
    try{
      qp($file, 'root')->append('<foo><bar></foo>');
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
  }
  
  /**
    * @expectedException QueryPathException
    */
   public function testAppendBadObject() {
     $file = DATA_FILE;
     try{
       qp($file, 'root')->append(new stdClass);
     }
     catch (Exception $e) {
       //print $e->getMessage();
       throw $e;
     }
   }
  
  public function testAppendTo() {
    $file = DATA_FILE;
    $dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
    $qp = qp($file,'li')->appendTo($dest);
    $this->assertEquals(5, $dest->find(':root li')->size());
  }
  
  public function testPrepend() {
    $file = DATA_FILE;
    $this->assertEquals(1, qp($file,'unary')->prepend('<test/>')->find(':root > unary > test')->size());
    $qp = qp($file,'#inner-one')->prepend('<li id="appended"/>')->find('#appended');
    $this->assertEquals(1, $qp->size());
    $this->assertNull($qp->get(0)->previousSibling);
    
    // Test repeated insert
    $this->assertEquals(2, qp($file,'inner')->prepend('<test/>')->top()->find('test')->size());
  }
  
  public function testPrependTo() {
    $file = DATA_FILE;
    $dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
    $qp = qp($file,'li')->prependTo($dest);
    $this->assertEquals(5, $dest->find(':root li')->size());
  }
  
  public function testBefore() {
    $file = DATA_FILE;
    $this->assertEquals(1, qp($file,'unary')->before('<test/>')->find(':root > head ~ test')->size());
    $this->assertEquals('unary', qp($file,'unary')->before('<test/>')->find(':root > test')->get(0)->nextSibling->tagName);
    
    // Test repeated insert
    $this->assertEquals(2, qp($file,'inner')->before('<test/>')->top()->find('test')->size());
  }
  
  public function testAfter() {
    $file = DATA_FILE;
    $this->assertEquals(1, qp($file,'unary')->after('<test/>')->find(':root > unary ~ test')->size());
    $this->assertEquals('unary', qp($file,'unary')->after('<test/>')->find(':root > test')->get(0)->previousSibling->tagName);
    
    $this->assertEquals(2, qp($file,'inner')->after('<test/>')->top()->find('test')->size());
    
  }
  
  public function testInsertBefore() {
    $file = DATA_FILE;
    $dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
    $qp = qp($file,'li')->insertBefore($dest);
    $this->assertEquals(5, $dest->find(':root > li')->size());
    $this->assertEquals('li', $dest->end()->find('dest')->get(0)->previousSibling->tagName);
  }
  public function testInsertAfter() {
    $file = DATA_FILE;
    $dest = qp('<?xml version="1.0"?><root><dest/></root>', 'dest');
    $qp = qp($file,'li')->insertAfter($dest);
    //print $dest->get(0)->ownerDocument->saveXML();
    $this->assertEquals(5, $dest->find(':root > li')->size());
  }
  public function testReplaceWith() {
    $file = DATA_FILE;
    $qp = qp($file,'unary')->replaceWith('<test><foo/></test>')->find(':root test');
    //print $qp->get(0)->ownerDocument->saveXML();
    $this->assertEquals(1, $qp->size());
  }
  
  public function testReplaceAll() {
    $qp1 = qp('<?xml version="1.0"?><root><l/><l/></root>');
    $doc = qp('<?xml version="1.0"?><bob><m/><m/></bob>')->get(0)->ownerDocument;
    
    $qp2 = $qp1->find('l')->replaceAll('m', $doc);
    
    $this->assertEquals(2, $qp2->top()->find('l')->size());
  }
  
  public function testWrap() {
    $file = DATA_FILE;
    $xml = qp($file,'unary')->wrap('');
    $this->assertTrue($xml instanceof QP);
    
    $xml = qp($file,'unary')->wrap('<test id="testWrap"></test>')->get(0)->ownerDocument->saveXML();
    $this->assertEquals(1, qp($xml, '#testWrap')->get(0)->childNodes->length);
    
    $xml = qp($file,'li')->wrap('<test class="testWrap"></test>')->get(0)->ownerDocument->saveXML();
    $this->assertEquals(5, qp($xml, '.testWrap')->size());
    
    $xml = qp($file,'li')->wrap('<test class="testWrap"><inside><center/></inside></test>')->get(0)->ownerDocument->saveXML();
    $this->assertEquals(5, qp($xml, '.testWrap > inside > center > li')->size());
  }
  
  public function testWrapAll() {
    $file = DATA_FILE;
    
    $xml = qp($file,'unary')->wrapAll('');
    $this->assertTrue($xml instanceof QP);
    
    $xml = qp($file,'unary')->wrapAll('<test id="testWrap"></test>')->get(0)->ownerDocument->saveXML();
    $this->assertEquals(1, qp($xml, '#testWrap')->get(0)->childNodes->length);
    
    $xml = qp($file,'li')->wrapAll('<test class="testWrap"><inside><center/></inside></test>')->get(0)->ownerDocument->saveXML();
    $this->assertEquals(5, qp($xml, '.testWrap > inside > center > li')->size());
    
  }
  
  public function testWrapInner() {
    $file = DATA_FILE;
    
    $this->assertTrue(qp($file,'#inner-one')->wrapInner('') instanceof QP);
    
    $xml = qp($file,'#inner-one')->wrapInner('<test class="testWrap"></test>')->get(0)->ownerDocument->saveXML();
    // FIXME: 9 includes text nodes. Should fix this.
    $this->assertEquals(9, qp($xml, '.testWrap')->get(0)->childNodes->length);
  }
  
  public function testRemove() {
    $file = DATA_FILE;
    $qp = qp($file, 'li');
    $start = $qp->size();
    $finish = $qp->remove()->size();
    $this->assertEquals($start, $finish);
    $this->assertEquals(0, $qp->find(':root li')->size());
  }
  
  public function testHasClass() {
    $file = DATA_FILE;
    $this->assertTrue(qp($file, '#inner-one')->hasClass('innerClass'));
    
    $file = DATA_FILE;
    $this->assertFalse(qp($file, '#inner-one')->hasClass('noSuchClass'));
  }
  
  public function testAddClass() {
    $file = DATA_FILE;
    $this->assertTrue(qp($file, '#inner-one')->addClass('testClass')->hasClass('testClass'));
  }
  public function testRemoveClass() {
    $file = DATA_FILE;
    // The add class tests to make sure that this works with multiple values.
    $this->assertFalse(qp($file, '#inner-one')->removeClass('innerClass')->hasClass('innerClass'));
    $this->assertTrue(qp($file, '#inner-one')->addClass('testClass')->removeClass('innerClass')->hasClass('testClass'));
  }
  
  public function testAdd() {
    $file = DATA_FILE;
    $this->assertEquals(7, qp($file, 'li')->add('inner')->size());
  }
  
  public function testEnd() {
    $file = DATA_FILE;
    $this->assertEquals(2, qp($file, 'inner')->find('li')->end()->size());
  }
  
  public function testAndSelf() {
    $file = DATA_FILE;
    $this->assertEquals(7, qp($file, 'inner')->find('li')->andSelf()->size());
  }
  
  public function testChildren() {
    $file = DATA_FILE;
    $this->assertEquals(5, qp($file, 'inner')->children()->size());
    $this->assertEquals(5, qp($file, 'inner')->children('li')->size());
    $this->assertEquals(1, qp($file, ':root')->children('unary')->size());
  }
  public function testRemoveChildren() {
    $file = DATA_FILE;
    $this->assertEquals(0, qp($file, '#inner-one')->removeChildren()->find('li')->size());
  }
  
  public function testContents() {
    $file = DATA_FILE;
    $this->assertGreaterThan(5, qp($file, 'inner')->contents()->size());
    // Two cdata nodes and one element node.
    $this->assertEquals(3, qp($file, '#inner-two')->contents()->size());
  }
  
  public function testSiblings() {
    $file = DATA_FILE;
    $this->assertEquals(3, qp($file, '#one')->siblings()->size());
    $this->assertEquals(2, qp($file, 'unary')->siblings('inner')->size());
  }
  
  public function testHTML() {
    $file = DATA_FILE;
    $qp = qp($file, 'unary');
    $html = '<b>test</b>';
    $this->assertEquals($html, $qp->html($html)->find('b')->html());
    
    $html = '<html><head><title>foo</title></head><body>bar</body></html>';
    // We expect a DocType to be prepended:
    $this->assertEquals('<!DOCTYPE', substr(qp($html)->html(), 0, 9));
    
    // Check that HTML is not added to empty finds. Note the # is for a special 
    // case.
    $this->assertEquals('', qp($html, '#nonexistant')->html('<p>Hello</p>')->html());
    $this->assertEquals('', qp($html, 'nonexistant')->html('<p>Hello</p>')->html());
    
    // We expect NULL if the document is empty.
    $this->assertNull(qp()->html());
    
    // Non-DOMNodes should not be rendered:
    $fn = 'mapCallbackFunction';
    $this->assertNull(qp($file, 'li')->map(array($this, $fn))->html());
  }
  
  public function testInnerHTML() {
    $html = '<html><head></head><body><div id="me">Test<p>Again</p></div></body></html>';
    
    $this->assertEquals('Test<p>Again</p>', qp($html,'#me')->innerHTML());
  }
  
  public function testInnerXML() {
    $html = '<?xml version="1.0"?><div id="me">Test<p>Again</p></div>';
    $test = 'Test<p>Again</p>';
    
    $this->assertEquals($test, qp($html,'#me')->innerHTML());
    
    $html = '<?xml version="1.0"?><div id="me">Test<p>Again<br/></p><![CDATA[Hello]]><?pi foo ?></div>';
    $test = 'Test<p>Again<br/></p><![CDATA[Hello]]><?pi foo ?>';
    
    $this->assertEquals($test, qp($html,'#me')->innerHTML());
    
    $html = '<?xml version="1.0"?><div id="me"/>';
    $test = '';
    $this->assertEquals($test, qp($html,'#me')->innerHTML());    
  }
  
  public function testInnerXHTML() {
    $html = '<?xml version="1.0"?><html><head></head><body><div id="me">Test<p>Again</p></div></body></html>';
    
    $this->assertEquals('Test<p>Again</p>', qp($html,'#me')->innerHTML());
  }
  
  public function testXML() {
    $file = DATA_FILE;
    $qp = qp($file, 'unary');
    $xml = '<b>test</b>';
    $this->assertEquals($xml, $qp->xml($xml)->find('b')->xml());
    
    $xml = '<html><head><title>foo</title></head><body>bar</body></html>';
    // We expect an XML declaration to be prepended:
    $this->assertEquals('<?xml', substr(qp($xml, 'html')->xml(), 0, 5));
    
    // We don't want an XM/L declaration if xml(TRUE).
    $xml = '<?xml version="1.0"?><foo/>';
    $this->assertFalse(strpos(qp($xml)->xml(TRUE), '<?xml'));
    
    // We expect NULL if the document is empty.
    $this->assertNull(qp()->xml());
    
    // Non-DOMNodes should not be rendered:
    $fn = 'mapCallbackFunction';
    $this->assertNull(qp($file, 'li')->map(array($this, $fn))->xml());
  }
  
  public function testXHTML() {
    $file = DATA_FILE;
    $qp = qp($file, 'unary');
    $xml = '<b>test</b>';
    $this->assertEquals($xml, $qp->xml($xml)->find('b')->xhtml());
    
    $xml = '<html><head><title>foo</title></head><body>bar</body></html>';
    // We expect an XML declaration to be prepended:
    $this->assertEquals('<?xml', substr(qp($xml, 'html')->xhtml(), 0, 5));
    
    // We don't want an XM/L declaration if xml(TRUE).
    $xml = '<?xml version="1.0"?><foo/>';
    $this->assertFalse(strpos(qp($xml)->xhtml(TRUE), '<?xml'));
    
    // We expect NULL if the document is empty.
    $this->assertNull(qp()->xhtml());
    
    // Non-DOMNodes should not be rendered:
    $fn = 'mapCallbackFunction';
    $this->assertNull(qp($file, 'li')->map(array($this, $fn))->xhtml());
  }
  
  public function testWriteXML() {
    $xml = '<?xml version="1.0"?><html><head><title>foo</title></head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeXML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect an XML declaration at the top.
    $this->assertEquals('<?xml', substr($out, 0, 5));
    
    $xml = '<?xml version="1.0"?><html><head><script>
    <!-- 
    1 < 2;
    -->
    </script>
    <![CDATA[This is CDATA]]>
    <title>foo</title></head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeXML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect an XML declaration at the top.
    $this->assertEquals('<?xml', substr($out, 0, 5));
    
    // Test writing to a file:
    $name = './' . __FUNCTION__ . '.xml';
    qp($xml)->writeXML($name);
    $this->assertTrue(file_exists($name));
    $this->assertTrue(qp($name) instanceof QP);
    unlink($name);
  }
  
  public function testWriteXHTML() {
    $xml = '<?xml version="1.0"?><html><head><title>foo</title></head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeXHTML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect an XML declaration at the top.
    $this->assertEquals('<?xml', substr($out, 0, 5));
    
    $xml = '<?xml version="1.0"?><html><head><script>
    <!-- 
    1 < 2;
    -->
    </script>
    <![CDATA[This is CDATA]]>
    <title>foo</title></head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeXHTML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect an XML declaration at the top.
    $this->assertEquals('<?xml', substr($out, 0, 5));
    
    // Test writing to a file:
    $name = './' . __FUNCTION__ . '.xml';
    qp($xml)->writeXHTML($name);
    $this->assertTrue(file_exists($name));
    $this->assertTrue(qp($name) instanceof QP);
    unlink($name);
  }
  
  /**
   * @expectedException QueryPathIOException
   */
  public function testFailWriteXML() {
    try {
      qp()->writeXML('./test/no-writing.xml');
    }
    catch (Exception $e) {
      //print $e->getMessage();
      throw $e;
    }
    
  }
  
  /**
   * @expectedException QueryPathIOException
   */
  public function testFailWriteXHTML() {
    try {
      qp()->writeXHTML('./test/no-writing.xml');
    }
    catch (\QueryPath\QueryPathIOException $e) {
      //print $e->getMessage();
      throw $e;
    }
    
  }
  
  /**
   * @expectedException QueryPathIOException
   */
  public function testFailWriteHTML() {
    try {
      qp('<?xml version="1.0"?><foo/>')->writeXML('./test/no-writing.xml');
    }
    catch (QueryPathIOException $e) {
      // print $e->getMessage();
      throw $e;
    }
    
  }
  
  public function testWriteHTML() {
    $xml = '<html><head><title>foo</title></head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeHTML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect a doctype declaration at the top.
    $this->assertEquals('<!DOC', substr($out, 0, 5));
    
    $xml = '<html><head><title>foo</title>
    <script><!--
    var foo = 1 < 5;
    --></script>
    </head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeHTML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect a doctype declaration at the top.
    $this->assertEquals('<!DOC', substr($out, 0, 5));
    
    $xml = '<html><head><title>foo</title>
    <script><![CDATA[
    var foo = 1 < 5;
    ]]></script>
    </head><body>bar</body></html>';
    
    if (!ob_start()) die ("Could not start OB.");
    qp($xml, 'tml')->writeHTML();
    $out = ob_get_contents();
    ob_end_clean();
    
    // We expect a doctype declaration at the top.
    $this->assertEquals('<!DOC', substr($out, 0, 5));
    
    // Test writing to a file:
    $name = './' . __FUNCTION__ . '.html';
    qp($xml)->writeXML($name);
    $this->assertTrue(file_exists($name));
    $this->assertTrue(qp($name) instanceof QP);
    unlink($name);
  }
  
  public function testText() {
    $xml = '<?xml version="1.0"?><root><div>Text A</div><div>Text B</div></root>';
    $this->assertEquals('Text AText B', qp($xml)->text());
    $this->assertEquals('Foo', qp($xml, 'div')->eq(0)->text('Foo')->text());
  }
  
  public function testTextImplode() {
    $xml = '<?xml version="1.0"?><root><div>Text A</div><div>Text B</div></root>';
    $this->assertEquals('Text A, Text B', qp($xml, 'div')->textImplode());
    $this->assertEquals('Text A--Text B', qp($xml, 'div')->textImplode('--'));
    
    $xml = '<?xml version="1.0"?><root><div>Text A </div><div>Text B</div></root>';
    $this->assertEquals('Text A , Text B', qp($xml, 'div')->textImplode());
    
    $xml = '<?xml version="1.0"?><root><div>Text A </div>
    <div>
    </div><div>Text B</div></root>';
    $this->assertEquals('Text A , Text B', qp($xml, 'div')->textImplode(', ', TRUE));
    
    // Test with empties
    $xml = '<?xml version="1.0"?><root><div>Text A</div><div> </div><div>Text B</div></root>';
    $this->assertEquals('Text A- -Text B', qp($xml, 'div')->textImplode('-', FALSE));
  }
  
  public function testNext() {
    $file = DATA_FILE;
    $this->assertEquals('inner', qp($file, 'unary')->next()->tag());
    $this->assertEquals('foot', qp($file, 'inner')->next()->eq(1)->tag());
    
    $this->assertEquals('foot', qp($file, 'unary')->next('foot')->tag());
  }
  public function testPrev() {
    $file = DATA_FILE;
    $this->assertEquals('head', qp($file, 'unary')->prev()->tag());
    $this->assertEquals('inner', qp($file, 'inner')->prev()->eq(1)->tag());
    $this->assertEquals('head', qp($file, 'foot')->prev('head')->tag());
  }
  public function testNextAll() {
    $file = DATA_FILE;
    $this->assertEquals(3, qp($file, '#one')->nextAll()->size());
    $this->assertEquals(2, qp($file, 'unary')->nextAll('inner')->size());
  }
  public function testPrevAll() {
    $file = DATA_FILE;
    $this->assertEquals(3, qp($file, '#four')->prevAll()->size());
    $this->assertEquals(2, qp($file, 'foot')->prevAll('inner')->size());
  }
  public function testPeers() {
    $file = DATA_FILE;
    $this->assertEquals(3, qp($file, '#two')->peers()->size());
    $this->assertEquals(2, qp($file, 'foot')->peers('inner')->size());
  }
  public function testParent() {
    $file = DATA_FILE;
    $this->assertEquals('root', qp($file, 'unary')->parent()->tag());
    $this->assertEquals('root', qp($file, 'li')->parent('root')->tag());
    $this->assertEquals(2, qp($file, 'li')->parent()->size());
  }
  public function testClosest() {
    $file = DATA_FILE;
    $this->assertEquals('root', qp($file, 'li')->parent('root')->tag());
    
    $xml = '<?xml version="1.0"?>
    <root>
      <a class="foo">
        <b/>
      </a>
      <b class="foo"/>
    </root>';
    $this->assertEquals(2, qp($xml, 'b')->closest('.foo')->size());
  }
  
  public function testParents() {
    $file = DATA_FILE;
    
    // Three: two inners and a root.
    $this->assertEquals(3, qp($file, 'li')->parents()->size());
    $this->assertEquals('root', qp($file, 'li')->parents('root')->tag());
  }
  
  public function testCloneAll() {
    $file = DATA_FILE;
    
    // Shallow test
    $qp = qp($file, 'unary');
    $one = $qp->get(0);
    $two = $qp->cloneAll()->get(0);
    $this->assertTrue($one !== $two);
    $this->assertEquals('unary', $two->tagName);
    
    // Deep test: make sure children are also cloned.
    $qp = qp($file, 'inner');
    $one = $qp->find('li')->get(0);
    $two = $qp->find(':root inner')->cloneAll()->find('li')->get(0);
    $this->assertTrue($one !== $two);
    $this->assertEquals('li', $two->tagName);
  }
  
  public function testBranch() {
    $qp = qp(QP::HTML_STUB);
    $branch = $qp->branch();
    $branch->find('title')->text('Title');
    $qp->find('body')->text('This is the body');
    
    $this->assertEquals($qp->top()->find('title')->text(), $branch->top()->find('title')->text());
    
    $qp = qp(QP::HTML_STUB);
    $branch = $qp->branch('title');
    $branch->find('title')->text('Title');
    $qp->find('body')->text('This is the body');
    $this->assertEquals($qp->top()->find('title')->text(), $branch->text());
  }
  
  public function testXpath() {
    $file = DATA_FILE;
    
    $this->assertEquals('head', qp($file)->xpath("//*[@id='head']")->tag());
  }
    
  public function test__clone() {
    $file = DATA_FILE;
    
    $qp = qp($file, 'inner:first');
    $qp2 = clone $qp;
    $this->assertFalse($qp === $qp2);
    $qp2->find('li')->attr('foo', 'bar');
    $this->assertEquals('', $qp->find('li')->attr('foo'));
    $this->assertEquals('bar', $qp2->attr('foo'));
  }
  
  public function testStub() {
    $this->assertEquals(1, qp(QP::HTML_STUB)->find('title')->size());
    $this->assertEquals(1, qp(QP::HTML_STUB)->find('title')->size());
  }
  
  public function testIterator() {
    
    $qp = qp(QP::HTML_STUB, 'body')->append('<li/><li/><li/><li/>');
    
    $this->assertEquals(4, $qp->find('li')->size());
    $i = 0;
    foreach ($qp as $li) {
      ++$i;
      $li->text('foo');
    }
    $this->assertEquals(4, $i);
    $this->assertEquals('foofoofoofoo', $qp->top()->find('li')->text());
  }
  
  public function testModeratelySizedDocument() {
    
    $this->assertEquals(1, qp(MEDIUM_FILE)->size());
    
    $contents = file_get_contents(MEDIUM_FILE);
    $this->assertEquals(1, qp($contents)->size());
  }
}

/**
 * Test the XMLish functions of QueryPath.
 *
 * This uses a testing harness, XMLishMock, to test
 * a protected method of QueryPath.
 */
class XMLishTest extends PHPUnit_Framework_TestCase {
  public function testXMLishMock() {
    $tests = array(
      'this/is/a/path' => FALSE,
      "this is just some plain\ntext with a line break." => FALSE,
      '2 > 1' => FALSE,
      '1 < 2' => FALSE,
      //'1 < 2 > 1' => FALSE,
      '<html/>' => TRUE,
      '<?xml version="1.0"?><root/>' => TRUE,
      '<tag/><tag/><tag/>' => TRUE, // It's not valid, but HTML parser will try it.
    );
    foreach ($tests as $test => $correct) {
      $mock = new XMLishMock();
      $this->assertEquals($correct, $mock->exposedIsXMLish($test), "Testing $test");
    }
  }
  
  public function testXMLishWithBrokenHTML() {
    $html = '<div id="qp-top"><div class=header>Abe H. Rosenbloom Field<br></div> <p> Located in a natural bowl north of 10th Avenue, Rosenbloom Field was made possible by a gift from Virginia Whitney Rosenbloom \'36 and Abe H. Rosenbloom \'34. The Pioneers observed the occasion of the field\'s dedication on Oct. 4, 1975, by defeating Carleton 36-26. Rosenbloom Field has a seating capacity of 1,500. <br> <br> A former member of the Grinnell Advisory Board and other college committees, Abe Rosenbloom played football at Grinnell from 1931 to 1933. He played guard and was one of the Missouri Valley Conference\'s smallest gridders (5\'6" and 170 pounds). He averaged more than 45 minutes a game playing time during a 24-game varsity career and was named to the Des Moines Register\'s all-Missouri Valley Conference squad in 1932 and 1933. <br> <br> On the south side of the field, a memorial recalls the 100th anniversary of the first intercollegiate football game played west of the Mississippi. The game took place on the Grinnell campus on Nov. 16, 1889. On the north side, a marker commemorates the first 50 years of football in the west, and recalls the same game, played in 1889, Grinnell College vs. the University of Iowa. Grinnell won, 24-0. </p></div>';
    $mock = new XMLishMock();
    $this->assertEquals(TRUE, $mock->exposedIsXMLish($html), "Testing broken HTML");
  }

}

/**
 * A testing class for XMLish tests.
 */
class XMLishMock extends \QueryPath\QueryPath {
  public function exposedIsXMLish($str) {
    return $this->isXMLish($str);
  }
}

/**
 * A simple mock for testing qp()'s abstract factory.
 */
class QueryPathExtended extends \QueryPath\QueryPath {
  public function foonator() {
    return TRUE;
  }
}