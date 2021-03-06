<?php

use Pun\Preg;
use Pun\Re8map;
use Pun\Recap8;
use Pun\Token8;
use Pun\Token8Stream;
use Pun\KeyTable;
use Pun\ValueList;
use Pun\Type;
use Pun\UStr8;
use Pun\IntList;
use Pun\Path;
use Pun\TomlReader;


date_default_timezone_set("Australia/Sydney");

/** A bunch of undisciplined adhoc tests
    of some things that went wrong sometime.
 */

class Lexer
{


    // register all the regular expressions that
    // might be used.  Not all of them all the time!
    const T_BAD = 0;
    const T_EQUAL = 1;
    const T_BOOLEAN = 2;
    const T_DATE_TIME = 3;
    const T_EOS = 4;
    const T_INTEGER = 5;
    const T_3_QUOTATION_MARK = 6;
    const T_QUOTATION_MARK = 7;
    const T_3_APOSTROPHE = 8;
    const T_APOSTROPHE = 9;
    const T_NEWLINE = 10;
    const T_SPACE = 11;
    const T_LEFT_SQUARE_BRACE = 12;
    const T_RIGHT_SQUARE_BRACE = 13;
    const T_LEFT_CURLY_BRACE = 14;
    const T_RIGHT_CURLY_BRACE = 15;
    const T_COMMA = 16;
    const T_DOT = 17;
    const T_UNQUOTED_KEY = 18;
    const T_ESCAPED_CHARACTER = 19;
    const T_ESCAPE = 20;
    const T_BASIC_UNESCAPED = 21;
    const T_FLOAT = 22;
    const T_FLOAT_EXP = 23;
    const T_HASH = 24;
    const T_LITERAL_STRING = 25;
    const T_IGNORE_COMMENT = 26;
    const T_ANY_VALUE = 27;
    const T_CHAR = 28;
    const T_LAST_TOKEN = 28; // for range values of  named token lookup

	static private $_AllRegExp;
    static private $_AllExpIds;

	static public function getAllRegex(): Re8map
    {
        if (empty(Lexer::$_AllRegExp)) {
            $kt = new Re8map();
            $kt->setPreg(Lexer::T_EQUAL, "^(=)");
            $kt->setPreg(Lexer::T_BOOLEAN, "^(true|false)");
            $kt->setPreg(Lexer::T_DATE_TIME, "(^\\d{4}-\\d{2}-\\d{2}(T\\d{2}:\\d{2}:\\d{2}(\\.\\d{6})?(Z|-\\d{2}:\\d{2})?)?)");
            $kt->setPreg(Lexer::T_FLOAT_EXP,"^([+-]?((\d_?)+([\.](\d_?)*)?)([eE][+-]?(\d_?)+))");
            $kt->setPreg(Lexer::T_FLOAT, "^([+-]?((\d_?)+([\.](\d_?)*)))");
            $kt->setPreg(Lexer::T_INTEGER, "^([+-]?(\\d_?)+)");
            $kt->setPreg(Lexer::T_3_QUOTATION_MARK, "^(\"\"\")");
            $kt->setPreg(Lexer::T_QUOTATION_MARK, "^(\")");
            $kt->setPreg(Lexer::T_3_APOSTROPHE, "^(\'\'\')");
            $kt->setPreg(Lexer::T_APOSTROPHE, "^(\')");
            $kt->setPreg(Lexer::T_HASH, "^(#)");
            $kt->setPreg(Lexer::T_SPACE, "^(\\h+)");
            $kt->setPreg(Lexer::T_LEFT_SQUARE_BRACE, "^(\\[)");
            $kt->setPreg(Lexer::T_RIGHT_SQUARE_BRACE, "^(\\])");
            $kt->setPreg(Lexer::T_LEFT_CURLY_BRACE, "^(\\{)");
            $kt->setPreg(Lexer::T_RIGHT_CURLY_BRACE, "^(\\})");
            $kt->setPreg(Lexer::T_COMMA, "^(,)");
            $kt->setPreg(Lexer::T_DOT, "^(\\.)");
            $kt->setPreg(Lexer::T_UNQUOTED_KEY, "^([-A-Z_a-z0-9]+)");
            $kt->setPreg(
                    Lexer::T_ESCAPED_CHARACTER, "^(\\\\(n|t|r|f|b|\\\"|\\\\|u[0-9A-Fa-f]{4,4}|U[0-9A-Fa-f]{8,8}))");
            // ESCAPE \ would also be caught by LITERAL_STRING
            $kt->setPreg(Lexer::T_ESCAPE, "^(\\\\)");
            // T_BASIC_UNESCAPED Leaves out " \    (0x22, 0x5C)
            $kt->setPreg(Lexer::T_BASIC_UNESCAPED, "^([^\\x{0}-\\x{19}\\x{22}\\x{5C}]+)");
            // Literal strings are 'WYSIWYG'
            // Single 'quote' (0x27) is separate fetch.
            $kt->setPreg(Lexer::T_LITERAL_STRING, "^([^\\x{0}-\\x{19}\\x{27}]+)");
            $kt->setPreg(Lexer::T_IGNORE_COMMENT, "^(\\V*)");
            $kt->setPreg(Lexer::T_ANY_VALUE, "^([^\\s\\]\\},]+)");

            Lexer::$_AllRegExp = $kt;
        }
        return Lexer::$_AllRegExp;
    }

