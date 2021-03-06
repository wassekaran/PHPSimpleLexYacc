<?php
/** Builder module of PhpSimpleLexCC
 *
 * @package   Builder
 * @author    Oliver Heins 
 * @copyright 2013 Oliver Heins <oheins@sopos.org>
 * @license   GNU Affero General Public License; either version 3 of the license, or any later version. See <http://www.gnu.org/licenses/agpl-3.0.html>
 */
namespace PHPSimpleLexYacc\Parser;

require_once("AbstractBuilder.php");
require_once("RuleLexingRules.php");
require_once("Token.php");
require_once("Helpers/ParserRules.php");
use PHPSimpleLexYacc\Parser\Helpers\ParserRules;

require_once("Generators/MethodGenerator.php");
require_once("Generators/PropertyGenerator.php");
require_once("Generators/ClassGenerator.php");
use PHPSimpleLexYacc\Parser\Generators\MethodGenerator;
use PHPSimpleLexYacc\Parser\Generators\PropertyGenerator;
use PHPSimpleLexYacc\Parser\Generators\ClassGenerator;

/** ParserBuilder class
 *
 * Builds the parser from a definiton.
 */
abstract class ParserBuilder extends AbstractBuilder
{
    /** Holds the parsing rules
     *
     * @var ParserRules
     */
    private $rules;

    /** The parent class of the parser definition, i.e. this class
     *
     * @var ReflectionClass
     */
    private $parent;

    /** The grandparent class of the parser definition
     *
     * The parent of this class, i.e. AbstractBuilder
     *
     * @var ReflectionClass
     */
    private $grandpa;

    /** The list of precedence/associativity rules
     *
     * Each key in the list is a symbol, and the value is a tuple the
     * associativity ('left' or 'right') and the precedence level.
     * The higher the precedence level, the lower its actual
     * precedence is.
     *
     * @var array
     */
    private $precedence = array();

    /** The list of complex points
     *
     * cp[ambiguous symbol] => [no of rule, pos]
     *
     * @var array
     */
    private $complexpoints;

    /** Sets the list of precedence/associativity rules
     *
     * Asserts that the parameter is a list of arrays, each containing
     * only strings.
     *
     * @param array $precedence    the precedence rules
     */
    protected function setPrecedence(array $precedence)
    {
	$this->precedence = array();
	$level = count($precedence);
	foreach ($precedence as $line) {
	    assert(is_array($line));
	    $associativity = null;
	    foreach ($line as $token) {
		assert(is_string($token));
		if ($associativity === null) {
		    assert($token == 'left' or $token == 'right');
		    $associativity = $token == 'left' ? 0 : 1;
		} else {
		    if (array_key_exists($token, $this->precedence)) {
			throw new \Exception('Duplicate precedence setting: ' . $token);
		    }
		    $this->precedence[$token] = array($level, $associativity);
		}
	    }
	    $level--;
	}
    }

    /** Constructor, empty for just being there
     *
     * Otherwise, the constructor of the ParserRules would be added.
     */
    public function __construct() {}

