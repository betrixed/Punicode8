#include <phpcpp.h>
#include "ucode8.h"
#include "pun8.h"
#include "pcre8.h"
#include "re8map.h"

/**
* Iterate through a UTF-8 string,
* Keep an internal array index to beginning of
* next character, as a byte index
* Allow the internal array index to be set, or get
* holds a string as a PHP value object
*/


/**
 *  tell the compiler that the get_module is a pure C function
 */

/**
 *  Function that is called by PHP right after the PHP process
 *  has started, and that returns an address of an internal PHP
 *  strucure with all the details and features of your extension
 *
 *  @return void*   a pointer to an address that is understood by PHP
 */

extern "C" {

PHPCPP_EXPORT void *get_module() 
{
    // static(!) Php::Extension object that should stay in memory
    // for the entire duration of the process (that's why it's static)
    static Php::Extension extension("punicode", "1.0");
    
    // @todo    add your own functions, classes, namespaces to the extension
    Php::Class<Pun8> punic(Pun8::PHP_NAME.data());

    punic.method<&Pun8::__construct>("__construct");
    
    // using regular expression id map.
    punic.method<&Pun8::matchIdRex> ("matchIdRex");
    punic.method<&Pun8::matchPcre8> ("matchPcre8");

    punic.method<&Pun8::setIdRex> ("setIdRex");
    punic.method<&Pun8::getIdRex> ("getIdRex");
    punic.method<&Pun8::getIds> ("getIds");
    punic.method<&Pun8::setRe8map> ("setRe8map");

    // iteration of managed utf-8 string
    punic.method<&Pun8::setString> ("setString");
    punic.method<&Pun8::nextChar> ("nextChar");
    punic.method<&Pun8::getCode> ("getCode");
    punic.method<&Pun8::getOffset> ("getOffset");
    punic.method<&Pun8::setOffset> ("setOffset");
    punic.method<&Pun8::addOffset> ("addOffset");

    extension.add(std::move(punic));
 
    Php::Class<Pcre8> preg(Pcre8::PHP_NAME.data());
    preg.method<&Pcre8::__construct>("__construct");
    preg.method<&Pcre8::setIdString> ("setIdString");
    preg.method<&Pcre8::getString> ("getString");
    preg.method<&Pcre8::getId> ("getId");
    preg.method<&Pcre8::isCompiled> ("isCompiled");
    preg.method<&Pcre8::match> ("match");
    
    extension.add(std::move(preg));

    Php::Class<Re8map> re8(Re8map::PHP_NAME.data());
    re8.method<&Re8map::setIdRex> ("setIdRex");
    re8.method<&Re8map::hasIdRex> ("hasIdRex");
    re8.method<&Re8map::unsetIdRex> ("unsetIdRex");
    re8.method<&Re8map::getIdRex> ("getIdRex");
    re8.method<&Re8map::shareMap> ("shareMap");
    re8.method<&Re8map::getIds> ("getIds");
    re8.method<&Re8map::count> ("count");
    extension.add(std::move(re8));
    return extension;
}

}