    static public function getAllIds()
    {
        if (empty(Lexer::$_AllExpIds)) {
            Lexer::$_AllExpIds = [
                Lexer::T_EQUAL,
                Lexer::T_BOOLEAN,
                Lexer::T_DATE_TIME,
                Lexer::T_FLOAT_EXP,
                Lexer::T_FLOAT, Lexer::T_INTEGER,
                Lexer::T_3_QUOTATION_MARK, Lexer::T_QUOTATION_MARK,
                Lexer::T_3_APOSTROPHE, Lexer::T_APOSTROPHE,
                Lexer::T_HASH, Lexer::T_SPACE,
                Lexer::T_LEFT_SQUARE_BRACE, Lexer::T_RIGHT_SQUARE_BRACE,
                Lexer::T_LEFT_CURLY_BRACE, Lexer::T_RIGHT_CURLY_BRACE,
                Lexer::T_COMMA, Lexer::T_DOT, Lexer::T_UNQUOTED_KEY,
                Lexer::T_ESCAPED_CHARACTER, Lexer::T_ESCAPE,
                Lexer::T_BASIC_UNESCAPED, Lexer::T_LITERAL_STRING,
                Lexer::T_IGNORE_COMMENT, Lexer::T_ANY_VALUE
            ];
        }
        return Lexer::$_AllExpIds;
    }
    static private $_AllSingles;

    static public function getAllSingles(): array
    {
        if (empty(Lexer::$_AllSingles)) {
            $kt = [];
            $kt["="] = Lexer::T_EQUAL;
            $kt["["] = Lexer::T_LEFT_SQUARE_BRACE;
            $kt["]"] = Lexer::T_RIGHT_SQUARE_BRACE;
            $kt["."] = Lexer::T_DOT;
            $kt[","] = Lexer::T_COMMA;
            $kt["\""] = Lexer::T_QUOTATION_MARK;
            $kt["."] = Lexer::T_DOT;
            $kt["{"] = Lexer::T_LEFT_CURLY_BRACE;
            $kt["}"] = Lexer::T_RIGHT_CURLY_BRACE;
            $kt["'"] = Lexer::T_APOSTROPHE;
            $kt["#"] = Lexer::T_HASH;
            $kt["\\"] = Lexer::T_ESCAPE;
            $kt[" "] = Lexer::T_SPACE;
            $kt["\t"] = Lexer::T_SPACE;
            // maybe these are not necessary
            $kt["\f"] = Lexer::T_SPACE;
            $kt["\b"] = Lexer::T_SPACE;
            Lexer::$_AllSingles = $kt;
        }
        return Lexer::$_AllSingles;
    }
}



function show($result) {
	$ct = count($result);
	if ($ct > 0) {
		for($i = 0; $i < $ct; $i++) {
			echo $i . ": " . $result[$i] . PHP_EOL;
		}
	}
	else {
		echo "No captures returned" . PHP_EOL;
	}
}

function testToken($test,$id) {
	$map = Lexer::getAllRegex();
	$pun = new UStr8($test);
	$cap = new Recap8();
	$ids = Lexer::getAllIds();
    $intlist = new IntList($ids);

    $match = $map->firstMatch($pun, $cap, $intlist);

	if ($id !== $match) {
		echo "**** id: " . $match . PHP_EOL;
		show($cap);
		return false;
	}
	else if ($match === Lexer::T_ESCAPED_CHARACTER) {
		echo "______ id: " . $match . PHP_EOL;
		show($cap);
	}

	return true;
}