    /** Calculates the "complex points"
     * 
     * A complex point is a rule, at which a symbol occurs only on rhs, not on 
     * lhs.  At this point, not only reduction is possible, but also the removal
     * of abiguousity.  This method calculates the complex points for may be
     * ambiguous rules and stores them in as an array in 
     * ParserBuilder::complexpoints.
     * 
     * @return void
     * @see ParserBuilder::complexpoints
     */
    private function calculateComplexPoints()
    {
	$rules = $this->rules->getRules();

	// holds references to all ambiguous rules: no of rule => symbol
	$ambiguous = array();
	// table of ambiguous symbols: symbol => true
	$asymbols = array();

	// calculate ambiguous rules
	$i = 0;
	foreach ($rules as $rule) {
	    $lhs = $rule[0];
	    $rhs = $rule[1];
	    foreach ($rhs as $symbol) {
		if ($symbol == $lhs) {
		    // lhs is also in production rule
		    $ambiguous[$i] = $lhs;
		    $asymbols[$lhs] = true;
		}
	    }
	    $i++;
	}
	/* calculate complex points: a complex point is a rule, at
	   which a symbol occurs only on rhs, not on lhs
	
	   NOTE: evtl. ist es nötig zu prüfen, ob der complex point
	   selbst wieder eine mehrdeutige Produktionsregel ist --
	   ggf. muss dann eine Ebene höher gesprungen werden
	   (rekursiv) */
	$cp = array(); // $cp[symbol] => [no of rule, position]
	foreach ($asymbols as $symbol => $val) {
	    // rule number
	    $i = 0;
	    foreach ($rules as $rule) {
		$rhs = $rule[1];
		// symbol number
		$j = 0;
		foreach ($rhs as $s) {
		    // does symbol occur on right hand side of the, but not on lhs?
		    if ($symbol == $s && array_key_exists($i, $ambiguous) && $ambiguous[$i] != $s) {
			if (! array_key_exists($s, $cp)) {
			    $cp[$s] = array();
			}
			$cp[$s][] = array($i, $j);
		    }
		    $j++;
		}
		$i++;
	    }
	}
	$this->complexpoints = $cp;
    }

    /** Returns the generated Parser
     *
     * Checks if the parser definition was changed recently. If so, it
     * generates a new one before returning the parser.  Also builds
     * the class hierarchy.
     *
     * @access public
     * @param  string $parsername  The name of the parser (must be a valid classname)
     * @return AbstractParser      The generated parser
     */
    public function getParser($parsername)
    {
	return $this->getBuild("Parser", $parsername);
    }
    
    /** Wrapper function for the concrete creator
     *
     * Calls the concrete creator method
     * 
     * @param string $name the filename of the parser
     * @return string The code of the created parser
     */
    protected function createBuild($name)
    {
	return $this->createParser($name);
    }

    /** Builds a new parser
     *
     * Called by createBuild().  Extracts the code, then builds a new
     * parser class file.
     *
     * @access private
     * @param  string $parsername    The name of the lexer (must be a valid classname).
     * @see    ParserBuilder::extractCode() for the actual parsing of the lexer definition.
     * @see    ParserBuilder::$reductionMethods for the stored parser reduction methods.
     * @see    ParserBuilder::$extraMethods     for the rest of the methods.
     * @return string            The code of the created parser.
     */
    protected function createParser($parsername)
    {
	$this->extractCode();

	ob_start();
	echo '<?php' . "\n" .
            'namespace PHPSimpleLexYacc\Parser;' . "\n\n" .
	    'require_once("AbstractParser.php");' . "\n\n";

	$class = new ClassGenerator(array('name' => $parsername,
					  'extension' => 'AbstractParser'));

	foreach ($this->properties as $prop) {
	    $class->addProperty($prop);
	}

	$constructcode = $this->rules->generateCode();
	$constructcode .= $this->generateComplexPointCode();
	$c = new MethodGenerator(array('name'   => '__construct',
				       'source' => $constructcode,
				       'body'   => $constructcode));

	$class->addMethod($c);

	foreach ($this->innerMethods as $name => $method) {
	    $class->addMethod($method);
	}

	foreach ($this->extraMethods as $name => $method) {
	    $class->addMethod($method);
	}

	echo $class->generateCode();
	$parser = ob_get_contents();
	ob_end_clean();
	return $parser;
    }

    /** Returns the code for setting the complex points
     * 
     * @return string
     * @see ParserBuilder::genValue()
     */
    private function generateComplexPointCode()
    {
	assert(is_array($this->complexpoints));
	$code = '$this->setComplexPoints(';
	$code .= $this->genValue($this->complexpoints);
	$code .= ');' . "\n";

	return $code;
    }

