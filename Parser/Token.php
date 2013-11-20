<?php
/** Lexer module of PhpSimpleLexCC
 *
 * @package   Lexer
 * @author    Oliver Heins 
 * @copyright 2013 Oliver Heins <oheins@sopos.org>
 * @license GNU Affero General Public License; either version 3 of the license, or any later version. See <http://www.gnu.org/licenses/agpl-3.0.html>
 */
namespace PHPSimpleLexYacc\Parser;

/** Token class
 * 
 * Tokens are the atomic objects in parsing, generated by the lexer.
 */
class Token
{
    /** The type of the token
     *
     * @var string
     * @see Token::getType(), Token::setType()
     */
    public $type;
    
    /** The lexing rule associated with the token
     *
     * @var string
     * @see Token::getRule(), Token::setRule()
     */
    public $rule;
    
    /** The value associated with the token
     *
     * @var mixed
     * @see Token::getValue(), Token::setValue()
     */
    public $value;
    
    /** The position of the token in the input stream
     *
     * @var int
     * @see Token::getPosition(), Token::setPosition()
     */
    public $position;
    
    /** The line in which the token occures in the input stream
     *
     * Note: does not get set automatically.
     * 
     * @var int
     * @see Token::getLinenumber(), Token::setLinenumber()
     */
    public $linenumber;

    /** Constructor
     * 
     * @param array $t A key=>value list of the parameters
     * @see Token::setType(), Token::setRule(), Token::setValue(), Token::setPosition(), Token::setLinenumber()
     * @return void
     * @throws \Exception
     */
     public function __construct(array $t)
    {
	foreach ($t as $key => $value) {
	   switch ($key) {
	   case 'type':
	   case 'rule':
	   case 'value':
	   case 'position':
	   case 'linenumber':
	       $this->$key = $value;
	       break;
	   default:
	       throw new \Exception('Token has no property named ' . $key);
	   }
	}
    }

    /** Sets the type of the token
     * 
     * @param string $t
     * @return void
     * @see Token::getType(), Token::type
     */
    public function setType($t) 
    {
	assert(is_string($t) and $t != '');
	$this->type = $t;
    }

    /** Returns the type of the token
     * 
     * @return string
     * @see Token::setType(), Token::type
     */
    public function getType() 
    {
	$t = $this->type;
	assert(is_string($t) and $t != '');
	return $t;
    }

    /** Sets the tokens rule
     * 
     * @param string $r
     * @return void
     * @see Token::getRule(), Token::rule
     */
    public function setRule($r) 
    {
	assert(is_string($r) and $r != '');
	$this->rule = $r;
    }

    /** Returns the rule associated with the token
     * 
     * @return string
     * @see Token::setRule(), Token::rule
     */
    public function getRule() 
    {
	$r = $this->rule;
	assert(is_string($r) and $r != '');
	return $r;
    }

    /** Sets the value associated with the token
     * 
     * @param mixed $v
     * @return void
     * @see Token::getValue(), Token::value
     */
    public function setValue($v) 
    {
	$this->value = $v;
    }

    /** Returns the value associated with the token
     * 
     * @return mixed
     * @see Token::setValue(), Token::value
     */
    public function getValue() 
    {
	$v = $this->value;
	return $v;
    }

    /** Sets the position of the token in the input stream
     * 
     * @param int $p
     * @return void
     * @see Token::getPosition(), Token::position
     */
    public function setPosition($p) 
    {
	assert(is_int($p) and $p >= 0);
	$this->position = $p;
    }

    /** Returns the position of the token in the input stream
     * 
     * @return int
     * @see Token::setPosition(), Token::position
     */
    public function getPosition() 
    {
	$p = $this->position;
	assert(is_int($p) and $p >= 0);
	return $p;
    }

    /** Sets the linenumber of the token
     * 
     * @param int $l
     * @return void
     * @see Token::getLinenumber(), Token::linenumber
     */
    public function setLinenumber($l)
    {
	assert(is_int($l) and $l >= 0);
	$this->linenumber = $l;
    }

    /** Returns the linenumber of the token
     * 
     * @return int
     * @see Token::setLinenumber(), Token::linenumber
     */
    public function getLinenumber() {
	$l = $this->linenumber;
	assert(is_int($l) and $l >= 0);
	return $l;
    }
}