function testMatch() {
    $tests = [
        ["title", Lexer::T_UNQUOTED_KEY],
        ["2.5", Lexer::T_FLOAT],
        ["9_224_617.445_991_228_313", Lexer::T_FLOAT],
        ["-2.5", Lexer::T_FLOAT],
        ["5e+22", Lexer::T_FLOAT_EXP],
        ["1e1_000", Lexer::T_FLOAT_EXP],
        ["6.626e-34", Lexer::T_FLOAT_EXP],
        ["1.", Lexer::T_FLOAT],
        ["25",  Lexer::T_INTEGER],
        ["-25",  Lexer::T_INTEGER],
        ["2_5",  Lexer::T_INTEGER],
        ["25",  Lexer::T_INTEGER],
        ["25",  Lexer::T_INTEGER],
        ["25",  Lexer::T_INTEGER],
        ["1987-07-05T17:45Z",Lexer::T_DATE_TIME],
        ["\\u03B4",Lexer::T_ESCAPED_CHARACTER],
    ];

    foreach($tests as $idx => $test)
    {
        if (!testToken($test[0], $test[1]))
        {
            echo "Match Error " . $test[0] . PHP_EOL;
        }
    }
}
function keytable() {
    $kt = new KeyTable();

    $kt->set("key1", "value1");
    $kt->set("2.1", "value2");
    echo "exists..";
    if ($kt->exists("key1"))
    {
        echo "pass" . PHP_EOL;
    }
    else {
        echo "fail" . PHP_EOL;
    }

    // dynamic properties
    $kt->MyProperty1 = "Set Property";
    if (isset($kt->MyProperty1)) {
        echo "MyProperty set to " . $kt->MyProperty1 . PHP_EOL;
    }
    else {
        echo "MyProperty not set " . PHP_EOL;
    }
    $kt->MyProperty1 = null;
    

     if (isset($kt->MyProperty1)) {
        echo "MyProperty set to " . $kt->MyProperty1 . PHP_EOL;
    }
    else {
        echo "MyProperty not set " . PHP_EOL;
    }
        $kt->MyProperty1 = "Another Value";

    $kt["100.1"] = 1000;
    $kt[100] = 1111;

    $a = $kt->toArray();


    // merge something
    $m = new KeyTable();
    $m->set("modules", ["a" => ['name' => 'a'], "b" => ['name' => 'b']]);
    $kt->merge($m);

    echo "merge " . print_r($kt->toArray(),true) . PHP_EOL;

}

function valuelist() {
    $kt = new KeyTable();
    $list = new ValueList();

    $ptype = new Type();
    $ptype->fromValue($kt);

    $list->pushBack($kt);

    echo "Type is " . $ptype->name() . PHP_EOL;

    $a = $list->toArray();
    echo "ValueList " . print_r($a,true) . PHP_EOL;

    $list->clear();
    for($i = 0; $i <= 10; $i++) {
        $list->pushBack($i);
    }
    foreach($list as $idx => $val) {
        echo "list " . $idx . ": " . $val . PHP_EOL;
    }
}
function routine() {


    $ts = new Token8Stream();

    $map = Lexer::getAllRegex();
    $ts->setRe8map($map);
    $ts->setExpSet( [Lexer::T_SPACE,
            Lexer::T_UNQUOTED_KEY,
            Lexer::T_INTEGER]  );
    $ts->setInput("t = true");
    $ts->setSingles(Lexer::getAllSingles());

    $id = $ts->moveNextId();
    gc_collect_cycles();
    echo "ID is " . $id . PHP_EOL;
    echo "Value is " . $ts->getValue() . PHP_EOL;
    echo "Tail is " . $ts->beforeEOL() . PHP_EOL;
}

function reader_0() {
    $rdr = new TomlReader();
}

function testmix()
{
    return <<<toml
    t = true
    f = false
    [table]
    string = "Escape Me \\u03B4"
toml;
}

function reader()
{
    $a = [ 't' => true, 'f' => false];
    echo "Target result " . print_r($a) . PHP_EOL;
    $rdr = new TomlReader();

    $input = testmix();

    $result = $rdr->parse($input);

    echo "TOML Reader " . print_r($result->toArray()) . PHP_EOL;
}

function match_integer() {
    $pun = new Pun8("42");
    $map = Lexer::getAllRegex();
    $pun->setRe8map($map);
    $caps = new Recap8();
    $pun->matchMapId(Lexer::T_INTEGER, $caps);
    $ct = $caps->count();
    for($i = 0; $i < $ct; $i++)
    {
        echo "Capture " . $caps->getCap($i);
    }

}

function parser() {
    $parser = new TomlReader();

    try {
        $parser->parse("ints = [1,2,3]");
        $parser->parse(" x = _42");
    }
    catch(Exception $ex) {
        echo $ex->getMessage();
    }
    $input = "Zebra = 'stripes'\nLeopard = 'spots'\nMan = 'naked'\nArmidillo = 'scales'";

    $kt = $parser->parse($input);
    echo print_r($kt->toArray(),true) . PHP_EOL;
    $isTraversable = ($kt instanceOf \Traversable) ? 1 : 0;

    echo "KeyTable is " . $isTraversable . PHP_EOL;
    foreach($kt as $key => $value) {
        echo $key . " : " . $value . PHP_EOL;
    }
}