    /** Generic method to generate code from data structures
     * 
     * According to the type of $value, a data structure is turned into php 
     * code.  Works with boolean, double, float, integer and recursively on 
     * arrays.  Fails with objects and resources.
     * 
     * @param mixed $value
     * @return string
     * @throws \Exception
     */
    private function genValue($value)
    {
	// FIXME: This method is an exact duplicate of
	// PropertyGenerator::genValue($value)
	$type = gettype($value);
	switch ($type) {
	case 'array':
	    // look if the array is a 'flat' array, i.e. keys are from
	    // 0..len(array)-1.  This enables a more concise notation,
	    // but might be expensive.
	    //
	    // The idea is to create a flat array, and compare its
	    // keys with the keys of the original.  If there's no
	    // difference, the array is a flat one.
	    $flat = false;
	    $flatarray = array_values($value);
	    if (count(array_diff(array_keys($value), array_keys($flatarray))) == 0) {
		$flat = true;
	    }
	    $result = array();
	    foreach ($value as $key => $subval) {
		if ($flat == true) {
		    $result[] = $this->genValue($subval);
		} else {
		    $result[] = $this->genValue($key) . ' => ' . $this->genValue($subval);
		}
	    }
	    return 'array('. implode(', ', $result) . ')';
	case 'boolean':
	    return $value ? 'true' : 'false';
	case 'double':
	case 'float':
	case 'integer':
	    return $value;
	case 'string':
	    $result = '';
	    while (strlen($value) > 0) {
		$char = substr($value, 0, 1);
		$value = substr($value, 1);
		if ($char == "'") {
		    $char = "\\'";
		} elseif ($char == '\\') {
		    $char = '\\\\';
		}
		$result .= $char;
	    }
	    return "'" . $result . "'";
	case 'object':
	case 'resource':
	    throw new \Exception(ucfirst($type) . "s are not (yet) implemented.  Don't use them now, but file a bug report if you really need them.");
	case 'unknown type':
	    throw new \Exception($type . ' is not a valid type, check your source.');
	default:
	    throw new \Exception('This should not happen, consider this a bug: type '. $type . ' is unknown, but should be known. :(');
	}
    }

    /** Main method to extract the code of the concrete ParserBuilder class
     * 
     * First, sets this class and its parent class, AbstractBuilder, as not 
     * interesting.  Then gets all methods and but only keeps the interesting, 
     * which are processed.  Calls calculateComplexPoints(), and gets the
     * interesting properties, which are processed.
     * 
     * @see ParserBuilder::getInteresting(), ParserBuilder::processMethods(), ParserBuilder::processProperties(), ParserBuilder::calculateComplexPoints()
     * @return void
     */
    protected function extractCode()
    {
	// Setting up Reflecting Class and Base Class
	// We have two base classes (LexerBuilder and AbstractBuilder), 
	// which need to be excluded.  So $grandpa is our base to start 
	// reflecting.
	$object = new \ReflectionObject($this);
	$this->parent = $object->getParentClass();
	while ($ancestor = $this->parent->getParentClass()) {
	    $this->grandpa = $this->parent;
	    $this->parent = $ancestor;
	}

	// see what methods are "interesting", i.e. are defined by the user
	$objectMethods = $object->getMethods();
	$interesting = $this->getInteresting($objectMethods);
	$this->processMethods($interesting);

	// we need to compute the "complex points", points at which
	// from ambiguous parse trees can be chosen the best one
	$this->calculateComplexPoints();

	// now look for "interesting" properties
	$objectProperties = $object->getProperties();
	$defaultProperties = $object->getDefaultProperties();
	$interesting = $this->getInteresting($objectProperties);
	$this->processProperties($interesting, $defaultProperties);
    }

    /** Checks which methods/properties are interesting
     * 
     * Checks whether a method/property is already defined in parent or 
     * grandparent (i.e. this class or its parent class, AbstractBuilder).
     * If not, it is considered interesting.
     * 
     * @param array $members
     * @return array list of interesting methods/properties
     * @throws \Exception
     * @see ParserBuilder::parent, ParserBuilder::grandpa
     */
    private function getInteresting(array $members)
    {
	$interesting = array();
	foreach ($members as $member) {
	    $name = $member->getName();
	    if ($member instanceof \ReflectionProperty) {
		$has = 'hasProperty';
	    } elseif ($member instanceof \ReflectionMethod) {
		$has = 'hasMethod';
	    } else {
		throw new \Exception('Member neither instance of ReflectionProperty nor of ReflectionMethod.  This should never happen!');
	    }

	    if ($this->grandpa->$has($name) or $this->parent->$has($name)) { 
		// we are only interested in methods of the child
		// classes (those defined by the user)
		continue;
	    }
	    $interesting[] = $member;
	}
	return $interesting;
    }

    /** Adds a list of properties to the main property list
     * 
     * Adds a list of properties and their corresponding values to the main 
     * properties list.
     * 
     * @see PHPSimpleLexYacc\Parser\Generators\ProperyGenerator::__construct()
     * @param array $interesting
     * @param array $default
     * @return void
     */
    private function processProperties($interesting, $default)
    {
	foreach ($interesting as $prop) {
	    $name     = $prop->getName();
	    if (array_key_exists($name, $default)) {
		$value = $default[$name];
	    } else {
		$value = null;
	    }
	    $this->properties[] = new PropertyGenerator(array('name' => $name,
							      'reflection' => $prop,
							      'static' => $prop->isStatic(),
							      'public' => $prop->isPublic(),
							      'protected' => $prop->isProtected(),
							      'private' => $prop->isPrivate(),
							      'value' => $value));
	}
    }

    /** Adds a list of properties to the main property list
     * 
     * Extracts the relevant portion from the source file.  If the method has 
     * the form of /p_[a-zA-Z]+/, it is considered an inner method, i.e. a 
     * method containing a rule.  The rule is extracted then, and the method 
     * added to the innerMethods list.  Otherwise, the method is added to the
     * extraMethods list.
     * 
     * @see PHPSimpleLexYacc\Parser\Generators\MethodGenerator::_construct(), ParserBuilder::extractBody(), ParserBuilder::addRules(), ParserBuilder::innerMethods, ParserBuilder::extraMethods
     * @param array $interesting
     * @return void
     */
    private function processMethods($interesting)
    {
	$this->rules = new ParserRules();

	$filename = '';
	foreach ($interesting as $method) {
	    $methodName = $method->getName();
	    $visibility = "protected";
	    if ($method->isPrivate()) {
                $visibility = "private";
            } elseif ($method->isPublic()) {
                $visibility = "public";
            }
//	    $static   = $method->isStatic()   ? 'static'   : '';
//	    $final    = $method->isFinal()    ? 'final'    : '';
//	    $abstract = $method->isAbstract() ? 'abstract' : '';
	    $filename_ = $method->getFileName();
	    if ($filename_ != $filename) {
		// only read file if method is another file
		$filename = $filename_;
		$file = file($filename);
	    }
	    $startline = $method->getStartLine() - 1;
	    $endline   = $method->getEndLine();
	    $numLines  = $endline - $startline + 1;
	    $methodSource = array_slice($file, $startline, $numLines);
	    array_walk($methodSource, function(&$line) {
		    $line = trim($line);
		});
	    $methodSource = implode("\n", $methodSource);

//	    $classname = $method->getDeclaringClass()->getName();
//	    $classhierarchy = $this->getLevelForClass($classname);

	    $m = new MethodGenerator(array('name'       => $methodName,
					   'source'     => $methodSource,
					   'parameters' => $method->getParameters(),
					   'reflection' => $method,
					   'docstring'  => $method->getDocComment()));
	    $m->extractBody();

	    if (preg_match('/p_([a-zA-Z]+)/', $methodName)) {
		$result = $this->extractRules($m->getBody());
		$body  = $result[0];
		$rules = $result[1];
		$m->setBody($body);
		$this->addRules($rules, $methodName);
		$this->innerMethods[$methodName] = $m;
	    } else {
		$this->extraMethods[$methodName] = $m;
	    }
	}
    }