function utf16() {
    $pun = new Pun8(testmix());
    $filename = "TestUTF16-TextToml.toml";
    $output = $pun->bomUTF16() . $pun->asUTF16();
    echo "Output " . $filename . PHP_EOL;

    file_put_contents($filename, $output);

    $parser = new TomlReader();
    $readback = $parser->parse(file_get_contents($filename));
    foreach($readback as $key => $value) {
        echo $key . " : " . $value . PHP_EOL;
    }
}

function serial() {
    $kt = new KeyTable();
    $key = "One Key variable";
    $kt->set($key , 1);

    $kt->set("Time now", new DateTime());

    $kt2 = new KeyTable();

    $kt2->set("Referenced key value", 2);
    $kt->set("Nested", $kt2);

    $valone = new ValueList();


    for($i = 0; $i < 5; $i++) {
        $valtwo = new ValueList();
        $valone->pushBack($valtwo);
        $valtwo->pushBack($i);
        $valtwo->pushBack($i*2);
    }
    $kt2->set("Arrays", $valone);

    echo "As Original " . print_r( $kt->toArray(), true) . PHP_EOL;
    $fname = "serial_test1.dat";
    file_put_contents($fname, serialize($kt));

    $kt = unserialize(file_get_contents($fname));


    echo "Serial to Array " . print_r( $kt->toArray(), true) . PHP_EOL;
    if (!defined('BIG_CONST'))
    {
        define('BIG_CONST', "Hello");
    }

    $kt["defined"] = 'BIG_CONST is ${BIG_CONST}';
    $dlist = get_defined_constants();

    $kt->replaceVars($dlist);

    echo "defined is " . $kt["defined"] . PHP_EOL;

}

function ustr()
{
    $test = new UStr8("Tester");
    $test->setRange(4,6);
    echo "UStr8 : " . $test->value() . PHP_EOL;


    $u8 = new UStr8(testmix());
    $filename = "TestUTF16-TextToml.toml";
    $output = $u8->bomUTF16() . $u8->asUTF16();
    echo "Output " . $filename . PHP_EOL;

    file_put_contents($filename, $output);

    $parser = new TomlReader();
    $readback = $parser->parse(file_get_contents($filename));
    foreach($readback as $key => $value) {
        echo $key . " : " . $value . PHP_EOL;
    }
    $u8->setString("\\Path\\To\\The\\Sustainable\\Future");
    $path = $u8->replaceAll("\\","/");
    echo "Replaced " . $path . PHP_EOL;
    if (!$path->endsWith("/"))
    {
        $path->pushBack("/");
    }
    echo "Added " . $path . PHP_EOL;
    if ($path->endsWith("/"))
    {
        $path->popBack(1);
    }
    echo "Removed " . $path . PHP_EOL;
}

function allMatches() {
    $u = new UStr8("\${HELLO} \${WORLD} \${DEFINE}");

    $preg = new Preg(1, '\${(\w+)}');

    $caps = $preg->matchAll($u);
    echo "Matches for " . $u . PHP_EOL;
    if (count($caps) > 0) {
        foreach($caps as $idx => $cap) {
            echo $idx . ": " . PHP_EOL;
            $ct = count($cap);
            for($i=0; $i < $ct; $i++)
            {
                echo "  " . $i . ":" . $cap[$i] . PHP_EOL;
            }
        }
    }
}

function testPath()
{
    echo "Separator = " . Path::sep() . PHP_EOL;
    $pn = new UStr8("/UnixPath/End");
    $a = Path::endSep($pn);
    echo "End " . $a . PHP_EOL;

    $py = new UStr8("/UnixPath/NoEnd/");
    $b = Path::noEndSep($py);
    echo "No End "  . $b . PHP_EOL;
    
    echo "Class " . get_class($a) . " " . get_class($b) . PHP_EOL;
}

testPath();
keytable();
valuelist();
reader_0();
routine();
parser();
ustr();
allMatches();
serial();

testMatch();

$memInc = 0.0;
$i = 0;
$startMem = $endMem = 0;
$memInit = memory_get_usage();

for ($i = 0 ; $i < 2; $i++)
{
    if ($i == 1) {
        $startMem = memory_get_usage();
    }
    reader();
}
gc_collect_cycles();
$endMem = memory_get_usage();
$memInc +=  ($endMem - $startMem);
echo "*** Memory Inc  = " . $memInc . PHP_EOL;