   /** Adds the rules to the rule table
    * 
    * Lexes the rules, then parses it with an ad hoc parser.  Care of 
    * precedence rules is taken Finally adds them to the rule table.
    * 
    * @see PHPSimpleLexYacc\Parser\RuleLexingRules::__construct(), ParserBuilder::precedence, ParserBuilder::rules
    * @param string $string
    * @param string $methodName
    * @throws \Exception
    * @return void
    */
    private function addRules($string, $methodName)
    {
	assert(is_string($string) && is_string($methodName));
	$r = new RuleLexingRules();
	$lexer = $r->getLexer('ParserRuleLexer');
	$lexer->setData($string);
	$lexer->lex();
	$tokens = $lexer->getTokens();

        // Parse the rules
        $state = 0; // 0: set LHS, 1: set RHS
	$rhs = array();
	$precedence = array();
	$i = 0; // counter for the rules
	foreach ($tokens as $token) {
	    assert($token instanceof Token);
	    switch ($state) {
	    case 0:
		$precedence[$i] = array(0, 0);
		switch ($token->getType()) {
		case 'SYMBOL':
		    if (isset($lhs)) {
			throw new \Exception("Error in parser rule definition: LHS symbol defined twice:\n" .
					    "pos " .$token->getPosition(). ", line ". $token->getLinenumber());
		    }
		    $lhs = $token->getValue();
		    break;
		case 'COLON':
		    if (!isset($lhs)) {
			throw new \Exception("Error in parser rule definition: LHS symbol not defined:\n" .
					    "pos " .$token->getPosition(). ", line ". $token->getLinenumber());
		    }
		    $state = 1;
		    $rhs[0] = array();
		    break;
		default:
		    throw new \Exception("Error in parser rule definition: unknown symbol:\n" .
					"pos " .$token->getPosition(). ", line ". $token->getLinenumber());
		}
		break;
	    case 1:
		switch ($token->getType()) {
		case 'SYMBOL':
		    $value = $token->getValue();
		    $rhs[$i][] = $value;
		    if (array_key_exists($value, $this->precedence)) {
			$precedence[$i] = $this->precedence[$value];
		    }
		    break;
		case 'CHAR':
		    $value = $token->getValue();
		    $rhs[$i][] = $value;
		    if (array_key_exists($value, $this->precedence)) {
			$precedence[$i] = $this->precedence[$value];
		    }
		    break;
		case 'BAR':
		    if (count($rhs[$i]) == 0) { // no rhs defined yet
			throw new \Exception("Error in parser rule definition: no rhs defined:\n" .
					    "pos " .$token->getPosition(). ", line ". $token->getLinenumber());
		    }
		    $i++;
		    $precedence[$i] = array(0, 0);
		    $rhs[$i] = array();
		    break;
		default:
		    throw new \Exception("Error in parser rule definition: unknown symbol:\n" .
					"pos " .$token->getPosition(). ", line ". $token->getLinenumber());

		}
		break;
	    default:
		throw new \Exception("This should never happen.  Error in ParserBuilder::parseRules()");
	    }
	}
	foreach ($rhs as $i => $rule) {
	    $this->rules->addRule($lhs, $rule, $methodName, $precedence[$i][0], $precedence[$i][1]);
	}
    }

    /** Extracts the Rules out of a method definition
     * 
     * @param string $source
     * @return array Holds two strings: the stripped off body and the rules
     * @throws \Exception
     */
    private function extractRules($source)
    {
	// Extract the regexp
	$needle = '/^\s*((?:\'[^\']+\')|(?:"[^"]+"))\h*;\s*/';
	$found = preg_match($needle, $source, $matches);
	if ($found) {
	    $line = $matches[0];
	    $rules = substr($matches[1], 1, -1);
	    $body = str_replace($line, '', $source);
	} elseif ($found === false) {
	    throw new \Exception("Fatal regexp error");
	} else {
            throw new \Exception('No Rule found!');
	}
	return array($body, $rules);
    }


}